<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
db()->exec("CREATE TABLE IF NOT EXISTS audit_ip_locations (
    ip_address VARCHAR(45) PRIMARY KEY,
    location_data TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX location_expiry(expires_at)
) ENGINE=InnoDB");
echo "Audit IP location cache is ready. Existing audit records preserved.\n";
