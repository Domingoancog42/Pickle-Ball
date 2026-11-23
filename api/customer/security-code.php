<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/mail-service.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$current=$data['current_password'] ?? '';
    if(!is_string($current)||!security_password_verify($current,(string)scalar('SELECT password_hash FROM users WHERE user_id=?',[$user['user_id']])))throw new ApiError('Enter your correct current password before requesting a code.',403);
    $identity=hash('sha256','profile-password:'.$user['user_id']);
    if(scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 60 SECOND)',[$identity])||(int)scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$identity])>=5)throw new ApiError('Please wait one minute before requesting another code. Requests are limited to five per 15 minutes.',429);
    query('INSERT INTO login_attempts(identity_hash) VALUES(?)',[$identity]);
    $id=bin2hex(random_bytes(24));$code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
    db()->beginTransaction();$account=one('SELECT password_hash FROM users WHERE user_id=? FOR UPDATE',[$user['user_id']]);
    if(!security_password_verify($current,$account['password_hash']))throw new ApiError('Your password changed. Please sign in again.',403);
    if(!empty($_SESSION['profile_password_otp_id']))query('UPDATE password_reset_codes SET used_at=NOW() WHERE reset_id=? AND user_id=?',[$_SESSION['profile_password_otp_id'],$user['user_id']]);
    query('INSERT INTO password_reset_codes(reset_id,user_id,code_hash,ticket_hash,expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))',[$id,$user['user_id'],$hash,hash('sha256',$account['password_hash'])]);
    try{send_password_reset_code($user['email'],$user['full_name'],$code,true);}catch(Throwable){throw new ApiError('The verification email could not be sent. Please try again shortly.',503);}
    db()->commit();$_SESSION['profile_password_otp_id']=$id;
    return ['ok'=>true,'resend_after'=>60,'message'=>'A 6-digit verification code was sent to your account email. It expires in 10 minutes.'];
});
