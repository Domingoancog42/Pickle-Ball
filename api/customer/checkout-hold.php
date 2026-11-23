<?php
require __DIR__.'/bootstrap.php';

function customer_checkout_hold_result(array $hold,array $user): array {
    $ids=array_column(rows('SELECT booking_id FROM bookings WHERE checkout_hold_id=? AND user_id=? ORDER BY booking_date,start_time,booking_id',[$hold['hold_id'],$user['user_id']]),'booking_id');
    $bookings=array_map(fn($id)=>player_booking((int)$id,(int)$user['user_id']),$ids);
    if(defined('PIKOL_GUEST_CHECKOUT')){
        foreach($bookings as &$booking){
            unset($booking['user_id'],$booking['avatar_path']);
            $booking['can_cancel']=false;$booking['can_reschedule']=false;
        }unset($booking);
    }
    $payments=rows("SELECT p.payment_id,p.payment_method_id FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE b.checkout_hold_id=? AND b.user_id=? AND p.status IN ('Unpaid','Pending Verification') ORDER BY b.start_time,p.payment_id",[$hold['hold_id'],$user['user_id']]);
    $expires=strtotime($hold['expires_at']);
    $addons=[];foreach($ids as $id)array_push($addons,...booking_addon_items((int)$id));
    $expired=$hold['expired_at']||in_array('Cancelled',array_column($bookings,'booking_status'),true);
    return ['addons'=>$addons,'hold_id'=>(int)$hold['hold_id'],'expires_at'=>date(DATE_ATOM,$expires),'expires_at_unix'=>$expires,'server_time'=>(int)scalar('SELECT UNIX_TIMESTAMP(NOW())'),'state'=>$expired?'expired':($hold['proof_submitted_at']?'submitted':'holding'),'payment_ids'=>array_map('intval',array_column($payments,'payment_id')),'payment_method_id'=>isset($payments[0])?(int)$payments[0]['payment_method_id']:null,'bookings'=>$bookings,'count'=>count($bookings),'total'=>round(array_sum(array_column($bookings,'court_fee')),2)];
}

customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $hold=one('SELECT * FROM booking_checkout_holds WHERE hold_id=? AND user_id=?',[positive_id($_GET['hold_id'] ?? null),$user['user_id']]);
        if(!$hold)throw new ApiError('Payment hold not found.',404);
        return customer_checkout_hold_result($hold,$user);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$request=$data['request_id'] ?? null;
    if(!is_string($request)||!preg_match('/^[a-f0-9]{32}$/D',$request))throw new ApiError('Review your selected times and try again.');
    $guest=defined('PIKOL_GUEST_CHECKOUT');
    $contact=$guest?guest_checkout_contact($data):null;
    if($guest){
        $existingId=$_SESSION['guest_checkout_requests'][$request] ?? null;
        $existing=$existingId?guest_checkout_hold((int)$existingId):null;
        if($existing)$user['user_id']=(int)$existing['user_id'];
    }else $existing=one('SELECT * FROM booking_checkout_holds WHERE user_id=? AND request_key=?',[$user['user_id'],$request]);
    if($existing)return customer_checkout_hold_result($existing,$user);
    $method=allowed_customer_method($data['payment_method_id'] ?? null);
    $tokens=$data['quote_tokens'] ?? [];
    if(!is_array($tokens)||!array_is_list($tokens)||count($tokens)<1||count($tokens)>10)throw new ApiError('Choose between one and ten different time slots.');
    $quotes=[];
    foreach($tokens as $token){
        if(!is_string($token)||!preg_match('/^[a-f0-9]{48}$/D',$token))throw new ApiError('Review your selected times again.');
        $quotes[]=saved_quote(['quote_token'=>$token]);
    }
    if(count(array_unique($tokens))!==count($tokens))throw new ApiError('Choose different time slots.');
    usort($quotes,fn($a,$b)=>strcmp($a['start_time'],$b['start_time']));
    $court=(int)$quotes[0]['court_id'];$date=$quotes[0]['booking_date'];
    foreach($quotes as $index=>$quote){
        if((int)$quote['court_id']!==$court||$quote['booking_date']!==$date||strtotime($quote['end_time'])-strtotime($quote['start_time'])!==3600||substr($quote['start_time'],3,2)!=='00')throw new ApiError('Select one-hour slots on the same court and date, starting on the hour.');
        if($index&&$quote['start_time']<$quotes[$index-1]['end_time'])throw new ApiError('Selected court times must not overlap.');
    }
    db()->beginTransaction();
    try{
        one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$court]);
        expire_payment_holds_for_court($court);
        if($guest){limit_guest_checkout();$user=guest_checkout_customer($contact);}
        $hold=one('SELECT * FROM booking_checkout_holds WHERE user_id=? AND request_key=? FOR UPDATE',[$user['user_id'],$request]);
        if($guest&&$hold&&empty($_SESSION['guest_checkout_holds'][$hold['hold_id']]))throw new ApiError('Choose your slots again to start a new checkout.',409);
        if(!$hold){
            $addons=booking_addon_selection($data['addons']??[],true);
            $addonFee=round(array_sum(array_column($addons,'line_total')),2);
            if($addons&&(!isset($data['addons_total'])||!is_numeric($data['addons_total'])||abs((float)$data['addons_total']-$addonFee)>0.005))throw new ApiError('Add-on prices changed. Return to slot selection and review the updated prices before confirming.',409);
            $holdId=create_payment_hold_record((int)$user['user_id'],$court,$request);
            if($guest)query('UPDATE booking_checkout_holds SET guest_name=?,guest_email=?,guest_phone=? WHERE hold_id=?',[$contact['full_name'],$contact['email'],$contact['phone'],$holdId]);
            foreach($quotes as $index=>$quote){
                $booking=create_reservation(['court_id'=>$court,'booking_date'=>$date,'start_time'=>$quote['start_time'],'end_time'=>$quote['end_time'],'status'=>'Pending','user_id'=>$user['user_id'],'payment_method_id'=>$method,'admin_notes'=>''],(float)$quote['amount'],false);
                query('UPDATE bookings SET checkout_hold_id=? WHERE booking_id=?',[$holdId,$booking['booking_id']]);
                // One add-on order per checkout, attached to its first time slot only.
                // court_fee is the legacy locked booking total used by payments/reports.
                if($index===0&&$addons){
                    foreach($addons as $item)query('INSERT INTO booking_addon_items(booking_id,product_id,name,quantity,unit_price) VALUES(?,?,?,?,?)',[$booking['booking_id'],$item['product_id'],$item['name'],$item['quantity'],$item['unit_price']]);
                    $notes='Add-ons: '.implode('; ',array_map(fn($item)=>$item['name'].' x'.$item['quantity'].' (PHP '.number_format($item['unit_price'],2).' each)',$addons));
                    query('UPDATE bookings SET court_fee=court_fee+?,addons_total=?,admin_notes=? WHERE booking_id=?',[$addonFee,$addonFee,$notes,$booking['booking_id']]);
                    query('UPDATE payments SET amount=amount+? WHERE booking_id=?',[$addonFee,$booking['booking_id']]);
                    log_activity('booking',(int)$booking['booking_id'],'Add-ons ordered',$notes);
                }
            }
            $hold=one('SELECT * FROM booking_checkout_holds WHERE hold_id=?',[$holdId]);
        }
        $result=customer_checkout_hold_result($hold,$user);
        db()->commit();
        if($guest){
            $_SESSION['guest_checkout_holds'][$hold['hold_id']]=true;
            $_SESSION['guest_checkout_requests'][$request]=(int)$hold['hold_id'];
        }
        foreach($tokens as $token)unset($_SESSION['booking_quotes'][$token]);
        return $result;
    }catch(Throwable $error){
        if(db()->inTransaction())db()->rollBack();
        if(!$guest&&$error instanceof PDOException&&$error->getCode()==='23000'){
            $existing=one('SELECT * FROM booking_checkout_holds WHERE user_id=? AND request_key=?',[$user['user_id'],$request]);
            if($existing)return customer_checkout_hold_result($existing,$user);
        }
        throw $error;
    }
});
