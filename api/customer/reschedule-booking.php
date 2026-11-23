<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$quote=saved_quote($data,true);$id=(int)$quote['booking_id'];$b=player_booking($id,(int)$user['user_id']);db()->beginTransaction();
    $court=one('SELECT * FROM courts WHERE court_id=? FOR UPDATE',[$b['court_id']]);$b=player_booking($id,(int)$user['user_id'],true);customer_policy($b);
    if(scalar("SELECT COUNT(*) FROM booking_change_requests WHERE booking_id=? AND request_status='Pending'",[$id]))throw new ApiError('A reschedule request is already awaiting review.',409);
    if($quote['booking_date']===$b['booking_date'] && $quote['start_time']===$b['start_time'])throw new ApiError('Choose a different date or start time.');
    if((float)$quote['amount']!==(float)$b['court_fee'])throw new ApiError('The reservation changed. Please review it again.',409);
    validate_slot($court,$quote['booking_date'],$quote['start_time'],$quote['end_time'],$id);
    query('INSERT INTO booking_change_requests (booking_id,requested_date,requested_start,requested_end,customer_reason) VALUES (?,?,?,?,?)',[$id,$quote['booking_date'],$quote['start_time'],$quote['end_time'],text_value($data,'reason',1000)]);
    log_activity('booking',$id,'Reschedule requested',$quote['booking_date'].' '.$quote['start_time']);notify('Reschedule requested',$b['reference_no'].' has a new time request awaiting admin approval. The original time remains booked.','reservations',$id);
    db()->commit();unset($_SESSION['booking_quotes'][$data['quote_token']]);return ['ok'=>true,'message'=>'Request sent. Your original booking stays in place until an administrator approves the new time.'];
});
