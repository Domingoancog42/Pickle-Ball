<?php
require __DIR__.'/bootstrap.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $items=rows("SELECT u.user_id,u.full_name,u.email,u.role,SUM(t.failed_attempts) AS failed_attempts,MAX(t.locked_until) AS locked_until FROM login_security_tracking t JOIN users u ON u.user_id=t.user_id WHERE t.locked_until>NOW() GROUP BY u.user_id,u.full_name,u.email,u.role ORDER BY locked_until DESC,u.user_id");
        return ['items'=>$items,'total'=>count($items)];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();if(($data['action']??'')!=='unlock')throw new ApiError('Invalid action.');$id=positive_id($data['user_id']??null);
    db()->beginTransaction();$user=one('SELECT user_id,full_name,email FROM users WHERE user_id=? FOR UPDATE',[$id]);if(!$user)throw new ApiError('Account not found.',404);
    query('DELETE FROM login_security_tracking WHERE user_id=?',[$id]);
    query('DELETE FROM login_attempts WHERE identity_hash=?',[hash('sha256','email:'.strtolower($user['email']))]);
    audit_event('Account unlocked','Administrator cleared failed sign-in attempts for this account.','user',$id);
    db()->commit();return ['ok'=>true];
});
