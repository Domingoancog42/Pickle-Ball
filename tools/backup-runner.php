<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/backup-service.php';
$lock='pikol-backup-runner-'.substr(hash('sha256',database_config()['name']),0,16);
if(!scalar('SELECT GET_LOCK(?,0)',[$lock])){echo "Runner already active.\n";exit;}
try{
    query('UPDATE backup_settings SET scheduler_last_seen=NOW() WHERE id=1');
    $s=one('SELECT * FROM backup_settings WHERE id=1');
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
    if(!$s['automatic_backup_enabled']||!$s['next_run_at']||new DateTimeImmutable($s['next_run_at'])>$now){echo "No backup due.\n";exit;}
    $row=backup_create('Automatic');
    $next=new DateTimeImmutable($s['next_run_at']);$anchor=new DateTimeImmutable($s['scheduled_at']);
    do{
        if($s['backup_frequency']==='monthly'){
            $next=$next->modify('first day of next month');
            $next=$next->setDate((int)$next->format('Y'),(int)$next->format('m'),min((int)$anchor->format('d'),(int)$next->format('t')));
        }else $next=$next->modify($s['backup_frequency']==='weekly'?'+7 days':'+1 day');
    }while($next<=$now);
    query('UPDATE backup_settings SET next_run_at=? WHERE id=1 AND next_run_at=? AND backup_frequency=?',[$next->format('Y-m-d H:i:s'),$s['next_run_at'],$s['backup_frequency']]);
    echo 'Completed '.$row['backup_reference']."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
finally{scalar('SELECT RELEASE_LOCK(?)',[$lock]);}
