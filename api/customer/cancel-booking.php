<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$id=positive_id($data['booking_id'] ?? null);$existing=player_booking($id,(int)$user['user_id']);db()->beginTransaction();
    one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$existing['court_id']]);$b=player_booking($id,(int)$user['user_id'],true);customer_policy($b);
    $reason=text_value($data,'reason',1000);
    query("UPDATE bookings SET status_id=(SELECT status_id FROM booking_statuses WHERE name='Cancelled'),cancellation_reason=? WHERE booking_id=? AND user_id=?",[$reason,$id,$user['user_id']]);
    query("UPDATE booking_change_requests SET request_status='Rejected',admin_response='The reservation was cancelled.',reviewed_at=NOW() WHERE booking_id=? AND request_status='Pending'",[$id]);
    log_activity('booking',$id,'Cancelled by customer',$reason);notify('Reservation cancelled',$b['reference_no'].' was cancelled. Paid funds require separate refund review.','reservations',$id);db()->commit();return ['ok'=>true];
});
