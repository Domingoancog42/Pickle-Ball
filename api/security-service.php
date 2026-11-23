<?php
// Shared by authentication, admin APIs, customer APIs and protected CLI jobs.
function security_settings(): array {
    static $settings;
    if($settings!==null)return $settings;
    $settings=require dirname(__DIR__).'/config/security.php';
    foreach(rows("SELECT setting_key,setting_value FROM system_settings WHERE setting_key LIKE 'security_policy_%'") as $row){
        $key=substr($row['setting_key'],16);
        if(array_key_exists($key,$settings))$settings[$key]=(int)$row['setting_value'];
    }
    return $settings;
}
function security_password_hash(string $password): string {
    // SHA-384 prehash gives bcrypt a fixed 64-byte input, avoiding its 72-byte limit.
    return 'pikol-v1:'.password_hash(base64_encode(hash('sha384',$password,true)),PASSWORD_DEFAULT);
}
function security_password_verify(string $password,string $hash): bool {
    if(str_starts_with($hash,'pikol-v1:'))return password_verify(base64_encode(hash('sha384',$password,true)),substr($hash,9));
    return password_verify($password,$hash); // Preserve all existing PHP password hashes.
}
function security_password_valid(string $password): void {
    $s=security_settings();$n=mb_strlen($password,'UTF-8');
    if(str_contains($password,"\0")||$n<(int)$s['minimum_password_length']||$n>(int)$s['maximum_password_length'])throw new ApiError('Use '.$s['minimum_password_length'].'–'.$s['maximum_password_length'].' characters for your new password.');
    if(in_array(strtolower($password),['password','password123','123456789012','qwerty123456','admin123456','pikolcastle123'],true))throw new ApiError('Choose a less common password.');
}
function security_password_changed(int $id,bool $alwaysInvalidate=false): void {
    $increment=$alwaysInvalidate||(bool)security_settings()['invalidate_sessions'];
    query('UPDATE users SET password_changed_at=NOW(),session_version=session_version+? WHERE user_id=?',[$increment?1:0,$id]);
    audit_event('Password changed','Account password changed.','user',$id,'success',null,$id);
}
function audit_event(string $action,string $description='',string $type='',?int $id=null,string $result='success',?array $changes=null,?int $actorId=null): void {
    $actorId ??= $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    $actor=$actorId?one('SELECT full_name,email,role FROM users WHERE user_id=?',[$actorId]):null;
    $ua=mb_substr($_SERVER['HTTP_USER_AGENT'] ?? (PHP_SAPI==='cli'?'CLI runner':''),0,512);
    $browser='Unknown';foreach(['Edg/'=>'Edge','OPR/'=>'Opera','Firefox/'=>'Firefox','Chrome/'=>'Chrome','Safari/'=>'Safari'] as $token=>$name)if(str_contains($ua,$token)){$browser=$name;break;}
    $os='Unknown';foreach(['Windows'=>'Windows','Android'=>'Android','iPhone'=>'iOS','iPad'=>'iOS','Mac OS'=>'macOS','Linux'=>'Linux'] as $token=>$name)if(str_contains($ua,$token)){$os=$name;break;}
    $device=PHP_SAPI==='cli'?'Server':(preg_match('/iPad|Tablet/i',$ua)?'Tablet':(preg_match('/Mobile|iPhone|Android/i',$ua)?'Mobile':'Desktop'));
    $ip=$_SERVER['REMOTE_ADDR'] ?? null;if($ip&&!filter_var($ip,FILTER_VALIDATE_IP))$ip=null;
    // Callers provide only approved setting values. Never include submitted credentials.
    query('INSERT INTO audit_logs(user_id,actor_name,actor_email,actor_role,action,description,entity_type,entity_id,ip_address,user_agent,browser,operating_system,device_type,result_status,change_data) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$actorId,$actor['full_name'] ?? (PHP_SAPI==='cli'?'Scheduled runner':'Unauthenticated'),$actor['email'] ?? '',$actor['role'] ?? 'system',$action,mb_substr($description,0,3000),$type,$id,$ip,$ua,$browser,$os,$device,$result,$changes?json_encode($changes,JSON_THROW_ON_ERROR):null]);
}
function security_lock_check(string $key,?int $userId=null): void {
    if($userId){
        // Honor account locks created before account-wide tracking was introduced.
        $locked=scalar('SELECT MAX(locked_until) FROM login_security_tracking WHERE user_id=? AND locked_until>NOW()',[$userId]);
        if($locked){$minutes=max(1,(int)ceil((strtotime($locked)-time())/60));throw new ApiError("This account is temporarily locked. Try again in {$minutes} minute(s).",429);}
    }
    $r=one('SELECT * FROM login_security_tracking WHERE identity_hash=?',[$key]);
    if(!$r)return;
    if($r['locked_until']&&strtotime($r['locked_until'])>time()){$minutes=max(1,(int)ceil((strtotime($r['locked_until'])-time())/60));throw new ApiError("This account is temporarily locked. Try again in {$minutes} minute(s).",429);}
    if($r['locked_until']){
        query('UPDATE login_security_tracking SET locked_until=NULL,failed_attempts=0 WHERE identity_hash=?',[$key]);
        audit_event('Account unlocked','Lockout period elapsed.','authentication',null,'success',null,$r['user_id']?(int)$r['user_id']:null);
    }
}
function security_failed_login(string $key,?int $userId): void {
    $s=security_settings();
    query('INSERT INTO login_security_tracking(identity_hash,user_id,failed_attempts,last_failed_at) VALUES(?,?,1,NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),failed_attempts=IF(last_failed_at<DATE_SUB(NOW(),INTERVAL 1 DAY),1,failed_attempts+1),last_failed_at=NOW()',[$key,$userId]);
    $n=(int)scalar('SELECT failed_attempts FROM login_security_tracking WHERE identity_hash=?',[$key]);
    audit_event('Login failure','Incorrect credentials or unavailable account.','authentication',null,'failure',null,$userId);
    if($n>=(int)$s['lockout_failed_attempts']){
        query('UPDATE login_security_tracking SET locked_until=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE identity_hash=?',[(int)$s['lockout_duration_minutes'],$key]);
        audit_event('Account locked','Login attempt limit reached for this individual account.','authentication',null,'failure',null,$userId);
    }
}
