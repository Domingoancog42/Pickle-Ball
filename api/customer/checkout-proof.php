<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $d=input();$ids=$d['payment_ids']??[];
    if(!is_array($ids)||!count($ids)||count($ids)>10)throw new ApiError('Choose between one and ten payments.');
    $ids=array_map('positive_id',$ids);if(count(array_unique($ids))!==count($ids))throw new ApiError('Duplicate payments are not allowed.');sort($ids);
    $method=allowed_customer_method($d['payment_method_id']??null);
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $payments=rows("SELECT p.payment_id,p.booking_id,b.court_id,b.booking_date FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE p.payment_id IN ($marks) AND b.user_id=? ORDER BY p.booking_id,p.payment_id",[...$ids,$user['user_id']]);
    if(count($payments)!==count($ids))throw new ApiError('Payment not found.',404);
    if(count(array_unique(array_column($payments,'court_id')))!==1||count(array_unique(array_column($payments,'booking_date')))!==1)throw new ApiError('A shared receipt must cover one court and booking date.');
    $proof=null;db()->beginTransaction();
    try{
        one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$payments[0]['court_id']]);
        $bookings=[];
        foreach($payments as $payment){
            $b=player_booking((int)$payment['booking_id'],(int)$user['user_id'],true);
            require_open_payment_hold($b);
            if((int)$b['court_id']!==(int)$payments[0]['court_id']||$b['booking_date']!==$payments[0]['booking_date'])throw new ApiError('A reservation changed. Review its latest court and date before submitting proof.',409);
            $p=one('SELECT * FROM payments WHERE payment_id=? FOR UPDATE',[$payment['payment_id']]);
            if(!in_array($b['booking_status'],['Pending','Confirmed'],true)||!in_array($p['status'],['Unpaid','Pending Verification'],true))throw new ApiError('A payment is no longer eligible for proof submission. Refresh your reservations.',409);
            $bookings[$b['booking_id']]=$b;
        }
        $proof=safe_image($_FILES['proof']??null,'proofs');if(!$proof)throw new ApiError('Upload your successful payment screenshot.');
        foreach($bookings as $b)require_open_payment_hold($b);
        foreach($payments as $payment){
            query("UPDATE payments SET payment_method_id=?,proof_path=?,status='Pending Verification',verified_by=NULL,verified_at=NULL WHERE payment_id=?",[$method,$proof,$payment['payment_id']]);
            log_activity('payment',(int)$payment['payment_id'],'Customer submitted checkout payment proof');
        }
        foreach($bookings as $b){record_payment_hold_proof((int)($b['checkout_hold_id'] ?? 0));log_activity('booking',(int)$b['booking_id'],'Checkout payment proof submitted');notify('Payment awaiting verification',$b['reference_no'].' has a submitted receipt. Staff verification is required.','reservations',(int)$b['booking_id']);}
        db()->commit();return ['ok'=>true,'status'=>'Pending Verification','count'=>count($payments)];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($proof&&preg_match('~^storage/proofs/[a-f0-9]{40}\.(png|jpg|webp)$~D',$proof))@unlink(dirname(__DIR__,2).'/'.$proof);throw $e;}
});
