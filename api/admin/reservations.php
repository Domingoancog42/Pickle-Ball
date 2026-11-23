<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if (!empty($_GET['id'])) {
            $id=positive_id($_GET['id']); $booking=one(reservation_select().' WHERE b.booking_id=?',[$id]);
            if (!$booking) throw new ApiError('Reservation not found.',404);
            $booking['addon_items']=booking_addon_items($id);
            $booking['payments']=rows('SELECT p.*,m.name AS payment_method FROM payments p JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE p.booking_id=? ORDER BY p.payment_id DESC',[$id]);
            $booking['activity']=rows("SELECT a.*,u.full_name AS actor_name FROM activity_logs a LEFT JOIN users u ON u.user_id=a.actor_id WHERE a.entity_type='booking' AND a.entity_id=? ORDER BY a.created_at DESC,a.activity_id DESC",[$id]);
            return $booking;
        }
        [$where,$params]=reservation_filters($_GET);
        $where.=' AND '.unarchived_sql('reservations','b.booking_id');
        $sql=reservation_select().' WHERE '.$where;
        if (!empty($_GET['payment_status'])) { $sql='SELECT * FROM ('.$sql.') results WHERE payment_status=?'; $params[]=$_GET['payment_status']; }
        $total=(int)scalar('SELECT COUNT(*) FROM ('.$sql.') records',$params);[$page,$size,$offset,$pages]=page_window($total,20);
        
        return ['items'=>rows($sql." ORDER BY booking_date DESC,start_time DESC,booking_id DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size];
    }
    $data=input();
    if (($data['action'] ?? '')==='create') {
        if(empty($data['user_id'])){
            $phone=text_value($data,'phone',11,false);
            if($phone!==''&&!preg_match('/^[0-9]{11}$/D',$phone))throw new ApiError('Enter an 11-digit phone number or leave it blank.');
            $data['phone']=$phone;
        }
        $start=valid_time((string)($data['start_time'] ?? ''));
        $end=valid_booking_end((string)($data['end_time'] ?? ''));
        $minutes=fn($time)=>(int)substr($time,0,2)*60+(int)substr($time,3,2);
        if(substr($start,3,2)!=='00' || $minutes($end)-$minutes($start)!==60)
            throw new ApiError('Select a one-hour court time starting on the hour.');
        return create_reservation($data,null,true,($data['customer_mode']??'')==='walk-in');
    }
    $id=positive_id($data['booking_id'] ?? null);
    $booking=one('SELECT court_id FROM bookings WHERE booking_id=?',[$id]);
    if (!$booking) throw new ApiError('Reservation not found.',404);
    db()->beginTransaction();
    try {
        $court=one('SELECT * FROM courts WHERE court_id=? FOR UPDATE',[$booking['court_id']]);
        $booking=one(reservation_select().' WHERE b.booking_id=? FOR UPDATE',[$id]);
        require_unarchived('reservations',$id);
        if (($data['action'] ?? '')==='notes') {
            query('UPDATE bookings SET admin_notes=? WHERE booking_id=?',[text_value($data,'admin_notes',3000,false),$id]);
            log_activity('booking',$id,'Admin notes updated'); db()->commit(); return ['ok'=>true];
        }
        $current=$booking['booking_status']; $next=enum_value($data,'status',['Confirmed','Cancelled','Completed']);
        $allowed=['Pending'=>['Confirmed','Cancelled'],'Confirmed'=>['Cancelled','Completed'],'Cancelled'=>[],'Completed'=>[],'No-show'=>[]];
        if (!in_array($next,$allowed[$current] ?? [],true)) throw new ApiError('This reservation cannot make that status change.',409);
        $reason='';
        if ($next==='Confirmed') {
            require_open_payment_hold($booking);
            $customer=one('SELECT account_status FROM users WHERE user_id=?',[$booking['user_id']]);
            if ($customer['account_status']!=='active') throw new ApiError('The customer account is inactive.',409);
            validate_slot($court,$booking['booking_date'],$booking['start_time'],$booking['end_time'],$id);
            require_open_payment_hold($booking);
        }
        if ($next==='Completed' && new DateTimeImmutable($booking['booking_date'].' '.$booking['end_time'])>new DateTimeImmutable()) throw new ApiError('A reservation can be completed only after its scheduled end time.',409);
        if ($next==='Cancelled') $reason=text_value($data,'reason',1000);
        query('UPDATE bookings SET status_id=(SELECT status_id FROM booking_statuses WHERE name=?),cancellation_reason=? WHERE booking_id=?',[$next,$reason ?: null,$id]);
        log_activity('booking',$id,'Status changed to '.$next,$reason);
        notify('Reservation '.$next,$booking['reference_no'].' · '.$next,'reservations',$id);
        db()->commit(); return ['ok'=>true];
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
});
