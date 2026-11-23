<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
$ddl=[
"CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,actor_name VARCHAR(100) NOT NULL,actor_email VARCHAR(150) NOT NULL DEFAULT '',actor_role VARCHAR(20) NOT NULL,action VARCHAR(150) NOT NULL,description TEXT NULL,entity_type VARCHAR(50) NULL,entity_id BIGINT NULL,ip_address VARCHAR(45) NULL,user_agent VARCHAR(512) NULL,browser VARCHAR(40) NULL,operating_system VARCHAR(40) NULL,device_type VARCHAR(20) NULL,result_status VARCHAR(10) NOT NULL DEFAULT 'success',change_data JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX audit_time(created_at,id),INDEX audit_actor(user_id,created_at),INDEX audit_filter(actor_role,result_status,created_at),INDEX audit_entity(entity_type,entity_id)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS login_security_tracking (identity_hash CHAR(64) PRIMARY KEY,user_id INT NULL,failed_attempts INT NOT NULL DEFAULT 0,locked_until DATETIME NULL,last_failed_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX lock_expiry(locked_until)) ENGINE=InnoDB",
];
foreach($ddl as $sql)db()->exec($sql);
require __DIR__.'/setup-account-lockout.php';
if(!scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_changed_at'")){
    db()->exec('ALTER TABLE users ADD password_changed_at DATETIME NULL');
    db()->exec('UPDATE users SET password_changed_at=NOW() WHERE password_changed_at IS NULL');
}
db()->exec('UPDATE users SET password_changed_at=NOW() WHERE password_changed_at IS NULL');
db()->exec('ALTER TABLE users MODIFY password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
echo "Application audit and lockout schema ready. Existing data preserved.\n";
