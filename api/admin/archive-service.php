<?php
// Loaded by bootstrap; archival changes visibility without deleting linked history.
if(realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}

function archive_modules(): array {
    return ['courts'=>['courts','court_id','court'],
        'reservations'=>['bookings','booking_id','booking'],
        'customers'=>['users','user_id','customer'],
        'payments'=>['payments','payment_id','payment']];
}
function unarchived_sql(string $module,string $idColumn): string {
    if(!isset(archive_modules()[$module]))throw new LogicException('Unsupported archive module.');
    $columns=['courts'=>['c.court_id'],'reservations'=>['b.booking_id'],'customers'=>['u.user_id'],'payments'=>['p.payment_id']];
    if(!in_array($idColumn,$columns[$module],true))throw new LogicException('Unsupported archive record column.');
    // Both arguments are developer-owned SQL fragments, never request input.
    return "NOT EXISTS (SELECT 1 FROM record_archives ra WHERE ra.module='$module' AND ra.record_id=$idColumn)";
}
function require_unarchived(string $module,int $id): void {
    if(scalar('SELECT COUNT(*) FROM record_archives WHERE module=? AND record_id=?',[$module,$id]))
        throw new ApiError('This record is archived. Restore it from Archived before making changes.',409);
}
function change_record_archive(string $module,int $id,string $action,int $actor): array {
    [$table,$key,$entity]=archive_modules()[$module];
    db()->beginTransaction();
    try {
        // Keep the same court -> booking -> payment lock order as booking/verification.
        if(in_array($module,['reservations','payments'],true)){
            $bookingId=$module==='reservations'?$id:(int)scalar('SELECT booking_id FROM payments WHERE payment_id=?',[$id]);
            $courtId=(int)scalar('SELECT court_id FROM bookings WHERE booking_id=?',[$bookingId]);
            if(!$courtId)throw new ApiError('Record not found.',404);
            one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$courtId]);
            $booking=one('SELECT b.*,s.name AS booking_status FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.booking_id=? FOR UPDATE',[$bookingId]);
        }
        $record=one("SELECT * FROM $table WHERE $key=? FOR UPDATE",[$id]);
        if(!$record || ($module==='customers' && $record['role']!=='customer'))throw new ApiError('Record not found.',404);
        $archive=one('SELECT * FROM record_archives WHERE module=? AND record_id=? FOR UPDATE',[$module,$id]);
        if(($action==='archive' && $archive) || ($action==='restore' && !$archive)){
            db()->commit();return ['ok'=>true,'changed'=>false];
        }
        if($action==='archive'){
            $prior=[];
            if($module==='courts'){
                expire_payment_holds_for_court($id);
                if(scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.court_id=? AND s.name IN ('Pending','Confirmed') AND TIMESTAMP(b.booking_date,b.end_time)>NOW()",[$id]))
                    throw new ApiError('Resolve upcoming reservations before archiving this court.',409);
                $prior=['court_status'=>$record['court_status'],'is_active'=>(int)$record['is_active']];
                query("UPDATE courts SET deleted_at=NOW(),court_status='disabled',is_active=0 WHERE court_id=?",[$id]);
            }elseif($module==='customers'){
                if(scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND s.name IN ('Pending','Confirmed') AND TIMESTAMP(b.booking_date,b.end_time)>NOW()",[$id]))
                    throw new ApiError('Resolve upcoming reservations before archiving this customer.',409);
                if(scalar("SELECT COUNT(*) FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE b.user_id=? AND p.status='Pending Verification'",[$id]))
                    throw new ApiError('Review pending payment proofs before archiving this customer.',409);
                $prior=['account_status'=>$record['account_status']];
                query("UPDATE users SET account_status='inactive',session_version=session_version+1 WHERE user_id=?",[$id]);
            }else{
                if(!in_array($booking['booking_status'],['Completed','Cancelled','No-show'],true))
                    throw new ApiError('Complete or cancel the reservation before archiving its records.',409);
                if(scalar("SELECT COUNT(*) FROM payments WHERE booking_id=? AND status='Pending Verification'",[$bookingId]))
                    throw new ApiError('Review pending payment proofs before archiving this record.',409);
                if(scalar("SELECT COUNT(*) FROM booking_change_requests WHERE booking_id=? AND request_status='Pending'",[$bookingId]))
                    throw new ApiError('Review the pending reschedule request before archiving this reservation.',409);
            }
            query('INSERT INTO record_archives (module,record_id,archived_by,prior_state) VALUES (?,?,?,?)',[$module,$id,$actor,json_encode($prior,JSON_THROW_ON_ERROR)]);
        }else{
            $prior=json_decode($archive['prior_state'] ?? '{}',true,16,JSON_THROW_ON_ERROR);
            if($module==='courts'){
                $status=in_array($prior['court_status'] ?? '',['active','disabled','maintenance'],true)?$prior['court_status']:'disabled';
                query('UPDATE courts SET deleted_at=NULL,court_status=?,is_active=? WHERE court_id=?',[$status,$status==='active' && !empty($prior['is_active'])?1:0,$id]);
            }elseif($module==='customers'){
                query('UPDATE users SET account_status=? WHERE user_id=?',[($prior['account_status'] ?? '')==='active'?'active':'inactive',$id]);
            }
            query('DELETE FROM record_archives WHERE module=? AND record_id=?',[$module,$id]);
        }
        log_activity($entity,$id,$action==='archive'?'Record archived':'Record restored',$module);
        db()->commit();return ['ok'=>true,'changed'=>true];
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
}
