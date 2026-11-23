<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
date_default_timezone_set('Asia/Manila');
require_once dirname(__DIR__).'/api/connection-pdo.php';
$config=database_config();
if (!preg_match('/^[a-zA-Z0-9_]+$/',$config['name'])) throw new RuntimeException('Invalid database name.');
$pdo=pdo_connect(false);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$config['name']}`");
$pdo->exec("SET time_zone = '+08:00'");
if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/pickleball_booking.sql'));
    echo "Imported the existing normalized schema.\n";
}
function column(PDO $pdo,string $table,string $name,string $definition): void {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'); $q->execute([$table,$name]);
    if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
}
foreach ([
    ['users','role',"ENUM('customer','admin') NOT NULL DEFAULT 'customer'"],
    ['users','account_status',"ENUM('active','inactive','disabled') NOT NULL DEFAULT 'active'"],
    ['users','avatar_path','VARCHAR(255) NULL'],
    ['courts','description',"TEXT NULL"],['courts','image_path','VARCHAR(255) NULL'],
    ['courts','hourly_rate','DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (hourly_rate >= 0)'],
    ['courts','opens_at',"TIME NOT NULL DEFAULT '06:00:00'"],['courts','closes_at',"TIME NOT NULL DEFAULT '22:00:00'"],
    ['courts','court_status',"ENUM('active','disabled','maintenance') NOT NULL DEFAULT 'active'"],
    ['courts','deleted_at','DATETIME NULL'],
    ['courts','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ['bookings','reference_no','VARCHAR(40) NULL UNIQUE'],['bookings','admin_notes','TEXT NULL'],['bookings','cancellation_reason','TEXT NULL'],
    ['payments','status',"ENUM('Unpaid','Pending Verification','Paid','Refunded') NOT NULL DEFAULT 'Paid'"],
    ['payments','proof_path','VARCHAR(255) NULL'],['payments','verified_by','INT NULL'],['payments','verified_at','DATETIME NULL'],
] as [$table,$name,$definition]) column($pdo,$table,$name,$definition);
// A cancelled booking must not permanently block its old start time. Court-row
// locks plus overlap validation replace the legacy unique-start constraint.
$indexes=$pdo->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND NON_UNIQUE=0 AND INDEX_NAME!='PRIMARY' GROUP BY INDEX_NAME")->fetchAll();
foreach ($indexes as $index) if ($index['cols']==='court_id,booking_date,start_time') $pdo->exec('ALTER TABLE bookings DROP INDEX `'.str_replace('`','``',$index['INDEX_NAME']).'`');
$pdo->exec("UPDATE bookings SET reference_no=CONCAT('PC-LEGACY-',booking_id) WHERE reference_no IS NULL");
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/admin-migration.sql'));
$defaults=[
    'venue_name'=>'Pikol Castle','business_description'=>'Serving memories 💕 Rallying through life.','contact_phone'=>'0965 536 0903','contact_email'=>'laurenxtinoy@gmail.com','venue_address'=>'KIBAGHOT, LAGUINDINGAN, MISAMIS ORIENTAL, 9019',
    'opens_at'=>'06:00','closes_at'=>'22:00','booking_policies'=>'Pay at the venue. Cancel at least 6 hours before your reservation.',
    'default_duration'=>'60','advance_days'=>'60','cancellation_hours'=>'6','maximum_duration'=>'240','slot_minutes'=>'30',
];
$insert=$pdo->prepare('INSERT IGNORE INTO system_settings (setting_key,setting_value) VALUES (?,?)');
foreach ($defaults as $key=>$value) $insert->execute([$key,$value]);
$methods=$pdo->prepare('INSERT IGNORE INTO payment_methods (name) VALUES (?)');
foreach (['Cash','GCash','Maya','Online Payment Gateway'] as $method) $methods->execute([$method]);
// Initialize only an empty catalog, using the existing site's actual court cards.
if ((int)$pdo->query('SELECT COUNT(*) FROM courts')->fetchColumn()===0) {
    $venue=$pdo->query('SELECT venue_id FROM venues ORDER BY venue_id LIMIT 1')->fetchColumn();
    if (!$venue) { $pdo->exec("INSERT INTO venues (name,address,city,opens_at,closes_at) VALUES ('Pikol Castle','KIBAGHOT, LAGUINDINGAN, MISAMIS ORIENTAL, 9019','LAGUINDINGAN, MISAMIS ORIENTAL','06:00','22:00')"); $venue=$pdo->lastInsertId(); }
    $doc=new DOMDocument(); @$doc->loadHTML(file_get_contents(dirname(__DIR__).'/html/courts.html'));
    $xp=new DOMXPath($doc);
    foreach ($xp->query('//*[@data-court-id]') as $card) {
        $read=fn($attr)=>trim($xp->query('.//*[@'.$attr.']',$card)->item(0)->textContent);
        $type=$read('data-court-type'); $q=$pdo->prepare('SELECT surface_type_id FROM surface_types WHERE name=?'); $q->execute([$type]); $surface=$q->fetchColumn();
        if (!$surface) { $q=$pdo->prepare('INSERT INTO surface_types (name) VALUES (?)'); $q->execute([$type]); $surface=$pdo->lastInsertId(); }
        $image=$xp->query('.//img',$card)->item(0)->getAttribute('src');
        $description=trim($xp->query('.//*[@data-slot="card-content"]/p[2]',$card)->item(0)?->textContent ?? '');
        $q=$pdo->prepare('INSERT INTO courts (venue_id,surface_type_id,court_name,hourly_rate,image_path,description) VALUES (?,?,?,?,?,?)');
        $q->execute([$venue,$surface,$read('data-court-name'),(float)preg_replace('/[^0-9.]/','',$read('data-court-rate')),$image,$description]);
    }
    echo "Initialized courts from the existing website. No demo bookings or customers added.\n";
}
echo "Database {$config['name']} is ready. Existing records preserved.\n";
require __DIR__.'/setup-customer.php';

require __DIR__.'/setup-signup.php';

require __DIR__.'/setup-password-reset.php';

require __DIR__.'/setup-settings.php';
require __DIR__.'/setup-payment-holds.php';
require __DIR__.'/setup-guest-checkout.php';
require __DIR__.'/setup-booking-addons.php';
require __DIR__.'/setup-archives.php';
require __DIR__.'/setup-audit-locations.php';
require __DIR__.'/setup-customer-status.php';
require __DIR__.'/setup-backups.php';
require __DIR__.'/setup-payment-methods.php';
require __DIR__.'/setup-ratings.php';
require __DIR__.'/setup-live-updates.php';
