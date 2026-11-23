<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
if(!scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_security_tracking' AND INDEX_NAME='lock_account'"))query('ALTER TABLE login_security_tracking ADD INDEX lock_account(user_id,locked_until)');
echo "Per-account lockout index ready. Existing lock records preserved.\n";
