<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
db()->exec("CREATE TABLE IF NOT EXISTS backup_history (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 backup_reference VARCHAR(60) NOT NULL UNIQUE,
 backup_type ENUM('Manual','Automatic') NOT NULL DEFAULT 'Manual',
 file_name VARCHAR(100) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 checksum CHAR(64) NULL,
 status ENUM('Pending','Completed','Failed') NOT NULL DEFAULT 'Pending',
 error_summary TEXT NULL,
 created_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL,
 deleted_at DATETIME NULL,
 FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Backup history ready. Existing records preserved.\n";
db()->exec("CREATE TABLE IF NOT EXISTS backup_settings (
 id INT NOT NULL PRIMARY KEY,
 automatic_backup_enabled TINYINT NOT NULL DEFAULT 0,
 backup_frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
 scheduled_at DATETIME NULL,
 next_run_at DATETIME NULL,
 scheduler_last_seen DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
foreach(['scheduled_at','next_run_at','scheduler_last_seen'] as $name){
    $q=db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='backup_settings' AND COLUMN_NAME=?");$q->execute([$name]);
    if(!$q->fetchColumn())db()->exec("ALTER TABLE backup_settings ADD `$name` DATETIME NULL");
}
db()->exec('INSERT IGNORE INTO backup_settings(id,scheduled_at,next_run_at) VALUES(1,NOW()+INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY)');
db()->exec('UPDATE backup_settings SET scheduled_at=COALESCE(scheduled_at,NOW()+INTERVAL 1 DAY),next_run_at=COALESCE(next_run_at,NOW()+INTERVAL 1 DAY) WHERE id=1');
