<?php
require dirname(__DIR__).'/database-export-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $stream=database_export_stream(hosting_portable());
    try {
        audit_event('Database exported','SQL database download generated without retaining a server backup file.','backup');
        session_write_close();
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="pikol-database-'.date('Y-m-d-His').'.sql"');
        header('Content-Length: '.fstat($stream)['size']);
        fpassthru($stream);
    }finally{fclose($stream);}
    exit;
});
