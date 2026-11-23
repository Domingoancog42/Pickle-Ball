<?php
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}
// All hold transitions use the same court-row lock as normal booking and payment writes.
function create_payment_hold_record(int $userId,int $courtId,string $requestKey): int {
    query('INSERT INTO booking_checkout_holds(user_id,court_id,request_key,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))',[$userId,$courtId,$requestKey]);
    return (int)db()->lastInsertId();
}

function booking_blocks_slot_sql(string $alias='b'): string {
    return "($alias.checkout_hold_id IS NULL OR EXISTS (SELECT 1 FROM booking_checkout_holds active_hold WHERE active_hold.hold_id=$alias.checkout_hold_id AND (active_hold.proof_submitted_at IS NOT NULL OR (active_hold.expired_at IS NULL AND active_hold.expires_at>NOW()))) OR EXISTS (SELECT 1 FROM payments hold_payment WHERE hold_payment.booking_id=$alias.booking_id AND hold_payment.status IN ('Paid','Pending Verification')))";
}

function expire_payment_holds_for_court(int $courtId): void {
    if(!db()->inTransaction())throw new LogicException('Hold expiry requires a locked court transaction.');
    $holds=rows('SELECT hold_id FROM booking_checkout_holds WHERE court_id=? AND expires_at<=NOW() AND proof_submitted_at IS NULL AND expired_at IS NULL ORDER BY hold_id FOR UPDATE',[$courtId]);
    foreach($holds as $hold){
        $expired=false;$protected=false;
        foreach(rows("SELECT b.booking_id,s.name AS booking_status FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.checkout_hold_id=? ORDER BY b.booking_id FOR UPDATE",[$hold['hold_id']]) as $booking){
            $payments=rows('SELECT status FROM payments WHERE booking_id=? ORDER BY payment_id FOR UPDATE',[$booking['booking_id']]);
            if(array_intersect(array_column($payments,'status'),['Paid','Pending Verification'])){$protected=true;continue;}
            if($booking['booking_status']==='Cancelled'){$expired=true;continue;}
            if(!in_array($booking['booking_status'],['Pending','Confirmed'],true))continue;
            query("UPDATE bookings SET status_id=(SELECT status_id FROM booking_statuses WHERE name='Cancelled'),cancellation_reason='Payment window expired after 10 minutes.' WHERE booking_id=?",[$booking['booking_id']]);
            query("UPDATE booking_change_requests SET request_status='Rejected',admin_response='The payment window expired.',reviewed_at=NOW() WHERE booking_id=? AND request_status='Pending'",[$booking['booking_id']]);
            query("INSERT INTO activity_logs(actor_id,entity_type,entity_id,action,details) VALUES(NULL,'booking',?,'Payment hold expired','Unpaid slot released after the 10-minute payment window.')",[$booking['booking_id']]);
            $expired=true;
        }
        if($expired)query('UPDATE booking_checkout_holds SET expired_at=NOW(),proof_submitted_at=IF(?,COALESCE(proof_submitted_at,NOW()),NULL) WHERE hold_id=?',[$protected?1:0,$hold['hold_id']]);
        else query('UPDATE booking_checkout_holds SET proof_submitted_at=NOW() WHERE hold_id=?',[$hold['hold_id']]);
    }
}

function expire_payment_holds(): void {
    foreach(rows('SELECT DISTINCT court_id FROM booking_checkout_holds WHERE expires_at<=NOW() AND proof_submitted_at IS NULL AND expired_at IS NULL ORDER BY court_id') as $item){
        db()->beginTransaction();
        try{
            one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$item['court_id']]);
            expire_payment_holds_for_court((int)$item['court_id']);
            db()->commit();
        }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    }
}

function require_open_payment_hold(array $booking): void {
    if(empty($booking['checkout_hold_id']))return;
    $hold=one('SELECT *,expires_at<=NOW() AS deadline_passed FROM booking_checkout_holds WHERE hold_id=? FOR UPDATE',[$booking['checkout_hold_id']]);
    if(!$hold)throw new ApiError('Payment hold not found.',404);
    // A timely submitted receipt stays reserved during manual verification.
    if(scalar("SELECT COUNT(*) FROM payments WHERE booking_id=? AND status IN ('Paid','Pending Verification')",[$booking['booking_id']]))return;
    if($hold['expired_at']||$hold['deadline_passed'])throw new ApiError('Your 10-minute payment window expired. Choose your slots again.',409);
}

function record_payment_hold_proof(int $holdId): void {
    if(!$holdId)return;
    $unpaid=(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.checkout_hold_id=? AND s.name IN ('Pending','Confirmed') AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id=b.booking_id AND p.status IN ('Paid','Pending Verification'))",[$holdId]);
    if(!$unpaid)query('UPDATE booking_checkout_holds SET proof_submitted_at=COALESCE(proof_submitted_at,NOW()) WHERE hold_id=? AND expired_at IS NULL',[$holdId]);
}
