<?php
require_once __DIR__.'/analytics-service.php';

function report_definitions(): array {
    return [
        'bookings'=>['Booking Reservation Report',['reference_no'=>'Booking Reference','full_name'=>'Customer Name','court_name'=>'Court','booking_date'=>'Booking Date','time_slot'=>'Time Slot','duration'=>'Duration','amount'=>'Amount','status'=>'Status']],
        'revenue'=>['Revenue Sales Report',['date'=>'Date','booking_reference'=>'Booking Reference','court_name'=>'Court','gross'=>'Gross Amount','refund'=>'Refund Amount','net'=>'Net Amount','method'=>'Payment Method']],
        'customers'=>['Customer/Player Report',['full_name'=>'Customer Name','email'=>'Email','registration_date'=>'Registration Date','total_bookings'=>'Total Bookings','completed_bookings'=>'Completed Bookings','last_booking'=>'Last Booking']],
        'transactions'=>['Payment Transactions Report',['payment_reference'=>'Payment Reference','full_name'=>'Customer','booking_reference'=>'Booking Reference','method'=>'Method','amount'=>'Amount','payment_date'=>'Payment Date','status'=>'Status']],
        'cancellations'=>['Cancellation & No-show Report',['booking_reference'=>'Booking Reference','full_name'=>'Customer','court_name'=>'Court','booking_date'=>'Booking Date','cancellation_date'=>'Cancellation Date','reason'=>'Reason','refund_status'=>'Refund Status']],
    ];
}

