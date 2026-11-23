<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';

// Keep linked reservations and payments while removing these courts from the catalog.
$pdo=db();
$column=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courts' AND COLUMN_NAME='deleted_at'");
if(!(int)$column->fetchColumn())$pdo->exec('ALTER TABLE courts ADD COLUMN deleted_at DATETIME NULL');
$names=['Sunset Bastion — Outdoor Court','The Keep — Indoor Pro Court'];
$pdo->beginTransaction();
try{
    $find=$pdo->prepare('SELECT court_id,court_name FROM courts WHERE court_name IN (?,?) FOR UPDATE');
    $find->execute($names);
    $courts=$find->fetchAll();
    $remove=$pdo->prepare("UPDATE courts SET is_active=0,court_status='disabled',deleted_at=COALESCE(deleted_at,NOW()) WHERE court_id=?");
    foreach($courts as $court)$remove->execute([$court['court_id']]);
    $pdo->commit();
    foreach($courts as $court)echo 'Removed from catalog: ',$court['court_name'],PHP_EOL;
    echo 'Current courts: ',$pdo->query('SELECT COUNT(*) FROM courts WHERE deleted_at IS NULL')->fetchColumn(),PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $error;
}
