<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
// Execute separately to retain the application's stacked-statement restriction.
foreach(['booking-addons-migration.sql','booking-refreshments-migration.sql'] as $migration){
    foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/'.$migration)) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
}
$column=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='addons_total'");
if(!(int)$column->fetchColumn())$pdo->exec('ALTER TABLE bookings ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0');
echo "Booking add-ons are ready. Example prices added only for missing products; existing prices and bookings preserved.\n";
