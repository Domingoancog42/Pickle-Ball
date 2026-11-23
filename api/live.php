<?php
require __DIR__.'/live-service.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $user=session_user();$role=$user['role']??'public';
    $since=$_GET['since']??'';
    if(!is_string($since)||($since!==''&&!preg_match('/^[a-f0-9]{64}$/D',$since)))throw new ApiError('Invalid update cursor.');
    $wait=filter_var($_GET['wait']??18,FILTER_VALIDATE_INT);
    if($wait===false||$wait<0||$wait>20)throw new ApiError('Invalid wait duration.');
    if(hosting_portable())$wait=0;
    // Release PHP's session lock before waiting so other requests can proceed.
    session_write_close();
    $deadline=microtime(true)+$wait;
    do{
        $versions=live_snapshot($role);
        $version=hash('sha256',$role.':'.($user['user_id']??0).':'.json_encode($versions,JSON_THROW_ON_ERROR));
        if($version!==$since||microtime(true)>=$deadline||connection_aborted())break;
        usleep(750000);
    }while(true);
    json_response(['version'=>$version,'versions'=>$versions,'audience'=>$role,'retry_after_ms'=>hosting_portable()?10000:250]);
}catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}
catch(Throwable $e){error_log('Live updates: '.$e->getMessage());json_response(['error'=>'Live updates are temporarily unavailable.'],503);}
