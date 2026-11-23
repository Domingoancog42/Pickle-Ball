<?php
require dirname(__DIR__).'/backup-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if(hosting_portable())return ['direct_download_only'=>true,'database'=>database_config()['name']];
        if(isset($_GET['id'])){
            $record=one('SELECT * FROM backup_history WHERE id=? AND deleted_at IS NULL',[positive_id($_GET['id'])]);
            if(!$record)throw new ApiError('Backup not found.',404);
            return $record;
        }
        $where='deleted_at IS NULL';$params=[];
        if(!empty($_GET['search'])){$where.=' AND backup_reference LIKE ?';$params[]='%'.text_value($_GET,'search',150).'%';}
        if(!empty($_GET['status'])){$where.=' AND status=?';$params[]=enum_value($_GET,'status',['Pending','Completed','Failed']);}
        if(!empty($_GET['type'])){$where.=' AND backup_type=?';$params[]=enum_value($_GET,'type',['Manual','Automatic']);}
        $direction=enum_value($_GET+['sort'=>'newest'],'sort',['newest','oldest'])==='oldest'?'ASC':'DESC';
        $total=(int)scalar('SELECT COUNT(*) FROM backup_history WHERE '.$where,$params);[$page,$size,$offset,$pages]=page_window($total,10);
        $settings=one('SELECT * FROM backup_settings WHERE id=1');
        return ['items'=>rows("SELECT * FROM backup_history WHERE $where ORDER BY id $direction LIMIT ? OFFSET ?",[...$params,$size,$offset]),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size,'database'=>database_config()['name'],'settings'=>$settings,'last_automatic'=>scalar("SELECT MAX(completed_at) FROM backup_history WHERE backup_type='Automatic' AND status='Completed'"),'scheduler_operational'=>$settings['scheduler_last_seen']&&strtotime($settings['scheduler_last_seen'])>time()-660];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();
    if(hosting_portable())throw new ApiError('Use Download database backup. Scheduled server backups are unavailable on this hosting.');
    if(($data['action']??'')==='settings'){
        $frequency=enum_value($data,'backup_frequency',['daily','weekly','monthly']);
        $enabled=enum_value($data+['automatic_backup_enabled'=>'0'],'automatic_backup_enabled',['0','1']);
        $value=text_value($data,'scheduled_at',16);$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Asia/Manila'));
        if(!$date||$date->format('Y-m-d\TH:i')!==$value||$date->format('Y')<'2000'||$date->format('Y')>'2100')throw new ApiError('Choose a valid backup date and time from 2000 to 2100.');
        $current=one('SELECT * FROM backup_settings WHERE id=1');
        $changed=$current['backup_frequency']!==$frequency||substr((string)$current['scheduled_at'],0,16)!==str_replace('T',' ',$value);
        db()->beginTransaction();
        query('UPDATE backup_settings SET automatic_backup_enabled=?,backup_frequency=?,scheduled_at=?,next_run_at=IF(? OR next_run_at IS NULL,?,next_run_at) WHERE id=1',[(int)$enabled,$frequency,$date->format('Y-m-d H:i:s'),$changed?1:0,$date->format('Y-m-d H:i:s')]);
        audit_event('Backup schedule updated','Automatic backup schedule updated.','backup_settings');db()->commit();
        return ['ok'=>true];
    }
    if(($data['action']??'')!=='create'||($data['confirm']??'')!=='BACKUP')throw new ApiError('Confirm backup creation to continue.');
    $record=backup_create('Manual',(int)$admin['user_id']);
    return ['ok'=>true,'id'=>$record['id'],'reference'=>$record['backup_reference']];
});
