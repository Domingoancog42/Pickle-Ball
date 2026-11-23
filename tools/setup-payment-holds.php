<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS booking_checkout_holds (
    hold_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    court_id INT NOT NULL,
    request_key CHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    proof_submitted_at DATETIME NULL,
    expired_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY checkout_request (user_id,request_key),
    INDEX checkout_expiry (proof_submitted_at,expired_at,expires_at),
    FOREIGN KEY(user_id) REFERENCES users(user_id),
    FOREIGN KEY(court_id) REFERENCES courts(court_id)
) ENGINE=InnoDB");
$column=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='checkout_hold_id'");
if(!(int)$column->fetchColumn())$pdo->exec('ALTER TABLE bookings ADD checkout_hold_id BIGINT UNSIGNED NULL, ADD INDEX checkout_hold_bookings(checkout_hold_id), ADD CONSTRAINT fk_booking_checkout_hold FOREIGN KEY(checkout_hold_id) REFERENCES booking_checkout_holds(hold_id)');
echo "Ten-minute payment hold schema is ready. Existing reservations preserved.\n";
