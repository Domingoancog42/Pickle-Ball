<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/connection-pdo.php';
$pdo=db();
$exists=static function(string $table)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (bool)$s->fetchColumn();};
$tables=array_values(array_filter(['notification_reads','system_security_settings'],$exists));
if(!$tables){echo "Requested tables are already removed.\n";exit;}
$scope=database_config()['name'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$scope))throw new RuntimeException('Invalid database scope.');
$dir=(getenv('PIKOL_BACKUP_ROOT')?:dirname(__DIR__,3).'/pikolcastle-private').'/'.$scope.'/retired-schema';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Private archive unavailable.');
$archive=$dir.'/retired-settings-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
$sql="-- User-requested retirement archive. Restore manually only if needed.\nSET NAMES utf8mb4;\n";
foreach($tables as $table){$create=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);$sql.=$create[1].";\n";
    foreach($pdo->query("SELECT * FROM `$table`") as $row){$cols=implode(',',array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($row)));$values=implode(',',array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row)));$sql.="INSERT INTO `$table` ($cols) VALUES ($values);\n";}
}
if(file_put_contents($archive,$sql,LOCK_EX)!==strlen($sql))throw new RuntimeException('Archive failed. Tables preserved.');
if($exists('system_security_settings')){
    $old=$pdo->query('SELECT * FROM system_security_settings WHERE id=1')->fetch();
    $policy=require dirname(__DIR__).'/config/security.php';
    if($old)foreach($policy as $key=>$value)$policy[$key]=(int)$old[$key];
    $source="<?php\n// Fixed policy preserved when the security settings table was retired.\nreturn ".var_export($policy,true).";\n";
    if(file_put_contents(dirname(__DIR__).'/config/security.php',$source,LOCK_EX)!==strlen($source))throw new RuntimeException('Policy could not be preserved. Tables kept.');
}
$q=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='read_by'");
if(!$q->fetchColumn())$pdo->exec('ALTER TABLE notifications ADD read_by JSON NULL');
if($exists('notification_reads')){
    $pdo->beginTransaction();
    try{$update=$pdo->prepare('UPDATE notifications SET read_by=JSON_INSERT(COALESCE(read_by,JSON_OBJECT()),?,?) WHERE notification_id=?');
        foreach($pdo->query('SELECT notification_id,user_id,read_at FROM notification_reads') as $row)$update->execute(['$."'.(int)$row['user_id'].'"',$row['read_at'],$row['notification_id']]);
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
foreach($tables as $table)$pdo->exec("DROP TABLE `$table`");
echo 'Removed '.count($tables)." requested tables. Notification read states and login policy preserved.\nPrivate archive: $archive\n";
