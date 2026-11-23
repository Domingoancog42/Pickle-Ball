<?php
require __DIR__.'/availability-service.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $court=one("SELECT * FROM courts WHERE court_id=? AND is_active=1 AND court_status='active'",[positive_id($_GET['court_id'] ?? null)]);if(!$court)throw new ApiError('This court is not available for booking.',404);
    $duration=(int)($_GET['duration'] ?? 60);$ignore=null;
    if(!empty($_GET['booking_id'])){$old=player_booking(positive_id($_GET['booking_id']),(int)$user['user_id']);if((int)$old['court_id']!==(int)$court['court_id'])throw new ApiError('Choose the original court.');$duration=(int)$old['duration_minutes'];$ignore=(int)$old['booking_id'];}
    if($ignore===null&&$duration!==60)throw new ApiError('Each court booking must be exactly 1 hour.');
    if(!empty($_GET['month'])){
        $from=valid_date($_GET['month'].'-01');$to=date('Y-m-t',strtotime($from));$context=availability_context($court,$from,$to);$dates=[];
        for($day=new DateTimeImmutable($from);$day<=new DateTimeImmutable($to);$day=$day->modify('+1 day')){$date=$day->format('Y-m-d');$slots=slots_for_date($court,$date,$duration,$context,$ignore);$dates[$date]=count(array_filter($slots,fn($s)=>$s['state']==='Available'));}
        return ['dates'=>$dates,'timezone'=>'Asia/Manila'];
    }
    $date=valid_date($_GET['date'] ?? '');return ['slots'=>slots_for_date($court,$date,$duration,availability_context($court,$date,$date),$ignore),'date'=>$date,'duration'=>$duration,'timezone'=>'Asia/Manila'];
});
