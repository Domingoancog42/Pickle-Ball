<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
if(!scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='session_version'"))db()->exec('ALTER TABLE users ADD session_version INT UNSIGNED NOT NULL DEFAULT 0');
db()->exec("CREATE TABLE IF NOT EXISTS password_reset_codes (reset_id CHAR(48) PRIMARY KEY,user_id INT NULL,code_hash VARCHAR(255) NOT NULL,expires_at DATETIME NOT NULL,attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,ticket_hash CHAR(64) NULL,verified_at DATETIME NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_reset_user(user_id,created_at),FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE) ENGINE=InnoDB");
echo "Password reset schema ready. Existing accounts preserved.\n";
