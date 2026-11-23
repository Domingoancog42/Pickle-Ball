<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/audit-location-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $id=positive_id($_GET['id'] ?? null);
    $record=one('SELECT ip_address FROM audit_logs WHERE id=?',[$id]);
    if(!$record)throw new ApiError('Audit record not found.',404);
    // Location requests must not hold up other requests in this administrator's session.
    session_write_close();
    return ['id'=>$id,'location'=>audit_location_lookup($record['ip_address'])];
});
