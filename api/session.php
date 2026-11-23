<?php
require __DIR__.'/admin/bootstrap.php';
try {
    $method=$_SERVER['REQUEST_METHOD'];
    if(!in_array($method,['GET','POST'],true))throw new ApiError('Method not allowed.',405);
    if($method==='POST')require_csrf();
    // Reading status must not renew an inactive session.
    $_SERVER['HTTP_X_PIKOL_BACKGROUND']='1';
    $user=session_user();$data=$method==='POST'?input():[];
    if($method==='POST'&&($data['action']??'')==='logout'){
        if($user)audit_event('Logout','Signed out from the inactivity warning.','user',(int)$user['user_id']);
        $_SESSION=[];session_destroy();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);json_response(['authenticated'=>false]);
    }
    if($method==='POST'){
        if(!$user)throw new ApiError('Please sign in again.',401);
        $action=enum_value($data,'action',['activity','stay']);
        $limit=(int)security_settings()['session_timeout_minutes']*60;
        if($user['role']==='customer'&&$action==='activity'&&time()-($_SESSION['last_activity']??0)>=$limit)throw new ApiError('Choose Stay signed in to continue.',409);
        $_SESSION['last_activity']=time();
    }
    $warning=$user&&$user['role']==='customer'?($_SESSION['last_activity']??time())+(int)security_settings()['session_timeout_minutes']*60:null;
    json_response(['authenticated'=>$user!==null,'role'=>$user['role']??null,'csrf'=>csrf_token(),'server_time'=>time(),'warning_at'=>$warning,'expires_at'=>$warning===null?null:$warning+30]);
}catch(ApiError $error){json_response(['error'=>$error->getMessage()],$error->status);}
catch(Throwable $error){error_log((string)$error);json_response(['error'=>'Could not check your session.'],500);}
