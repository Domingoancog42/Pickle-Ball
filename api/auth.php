<?php
require __DIR__.'/password-setup-service.php';
require_once __DIR__.'/password-policy.php';
require_once __DIR__.'/turnstile-service.php';
try {
    if ($_SERVER['REQUEST_METHOD']==='GET') json_response(['user'=>session_user(),'csrf'=>csrf_token(),'requires_password_change'=>pending_password_user()!==null,'turnstile'=>['site_key'=>turnstile_config()['site_key']]]);
    if ($_SERVER['REQUEST_METHOD']!=='POST') throw new ApiError('Method not allowed.',405);
    require_csrf(); $data=input(); $action=$data['action'] ?? '';
    if ($action==='logout') {
        if($actor=session_user())audit_event('Logout','Signed out.','user',(int)$actor['user_id']);
        $_SESSION=[]; session_destroy();
        setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
        json_response(['ok'=>true]);
    }
    if($action==='change_temporary_password'){
        $pending=pending_password_user();if(!$pending)throw new ApiError('Your password setup session expired. Log in with your temporary password again.',401);
        $new=$data['new_password'] ?? '';if(!is_string($new)||$new!==($data['confirm_password'] ?? null))throw new ApiError('The passwords must match.');security_password_valid($new);
        foreach(password_requirements($new,$pending['email']) as $rule=>$valid)if(!$valid)throw new ApiError(password_policy()['labels'][$rule].'.');
        db()->beginTransaction();
        $account=one('SELECT * FROM users WHERE user_id=? FOR UPDATE',[$pending['user_id']]);
        if(!$account['must_change_password']||$account['account_status']!=='active'||strtotime($account['temporary_password_expires'])<=time()||!hash_equals($_SESSION['password_setup_version'] ?? '',hash('sha256',$account['password_hash'])))throw new ApiError('This temporary password is no longer valid. Please log in again.',401);
        if(security_password_verify($new,$account['password_hash']))throw new ApiError('Your new password must be different from the temporary password.');
        query('UPDATE users SET password_hash=?,must_change_password=0,temporary_password_expires=NULL WHERE user_id=?',[security_password_hash($new),$pending['user_id']]);
        security_password_changed((int)$pending['user_id'],true);
        query("INSERT INTO activity_logs(actor_id,entity_type,entity_id,action,details) VALUES(?,'customer',?,'Initial password changed','Login required after password setup')",[$pending['user_id'],$pending['user_id']]);db()->commit();
        session_regenerate_id(true);$_SESSION=['csrf'=>bin2hex(random_bytes(32))];
        json_response(['ok'=>true,'csrf'=>csrf_token(),'redirect'=>'index.html#login','message'=>'Password saved. Log in again with your new password.']);
    }
    if (!in_array($action,['login','signup'],true)) throw new ApiError('Invalid authentication action.');
    if ($action==='login') require_turnstile($data['cf-turnstile-response'] ?? null);
    $email=strtolower(text_value($data,'email',150)); $password=$data['password'] ?? '';
    if (!is_string($password) || strlen($password)>4096) throw new ApiError('Invalid email or password.',401);
    $ip=hash('sha256','ip:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown')); $identity=hash('sha256','email:'.$email);
    // Sign-in uses the per-account policy below; signup keeps its separate abuse limit.
    if ($action==='signup' && (int)scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$ip])>=10) throw new ApiError('Too many attempts. Try again in 15 minutes.',429);
    if ($action==='signup') {
        $name=text_value($data,'name',100);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid email address.');
        if(scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 60 SECOND)',[$identity]))throw new ApiError('Wait one minute before requesting another signup email.',429);
        query('INSERT INTO login_attempts (identity_hash) VALUES (?),(?)',[$ip,$identity]);
        require_once __DIR__.'/mail-service.php';$temporary=bin2hex(random_bytes(10));db()->beginTransaction();
        $existing=one('SELECT * FROM users WHERE email=? FOR UPDATE',[$email]);
        if($existing&&(!$existing['must_change_password']||$existing['role']!=='customer'||$existing['account_status']!=='active'))throw new ApiError('An account with this email already exists. Please log in.',409);
        $hash=password_hash($temporary,PASSWORD_DEFAULT);
        if($existing)query('UPDATE users SET password_hash=?,temporary_password_expires=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE user_id=?',[$hash,$existing['user_id']]);
        else query("INSERT INTO users(full_name,email,password_hash,role,must_change_password,temporary_password_expires) VALUES(?,?,?,'customer',1,DATE_ADD(NOW(),INTERVAL 1 HOUR))",[$name,$email,$hash]);
        try{send_temporary_password($email,$existing['full_name'] ?? $name,$temporary);}catch(Throwable){throw new ApiError('We could not send your temporary password. Please try again shortly or contact the venue.',503);}
        audit_event('Customer registered','Signup temporary password requested.','user',(int)($existing['user_id'] ?? db()->lastInsertId()),'success',null,(int)($existing['user_id'] ?? db()->lastInsertId()));
        db()->commit();json_response(['ok'=>true,'message'=>'Your temporary password has been emailed. Check your inbox or spam folder, then log in.']);
    }
    db()->beginTransaction();
    // Serialize password attempts for the same user across browsers and IPs.
    $user=one('SELECT * FROM users WHERE email=? FOR UPDATE',[$email]);
    $lockKey=hash('sha256',$user?'account:'.$user['user_id']:'unknown:'.$identity);
    security_lock_check($lockKey,$user?(int)$user['user_id']:null);
    $valid=security_password_verify($password,$user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
    if (!$user || !$valid || $user['account_status']!=='active' || !in_array($user['role'],['admin','customer'],true)) {
        security_failed_login($lockKey,$user?(int)$user['user_id']:null);
        query('INSERT INTO login_attempts (identity_hash) VALUES (?),(?)',[$ip,$identity]);
        db()->commit(); // Failed attempts must survive the error response.
        security_lock_check($lockKey,$user?(int)$user['user_id']:null);
        throw new ApiError('Invalid email or password.',401);
    }
    query('DELETE FROM login_security_tracking WHERE user_id=?',[$user['user_id']]);
    query('DELETE FROM login_attempts WHERE identity_hash=?',[$identity]);
    db()->commit();
    $policy=security_settings();
    if(!$user['must_change_password']&&$policy['require_expired_reset']&&(int)$policy['password_expiry_days']>0&&$user['password_changed_at']&&strtotime($user['password_changed_at'].' +'.(int)$policy['password_expiry_days'].' days')<=time()){audit_event('Login blocked','Expired password requires email recovery.','user',(int)$user['user_id'],'failure',null,(int)$user['user_id']);throw new ApiError('Your password has expired. Use Forgot password to verify your email and choose a new password.',403);}
    if($user['must_change_password']){
        if(!$user['temporary_password_expires']||strtotime($user['temporary_password_expires'])<=time())throw new ApiError('Your temporary password expired. Submit the signup form again to receive a new one.',401);
    audit_event('Login success','Signed in through shared login.','user',(int)$user['user_id'],'success',null,(int)$user['user_id']);
        session_regenerate_id(true);$_SESSION=['password_setup_user_id'=>$user['user_id'],'password_setup_until'=>time()+900,'password_setup_version'=>hash('sha256',$user['password_hash']),'csrf'=>bin2hex(random_bytes(32))];
        setcookie(session_name(),session_id(),['expires'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
        json_response(['requires_password_change'=>true,'csrf'=>csrf_token(),'redirect'=>'change-password.php']);
    }
    audit_event('Login success','Signed in through shared login.','user',(int)$user['user_id'],'success',null,(int)$user['user_id']);
    if(!str_starts_with($user['password_hash'],'pikol-v1:') || password_needs_rehash(substr($user['password_hash'],9),PASSWORD_DEFAULT)) query('UPDATE users SET password_hash=? WHERE user_id=?',[security_password_hash($password),$user['user_id']]);
    $remember=!empty($data['remember']); session_regenerate_id(true);
    $_SESSION=['user_id'=>$user['user_id'],'session_version'=>(int)$user['session_version'],'last_activity'=>time(),'session_ttl'=>$remember?2592000:7200,'csrf'=>bin2hex(random_bytes(32))];
    if($user['role']==='admin') $_SESSION['admin_id']=$user['user_id'];
    if($policy['login_notifications']){query("INSERT INTO notifications(title,message,entity_type,entity_id,recipient_id) VALUES('New sign-in','Your account signed in. Review Account security if this was not you.','user',?,?)",[$user['user_id'],$user['user_id']]);}
    setcookie(session_name(),session_id(),['expires'=>$remember?time()+2592000:0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
    json_response(['user'=>session_user(),'csrf'=>csrf_token(),'redirect'=>$user['role']==='admin'?'admin/index.php':'index.html']);
} catch(ApiError $e) { try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}json_response(['error'=>$e->getMessage()],$e->status); }
catch(Throwable $e) { try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}error_log('Authentication error: '.get_class($e)); json_response(['error'=>'Authentication is unavailable. Open the website through XAMPP Apache and check MySQL is running.'],503); }
