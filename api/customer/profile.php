<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/password-policy.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']==='GET')return ['policy'=>password_policy(),'user'=>one('SELECT user_id,full_name,email,phone,avatar_path,created_at FROM users WHERE user_id=?',[$user['user_id']])];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);$data=input();$name=text_value($data,'full_name',100);$phone=text_value($data,'phone',11,false);$password=$data['new_password'] ?? '';
    if($phone!==''&&!preg_match('/^[0-9]{11}$/D',$phone))throw new ApiError('Enter an 11-digit contact number or leave it blank.');
    if(!is_string($password))throw new ApiError('Invalid password.');
    if($password!==''){
        if($password!==($data['confirm_password'] ?? ''))throw new ApiError('The passwords must match.');security_password_valid($password);
        $current=$data['current_password'] ?? '';if(!is_string($current) || !security_password_verify($current,(string)scalar('SELECT password_hash FROM users WHERE user_id=?',[$user['user_id']])))throw new ApiError('Your current password is incorrect.',403);
        foreach(password_requirements($password,$user['email']) as $rule=>$valid)if(!$valid)throw new ApiError(password_policy()['labels'][$rule].'.');
    }
    db()->beginTransaction();$account=one('SELECT password_hash,session_version FROM users WHERE user_id=? FOR UPDATE',[$user['user_id']]);
    if($password!==''){
        if(!security_password_verify($current,$account['password_hash']))throw new ApiError('Your current password is incorrect.',403);
        if(security_password_verify($password,$account['password_hash']))throw new ApiError('Choose a password different from your current password.');
        $id=$_SESSION['profile_password_otp_id'] ?? '';$code=$data['otp_code'] ?? '';
        if(!$id||!is_string($code)||!preg_match('/^\d{6}$/D',$code))throw new ApiError('Request and enter the 6-digit email verification code before changing your password.');
        $otp=one('SELECT * FROM password_reset_codes WHERE reset_id=? AND user_id=? FOR UPDATE',[$id,$user['user_id']]);
        if(!$otp||$otp['used_at']||$otp['attempts']>=5||strtotime($otp['expires_at'])<=time()||!hash_equals($otp['ticket_hash'] ?? '',hash('sha256',$account['password_hash'])))throw new ApiError('The verification code expired or is no longer valid. Request a new code.',409);
        if(!password_verify($code,$otp['code_hash'])){query('UPDATE password_reset_codes SET attempts=attempts+1 WHERE reset_id=?',[$id]);db()->commit();throw new ApiError('Incorrect verification code. Check your email and try again.');}
    }
    $avatar=safe_image($_FILES['avatar'] ?? null,'avatars') ?? $user['avatar_path'];query('UPDATE users SET full_name=?,phone=?,avatar_path=? WHERE user_id=?',[$name,$phone,$avatar,$user['user_id']]);
    if($password!==''){
        query('UPDATE users SET password_hash=? WHERE user_id=?',[security_password_hash($password),$user['user_id']]);
        security_password_changed((int)$user['user_id']);
        query('UPDATE password_reset_codes SET used_at=NOW(),ticket_hash=NULL WHERE user_id=? AND used_at IS NULL',[$user['user_id']]);
    }
    log_activity('customer',(int)$user['user_id'],'Player profile updated');db()->commit();
    if($password!==''){session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['session_version']=(int)scalar('SELECT session_version FROM users WHERE user_id=?',[$user['user_id']]);unset($_SESSION['profile_password_otp_id']);}
    return ['user'=>session_user(),'csrf'=>csrf_token()];
});
