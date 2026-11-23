<?php
require_once __DIR__.'/bootstrap.php';
function availability_context(array $court,string $from,string $to): array {
    $blocks=booking_blocks_slot_sql();
    return ['settings'=>settings(),'schedules'=>array_column(rows('SELECT * FROM court_schedules WHERE court_id=?',[$court['court_id']]),null,'day_of_week'),
        'bookings'=>rows("SELECT b.booking_id,b.booking_date,b.start_time,b.end_time FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.court_id=? AND b.booking_date BETWEEN ? AND ? AND s.name IN ('Pending','Confirmed','Completed') AND $blocks",[$court['court_id'],$from,$to]),
        'maintenance'=>rows('SELECT starts_at,ends_at FROM court_maintenance WHERE court_id=? AND starts_at<? AND ends_at>?',[$court['court_id'],$to.' 23:59:59',$from.' 00:00:00'])];
}
function slots_for_date(array $court,string $date,int $duration,array $context,?int $ignore=null): array {
    $cfg=$context['settings'];$step=(int)$cfg['slot_minutes'];
    if($duration<$step || $duration>(int)$cfg['maximum_duration'] || $duration%$step) throw new ApiError('Choose a duration allowed by the booking policy.');
    $max=(new DateTimeImmutable('today'))->modify('+'.(int)$cfg['advance_days'].' days')->format('Y-m-d');
    if($date<date('Y-m-d') || $date>$max)return [];
    $result=[];
    foreach(operating_windows($court,$date,$cfg,$context['schedules']) as [$open,$last]) {
    $first=(int)(ceil($open/$step)*$step);
    for($i=$first;$i<$last;$i+=$step){
        $start=sprintf('%02d:%02d:00',intdiv($i,60),$i%60);$finish=$i+$duration;$end=sprintf('%02d:%02d:00',intdiv($finish,60),$finish%60);
        $state='Available';
        if($finish>$last || strtotime("$date $start")<=time())$state='Unavailable';
        foreach($context['bookings'] as $b)if($b['booking_date']===$date && (int)$b['booking_id']!==$ignore && $b['start_time']<$end && $b['end_time']>$start)$state='Reserved';
        foreach($context['maintenance'] as $m)if($m['starts_at']<"$date $end" && $m['ends_at']>"$date $start")$state='Maintenance';
        $result[]=['start_time'=>$start,'end_time'=>$end,'state'=>$state];
    }
    }
    return $result;
}
