<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS record_archives (
    module VARCHAR(24) NOT NULL,
    record_id INT NOT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_by INT NULL,
    prior_state TEXT NULL,
    PRIMARY KEY(module,record_id),
    INDEX archive_date(archived_at),
    FOREIGN KEY(archived_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB");
// Previously retired courts remain hidden and can now be found in Archived.
$pdo->exec("INSERT IGNORE INTO record_archives (module,record_id,archived_at,prior_state)
    SELECT 'courts',court_id,deleted_at,JSON_OBJECT('court_status',court_status,'is_active',is_active)
    FROM courts WHERE deleted_at IS NOT NULL");
echo "Record archives are ready. Existing records preserved.\n";
