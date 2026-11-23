<?php
require_once __DIR__.'/admin/bootstrap.php';
function pending_password_user(): ?array {
    $id=$_SESSION['password_setup_user_id'] ?? null;
    if(!$id||time()>(int)($_SESSION['password_setup_until'] ?? 0))return null;
    $user=one("SELECT user_id,email,full_name,password_hash FROM users WHERE user_id=? AND must_change_password=1 AND account_status='active' AND temporary_password_expires>NOW()",[$id]);
    if(!$user||!hash_equals($_SESSION['password_setup_version'] ?? '',hash('sha256',$user['password_hash'])))return null;
    unset($user['password_hash']);return $user;
}
