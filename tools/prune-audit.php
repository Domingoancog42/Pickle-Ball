<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$days=(int)security_settings()['audit_retention_days'];
backup_protected_event('Audit retention pruning','requested','older-than-'.$days.'-days');
$count=query('DELETE FROM audit_logs WHERE created_at<DATE_SUB(NOW(),INTERVAL ? DAY)',[$days])->rowCount();
audit_event('Audit retention applied','Removed '.$count.' records older than '.$days.' days.','audit');
echo "Audit retention applied: $count records.\n";
