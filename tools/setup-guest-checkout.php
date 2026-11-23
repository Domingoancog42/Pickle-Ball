<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
$columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='booking_checkout_holds'")->fetchAll(PDO::FETCH_COLUMN);
foreach(['guest_name'=>'VARCHAR(100) NULL','guest_email'=>'VARCHAR(150) NULL','guest_phone'=>'VARCHAR(20) NULL'] as $name=>$definition){
    if(!in_array($name,$columns,true))$pdo->exec("ALTER TABLE booking_checkout_holds ADD COLUMN `$name` $definition");
}
echo "Guest checkout contact fields are ready. Existing accounts and reservations preserved.\n";
