<?php
require_once __DIR__.'/booking-service.php';
function report_range(array $input): array {
    $from=valid_date($input['from'] ?? date('Y-m-01')); $to=valid_date($input['to'] ?? date('Y-m-d'));
    if ($to<$from || (strtotime($to)-strtotime($from))/86400>365) throw new ApiError('Choose a reporting range of up to one year.');
    return [$from,$to];
}
function revenue_rows(array $filters): array {
    [$from,$to]=report_range($filters);
    $sql="SELECT p.payment_id,p.reference_no,p.amount,p.paid_at,p.status,m.name AS payment_method,b.reference_no AS booking_reference,b.court_id,c.court_name,u.full_name FROM payments p JOIN bookings b ON b.booking_id=p.booking_id JOIN courts c ON c.court_id=b.court_id JOIN users u ON u.user_id=b.user_id JOIN payment_methods m ON m.payment_method_id=p.payment_method_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE p.status='Paid' AND p.paid_at>=? AND p.paid_at<DATE_ADD(?,INTERVAL 1 DAY)";
    $params=[$from,$to];
    if(!empty($filters['court_id'])) { $sql.=' AND b.court_id=?'; $params[]=positive_id($filters['court_id']); }
    if(!empty($filters['status'])) { $sql.=' AND s.name=?'; $params[]=$filters['status']; }
    return rows($sql.' ORDER BY p.paid_at,p.payment_id',$params);
}
function booking_report(array $filters): array {
    [$from,$to]=report_range($filters); $filters['from']=$from; $filters['to']=$to;
    [$where,$params]=reservation_filters($filters);
    $data=rows(reservation_select().' WHERE '.$where.' ORDER BY b.booking_date,b.start_time',$params);
    if(!empty($filters['payment_status'])) $data=array_values(array_filter($data,fn($row)=>$row['payment_status']===$filters['payment_status']));
    return $data;
}
function utilization(array $filters,array $bookings): array {
    [$from,$to]=report_range($filters); $result=[];
    $courts=rows('SELECT * FROM courts WHERE deleted_at IS NULL'.(!empty($filters['court_id'])?' AND court_id=?':''),!empty($filters['court_id'])?[positive_id($filters['court_id'])]:[]);
    $config=settings(); $schedules=[]; $blocks=[];
    foreach(rows('SELECT * FROM court_schedules') as $schedule) $schedules[$schedule['court_id']][$schedule['day_of_week']]=$schedule;
    foreach(rows('SELECT court_id,starts_at,ends_at FROM court_maintenance WHERE starts_at<? AND ends_at>?',[$to.' 23:59:59',$from.' 00:00:00']) as $block) $blocks[$block['court_id']][]=$block;
    foreach($courts as $court) {
        $available=0; $booked=0; $count=0;
        foreach($bookings as $b) if((int)$b['court_id']===(int)$court['court_id'] && in_array($b['booking_status'],['Confirmed','Completed'],true)) { $booked+=(strtotime($b['end_time'])-strtotime($b['start_time']))/60; $count++; }
        if($court['is_active'] && $court['court_status']==='active') {
            for($day=new DateTimeImmutable($from);$day<=new DateTimeImmutable($to);$day=$day->modify('+1 day')) {
                $date=$day->format('Y-m-d');
                foreach(operating_windows($court,$date,$config,$schedules[$court['court_id']]??[]) as [$open,$close]) {
                $start=strtotime($date.' 00:00:00')+$open*60;$end=strtotime($date.' 00:00:00')+$close*60;$minutes=($end-$start)/60;
                $closures=[];foreach($blocks[$court['court_id']] ?? [] as $m){$a=max($start,strtotime($m['starts_at']));$b=min($end,strtotime($m['ends_at']));if($b>$a)$closures[]=[$a,$b];}
                usort($closures,fn($a,$b)=>$a[0]<=>$b[0]);$lastEnd=$start;
                foreach($closures as [$a,$b]){if($b>$lastEnd){$minutes-=($b-max($a,$lastEnd))/60;$lastEnd=$b;}}
                $available+=max(0,$minutes);
                }
            }
        }
        $result[]=['court_id'=>$court['court_id'],'court_name'=>$court['court_name'],'bookings'=>$count,'booked_hours'=>round($booked/60,1),'available_hours'=>round($available/60,1),'utilization'=>$available>0?round($booked/$available*100,1):null];
    }
    return $result;
}
