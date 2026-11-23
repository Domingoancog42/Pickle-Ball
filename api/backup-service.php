<?php
require_once __DIR__.'/admin/bootstrap.php';
function backup_mysql_binary(string $utility): string {
    if(!in_array($utility,['mysql','mysqldump'],true))throw new InvalidArgumentException('Unsupported database utility.');
    $configured=getenv('PIKOL_MYSQL_BIN');
    if($configured)$directories=[$configured];
    elseif(PHP_OS_FAMILY==='Windows'){
        // Apache's PHP_BINARY points to httpd.exe, not the PHP installation.
        $ini=php_ini_loaded_file();
        $directories=array_filter([$ini?dirname($ini,2).'/mysql/bin':null,dirname(PHP_BINARY,2).'/mysql/bin',dirname(PHP_BINDIR).'/mysql/bin']);
    }else $directories=['/usr/bin','/usr/local/bin'];
    foreach($directories as $directory){
        $path=realpath($directory.'/'.$utility.(PHP_OS_FAMILY==='Windows'?'.exe':''));
        if($path!==false&&is_file($path))return $path;
    }
    throw new RuntimeException('Database utility unavailable. Check the MySQL installation or PIKOL_MYSQL_BIN.');
}
function backup_directory(): string {
    $name=database_config()['name'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name))throw new RuntimeException('Invalid database scope.');
    // Fixed sibling of htdocs, or an operator-defined location outside DocumentRoot.
    $base=getenv('PIKOL_BACKUP_ROOT') ?: dirname(__DIR__,3).'/pikolcastle-private';
    if(!is_dir($base)&&!mkdir($base,0700,true))throw new RuntimeException('Private backup storage unavailable.');
    $base=realpath($base);$web=realpath(dirname(__DIR__,2));
    $normal=static fn($p)=>strtolower(str_replace('\\','/',$p));
    if($normal($base)===$normal($web)||str_starts_with($normal($base).'/', $normal($web).'/'))throw new RuntimeException('Backup storage must be outside the web root.');
    $dir=$base.'/'.$name;if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Private backup storage unavailable.');
    if(PHP_OS_FAMILY==='Windows'&&!is_file($dir.'/.storage-permissions')){
        // chmod does not set Windows ACLs. Grant only the PHP identity, SYSTEM and Administrators.
        $system=getenv('SystemRoot') ?: 'C:/Windows';
        $who=proc_open([$system.'/System32/whoami.exe','/user','/fo','csv','/nh'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($who))throw new RuntimeException('Storage identity unavailable.');
        fclose($pipes[0]);$identity=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($who);
        if($exit!==0||!preg_match('/S-1-[0-9-]+/',$identity,$match))throw new RuntimeException('Storage identity unavailable.');
        $acl=proc_open([$system.'/System32/icacls.exe',$dir,'/inheritance:r','/grant:r','*'.$match[0].':(OI)(CI)F','*S-1-5-18:(OI)(CI)F','*S-1-5-32-544:(OI)(CI)F'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($acl))throw new RuntimeException('Private storage permissions unavailable.');
        fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
        if(proc_close($acl)!==0)throw new RuntimeException('Private storage permissions could not be restricted.');
        if(file_put_contents($dir.'/.storage-permissions','restricted')===false)throw new RuntimeException('Private storage permissions unavailable.');
    }
    return $dir;
}
function backup_protected_event(string $action,string $result,string $reference): void {
    $line=json_encode(['time'=>date(DATE_ATOM),'action'=>$action,'result'=>$result,'reference'=>$reference,'actor_id'=>$_SESSION['user_id'] ?? null],JSON_THROW_ON_ERROR)."\n";
    if(file_put_contents(backup_directory().'/recovery-audit.jsonl',$line,FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Protected recovery journal unavailable.');
    @chmod(backup_directory().'/recovery-audit.jsonl',0600);
}
function backup_verified(array $row): string {
    if($row['status']!=='Completed'||$row['deleted_at']||!preg_match('/^backup-[a-f0-9]{40}\.sql$/D',$row['file_name']))throw new ApiError('This backup is not available.',409);
    $path=backup_directory().'/'.$row['file_name'];
    if(!is_file($path)||(int)filesize($path)!==(int)$row['file_size']||!hash_equals($row['checksum'] ?? '',hash_file('sha256',$path)))throw new ApiError('Backup integrity verification failed. Restore and download are blocked.',409);
    return $path;
}
function backup_lock() {
    $handle=fopen(backup_directory().'/operation.lock','c');
    if(!$handle||!flock($handle,LOCK_EX|LOCK_NB))throw new ApiError('Another backup operation is running. Please try again later.',409);
    return $handle;
}
function backup_create(string $type='Manual',?int $actor=null): array {
    try{$lock=backup_lock();}
    catch(Throwable $e){
        if($e instanceof ApiError&&$e->status===409)throw $e;
        $reference='BKP-'.date('YmdHis').'-'.bin2hex(random_bytes(4));
        query("INSERT INTO backup_history(backup_reference,backup_type,file_name,created_by,status,error_summary,completed_at) VALUES(?,?,?,?,'Failed','Private backup storage is unavailable. Ask the server operator to check permissions.',NOW())",[$reference,$type,'backup-'.bin2hex(random_bytes(20)).'.sql',$actor]);
        audit_event('Backup failed','Private storage could not be prepared.','backup',(int)db()->lastInsertId(),'failure');
        throw new ApiError('Private backup storage is unavailable. Check backup history and ask the server operator to verify permissions.',503);
    }
    $id=null;$file=null;$credential=null;
    try{
        $reference='BKP-'.date('YmdHis').'-'.bin2hex(random_bytes(4));$name='backup-'.bin2hex(random_bytes(20)).'.sql';
        query('INSERT INTO backup_history(backup_reference,backup_type,file_name,created_by) VALUES(?,?,?,?)',[$reference,$type,$name,$actor]);$id=(int)db()->lastInsertId();
        audit_event($type.' backup requested','Database scope: '.database_config()['name'],'backup',$id);backup_protected_event('Backup requested','pending',$reference);
        $cfg=database_config();$dir=backup_directory();$file=$dir.'/'.$name;
        $binary=backup_mysql_binary('mysqldump');
        if(!function_exists('proc_open'))throw new RuntimeException('Dump utility unavailable.');
        $credential=tempnam($dir,'db-options-');@chmod($credential,0600);
        $escape=static fn($s)=>str_replace(["\\","\"","\n","\r"],["\\\\","\\\"","\\n","\\r"],(string)$s);
        $ini="[client]\nhost=\"".$escape($cfg['host'])."\"\nport=".(int)$cfg['port']."\nuser=\"".$escape($cfg['user'])."\"\npassword=\"".$escape($cfg['password'])."\"\n";
        if(file_put_contents($credential,$ini)===false)throw new RuntimeException('Could not prepare backup.');
        $stderr=$dir.'/process-'.bin2hex(random_bytes(10)).'.log';
        $process=proc_open([$binary,'--defaults-extra-file='.$credential,'--single-transaction','--quick','--routines','--events','--triggers','--hex-blob','--default-character-set=utf8mb4',$cfg['name']],[0=>['pipe','r'],1=>['file',$file,'w'],2=>['file',$stderr,'w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Could not start backup.');
        fclose($pipes[0]);$exit=proc_close($process);@unlink($stderr);@unlink($credential);$credential=null;@chmod($file,0600);
        if($exit!==0||!is_file($file)||filesize($file)<100)throw new RuntimeException('Database dump did not complete.');
        $handle=fopen($file,'rb');$head=fread($handle,4096);fseek($handle,max(0,filesize($file)-4096));$tail=stream_get_contents($handle);fclose($handle);
        if(!preg_match('/(?:MySQL|MariaDB) dump/i',$head)||!str_contains($tail,'Dump completed'))throw new RuntimeException('Dump verification failed.');
        $checksum=hash_file('sha256',$file);
        query("UPDATE backup_history SET status='Completed',file_size=?,checksum=?,completed_at=NOW() WHERE id=?",[filesize($file),$checksum,$id]);
        audit_event($type.' backup completed','Verified SQL structure and data dump.','backup',$id);backup_protected_event('Backup completed','success',$reference);
        return one('SELECT * FROM backup_history WHERE id=?',[$id]);
    }catch(Throwable $e){
        if($credential&&is_file($credential))@unlink($credential);if($file&&is_file($file))@unlink($file);
        if($id){query("UPDATE backup_history SET status='Failed',error_summary='Backup could not be completed. Ask the server operator to check storage and dump utility access.',completed_at=NOW() WHERE id=?",[$id]);audit_event('Backup failed','Backup process or output verification failed.','backup',$id,'failure');backup_protected_event('Backup failed','failure',$reference);}
        error_log('Backup operation failed: '.get_class($e));throw new ApiError('Backup failed. Check the history and ask the server operator to verify the dump utility and private storage.',503);
    }finally{if(isset($stderr)&&is_file($stderr))@unlink($stderr);flock($lock,LOCK_UN);fclose($lock);}
}
function backup_delete(array $row): void {
    $lock=backup_lock();
    try{
        $path=backup_verified($row);$others=rows("SELECT * FROM backup_history WHERE status='Completed' AND deleted_at IS NULL AND id<>?",[$row['id']]);$valid=false;
        foreach($others as $candidate){try{backup_verified($candidate);$valid=true;break;}catch(ApiError){}}
        if(!$valid)throw new ApiError('Keep at least one other verified backup before deleting this file.',409);
        backup_protected_event('Backup deleted','requested',$row['backup_reference']);
        if(!unlink($path))throw new ApiError('The backup could not be deleted.',503);
        query('UPDATE backup_history SET deleted_at=NOW() WHERE id=?',[$row['id']]);audit_event('Backup deleted','Verified backup file deleted.','backup',(int)$row['id']);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
