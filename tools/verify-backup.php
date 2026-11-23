<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$id=positive_id($argv[1] ?? null);$row=one('SELECT * FROM backup_history WHERE id=?',[$id]);if(!$row)throw new RuntimeException('Backup not found.');
$file=backup_verified($row);$cfg=database_config();
$staging='pikol_restore_test_'.bin2hex(random_bytes(8));$credential=null;$errorFile=null;
backup_protected_event('Restore validation requested','pending',$row['backup_reference']);
try{
    $admin=pdo_connect(false);$admin->exec('CREATE DATABASE `'.$staging.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $binary=backup_mysql_binary('mysql');
    $credential=tempnam(backup_directory(),'verify-options-');@chmod($credential,0600);
    $escape=static fn($s)=>str_replace(["\\","\"","\n","\r"],["\\\\","\\\"","\\n","\\r"],(string)$s);
    file_put_contents($credential,"[client]\nhost=\"".$escape($cfg['host'])."\"\nport=".(int)$cfg['port']."\nuser=\"".$escape($cfg['user'])."\"\npassword=\"".$escape($cfg['password'])."\"\n");
    $errorFile=backup_directory().'/verify-'.bin2hex(random_bytes(10)).'.log';
    $process=proc_open([$binary,'--defaults-extra-file='.$credential,'--binary-mode','--database='.$staging],[0=>['file',$file,'r'],1=>['file',$errorFile,'w'],2=>['file',$errorFile,'a']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Disposable restore import failed.');
    foreach(['users','courts','bookings','payments','audit_logs','system_settings','login_security_tracking','backup_settings','backup_history'] as $table){$admin->query('SELECT COUNT(*) FROM `'.$staging.'`.`'.$table.'`')->fetchColumn();}
    backup_protected_event('Restore validation completed','success',$row['backup_reference']);audit_event('Restore validation completed','Verified SQL imported into a disposable database; required tables readable.','backup',$id);
    echo "PASS: backup checksum, SQL import and required tables verified in disposable database. Live database unchanged.\n";
}catch(Throwable $e){backup_protected_event('Restore validation failed','failure',$row['backup_reference']);audit_event('Database restore failed','Disposable restore validation failed.','backup',$id,'failure');throw new RuntimeException('Restore validation failed. Live database unchanged.');}
finally{if($credential&&is_file($credential))unlink($credential);if($errorFile&&is_file($errorFile))unlink($errorFile);if(isset($admin)&&preg_match('/^pikol_restore_test_[a-f0-9]{16}$/D',$staging)&&$staging!==$cfg['name'])$admin->exec('DROP DATABASE IF EXISTS `'.$staging.'`');}
