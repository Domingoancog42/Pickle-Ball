<?php
require __DIR__.'/booking-service.php';
require_once dirname(__DIR__).'/mail-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $where=empty($_GET['id'])?unarchived_sql('payments','p.payment_id'):'1=1'; $params=[];
        if (!empty($_GET['status'])) { $where.=' AND p.status=?'; $params[]=text_value($_GET,'status',50); }
        if (!empty($_GET['method_id'])) { $where.=' AND p.payment_method_id=?'; $params[]=positive_id($_GET['method_id']); }
        if (!empty($_GET['search'])) { $where.=' AND (p.reference_no LIKE ? OR b.reference_no LIKE ? OR u.full_name LIKE ?)'; $term='%'.text_value($_GET,'search',150).'%'; array_push($params,$term,$term,$term); }
        $base=' FROM payments p JOIN bookings b ON b.booking_id=p.booking_id JOIN users u ON u.user_id=b.user_id JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE '.$where;
        if(!empty($_GET['id'])) {
            $payment=one('SELECT p.*,b.reference_no AS booking_reference,u.full_name,m.name AS payment_method'.$base.' AND p.payment_id=?',array_merge($params,[positive_id($_GET['id'])]));
            if(!$payment)throw new ApiError('Payment not found.',404);
            return $payment;
        }
        $total=(int)scalar('SELECT COUNT(*)'.$base,$params);[$page,$size,$offset,$pages]=page_window($total,20); 
        return ['items'=>rows('SELECT p.*,b.reference_no AS booking_reference,u.full_name,m.name AS payment_method'.$base." ORDER BY p.payment_id DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size,'methods'=>rows('SELECT * FROM payment_methods')];
    }
    $data=input(); $id=positive_id($data['payment_id'] ?? null);
    $p=one('SELECT p.booking_id,b.court_id FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE p.payment_id=?',[$id]); if (!$p) throw new ApiError('Payment not found.',404);
    $confirmation=null;
    db()->beginTransaction();
    try {
        $court=one('SELECT * FROM courts WHERE court_id=? FOR UPDATE',[$p['court_id']]);
        $booking=one('SELECT b.*,s.name AS booking_status FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.booking_id=? FOR UPDATE',[$p['booking_id']]);
        $payment=one('SELECT p.*,m.name AS method FROM payments p JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE p.payment_id=? FOR UPDATE',[$id]);
        require_unarchived('payments',$id);
        if ($payment['method']==='Online Payment Gateway') throw new ApiError('Online payment processing is not configured.');
        $status=enum_value($data,'status',['Pending Verification','Paid','Refunded']);
        $allowed=['Unpaid'=>['Pending Verification','Paid'],'Pending Verification'=>['Paid'],'Paid'=>['Refunded'],'Refunded'=>[]];
        if (!in_array($status,$allowed[$payment['status']] ?? [],true)) throw new ApiError('This payment cannot make that status change.',409);
        if ($status==='Paid') {
            require_open_payment_hold($booking);
            if ($booking['booking_status']==='Cancelled') throw new ApiError('A cancelled reservation cannot receive payment.',409);
            $paid=(float)scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=? AND status='Paid'",[$booking['booking_id']]);
            if ($paid+(float)$payment['amount']>(float)$booking['court_fee']+0.005) throw new ApiError('This payment would exceed the booking balance.',409);
            if ($paid+(float)$payment['amount']>=(float)$booking['court_fee']) {
                if ($booking['booking_status']==='Pending') {
                    if (scalar('SELECT account_status FROM users WHERE user_id=?',[$booking['user_id']])!=='active') throw new ApiError('The customer account is inactive.',409);
                    validate_slot($court,$booking['booking_date'],$booking['start_time'],$booking['end_time'],(int)$booking['booking_id']);
                    require_open_payment_hold($booking);
                    query("UPDATE bookings SET status_id=(SELECT status_id FROM booking_statuses WHERE name='Confirmed') WHERE booking_id=?",[$booking['booking_id']]);
                    log_activity('booking',(int)$booking['booking_id'],'Status changed to Confirmed','Full manual payment verified.');
                    $booking['booking_status']='Confirmed';
                }
                if ($booking['booking_status']==='Confirmed') {
                    notify('Reservation confirmed',$booking['reference_no'].' - Payment verified. Your reservation is confirmed.','reservations',(int)$booking['booking_id']);
                    $confirmation=one(reservation_select().' WHERE b.booking_id=?',[$booking['booking_id']]);
                }
            }
        }
        $note=text_value($data,'notes',1000); $proof=safe_image($_FILES['proof'] ?? null,'proofs') ?? $payment['proof_path'];
        if(in_array($status,['Paid','Pending Verification'],true))require_open_payment_hold($booking);
        query('UPDATE payments SET status=?,proof_path=?,verified_by=?,verified_at=NOW(),paid_at=IF(?=\'Paid\',NOW(),paid_at) WHERE payment_id=?',[$status,$proof,$admin['user_id'],$status,$id]);
        record_payment_hold_proof((int)($booking['checkout_hold_id'] ?? 0));
        log_activity('payment',$id,'Payment '.$status,$note); log_activity('booking',(int)$booking['booking_id'],'Payment '.$status,$note);
        notify('Payment '.$status,$booking['reference_no'].' · '.$status,'reservations',(int)$booking['booking_id']);
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack();audit_event('Payment verification rejected','The requested payment change was rejected.','payment',$id,'failure');throw $e; }
    $result=['ok'=>true,'reservation_confirmed'=>$confirmation!==null,'email_sent'=>false];
    if($confirmation){
        try {send_reservation_confirmation($confirmation);$result['email_sent']=true;}
        catch(Throwable $e){error_log('Reservation confirmation email failed for booking '.(int)$booking['booking_id']);$result['warning']='Payment saved and reservation confirmed, but the confirmation email could not be sent. Please contact the customer.';}
    }
    return $result;
});
