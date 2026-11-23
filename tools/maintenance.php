<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$flag=backup_directory().'/maintenance.flag';
if(($argv[1] ?? '')==='on'){file_put_contents($flag,date(DATE_ATOM),LOCK_EX);backup_protected_event('Maintenance enabled','success','operator');}
elseif(($argv[1] ?? '')==='off'){if(is_file($flag))unlink($flag);backup_protected_event('Maintenance disabled','success','operator');}
else throw new RuntimeException('Use on or off.');
echo "Maintenance ".$argv[1].".\n";