function selected_report(array $filters): array {
    [$from,$to]=report_range($filters);$definitions=report_definitions();
    $type=enum_value(['type'=>$filters['type']??'bookings'],'type',array_keys($definitions));
    [$title,$columns]=$definitions[$type];$items=[];$metrics=[];$series=[];$chartLabel='Bookings';$currency=false;
    $court=!empty($filters['court_id'])?positive_id($filters['court_id']):null;
    $courtSql=$court?' AND b.court_id=?':'';$params=[$from,$to];if($court)$params[]=$court;
    if($type==='bookings'||$type==='cancellations'||$type==='utilization')$bookings=booking_report(['from'=>$from,'to'=>$to,'court_id'=>$court]);
    if($type==='bookings') {
        $note='Reservations by scheduled booking date. Amount is the booking charge, including unpaid reservations.';
        $hours=0;$amount=0;$completed=0;
        foreach($bookings as $b){$duration=(strtotime($b['end_time'])-strtotime($b['start_time']))/3600;$hours+=$duration;$amount+=(float)$b['court_fee'];$completed+=$b['booking_status']==='Completed'?1:0;
            $items[]=['reference_no'=>$b['reference_no'],'full_name'=>$b['full_name'],'court_name'=>$b['court_name'],'booking_date'=>$b['booking_date'],'time_slot'=>substr($b['start_time'],0,5).' - '.substr($b['end_time'],0,5),'duration'=>round($duration,2).' hr','amount'=>(float)$b['court_fee'],'status'=>$b['booking_status']];
            $series[$b['booking_date']]=($series[$b['booking_date']]??0)+1;
        }
        $metrics=[['Reservations',count($items)],['Booked duration',round($hours,2).' hr'],['Booking charges',$amount,'money'],['Completed bookings',$completed]];
    } elseif($type==='revenue'||$type==='transactions') {
        $payments=rows("SELECT p.*,b.reference_no AS booking_reference,c.court_name,u.full_name,m.name AS method FROM payments p JOIN bookings b ON b.booking_id=p.booking_id JOIN courts c ON c.court_id=b.court_id JOIN users u ON u.user_id=b.user_id JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE p.paid_at>=? AND p.paid_at<DATE_ADD(?,INTERVAL 1 DAY)".$courtSql.($type==='revenue'?" AND p.status IN ('Paid','Refunded')":'').' ORDER BY p.paid_at,p.payment_id',$params);
        $gross=0;$refund=0;$paid=0;$pending=0;$chartLabel=$type==='revenue'?'Net revenue':'Transactions';$currency=$type==='revenue';
        foreach($payments as $p){$value=(float)$p['amount'];$refunded=$p['status']==='Refunded'?$value:0;$date=substr($p['paid_at'],0,10);
            if($type==='revenue'){$gross+=$value;$refund+=$refunded;$items[]=['date'=>$date,'booking_reference'=>$p['booking_reference'],'court_name'=>$p['court_name'],'gross'=>$value,'refund'=>$refunded,'net'=>$value-$refunded,'method'=>$p['method']];$series[$date]=($series[$date]??0)+$value-$refunded;}
            else {$items[]=['payment_reference'=>$p['reference_no'],'full_name'=>$p['full_name'],'booking_reference'=>$p['booking_reference'],'method'=>$p['method'],'amount'=>$value,'payment_date'=>$p['paid_at'],'status'=>$p['status']];$series[$p['status']]=($series[$p['status']]??0)+1;if($p['status']==='Paid')$paid+=$value;if($p['status']==='Refunded')$refund+=$value;if($p['status']==='Pending Verification')$pending++;}
        }
        $note=$type==='revenue'?'Verified or refunded transactions by original payment date. Refund Amount reflects full refunds recorded against these transactions; Net Amount is Gross minus Refund. This is not a refund-date cash-flow report.':'All payment statuses by recorded payment/submission date. Unpaid and pending amounts are not recognized as revenue.';
        $metrics=$type==='revenue'?[['Gross amount',$gross,'money'],['Refund amount',$refund,'money'],['Net amount',$gross-$refund,'money'],['Transactions',count($items)]]:[['Transactions',count($items)],['Verified paid',$paid,'money'],['Refunded',$refund,'money'],['Pending verification',$pending]];
    } elseif($type==='customers') {
        $note='Players registered on or before the end date. Booking counts and last booking use scheduled dates within the selected range. A court filter includes only players with bookings at that court in the range.';
        $items=rows("SELECT u.full_name,u.email,DATE(u.created_at) AS registration_date,COUNT(b.booking_id) AS total_bookings,COALESCE(SUM(s.name='Completed'),0) AS completed_bookings,MAX(b.booking_date) AS last_booking FROM users u LEFT JOIN bookings b ON b.user_id=u.user_id AND b.booking_date>=? AND b.booking_date<=?".$courtSql." LEFT JOIN booking_statuses s ON s.status_id=b.status_id WHERE u.role='customer' AND u.created_at<DATE_ADD(?,INTERVAL 1 DAY) GROUP BY u.user_id,u.full_name,u.email,u.created_at".($court?' HAVING COUNT(b.booking_id)>0':'').' ORDER BY total_bookings DESC,u.full_name',array_merge($params,[$to]));
        foreach(array_slice($items,0,10) as $index=>$item)$series[($index+1).'. '.$item['full_name']]=(int)$item['total_bookings'];
        $chartLabel='Bookings per player (top 10)';$metrics=[['Players',count($items)],['Players with bookings',count(array_filter($items,fn($r)=>(int)$r['total_bookings']>0))],['Total bookings',array_sum(array_column($items,'total_bookings'))],['Completed bookings',array_sum(array_column($items,'completed_bookings'))]];
    } elseif($type==='cancellations') {
        $note='Cancelled and No-show reservations by scheduled booking date. Missing cancellation dates are shown as not recorded; No-show is identified in the Reason column. Refunds require a separate recorded payment refund.';
        $events=rows("SELECT entity_id,MAX(created_at) AS cancelled_at FROM activity_logs WHERE entity_type='booking' AND action IN ('Status changed to Cancelled','Cancelled by customer','Payment hold expired') GROUP BY entity_id");$dates=array_column($events,'cancelled_at','entity_id');
        $refunds=rows("SELECT booking_id,SUM(CASE WHEN status='Refunded' THEN amount ELSE 0 END) AS refunded,SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) AS paid FROM payments GROUP BY booking_id");$refundMap=array_column($refunds,null,'booking_id');$cancelled=0;$noShow=0;$refundCount=0;
        foreach($bookings as $b){if(!in_array($b['booking_status'],['Cancelled','No-show'],true))continue;$isNoShow=$b['booking_status']==='No-show';$cancelled+=$isNoShow?0:1;$noShow+=$isNoShow?1:0;$p=$refundMap[$b['booking_id']]??['refunded'=>0,'paid'=>0];$refundStatus=(float)$p['refunded']>0?((float)$p['paid']>0?'Partially refunded':'Refunded'):((float)$p['paid']>0?'Not refunded':'No paid funds');$refundCount+=(float)$p['refunded']>0?1:0;
            $items[]=['booking_reference'=>$b['reference_no'],'full_name'=>$b['full_name'],'court_name'=>$b['court_name'],'booking_date'=>$b['booking_date'],'cancellation_date'=>$isNoShow?null:($dates[$b['booking_id']]??null),'reason'=>$isNoShow?'No-show'.(!empty($b['cancellation_reason'])?' - '.$b['cancellation_reason']:''):($b['cancellation_reason']?:'Not recorded'),'refund_status'=>$refundStatus];$series[$b['booking_status']]=($series[$b['booking_status']]??0)+1;
        }
        $chartLabel='Reservations';$metrics=[['Affected reservations',count($items)],['Cancelled',$cancelled],['No-show',$noShow],['With recorded refunds',$refundCount]];
    } else {
        $note='Confirmed and completed bookings only. Available hours use current court schedules and subtract maintenance closures. Peak hours are the busiest clock-hour windows by booked minutes; tied windows are listed.';
        $items=utilization(['from'=>$from,'to'=>$to,'court_id'=>$court],$bookings);$peaks=[];
        foreach($bookings as $b){if(!in_array($b['booking_status'],['Confirmed','Completed'],true))continue;$start=strtotime($b['booking_date'].' '.$b['start_time']);$end=strtotime($b['booking_date'].' '.$b['end_time']);for($hour=(int)date('G',$start);$hour<24;$hour++){$a=strtotime($b['booking_date'].' '.sprintf('%02d:00:00',$hour));if($a>=$end)break;$minutes=max(0,(min($end,$a+3600)-max($start,$a))/60);if($minutes>0)$peaks[$b['court_id']][$hour]=($peaks[$b['court_id']][$hour]??0)+$minutes;}}
        foreach($items as &$item){$hours=$peaks[$item['court_id']]??[];$windows=[];if($hours){$max=max($hours);ksort($hours);foreach($hours as $hour=>$minutes)if(abs($minutes-$max)<0.001)$windows[]=sprintf('%02d:00 - %02d:00',$hour,$hour+1);}$item['peak_hours']=$windows?implode(', ',$windows):'No bookings';$series[$item['court_name']]=$item['utilization']??0;}unset($item);
        $available=array_sum(array_column($items,'available_hours'));$booked=array_sum(array_column($items,'booked_hours'));$metrics=[['Courts',count($items)],['Available hours',round($available,1).' hr'],['Booked hours',round($booked,1).' hr'],['Utilization',$available>0?round($booked/$available*100,1).'%':'Not available']];$chartLabel='Utilization (%)';
    }
    return ['type'=>$type,'title'=>$title,'from'=>$from,'to'=>$to,'columns'=>$columns,'items'=>$items,'metrics'=>$metrics,'note'=>$note,'chart'=>['labels'=>array_keys($series),'values'=>array_values($series),'label'=>$chartLabel,'currency'=>$currency],'types'=>array_map(fn($d)=>$d[0],$definitions)];
}
