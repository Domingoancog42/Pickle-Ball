<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']!=='GET') throw new ApiError('Method not allowed.',405);
    $from=valid_date(substr($_GET['start'] ?? date('Y-m-01'),0,10)); $to=valid_date(substr($_GET['end'] ?? date('Y-m-t'),0,10));
    if ((strtotime($to)-strtotime($from))/86400>370) throw new ApiError('Choose a calendar range within one year.');
    $filter=['from'=>$from,'to'=>$to,'court_id'=>$_GET['court_id'] ?? null]; [$where,$params]=reservation_filters($filter);
    $where.=' AND '.unarchived_sql('reservations','b.booking_id');
    // Stable customer colors; status remains explicit text instead of relying on color alone.
    $colors=['#a8ded6','#b8d5f5','#dfc9f2','#f4d39f','#f3bbc8','#bddda8','#b9dfe9','#e3cbb1'];
    $events=[];
    foreach(rows(reservation_select().' WHERE '.$where,$params) as $b) $events[]=['id'=>'booking-'.$b['booking_id'],'title'=>$b['full_name'].' · '.$b['court_name'],'start'=>$b['booking_date'].'T'.$b['start_time'],'end'=>$b['booking_date'].'T'.$b['end_time'],'backgroundColor'=>$colors[(int)$b['user_id']%count($colors)],'borderColor'=>'transparent','textColor'=>'#17303b','extendedProps'=>['booking_id'=>$b['booking_id'],'customer_name'=>$b['full_name'],'court_name'=>$b['court_name'],'reference_no'=>$b['reference_no'],'status'=>$b['booking_status']]];
    $maintenance=rows('SELECT m.*,c.court_name FROM court_maintenance m JOIN courts c ON c.court_id=m.court_id WHERE m.starts_at<? AND m.ends_at>?'.(!empty($_GET['court_id'])?' AND m.court_id=?':''),!empty($_GET['court_id'])?[$to.' 23:59:59',$from.' 00:00:00',positive_id($_GET['court_id'])]:[$to.' 23:59:59',$from.' 00:00:00']);
    foreach($maintenance as $m) $events[]=['id'=>'maintenance-'.$m['maintenance_id'],'title'=>'Maintenance · '.$m['court_name'],'start'=>str_replace(' ','T',$m['starts_at']),'end'=>str_replace(' ','T',$m['ends_at']),'backgroundColor'=>'#7D607E','borderColor'=>'transparent','extendedProps'=>['maintenance'=>true,'reason'=>$m['reason']]];
    return $events;
});
