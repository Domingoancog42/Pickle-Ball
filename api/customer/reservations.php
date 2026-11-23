<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    if(!empty($_GET['id'])){
        $b=player_booking(positive_id($_GET['id']),(int)$user['user_id']);
        $b['payments']=rows('SELECT p.payment_id,p.reference_no,p.amount,p.status,p.paid_at,p.verified_at,(p.proof_path IS NOT NULL) AS has_proof,m.name AS payment_method FROM payments p JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE p.booking_id=? ORDER BY p.payment_id DESC',[$b['booking_id']]);
        $b['activity']=rows("SELECT action,created_at FROM activity_logs WHERE entity_type='booking' AND entity_id=? ORDER BY activity_id DESC",[$b['booking_id']]);
        $b['requests']=rows('SELECT request_id,requested_date,requested_start,requested_end,request_status,customer_reason,admin_response,created_at FROM booking_change_requests WHERE booking_id=? ORDER BY request_id DESC',[$b['booking_id']]);return $b;
    }
    $where='b.user_id=?';$params=[$user['user_id']];$tab=$_GET['tab'] ?? 'all';
    if(!empty($_GET['search'])){$where.=' AND (b.reference_no LIKE ? OR EXISTS (SELECT 1 FROM courts c WHERE c.court_id=b.court_id AND c.court_name LIKE ?))';$term='%'.text_value($_GET,'search',150).'%';array_push($params,$term,$term);}
    if(!empty($_GET['date'])){$where.=' AND b.booking_date=?';$params[]=valid_date($_GET['date']);}
    if($tab==='upcoming')$where.=" AND s.name='Confirmed' AND TIMESTAMP(b.booking_date,b.start_time)>NOW()";
    elseif(in_array($tab,['Pending','Completed','Cancelled'],true)){$where.=' AND s.name=?';$params[]=$tab;}
    
    $count=(int)scalar('SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE '.$where,$params);[$page,$size,$offset,$pages]=page_window($count,12);
    $ids=rows('SELECT b.booking_id FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE '.$where." ORDER BY b.booking_date DESC,b.start_time DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]);
    return ['items'=>array_map(fn($row)=>player_booking((int)$row['booking_id'],(int)$user['user_id']),$ids),'total'=>$count,'page'=>$page,'pages'=>$pages,'per_page'=>$size];
});
