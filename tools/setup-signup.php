<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
$columns=array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'"),'COLUMN_NAME');
foreach(['must_change_password'=>'TINYINT(1) NOT NULL DEFAULT 0','temporary_password_expires'=>'DATETIME NULL'] as $name=>$type)if(!in_array($name,$columns,true))db()->exec("ALTER TABLE users ADD $name $type");
echo "Temporary-password signup fields are ready. Existing passwords preserved.\n";
