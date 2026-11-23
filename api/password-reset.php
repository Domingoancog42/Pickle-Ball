<?php
require __DIR__.'/password-setup-service.php';
require_once __DIR__.'/password-policy.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    require_csrf();$data=input();$action=$data['action'] ?? '';
    if($action==='request'){
        $email=strtolower(text_value($data,'email',150));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ApiError('Enter a valid email address.');
        $ip=hash('sha256','reset-ip:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));$identity=hash('sha256','reset-email:'.$email);
        if((int)scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash IN (?,?) AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$ip,$identity])>=10||scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 60 SECOND)',[$identity]))throw new ApiError('Please wait before requesting another code. You can resend after one minute.',429);
        query('INSERT INTO login_attempts(identity_hash) VALUES(?),(?)',[$ip,$identity]);
        $user=one("SELECT user_id,full_name,email FROM users WHERE email=? AND account_status='active'",[$email]);
        $id=bin2hex(random_bytes(24));$code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);
        db()->beginTransaction();
        if(!empty($_SESSION['reset_request_id']))query('UPDATE password_reset_codes SET used_at=NOW() WHERE reset_id=?',[$_SESSION['reset_request_id']]);
        query('INSERT INTO password_reset_codes(reset_id,user_id,code_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))',[$id,$user['user_id'] ?? null,$hash]);
        if($user){require_once __DIR__.'/mail-service.php';try{send_password_reset_code($user['email'],$user['full_name'],$code);}catch(Throwable){throw new ApiError('We could not send the reset email. Please try again shortly.',503);}}
        db()->commit();unset($_SESSION['reset_ticket'],$_SESSION['reset_verified_until']);$_SESSION['reset_request_id']=$id;
        json_response(['ok'=>true,'message'=>'If an active account uses this email, a reset code has been sent. Check your inbox or spam folder.','resend_after'=>60,'expires_in'=>600]);
    }
    $id=$_SESSION['reset_request_id'] ?? '';if(!$id)throw new ApiError('Request a password reset code first.',401);
    if($action==='verify'){
        $code=text_value($data,'code',6);if(!preg_match('/^\d{6}$/D',$code))throw new ApiError('Enter the 6-digit code from your email.');
        db()->beginTransaction();$reset=one('SELECT * FROM password_reset_codes WHERE reset_id=? FOR UPDATE',[$id]);
        if(!$reset||$reset['used_at']||strtotime($reset['expires_at'])<=time()||$reset['attempts']>=5)throw new ApiError('The code expired or has too many attempts. Request a new code.',409);
        if(!password_verify($code,$reset['code_hash'])||!$reset['user_id']){query('UPDATE password_reset_codes SET attempts=attempts+1 WHERE reset_id=?',[$id]);db()->commit();throw new ApiError('Incorrect code. Check your email and try again.',422);}
        $ticket=bin2hex(random_bytes(32));query('UPDATE password_reset_codes SET verified_at=NOW(),ticket_hash=? WHERE reset_id=?',[hash('sha256',$ticket),$id]);db()->commit();session_regenerate_id(true);$_SESSION['reset_ticket']=$ticket;$_SESSION['reset_verified_until']=time()+600;$_SESSION['csrf']=bin2hex(random_bytes(32));
        json_response(['ok'=>true,'csrf'=>csrf_token(),'policy'=>password_policy()]);
    }
    if($action!=='reset')throw new ApiError('Invalid reset action.');
    if(empty($_SESSION['reset_ticket'])||time()>($_SESSION['reset_verified_until'] ?? 0))throw new ApiError('Verify your reset code before changing your password.',401);
    $reset=one('SELECT user_id FROM password_reset_codes WHERE reset_id=?',[$id]);if(!$reset||!$reset['user_id'])throw new ApiError('Request a new reset code.',401);
    db()->beginTransaction();$user=one('SELECT * FROM users WHERE user_id=? FOR UPDATE',[$reset['user_id']]);$reset=one('SELECT * FROM password_reset_codes WHERE reset_id=? FOR UPDATE',[$id]);
    if(!$user||$user['account_status']!=='active'||$reset['used_at']||!$reset['verified_at']||strtotime($reset['expires_at'])<=time()||!hash_equals($reset['ticket_hash'] ?? '',hash('sha256',$_SESSION['reset_ticket'])))throw new ApiError('Your reset session expired. Request a new code.',401);
    $new=$data['new_password'] ?? '';if(!is_string($new)||$new!==($data['confirm_password'] ?? null))throw new ApiError('The passwords must match.');
    foreach(password_requirements($new,$user['email']) as $rule=>$valid)if(!$valid)throw new ApiError(password_policy()['labels'][$rule].'.');
    if(security_password_verify($new,$user['password_hash']))throw new ApiError('Choose a password different from your current password.');
    query('UPDATE users SET password_hash=?,must_change_password=0,temporary_password_expires=NULL WHERE user_id=?',[security_password_hash($new),$user['user_id']]);
    query('UPDATE password_reset_codes SET used_at=NOW(),ticket_hash=NULL WHERE user_id=? AND used_at IS NULL',[$user['user_id']]);
    security_password_changed((int)$user['user_id'],true);
    query('DELETE FROM login_security_tracking WHERE user_id=?',[$user['user_id']]);
    query("INSERT INTO activity_logs(actor_id,entity_type,entity_id,action,details) VALUES(?,'customer',?,'Password reset','Email code verified; previous sessions invalidated')",[$user['user_id'],$user['user_id']]);db()->commit();session_regenerate_id(true);$_SESSION=['csrf'=>bin2hex(random_bytes(32))];
    json_response(['ok'=>true,'csrf'=>csrf_token(),'message'=>'Password changed. Log in with your new password.']);
}catch(ApiError $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}json_response(['error'=>$e->getMessage()],$e->status);}
catch(Throwable $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}error_log('Password reset error: '.get_class($e));json_response(['error'=>'Password reset is temporarily unavailable. Please try again.'],503);}
