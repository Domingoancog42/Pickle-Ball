<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/api/connection-pdo.php';
$config=database_config();
$pdo=db();
$required=['users','venues','courts','bookings','booking_statuses','payments','payment_methods','court_schedules','court_maintenance','system_settings','notifications','activity_logs','login_attempts','booking_change_requests','booking_checkout_holds','password_reset_codes','audit_logs','login_security_tracking'];
$required[]='record_archives';
$required[]='audit_ip_locations';
$required[]='backup_history';
$required[]='backup_settings';
$required[]='live_versions';
$required[]='customer_ratings';
foreach(['account_name','account_number','qr_image','instructions','customer_enabled','customer_managed','removed_at'] as $column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_methods' AND COLUMN_NAME=?");$q->execute([$column]);if(!$q->fetchColumn())throw new RuntimeException('Run tools/setup-payment-methods.php to add configurable payment methods.');}
$customerStatusColumn=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status'")->fetchColumn();
if(!$customerStatusColumn || !str_contains($customerStatusColumn,"'inactive'"))throw new RuntimeException('Run tools/setup-customer-status.php to add the Inactive account status.');
foreach($required as $table) {
    $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
    if($q->fetchColumn()!=='InnoDB') throw new RuntimeException("$table is missing or does not use InnoDB. Run the database setup.");
}
foreach(['must_change_password','temporary_password_expires','session_version','password_changed_at'] as $column){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME=?");$q->execute([$column]);if(!$q->fetchColumn())throw new RuntimeException('Run tools/setup-database.php to add signup fields.');}
$holdColumn=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='checkout_hold_id'");
if(!$holdColumn->fetchColumn())throw new RuntimeException('Run tools/setup-payment-holds.php to add payment hold fields.');
foreach(['pdo_mysql','fileinfo','mbstring','openssl','gd'] as $extension) if(!extension_loaded($extension)) throw new RuntimeException("Enable the $extension PHP extension.");
require dirname(__DIR__).'/vendor/autoload.php';
if(!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('Run composer install.');
if(!class_exists(\Zxing\QrReader::class)) throw new RuntimeException('Run composer install to enable payment QR validation.');
foreach(['chart.umd.js','gsap.min.js','ScrollTrigger.min.js','sweetalert2.all.min.js','lucide.min.js','fullcalendar.min.js','luxon.min.js','fullcalendar-luxon3.min.js','inter-latin-variable.woff2'] as $file) if(!is_file(dirname(__DIR__).'/assets/vendor/'.$file)) throw new RuntimeException('Missing frontend dependency: '.$file);
$admins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active'")->fetchColumn();
echo "PASS: MySQL schema, transaction engines, PHP extensions, PHPMailer, and local frontend assets.\n";
echo $admins>0 ? "An active administrator account is available.\n" : "Next: run php tools/create-admin.php to create your administrator account.\n";
