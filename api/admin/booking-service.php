<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/operating-hours.php';
require_once dirname(__DIR__).'/booking-addons-service.php';

function operating_hours(array $court,string $date): array {
    $settings=settings();
    $schedule=one('SELECT * FROM court_schedules WHERE court_id=? AND day_of_week=?',[$court['court_id'],(int)date('w',strtotime($date))]);
    if ($schedule && !$schedule['is_open']) throw new ApiError('This court is closed on the selected day.',409);
    return [max($schedule['opens_at'] ?? $court['opens_at'],$settings['opens_at'].':00'),min($schedule['closes_at'] ?? $court['closes_at'],$settings['closes_at'].':00')];
}
function court_availability(array $court): string {
    if ($court['court_status']==='maintenance') return 'maintenance';
    if (!$court['is_active'] || $court['court_status']!=='active') return 'disabled';
    $minute=(int)date('H')*60+(int)date('i');$isOpen=false;
    foreach(court_operating_windows($court,date('Y-m-d')) as [$a,$b])if($minute>=$a&&$minute<$b)$isOpen=true;
    if(!$isOpen)return 'closed';
    if(scalar('SELECT COUNT(*) FROM court_maintenance WHERE court_id=? AND starts_at<=NOW() AND ends_at>NOW()',[$court['court_id']])) return 'maintenance';
    $blocks=booking_blocks_slot_sql();
    if(scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE court_id=? AND booking_date=CURDATE() AND start_time<=CURTIME() AND end_time>CURTIME() AND s.name IN ('Pending','Confirmed') AND $blocks",[$court['court_id']])) return 'booked';
    return 'available';
}
function validate_slot(array $court,string $date,string $start,string $end,?int $ignore=null,bool $future=true): void {
    if(db()->inTransaction())expire_payment_holds_for_court((int)$court['court_id']);
    if (!$court['is_active'] || $court['court_status']!=='active') throw new ApiError('This court is disabled or under maintenance.',409);
    if ($end<=$start) throw new ApiError('The end time must be after the start time.');
    $config=settings(); $begins=new DateTimeImmutable("$date $start"); $finishes=new DateTimeImmutable("$date $end");
    if ($future && $begins<new DateTimeImmutable()) throw new ApiError('Choose a future reservation time.');
    if ($begins->format('Y-m-d')>(new DateTimeImmutable('today'))->modify('+'.(int)$config['advance_days'].' days')->format('Y-m-d')) throw new ApiError('The date is outside the advance booking window.');
    $minutes=($finishes->getTimestamp()-$begins->getTimestamp())/60;
    if ($minutes>(int)$config['maximum_duration'] || $minutes<(int)$config['slot_minutes'] || $minutes%(int)$config['slot_minutes']!==0) throw new ApiError('Duration must match the booking interval and maximum duration.');
    $step=(int)$config['slot_minutes'];
    foreach ([$start,$end] as $time) if (((int)substr($time,0,2)*60+(int)substr($time,3,2))%$step!==0) throw new ApiError('Start and end times must align with the configured booking interval.');
    if (!slot_in_operating_hours($court,$date,$start,$end)) throw new ApiError('This time is outside the court’s operating hours.',409);
    if (scalar("SELECT COUNT(*) FROM court_maintenance WHERE court_id=? AND starts_at<? AND ends_at>?",[$court['court_id'],"$date $end","$date $start"])) throw new ApiError('This time overlaps a maintenance block.',409);
    $blocks=booking_blocks_slot_sql();
    if (scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.court_id=? AND b.booking_date=? AND s.name IN ('Pending','Confirmed','Completed') AND $blocks AND b.start_time<? AND b.end_time>? AND b.booking_id<>?",[$court['court_id'],$date,$end,$start,$ignore ?? 0])) throw new ApiError('This court already has an overlapping reservation.',409);
}
function reservation_select(): string {
    return "SELECT b.*,COALESCE(gh.guest_name,u.full_name) AS full_name,COALESCE(gh.guest_email,u.email) AS email,COALESCE(gh.guest_phone,u.phone) AS phone,u.avatar_path,c.court_name,s.name AS booking_status,
        (SELECT ra.archived_at FROM record_archives ra WHERE ra.module='reservations' AND ra.record_id=b.booking_id) AS archived_at,
        COALESCE(p.paid_amount,0) AS paid_amount,
        CASE WHEN COALESCE(p.paid_amount,0)>=b.court_fee THEN 'Paid' WHEN COALESCE(p.pending_count,0)>0 THEN 'Pending Verification' WHEN COALESCE(p.refund_count,0)>0 AND COALESCE(p.paid_amount,0)=0 THEN 'Refunded' ELSE 'Unpaid' END AS payment_status
        FROM bookings b JOIN users u ON u.user_id=b.user_id JOIN courts c ON c.court_id=b.court_id JOIN booking_statuses s ON s.status_id=b.status_id
        LEFT JOIN booking_checkout_holds gh ON gh.hold_id=b.checkout_hold_id
        LEFT JOIN (SELECT booking_id,SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) AS paid_amount,SUM(status='Pending Verification') AS pending_count,SUM(status='Refunded') AS refund_count FROM payments GROUP BY booking_id) p ON p.booking_id=b.booking_id";
}
function reservation_filters(array $filters): array {
    $where=['1=1']; $params=[];
    if (!empty($filters['search'])) { $where[]='(b.reference_no LIKE ? OR COALESCE(gh.guest_name,u.full_name) LIKE ? OR COALESCE(gh.guest_email,u.email) LIKE ?)'; $term='%'.text_value($filters,'search',150).'%'; array_push($params,$term,$term,$term); }
    if (!empty($filters['court_id'])) { $where[]='b.court_id=?'; $params[]=positive_id($filters['court_id']); }
    if (!empty($filters['date'])) { $where[]='b.booking_date=?'; $params[]=valid_date($filters['date']); }
    if (!empty($filters['from'])) { $where[]='b.booking_date>=?'; $params[]=valid_date($filters['from']); }
    if (!empty($filters['to'])) { $where[]='b.booking_date<=?'; $params[]=valid_date($filters['to']); }
    if (!empty($filters['status'])) { $where[]='s.name=?'; $params[]=text_value($filters,'status',50); }
    return [implode(' AND ',$where),$params];
}
function create_reservation(array $data,?float $expectedFee=null,bool $manageTransaction=true,bool $walkIn=false): array {
    $courtId=positive_id($data['court_id'] ?? null); $date=valid_date((string)($data['booking_date'] ?? ''));
    $start=valid_time((string)($data['start_time'] ?? '')); $end=valid_booking_end((string)($data['end_time'] ?? ''));
    $status=enum_value($data,'status',['Pending','Confirmed']);
    $methodId=positive_id($data['payment_method_id'] ?? null);
    $method=one('SELECT * FROM payment_methods WHERE payment_method_id=?',[$methodId]);
    if (!$method || !empty($method['removed_at']) || $method['name']==='Online Payment Gateway') throw new ApiError('Select an available manual payment method.');
    $cashPaid=$walkIn&&strcasecmp(trim($method['name']),'Cash')===0;
    $cashAdmin=$cashPaid?require_admin():null;
    if($cashPaid)$status='Confirmed';
    if($manageTransaction)db()->beginTransaction();
    try {
        // Every operation changing availability locks the same court row first.
        $court=one('SELECT * FROM courts WHERE court_id=? FOR UPDATE',[$courtId]);
        if (!$court) throw new ApiError('Court not found.',404);
        validate_slot($court,$date,$start,$end);
        if (!empty($data['user_id'])) $customer=one("SELECT user_id,account_status FROM users WHERE user_id=? AND role='customer' FOR UPDATE",[positive_id($data['user_id'])]);
        else {
            $name=text_value($data,'full_name',100); $email=strtolower(text_value($data,'email',150)); $phone=text_value($data,'phone',20,false);
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid customer email.');
            $customer=one('SELECT user_id,account_status,role FROM users WHERE email=? FOR UPDATE',[$email]);
            if ($customer && $customer['role']!=='customer') throw new ApiError('Choose a customer account, not an administrator.');
            if (!$customer) {
                query("INSERT INTO users (full_name,email,phone,password_hash,role) VALUES (?,?,?,?,'customer')",[$name,$email,$phone,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
                $customer=['user_id'=>(int)db()->lastInsertId(),'account_status'=>'active'];
            }
        }
        if (!$customer || $customer['account_status']!=='active') throw new ApiError('Choose an active customer account.',409);
        $fee=round((strtotime($end)-strtotime($start))/3600*(float)$court['hourly_rate'],2);
        if($expectedFee!==null && abs($fee-$expectedFee)>0.005) throw new ApiError('The court rate changed. Please review a new quote before booking.',409);
        if ($fee<=0) throw new ApiError('Set a positive hourly rate before accepting bookings.');
        $reference='PC-'.strtoupper(bin2hex(random_bytes(6)));
        query('INSERT INTO bookings (user_id,court_id,status_id,booking_date,start_time,end_time,court_fee,reference_no,admin_notes) VALUES (?,?,(SELECT status_id FROM booking_statuses WHERE name=?),?,?,?,?,?,?)',[$customer['user_id'],$courtId,$status,$date,$start,$end,$fee,$reference,text_value($data,'admin_notes',3000,false)]);
        $id=(int)db()->lastInsertId();
        query('INSERT INTO payments (booking_id,payment_method_id,amount,status,reference_no,paid_at,verified_by,verified_at) VALUES (?,?,?,?,?,NOW(),?,?)',[$id,$methodId,$fee,$cashPaid?'Paid':'Unpaid','PAY-'.strtoupper(bin2hex(random_bytes(6))),$cashAdmin['user_id']??null,$cashPaid?date('Y-m-d H:i:s'):null]);
        $paymentId=(int)db()->lastInsertId();
        if($cashPaid){
            log_activity('payment',$paymentId,'Payment Paid','Cash received by administrator for walk-in reservation.');
            log_activity('booking',$id,'Payment Paid','Cash received in full. Reservation confirmed.');
            audit_event('Walk-in cash payment recorded','Cash received in full and reservation confirmed.','payment',$paymentId);
        }
        log_activity('booking',$id,'Reservation created',"$reference · $status");
        notify('New reservation',"$reference was created for {$court['court_name']}.",'reservations',$id);
        if($manageTransaction)db()->commit(); return ['booking_id'=>$id,'reference_no'=>$reference,'booking_status'=>$status,'payment_status'=>$cashPaid?'Paid':'Unpaid'];
    } catch (Throwable $e) { if ($manageTransaction && db()->inTransaction()) db()->rollBack(); throw $e; }
}
