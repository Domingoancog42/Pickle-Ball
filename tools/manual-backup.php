<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$row=backup_create('Manual');echo 'Completed backup ID '.$row['id'].' ('.$row['backup_reference'].")\n";
