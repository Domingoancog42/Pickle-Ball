<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$reference=$argv[1] ?? '';$result=$argv[2] ?? '';
if(!preg_match('/^BKP-[a-zA-Z0-9-]+$/D',$reference)||!in_array($result,['success','failure'],true))throw new RuntimeException('Use backup-reference success|failure.');
if($result==='failure')backup_protected_event('Database restore failure','failure',$reference);
if($result==='success'){
    require __DIR__.'/check-install.php';
    query('UPDATE users SET session_version=session_version+1');
    backup_protected_event('Database restore completed','success',$reference);
    audit_event('Database restore completed','Operator recorded restoration from '.$reference.'. Installation checks passed; sessions invalidated.','backup');
}else{try{audit_event('Database restore failed','Operator reported restoration failure for '.$reference.'. Keep maintenance enabled.','backup',null,'failure');}catch(Throwable){}}
echo "Restore outcome recorded in private recovery journal.\n";
