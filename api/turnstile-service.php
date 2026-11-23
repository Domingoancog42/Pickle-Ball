<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}

function turnstile_config(): array {
    static $config;
    return $config ??= require dirname(__DIR__).'/config/turnstile.php';
}

function require_turnstile(mixed $token): void {
    if(!is_string($token)||trim($token)===''||strlen($token)>2048)throw new ApiError('Complete the security check before logging in.',422);
    $config=turnstile_config();
    if($config['site_key']===''||$config['secret_key']===''||!function_exists('curl_init')){
        error_log('Turnstile configuration or PHP cURL is unavailable.');
        throw new ApiError('The security check is unavailable. Please try again shortly.',503);
    }
    $payload=['secret'=>$config['secret_key'],'response'=>$token];
    if(filter_var($_SERVER['REMOTE_ADDR'] ?? '',FILTER_VALIDATE_IP))$payload['remoteip']=$_SERVER['REMOTE_ADDR'];
    $request=curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($request,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($payload),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>10,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body=curl_exec($request);
    $status=curl_getinfo($request,CURLINFO_HTTP_CODE);
    curl_close($request);
    if($body===false||$status!==200){
        error_log('Turnstile Siteverify request failed.');
        throw new ApiError('The security check could not connect. Please try again shortly.',503);
    }
    $result=json_decode($body,true);
    if(!is_array($result)||!isset($result['success'])||!is_bool($result['success']))throw new ApiError('The security check is unavailable. Please try again shortly.',503);
    $errors=$result['error-codes'] ?? [];
    if(is_array($errors)&&array_intersect($errors,['missing-input-secret','invalid-input-secret','internal-error'])){
        error_log('Turnstile Siteverify configuration or service error.');
        throw new ApiError('The security check is unavailable. Please try again shortly.',503);
    }
    if($result['success']!==true||($result['action'] ?? '')!=='login')throw new ApiError('The security check failed or expired. Please complete it again.',422);
}
