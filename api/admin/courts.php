<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $items=rows('SELECT c.*,s.name AS surface_type,v.name AS venue_name FROM courts c JOIN surface_types s ON s.surface_type_id=c.surface_type_id JOIN venues v ON v.venue_id=c.venue_id WHERE c.deleted_at IS NULL ORDER BY c.court_id');
        foreach($items as &$court) $court['availability']=court_availability($court); unset($court);
        return ['items'=>$items,
            'surfaces'=>rows('SELECT * FROM surface_types'),'venues'=>rows('SELECT venue_id,name FROM venues'),
            'maintenance'=>rows('SELECT m.*,c.court_name FROM court_maintenance m JOIN courts c ON c.court_id=m.court_id WHERE c.deleted_at IS NULL AND m.ends_at>=NOW() ORDER BY m.starts_at'),
            'schedules'=>rows('SELECT s.* FROM court_schedules s JOIN courts c ON c.court_id=s.court_id WHERE c.deleted_at IS NULL')];
    }
    $data=input(); $action=$data['action'] ?? 'save';
    if (in_array($action,['maintenance','remove-maintenance','schedule'],true)) {
        $id=positive_id($data['court_id'] ?? null); db()->beginTransaction();
        try {
            $court=one('SELECT * FROM courts WHERE court_id=? AND deleted_at IS NULL FOR UPDATE',[$id]);
            if (!$court) throw new ApiError('Court not found.',404);
            if ($action==='maintenance') {
                $from=(string)($data['starts_at'] ?? ''); $to=(string)($data['ends_at'] ?? '');
                $parse=function($value) { $value=str_replace('T',' ',$value); $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$value); if (!$d || $d->format('Y-m-d H:i')!==$value) throw new ApiError('Enter valid maintenance dates and times.'); return $value.':00'; };
                $from=$parse($from); $to=$parse($to);
                if ($to<=$from || $to<date('Y-m-d H:i:s')) throw new ApiError('Choose a valid future maintenance period.');
                if (scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.court_id=? AND s.name IN ('Pending','Confirmed') AND TIMESTAMP(b.booking_date,b.start_time)<? AND TIMESTAMP(b.booking_date,b.end_time)>?",[$id,$to,$from])) throw new ApiError('Maintenance overlaps active bookings. Cancel or move those reservations first.',409);
                if (scalar('SELECT COUNT(*) FROM court_maintenance WHERE court_id=? AND starts_at<? AND ends_at>?',[$id,$to,$from])) throw new ApiError('This maintenance period overlaps an existing block.',409);
                query('INSERT INTO court_maintenance (court_id,starts_at,ends_at,reason,created_by) VALUES (?,?,?,?,?)',[$id,$from,$to,text_value($data,'reason',500),$admin['user_id']]);
                notify('Court maintenance',$court['court_name'].' has a new maintenance block.','courts',$id);
                log_activity('court',$id,'Maintenance scheduled',"$from – $to");
            } elseif ($action==='remove-maintenance') {
                query('DELETE FROM court_maintenance WHERE maintenance_id=? AND court_id=?',[positive_id($data['maintenance_id'] ?? null),$id]); log_activity('court',$id,'Maintenance removed');
            } else {
                $days=$data['days'] ?? []; if (count($days)!==7) throw new ApiError('Provide all seven weekdays.');
                $seen=[];
                foreach ($days as $day) {
                    $weekday=filter_var($day['day_of_week'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>6]]);
                    if ($weekday===false || isset($seen[$weekday])) throw new ApiError('Invalid weekday schedule.'); $seen[$weekday]=true;
                    $opens=valid_time($day['opens_at'] ?? ''); $closes=valid_time($day['closes_at'] ?? ''); if ($closes===$opens) throw new ApiError('Opening and closing times must be different.');
                    query('INSERT INTO court_schedules (court_id,day_of_week,opens_at,closes_at,is_open) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE opens_at=VALUES(opens_at),closes_at=VALUES(closes_at),is_open=VALUES(is_open)',[$id,$weekday,$opens,$closes,!empty($day['is_open']) ? 1:0]);
                }
                $future=rows("SELECT b.* FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.court_id=? AND s.name IN ('Pending','Confirmed') AND TIMESTAMP(b.booking_date,b.end_time)>NOW()",[$id]);
                foreach ($future as $booking) { if (!slot_in_operating_hours($court,$booking['booking_date'],$booking['start_time'],$booking['end_time'])) throw new ApiError('The new schedule conflicts with existing reservations.',409); }
                log_activity('court',$id,'Weekly schedule updated');
            }
            db()->commit(); return ['ok'=>true];
        } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
    }
    $name=text_value($data,'court_name',50); $description=text_value($data,'description',5000,false);
    $opens=valid_time($data['opens_at'] ?? ''); $closes=valid_time($data['closes_at'] ?? ''); if ($closes===$opens) throw new ApiError('Opening and closing times must be different.');
    $rate=filter_var($data['hourly_rate'] ?? null,FILTER_VALIDATE_FLOAT); if ($rate===false || $rate<=0 || $rate>1000000) throw new ApiError('Enter a positive hourly rate up to ₱1,000,000.');
    $status=enum_value($data,'court_status',['active','disabled','maintenance']);
    $venue=positive_id($data['venue_id'] ?? null); $surface=positive_id($data['surface_type_id'] ?? null);
    if (!one('SELECT venue_id FROM venues WHERE venue_id=?',[$venue]) || !one('SELECT surface_type_id FROM surface_types WHERE surface_type_id=?',[$surface])) throw new ApiError('Choose an existing venue and surface.');
    db()->beginTransaction();
    try {
        $id=!empty($data['court_id']) ? positive_id($data['court_id']):null;
        $image=null;
        if ($id) {
            $old=one('SELECT * FROM courts WHERE court_id=? AND deleted_at IS NULL FOR UPDATE',[$id]); if (!$old) throw new ApiError('Court not found.',404);
            $future=rows("SELECT b.* FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE court_id=? AND s.name IN ('Pending','Confirmed') AND TIMESTAMP(booking_date,end_time)>NOW()",[$id]);
            if ($future && $status!=='active') throw new ApiError('Cancel upcoming reservations before disabling this court.',409);
            foreach ($future as $booking) { $candidate=array_merge($old,['opens_at'=>$opens,'closes_at'=>$closes]); if (!slot_in_operating_hours($candidate,$booking['booking_date'],$booking['start_time'],$booking['end_time'])) throw new ApiError('Operating hours conflict with existing reservations.',409); }
            $image=$old['image_path'];
        }
        $image=safe_image($_FILES['image'] ?? null) ?? $image;
        if ($id) query('UPDATE courts SET court_name=?,description=?,hourly_rate=?,opens_at=?,closes_at=?,court_status=?,is_active=?,venue_id=?,surface_type_id=?,image_path=? WHERE court_id=?',[$name,$description,$rate,$opens,$closes,$status,$status==='active'?1:0,$venue,$surface,$image,$id]);
        else { query('INSERT INTO courts (court_name,description,hourly_rate,opens_at,closes_at,court_status,is_active,venue_id,surface_type_id,image_path) VALUES (?,?,?,?,?,?,?,?,?,?)',[$name,$description,$rate,$opens,$closes,$status,$status==='active'?1:0,$venue,$surface,$image]); $id=(int)db()->lastInsertId(); }
        log_activity('court',$id,isset($old)?'Court updated':'Court created',$name);
        if(isset($old)&&((float)$old['hourly_rate']!==(float)$rate))audit_event('Court price changed',$name,'court',$id,'success',['before'=>['hourly_rate'=>$old['hourly_rate']],'after'=>['hourly_rate'=>$rate]]);
        if(isset($old)&&$status!=='active'&&$old['court_status']==='active')audit_event('Court disabled',$name,'court',$id);  db()->commit(); return ['court_id'=>$id];
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); if($e instanceof PDOException && $e->getCode()==='23000') throw new ApiError('A court with this name already exists at that venue.',409); throw $e; }
});
