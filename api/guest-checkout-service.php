<?php
// Included only by the public checkout gateway. Guests never receive an account session.
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}

function guest_checkout_hold(int $id): array {
    if(empty($_SESSION['guest_checkout_holds'][$id]))throw new ApiError('This checkout is not available in this browser. Choose your slots again.',404);
    $hold=one('SELECT * FROM booking_checkout_holds WHERE hold_id=? AND guest_email IS NOT NULL',[$id]);
    if(!$hold)throw new ApiError('Checkout not found.',404);
    return $hold;
}

function guest_checkout_contact(array $data): array {
    $name=text_value($data,'full_name',100);
    $email=strtolower(text_value($data,'email',150));
    $phone=text_value($data,'phone',20);
    if(preg_match('/[\x00-\x1f\x7f]/u',$name))throw new ApiError('Enter your full name.');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ApiError('Enter a valid email address.');
    $phone=preg_replace('/[\s()\-]/','',$phone);
    if(!preg_match('/^(?:09\d{9}|\+639\d{9})$/D',$phone))throw new ApiError('Enter an 11-digit mobile number starting with 09, or use +639 followed by 9 digits.');
    return ['full_name'=>$name,'email'=>$email,'phone'=>$phone];
}

function guest_checkout_customer(array $contact): array {
    // Keep existing account details intact; each guest hold stores its own contact snapshot.
    $user=one('SELECT user_id,role,account_status FROM users WHERE email=? FOR UPDATE',[$contact['email']]);
    if($user&&($user['role']!=='customer'||$user['account_status']!=='active'))throw new ApiError('This email cannot be used for guest checkout. Use another contact email.');
    if(!$user){
        query("INSERT INTO users(full_name,email,phone,password_hash,role) VALUES(?,?,?,?,'customer')",[$contact['full_name'],$contact['email'],$contact['phone'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
        $user=['user_id'=>(int)db()->lastInsertId(),'role'=>'customer'];
    }
    return $user;
}

function limit_guest_checkout(): void {
    $identity=hash('sha256','guest-checkout:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if((int)scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$identity])>=10)throw new ApiError('Too many guest booking requests. Try again in 15 minutes.',429);
    query('INSERT INTO login_attempts(identity_hash) VALUES(?)',[$identity]);
}

function guest_checkout_handler(callable $callback): never {
    try{
        $action=PIKOL_GUEST_CHECKOUT;
        $method=$_SERVER['REQUEST_METHOD'];
        $allowed=['available-courts'=>['GET'],'available-slots'=>['GET'],'quote'=>['POST'],'checkout-hold'=>['GET','POST'],'checkout-proof'=>['POST']];
        if(!in_array($method,$allowed[$action] ?? [],true))throw new ApiError('Method not allowed.',405);
        if($method!=='GET')require_csrf();
        $data=$method==='GET'?$_GET:input();
        // Guest checkout can request new times only, never access or reschedule account records.
        if(!empty($data['booking_id']))throw new ApiError('Choose slots for a new guest booking.',403);
        $user=['user_id'=>0,'role'=>'guest'];
        if(($action==='checkout-hold'&&$method==='GET')||$action==='checkout-proof'){
            $hold=guest_checkout_hold(positive_id($data['hold_id'] ?? null));
            $user['user_id']=(int)$hold['user_id'];
            if($action==='checkout-proof'){
                $ids=$data['payment_ids'] ?? [];
                if(!is_array($ids)||!count($ids)||count($ids)>10)throw new ApiError('Choose between one and ten payments.');
                $ids=array_map('positive_id',$ids);
                $marks=implode(',',array_fill(0,count($ids),'?'));
                $count=(int)scalar("SELECT COUNT(*) FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE b.checkout_hold_id=? AND p.payment_id IN ($marks)",[$hold['hold_id'],...$ids]);
                if($count!==count($ids))throw new ApiError('Payment not found in this checkout.',404);
            }
        }
        json_response($callback($user));
    }catch(ApiError $error){if(db()->inTransaction())db()->rollBack();json_response(['error'=>$error->getMessage()],$error->status);}
    catch(Throwable $error){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}error_log((string)$error);json_response(['error'=>'We could not complete your checkout. Please try again.'],500);}
}
