<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $from=valid_date(substr($_GET['start'] ?? date('Y-m-01'),0,10));$to=valid_date(substr($_GET['end'] ?? date('Y-m-t'),0,10));if($to<$from || strtotime($to)-strtotime($from)>370*86400)throw new ApiError('Choose a valid calendar range.');
    $colors=['Pending'=>'#d5a844','Confirmed'=>'#18b7ae','Completed'=>'#69c59b','Cancelled'=>'#d86d79'];
    $items=rows("SELECT b.booking_id,b.reference_no,b.booking_date,b.start_time,b.end_time,c.court_name,s.name AS status FROM bookings b JOIN courts c ON c.court_id=b.court_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND b.booking_date BETWEEN ? AND ?",[$user['user_id'],$from,$to]);
    return array_map(fn($b)=>['id'=>$b['booking_id'],'title'=>$b['court_name'],'start'=>$b['booking_date'].'T'.$b['start_time'].'+08:00','end'=>$b['booking_date'].'T'.$b['end_time'].'+08:00','backgroundColor'=>$colors[$b['status']] ?? '#aab8bc','borderColor'=>'transparent','textColor'=>'#102225','extendedProps'=>['booking_id'=>$b['booking_id'],'status'=>$b['status']]],$items);
});
