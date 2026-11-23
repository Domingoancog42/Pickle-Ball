<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$method=allowed_customer_method($data['payment_method_id'] ?? null);
    if(array_key_exists('quote_tokens',$data)){
        $tokens=$data['quote_tokens'];
        if(!is_array($tokens)||!array_is_list($tokens)||count($tokens)<1||count($tokens)>10)throw new ApiError('Choose between 1 and 10 time slots.');
        foreach($tokens as $token)if(!is_string($token)||!preg_match('/^[a-f0-9]{48}$/D',$token))throw new ApiError('Review your selected times again.');
        if(count(array_unique($tokens))!==count($tokens))throw new ApiError('A time slot may only be selected once.');
        $quotes=array_map(fn($token)=>saved_quote(['quote_token'=>$token]),$tokens);
        foreach($quotes as $quote)if(strtotime($quote['end_time'])-strtotime($quote['start_time'])!==3600||!in_array(substr($quote['start_time'],3,2),['00','30'],true))throw new ApiError('Each selected booking must be exactly 1 hour. Review your times again.');
        $court=(int)$quotes[0]['court_id'];$date=$quotes[0]['booking_date'];
        foreach($quotes as $quote)if((int)$quote['court_id']!==$court||$quote['booking_date']!==$date)throw new ApiError('Choose time slots on the same court and date.');
        usort($quotes,fn($a,$b)=>strcmp($a['start_time'],$b['start_time']));
        for($i=1;$i<count($quotes);$i++)if($quotes[$i]['start_time']<$quotes[$i-1]['end_time'])throw new ApiError('Selected time slots must not overlap.');
        $ids=[];db()->beginTransaction();
        try{
            // All selections succeed together; a conflicting slot rolls back the whole request.
            one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$court]);
            $holdId=create_payment_hold_record((int)$user['user_id'],$court,bin2hex(random_bytes(16)));
            foreach($quotes as $quote){
                $booking=create_reservation(['court_id'=>$court,'booking_date'=>$date,'start_time'=>$quote['start_time'],'end_time'=>$quote['end_time'],'status'=>'Pending','user_id'=>$user['user_id'],'payment_method_id'=>$method,'admin_notes'=>''],(float)$quote['amount'],false);
                $ids[]=(int)$booking['booking_id'];
                query('UPDATE bookings SET checkout_hold_id=? WHERE booking_id=?',[$holdId,$booking['booking_id']]);
            }
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        foreach($tokens as $token)unset($_SESSION['booking_quotes'][$token]);
        $items=array_map(fn($id)=>player_booking($id,(int)$user['user_id']),$ids);
        return ['bookings'=>$items,'total'=>round(array_sum(array_column($items,'court_fee')),2),'count'=>count($items)];
    }
    $quote=saved_quote($data);
    if(strtotime($quote['end_time'])-strtotime($quote['start_time'])!==3600||!in_array(substr($quote['start_time'],3,2),['00','30'],true))throw new ApiError('Each court booking must be exactly 1 hour. Review your time again.');
    db()->beginTransaction();
    try{
        one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$quote['court_id']]);
        $holdId=create_payment_hold_record((int)$user['user_id'],(int)$quote['court_id'],bin2hex(random_bytes(16)));
        $booking=create_reservation(['court_id'=>$quote['court_id'],'booking_date'=>$quote['booking_date'],'start_time'=>$quote['start_time'],'end_time'=>$quote['end_time'],'status'=>'Pending','user_id'=>$user['user_id'],'payment_method_id'=>$method,'admin_notes'=>''],(float)$quote['amount'],false);
        query('UPDATE bookings SET checkout_hold_id=? WHERE booking_id=?',[$holdId,$booking['booking_id']]);
        db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    unset($_SESSION['booking_quotes'][$data['quote_token']]);return player_booking((int)$booking['booking_id'],(int)$user['user_id']);
});
