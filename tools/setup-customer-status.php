<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
$column=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status'")->fetchColumn();
if(!$column)throw new RuntimeException('Run tools/setup-database.php first.');
if(!str_contains($column,"'inactive'"))$pdo->exec("ALTER TABLE users MODIFY account_status ENUM('active','inactive','disabled') NOT NULL DEFAULT 'active'");
$pdo->exec("UPDATE users SET account_status='inactive',session_version=session_version+1 WHERE role='customer' AND account_status='disabled'");
echo "Customer Active / Inactive statuses are ready. Existing accounts preserved.\n";
