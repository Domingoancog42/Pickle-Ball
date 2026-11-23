<?php
require_once dirname(__DIR__).'/admin/booking-service.php';
function require_customer(): array {
    $user=session_user(); if(!$user) throw new ApiError('Please sign in to continue.',401);
    if($user['role']!=='customer') throw new ApiError('This area is for player accounts.',403);
    return $user;
}
function customer_handler(callable $callback): never {
    if(defined('PIKOL_GUEST_CHECKOUT'))guest_checkout_handler($callback);
    try { $user=require_customer(); if($_SERVER['REQUEST_METHOD']!=='GET') require_csrf(); json_response($callback($user)); }
    catch(ApiError $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}json_response(['error'=>$e->getMessage()],$e->status);}
    catch(Throwable $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){} error_log((string)$e);json_response(['error'=>'We could not complete your request. Please try again.'],500);}
}
function player_booking(int $id,int $owner,bool $lock=false): array {
    $b=one(reservation_select().' WHERE b.booking_id=? AND b.user_id=?'.($lock?' FOR UPDATE':''),[$id,$owner]);
    if(!$b) throw new ApiError('Reservation not found.',404);
    unset($b['admin_notes']);
    $b['addon_items']=booking_addon_items($id);
    $court=one('SELECT image_path FROM courts WHERE court_id=?',[$b['court_id']]); $b['image_path']=$court['image_path'];
    $start=new DateTimeImmutable($b['booking_date'].' '.$b['start_time']);
    $b['duration_minutes']=(strtotime($b['end_time'])-strtotime($b['start_time']))/60;
    $b['can_cancel']=in_array($b['booking_status'],['Pending','Confirmed'],true) && $start->getTimestamp()-time()>=(int)settings()['cancellation_hours']*3600 && $start>new DateTimeImmutable();
    $b['can_reschedule']=$b['can_cancel'];
    return $b;
}
function customer_policy(array $b): void {
    $start=new DateTimeImmutable($b['booking_date'].' '.$b['start_time']);
    if(!in_array($b['booking_status'],['Pending','Confirmed'],true) || $start<=new DateTimeImmutable()) throw new ApiError('This reservation can no longer be changed.',409);
    $hours=(int)settings()['cancellation_hours'];
    if($start->getTimestamp()-time()<$hours*3600) throw new ApiError("Changes require at least {$hours} hours before your booking starts.",409);
}
function customer_methods(): array {
    require_once dirname(__DIR__).'/payment-display-service.php';
    return configured_customer_methods();
}
function allowed_customer_method(mixed $id): int {
    $id=positive_id($id);foreach(customer_methods() as $method)if((int)$method['payment_method_id']===$id)return $id;
    throw new ApiError('Choose an enabled payment method.');
}
function booking_quote(array $input,array $user): array {
    $courtId=positive_id($input['court_id'] ?? null);$date=valid_date($input['booking_date'] ?? '');$start=valid_time($input['start_time'] ?? '');$end=valid_booking_end($input['end_time'] ?? '');
    $court=one('SELECT * FROM courts WHERE court_id=?',[$courtId]);if(!$court)throw new ApiError('Court not found.',404);
    $bookingId=!empty($input['booking_id'])?positive_id($input['booking_id']):null;
    if($bookingId){$old=player_booking($bookingId,(int)$user['user_id']);customer_policy($old);if($courtId!==(int)$old['court_id'] || (strtotime($end)-strtotime($start))/60!=$old['duration_minutes'])throw new ApiError('Rescheduling keeps the original court, duration, and price.');}
    if(!$bookingId&&((strtotime($end)-strtotime($start))!==3600||!in_array(substr($start,3,2),['00','30'],true)))throw new ApiError('Select a 1-hour booking starting on the hour or half-hour.');
    validate_slot($court,$date,$start,$end,$bookingId);
    $fee=$bookingId?(float)$old['court_fee']:round((strtotime($end)-strtotime($start))/3600*(float)$court['hourly_rate'],2);
    if($fee<=0)throw new ApiError('This court does not have a valid booking rate.');
    $quote=['court_id'=>$courtId,'court_name'=>$court['court_name'],'booking_date'=>$date,'start_time'=>$start,'end_time'=>$end,'hourly_rate'=>$court['hourly_rate'],'duration_minutes'=>(strtotime($end)-strtotime($start))/60,'amount'=>$fee,'booking_id'=>$bookingId,'expires'=>time()+300];
    $_SESSION['booking_quotes']=array_filter($_SESSION['booking_quotes'] ?? [],fn($q)=>$q['expires']>=time());
    if(count($_SESSION['booking_quotes'])>=10)array_shift($_SESSION['booking_quotes']);
    $token=bin2hex(random_bytes(24));$_SESSION['booking_quotes'][$token]=$quote;
    return $quote+['quote_token'=>$token];
}
function saved_quote(array $data,bool $reschedule=false): array {
    $token=text_value($data,'quote_token',100);$quote=$_SESSION['booking_quotes'][$token] ?? null;
    if(!$quote || $quote['expires']<time() || (bool)$quote['booking_id']!==$reschedule)throw new ApiError('Your quote expired. Please review your booking again.',409);
    return $quote;
}
