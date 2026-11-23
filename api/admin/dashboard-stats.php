<?php
require __DIR__.'/analytics-service.php';
api_handler(function($admin) {
    if($_SERVER['REQUEST_METHOD']!=='GET') throw new ApiError('Method not allowed.',405);
    $monthStart=date('Y-m-01'); $previousStart=date('Y-m-01',strtotime('first day of last month'));
    $priorDay=min((int)date('d'),(int)date('t',strtotime($previousStart)));
    $priorEnd=date('Y-m-',strtotime($previousStart)).str_pad((string)$priorDay,2,'0',STR_PAD_LEFT).' '.date('H:i:s');
    $current=(int)scalar('SELECT COUNT(*) FROM bookings WHERE created_at>=?',[$monthStart]);
    $previous=(int)scalar('SELECT COUNT(*) FROM bookings WHERE created_at>=? AND created_at<=?',[$previousStart,$priorEnd]);
    $revenueCurrent=(float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Paid' AND paid_at>=?",[$monthStart]);
    $revenuePrevious=(float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Paid' AND paid_at>=? AND paid_at<=?",[$previousStart,$priorEnd]);
    $available=0; $courtCount=(int)scalar('SELECT COUNT(*) FROM courts WHERE deleted_at IS NULL'); $now=date('H:i:s');
    foreach(rows("SELECT * FROM courts WHERE is_active=1 AND court_status='active'") as $court) {
        if(court_availability($court)==='available') $available++;
    }
    $period=$_GET['period'] ?? 'daily'; if(!in_array($period,['daily','weekly','monthly'],true)) throw new ApiError('Invalid chart period.');
    $start=match($period) {'weekly'=>(new DateTimeImmutable('monday this week'))->modify('-11 weeks'),'monthly'=>(new DateTimeImmutable('first day of this month'))->modify('-11 months'),default=>(new DateTimeImmutable('today'))->modify('-13 days')};
    $buckets=[]; $cursor=$start; $key=fn($date)=>match($period){'weekly'=>(new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d'),'monthly'=>substr($date,0,7),default=>substr($date,0,10)};
    while($cursor<=new DateTimeImmutable()) { $buckets[$key($cursor->format('Y-m-d'))]=['label'=>$cursor->format($period==='monthly'?'M Y':'d M'),'bookings'=>0,'revenue'=>0.0]; $cursor=$cursor->modify(match($period){'weekly'=>'+1 week','monthly'=>'+1 month',default=>'+1 day'}); }
    foreach(rows('SELECT booking_date,COUNT(*) AS amount FROM bookings WHERE booking_date>=? AND booking_date<=CURDATE() GROUP BY booking_date',[$start->format('Y-m-d')]) as $row) { $k=$key($row['booking_date']); if(isset($buckets[$k]))$buckets[$k]['bookings']+=(int)$row['amount']; }
    foreach(rows("SELECT DATE(paid_at) AS paid_date,SUM(amount) AS amount FROM payments WHERE status='Paid' AND paid_at>=? AND paid_at<=NOW() GROUP BY DATE(paid_at)",[$start->format('Y-m-d')]) as $row) { $k=$key($row['paid_date']); if(isset($buckets[$k]))$buckets[$k]['revenue']+=(float)$row['amount']; }
    return [
        'stats'=>[
            'reservations'=>(int)scalar('SELECT COUNT(*) FROM bookings'),'booking_trend'=>$previous>0?round(($current-$previous)/$previous*100,1):null,
            'today'=>(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE booking_date=CURDATE() AND s.name<>'Cancelled'"),
            'upcoming'=>(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE booking_date=CURDATE() AND start_time>CURTIME() AND s.name IN ('Pending','Confirmed')"),
            'available'=>$available,'courts'=>$courtCount,'revenue'=>(float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Paid'"),
            'revenue_trend'=>$revenuePrevious>0?round(($revenueCurrent-$revenuePrevious)/$revenuePrevious*100,1):null,
            'customers'=>(int)scalar("SELECT COUNT(*) FROM users WHERE role='customer'"),'new_customers'=>(int)scalar("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at>=?",[$monthStart]),
            'pending'=>(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE s.name='Pending'"),
            'comparison_label'=>'Month to date vs the equivalent previous-month period',
        ],
        'series'=>array_values($buckets),'status'=>rows('SELECT s.name,COUNT(b.booking_id) AS count FROM booking_statuses s LEFT JOIN bookings b ON b.status_id=s.status_id AND b.booking_date>=? AND b.booking_date<=CURDATE() GROUP BY s.status_id,s.name',[$start->format('Y-m-d')]),
        'range_label'=>$start->format('d M Y').' – '.date('d M Y'),
        'recent'=>rows(reservation_select().' WHERE '.unarchived_sql('reservations','b.booking_id').' ORDER BY b.created_at DESC,b.booking_id DESC LIMIT 6'),
        'activity'=>rows('SELECT a.*,u.full_name AS actor_name FROM activity_logs a LEFT JOIN users u ON u.user_id=a.actor_id ORDER BY a.activity_id DESC LIMIT 5'),
    ];
});
