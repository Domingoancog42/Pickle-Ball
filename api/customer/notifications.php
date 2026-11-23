<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/notification-service.php';
customer_handler(function($user){
    $id=(int)$user['user_id'];
    // A reserved dedup key identifies reminders, independently of their displayed title.
    $scope="recipient_id=? AND entity_type='reservations' AND dedup_key LIKE 'reminder-%' AND EXISTS (SELECT 1 FROM bookings reminder_booking WHERE reminder_booking.booking_id=notifications.entity_id AND reminder_booking.user_id=notifications.recipient_id)";
    if($_SERVER['REQUEST_METHOD']==='GET'){
        foreach(rows("SELECT b.booking_id,b.reference_no,b.booking_date,b.start_time FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND s.name='Confirmed' AND TIMESTAMP(b.booking_date,b.start_time) BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 24 HOUR)",[$id]) as $b)query("INSERT IGNORE INTO notifications (title,message,entity_type,entity_id,recipient_id,dedup_key) VALUES ('Upcoming booking reminder',?,'reservations',?,?,?)",[$b['reference_no'].' starts '.$b['booking_date'].' at '.substr($b['start_time'],0,5).' (Manila time).',$b['booking_id'],$id,'reminder-'.$b['booking_id'].'-'.$b['booking_date'].'-'.$b['start_time']]);
        $total=(int)scalar("SELECT COUNT(*) FROM notifications WHERE $scope",[$id]);[$page,$size,$offset,$pages]=page_window($total);
        $path=notification_read_path($id);
        return ['items'=>rows("SELECT notification_id,title,message,entity_id,created_at,JSON_UNQUOTE(JSON_EXTRACT(read_by,?)) AS read_at FROM notifications WHERE $scope ORDER BY notification_id DESC LIMIT ".$size.' OFFSET '.$offset,[$path,$id]),'unread'=>(int)scalar("SELECT COUNT(*) FROM notifications WHERE $scope AND JSON_EXTRACT(read_by,?) IS NULL",[$id,$path]),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);$data=input();
    if(($data['action'] ?? '')==='read-all')notification_mark_read($scope,[$id],$id);
    else { $notification=positive_id($data['notification_id'] ?? null);if(!one("SELECT notification_id FROM notifications WHERE notification_id=? AND $scope",[$notification,$id]))throw new ApiError('Notification not found.',404);notification_mark_read("notification_id=? AND $scope",[$notification,$id],$id); }
    return ['ok'=>true];
});
