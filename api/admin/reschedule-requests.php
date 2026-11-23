<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET')return ['items'=>rows("SELECT r.*,b.reference_no,b.booking_date,b.start_time,b.end_time,u.full_name,c.court_name FROM booking_change_requests r JOIN bookings b ON b.booking_id=r.booking_id JOIN users u ON u.user_id=b.user_id JOIN courts c ON c.court_id=b.court_id WHERE r.request_status='Pending' ORDER BY r.created_at LIMIT 100")];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);$data=input();$id=positive_id($data['request_id'] ?? null);$decision=enum_value($data,'decision',['Approved','Rejected']);$response=text_value($data,'response',1000);
    $request=one('SELECT r.booking_id,b.court_id FROM booking_change_requests r JOIN bookings b ON b.booking_id=r.booking_id WHERE request_id=?',[$id]);if(!$request)throw new ApiError('Request not found.',404);
    db()->beginTransaction();
    try{
        $court=one('SELECT * FROM courts WHERE court_id=? FOR UPDATE',[$request['court_id']]);$b=one(reservation_select().' WHERE b.booking_id=? FOR UPDATE',[$request['booking_id']]);$request=one('SELECT * FROM booking_change_requests WHERE request_id=? FOR UPDATE',[$id]);
        if($request['request_status']!=='Pending')throw new ApiError('This request has already been reviewed.',409);
        if($decision==='Approved'){
            if(!in_array($b['booking_status'],['Pending','Confirmed'],true) || strtotime($b['booking_date'].' '.$b['end_time'])<=time())throw new ApiError('This reservation can no longer be rescheduled.',409);
            if(scalar('SELECT account_status FROM users WHERE user_id=?',[$b['user_id']])!=='active')throw new ApiError('The customer account is inactive.',409);
            validate_slot($court,$request['requested_date'],$request['requested_start'],$request['requested_end'],(int)$b['booking_id']);
            query('UPDATE bookings SET booking_date=?,start_time=?,end_time=? WHERE booking_id=?',[$request['requested_date'],$request['requested_start'],$request['requested_end'],$b['booking_id']]);
        }
        query('UPDATE booking_change_requests SET request_status=?,admin_response=?,reviewed_by=?,reviewed_at=NOW() WHERE request_id=?',[$decision,$response,$admin['user_id'],$id]);
        log_activity('booking',(int)$b['booking_id'],'Reschedule '.$decision,$response);
        notify('Reschedule '.strtolower($decision),$b['reference_no'].' · '.$response,'reservations',(int)$b['booking_id']);db()->commit();return ['ok'=>true];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
});
