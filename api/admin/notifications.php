<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/notification-service.php';
api_handler(function($admin){
    $id=(int)$admin['user_id'];$path=notification_read_path($id);
    if($_SERVER['REQUEST_METHOD']==='GET')return [
        'items'=>rows('SELECT n.*,JSON_UNQUOTE(JSON_EXTRACT(n.read_by,?)) AS read_at FROM notifications n WHERE (n.recipient_id IS NULL OR n.recipient_id=?) ORDER BY n.notification_id DESC LIMIT 30',[$path,$id]),
        'unread'=>(int)scalar('SELECT COUNT(*) FROM notifications WHERE (recipient_id IS NULL OR recipient_id=?) AND JSON_EXTRACT(read_by,?) IS NULL',[$id,$path]),
        'pending_reservations'=>(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE s.name='Pending' AND ".unarchived_sql('reservations','b.booking_id')),
        'pending_payments'=>(int)scalar("SELECT COUNT(*) FROM payments p WHERE p.status='Pending Verification' AND ".unarchived_sql('payments','p.payment_id')),
    ];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();
    if(($data['action']??'')==='read-all')notification_mark_read('(recipient_id IS NULL OR recipient_id=?)',[$id],$id);
    else notification_mark_read('(recipient_id IS NULL OR recipient_id=?) AND notification_id=?',[$id,positive_id($data['notification_id']??null)],$id);
    return ['ok'=>true];
});
