<?php
require __DIR__.'/bootstrap.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET')return ['settings'=>security_settings()];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$before=security_settings();$values=[];$errors=[];
    foreach(['maximum_password_length'=>[6,256],'session_timeout_minutes'=>[1,1440],'lockout_failed_attempts'=>[3,20],'lockout_duration_minutes'=>[1,1440]] as $key=>[$min,$max]){
        $value=filter_var($data[$key]??null,FILTER_VALIDATE_INT);
        if($value===false||$value<$min||$value>$max)$errors[$key]="Enter a whole number from $min to $max.";
        else $values[$key]=$value;
    }
    if(isset($values['maximum_password_length'])&&$values['maximum_password_length']<$before['minimum_password_length'])$errors['maximum_password_length']='Maximum must be at least '.$before['minimum_password_length'].' characters.';
    if($errors)json_response(['error'=>'Check the highlighted security settings.','fields'=>$errors],422);
    // Preserve the existing expiry policy; this form no longer edits it.
    db()->beginTransaction();
    foreach($values as $key=>$value)query('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['security_policy_'.$key,(string)$value]);
    audit_event('Security settings updated','Password, session and lockout policies updated.','security',null,'success',['before'=>array_intersect_key($before,$values),'after'=>$values]);
    db()->commit();
    return ['ok'=>true,'settings'=>array_replace($before,$values)];
});
