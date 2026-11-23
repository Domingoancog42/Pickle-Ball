<?php
require dirname(__DIR__).'/backup-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $record=one('SELECT * FROM backup_history WHERE id=? AND deleted_at IS NULL',[positive_id($_GET['id']??null)]);
    if(!$record)throw new ApiError('Backup not found.',404);
    $path=backup_verified($record);
    audit_event('Backup downloaded','Verified SQL backup downloaded.','backup',(int)$record['id']);
    session_write_close();
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="'.$record['file_name'].'"');
    header('Content-Length: '.filesize($path));
    readfile($path);exit;
});
