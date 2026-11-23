<?php
require_once __DIR__.'/admin/bootstrap.php';

function live_groups(string $role): array {
    $groups=[
        'courts'=>['courts','venues','surface_types','court_schedules','court_maintenance','record_archives'],
        'reservations'=>['bookings','booking_statuses','booking_change_requests','booking_checkout_holds','record_archives'],
        'payments'=>['payments','payment_methods'],
        'settings'=>['system_settings','booking_addon_products'],
        'ratings'=>['customer_ratings'],
        'players'=>['users'],
    ];
    if($role==='public')unset($groups['payments']);
    else $groups['notifications']=['notifications'];
    if($role==='admin'){
        $groups['customers']=['users','record_archives'];
        $groups['audit']=['audit_logs'];
        $groups['backups']=['backup_history','backup_settings'];
        $groups['locks']=['login_security_tracking'];
    }
    return $groups;
}

function live_snapshot(string $role): array {
    $versions=array_column(rows('SELECT table_name,revision FROM live_versions'),'revision','table_name');
    $result=[];
    foreach(live_groups($role) as $group=>$tables){
        $values=[];foreach($tables as $table)$values[$table]=$versions[$table]??'0';
        // Availability and reminders also change as Manila time advances.
        if($group==='reservations')$values['minute']=(string)intdiv(time(),60);
        $result[$group]=hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    return $result;
}
