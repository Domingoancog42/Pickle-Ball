<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/connection-pdo.php';
date_default_timezone_set('Asia/Manila');
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime','2592000');
session_name('pikol_admin');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Strict','path'=>'/']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

final class ApiError extends RuntimeException {
    public function __construct(string $message, public int $status = 422) { parent::__construct($message); }
}
function query(string $sql, array $params=[]): PDOStatement {
    $statement=db()->prepare($sql);
    foreach($params as $key=>$value){
        if(!is_scalar($value)&&$value!==null)throw new InvalidArgumentException('SQL parameters must be scalar values or null.');
        $type=match(true){is_int($value)=>PDO::PARAM_INT,is_bool($value)=>PDO::PARAM_BOOL,$value===null=>PDO::PARAM_NULL,default=>PDO::PARAM_STR};
        $statement->bindValue(is_int($key)?$key+1:':'.ltrim($key,':'),$value,$type);
    }
    $statement->execute();
    if(hosting_portable()&&preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-z_]+)`?\b/i',$sql,$match)){
        $table=strtolower($match[1]);
        $tracked=['courts','venues','surface_types','court_schedules','court_maintenance','record_archives','bookings','booking_statuses','booking_change_requests','booking_checkout_holds','payments','payment_methods','system_settings','customer_ratings','users','notifications','audit_logs','backup_history','backup_settings','login_security_tracking','booking_addon_products','booking_addon_items'];
        if(in_array($table,$tracked,true)){
            // Preserve the originating INSERT identity for booking/customer/payment callers.
            $insertId=db()->lastInsertId();
            $change=db()->prepare('INSERT INTO live_versions(table_name,revision) VALUES(?,1+LAST_INSERT_ID(?)*0) ON DUPLICATE KEY UPDATE revision=revision+1+LAST_INSERT_ID(?)*0');
            $change->execute([$table,$insertId,$insertId]);
        }
    }
    return $statement;
}
function rows(string $sql, array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql, array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function scalar(string $sql, array $params=[]): mixed { return query($sql,$params)->fetchColumn(); }
function page_window(int $total,int $default=20): array {
    $size=filter_var($_GET['per_page'] ?? $default,FILTER_VALIDATE_INT);
    if(!in_array($size,[5,10,12,15,20,25,50,100],true))$size=$default;
    $pages=max(1,(int)ceil($total/$size));
    $requested=filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT);
    $page=min($pages,max(1,$requested ?: 1));
    return [$page,$size,($page-1)*$size,$pages];
}
function json_response(mixed $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function require_csrf(): void {
    if (!hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '')) throw new ApiError('Your security token expired. Refresh and try again.',403);
}
function session_user(): ?array {
    $id=$_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    if (!$id) return null;
    $user=one("SELECT user_id,full_name,email,phone,avatar_path,role,session_version,password_changed_at FROM users WHERE user_id=? AND account_status='active' AND must_change_password=0",[$id]);
    if($user&&(int)$user['session_version']!==(int)($_SESSION['session_version'] ?? 0)){$_SESSION=[];return null;}
    $idle=time()-($_SESSION['last_activity']??0);$idleLimit=(int)security_settings()['session_timeout_minutes']*60;
    if ($user && $user['role']==='customer' && $idle >= $idleLimit+30) { audit_event('Session expired','Player session expired after the 30-second inactivity warning.','user',(int)$id); $_SESSION=[]; session_regenerate_id(true); return null; }
    if($user && security_settings()['require_expired_reset'] && (int)security_settings()['password_expiry_days']>0 && $user['password_changed_at'] && strtotime($user['password_changed_at'].' +'.(int)security_settings()['password_expiry_days'].' days')<=time()){audit_event('Session expired','Password reset required by the expiry policy.','user',(int)$id);$_SESSION=[];return null;}
    if($user)unset($user['session_version'],$user['password_changed_at']);
    $passive=preg_match('~/(?:admin|customer)/notifications\.php$~',$_SERVER['SCRIPT_NAME'] ?? '') || (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/auth.php')&&($_SERVER['REQUEST_METHOD'] ?? '')==='GET');
    $passive=$passive||str_ends_with($_SERVER['SCRIPT_NAME']??'','/live.php')||($_SERVER['HTTP_X_PIKOL_BACKGROUND']??'')==='1';
    // Automatic data reads are not evidence of player activity. Browser activity
    // is recorded explicitly by session.php, so polling cannot prevent timeout.
    $passive=$passive||($user&&$user['role']==='customer'&&($_SERVER['REQUEST_METHOD']??'')==='GET'&&str_contains($_SERVER['SCRIPT_NAME']??'','/api/'));
    if ($user&&!$passive&&($user['role']!=='customer'||$idle<$idleLimit)) $_SESSION['last_activity']=time();
    return $user;
}
function require_admin(): array { $user=admin_user(); if (!$user) { audit_event('Administrator access denied','An admin-only endpoint rejected the request.','authorization',null,'failure');throw new ApiError('Please sign in with an administrator account.',401); } return $user; }
function admin_user(): ?array { $user=session_user(); return $user && $user['role']==='admin' ? $user : null; }
function input(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        try { $data=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new ApiError('Invalid JSON request.'); }
        if (!is_array($data)) throw new ApiError('Invalid request.');
        return $data;
    }
    return $_POST;
}
function text_value(array $data,string $key,int $max=255,bool $required=true): string {
    if (isset($data[$key]) && !is_scalar($data[$key])) throw new ApiError('Invalid '.str_replace('_',' ',$key).'.');
    $value=trim((string)($data[$key] ?? ''));
    if (($required && $value==='') || mb_strlen($value)>$max) throw new ApiError("Please enter a valid ".str_replace('_',' ',$key)." (maximum {$max} characters).");
    return $value;
}
function enum_value(array $data,string $key,array $allowed): string { if (isset($data[$key]) && !is_scalar($data[$key])) throw new ApiError('Invalid '.str_replace('_',' ',$key).'.'); $value=(string)($data[$key] ?? ''); if (!in_array($value,$allowed,true)) throw new ApiError('Invalid '.str_replace('_',' ',$key).'.'); return $value; }
function positive_id(mixed $value): int { $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) throw new ApiError('Invalid record ID.'); return $id; }
function valid_date(string $value): string { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value); if (!$d || $d->format('Y-m-d')!==$value) throw new ApiError('Enter a valid date.'); return $value; }
function valid_time(string $value): string { if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::00)?$/',$value)) throw new ApiError('Enter a valid time.'); return substr($value,0,5).':00'; }
function settings(): array { return array_column(rows('SELECT setting_key,setting_value FROM system_settings'),'setting_value','setting_key'); }
function log_activity(string $type,int $id,string $action,string $details=''): void {
    audit_event($action,$details,$type,$id);
    query('INSERT INTO activity_logs (actor_id,entity_type,entity_id,action,details) VALUES (?,?,?,?,?)',[$_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null,$type,$id,$action,$details]);
}
function notify(string $title,string $message,string $type='reservations',?int $id=null): void {
    // Operational alerts belong to administrators. Player reminders are generated separately.
    query('INSERT INTO notifications (title,message,entity_type,entity_id) VALUES (?,?,?,?)',[$title,$message,$type,$id]);
}
function safe_image(?array $file,string $folder='courts'): ?string {
    if (!$file || $file['error']===UPLOAD_ERR_NO_FILE) return null;
    if ($file['error']!==UPLOAD_ERR_OK || $file['size']>3*1024*1024) throw new ApiError('Choose an image smaller than 3 MB.');
    $info=@getimagesize($file['tmp_name']);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
    if (!$ext || !$info || $info[0]>6000 || $info[1]>6000) throw new ApiError('Upload a valid JPEG, PNG, or WebP image up to 6000 pixels.');
    $relative=($folder==='proofs' ? 'storage/' : 'uploads/').$folder.'/'.bin2hex(random_bytes(20)).'.'.$ext;
    $target=dirname(__DIR__,2).'/'.$relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true);
    if (!move_uploaded_file($file['tmp_name'],$target)) throw new ApiError('The image could not be saved.',500);
    return $relative;
}
function api_handler(callable $callback): never {
    try { $user=require_admin(); if ($_SERVER['REQUEST_METHOD']!=='GET') require_csrf(); json_response($callback($user)); }
    catch (ApiError $e) { json_response(['error'=>$e->getMessage()],$e->status); }
    catch (Throwable $e) { try { if (db()->inTransaction()) db()->rollBack(); } catch(Throwable) {} error_log((string)$e); json_response(['error'=>'The request could not be completed. Check the server configuration or try again.'],500); }
}

require_once dirname(__DIR__).'/security-service.php';
require_once dirname(__DIR__).'/payment-hold-service.php';
require_once __DIR__.'/archive-service.php';

if(PHP_SAPI!=='cli'){
    $privateRoot=getenv('PIKOL_BACKUP_ROOT') ?: dirname(__DIR__,4).'/pikolcastle-private';
    if(!hosting_portable()&&is_file($privateRoot.'/'.database_config()['name'].'/maintenance.flag'))json_response(['error'=>'The booking system is temporarily in maintenance. Please try again shortly.'],503);
    try{expire_payment_holds();}
    catch(Throwable $error){error_log('Payment hold expiry error: '.get_class($error));json_response(['error'=>'Bookings are temporarily unavailable. Please try again shortly.'],503);}
}
