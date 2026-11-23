<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $params=[$user['user_id']];$where='b.user_id=?';if(!empty($_GET['status'])){$where.=' AND p.status=?';$params[]=text_value($_GET,'status',50);}
        if(!empty($_GET['search'])){$where.=' AND (b.reference_no LIKE ? OR p.reference_no LIKE ? OR c.court_name LIKE ?)';$term='%'.text_value($_GET,'search',150).'%';array_push($params,$term,$term,$term);}
        if(!empty($_GET['method_id'])){$where.=' AND p.payment_method_id=?';$params[]=positive_id($_GET['method_id']);}
        $base=' FROM payments p JOIN bookings b ON b.booking_id=p.booking_id JOIN courts c ON c.court_id=b.court_id JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE '.$where;
        $total=(int)scalar('SELECT COUNT(*)'.$base,$params);[$page,$size,$offset,$pages]=page_window($total,15);
        $items=rows('SELECT p.payment_id,p.reference_no,p.booking_id,p.amount,p.status,p.paid_at,p.verified_at,(p.proof_path IS NOT NULL) AS has_proof,b.reference_no AS booking_reference,c.court_name,m.name AS payment_method'.$base." ORDER BY p.payment_id DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]);
        $summary=one("SELECT COALESCE(SUM(CASE WHEN p.status='Paid' THEN p.amount ELSE 0 END),0) AS paid,COALESCE(SUM(CASE WHEN p.status IN ('Unpaid','Pending Verification') AND s.name<>'Cancelled' THEN p.amount ELSE 0 END),0) AS pending FROM payments p JOIN bookings b ON b.booking_id=p.booking_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=?",[$user['user_id']]);
        return ['items'=>$items,'summary'=>$summary,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size,'methods'=>customer_methods()];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$id=positive_id($data['payment_id'] ?? null);$p=one('SELECT p.booking_id,b.court_id FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE p.payment_id=? AND b.user_id=?',[$id,$user['user_id']]);if(!$p)throw new ApiError('Payment not found.',404);
    $proof=null;db()->beginTransaction();
    try{
    one('SELECT court_id FROM courts WHERE court_id=? FOR UPDATE',[$p['court_id']]);
    $b=player_booking((int)$p['booking_id'],(int)$user['user_id'],true);
    require_open_payment_hold($b);
    $p=one('SELECT p.*,m.name AS method FROM payments p JOIN payment_methods m ON m.payment_method_id=p.payment_method_id WHERE p.payment_id=? FOR UPDATE',[$id]);
    if(!in_array($p['status'],['Unpaid','Pending Verification'],true) || !in_array($b['booking_status'],['Pending','Confirmed'],true))throw new ApiError('This payment is not eligible for proof submission.',409);
    $methodId=allowed_customer_method($data['payment_method_id'] ?? $p['payment_method_id']);
    $proof=safe_image($_FILES['proof'] ?? null,'proofs');if(!$proof)throw new ApiError('Choose a payment proof image.');
    require_open_payment_hold($b);
    query("UPDATE payments SET payment_method_id=?,status='Pending Verification',proof_path=?,verified_by=NULL,verified_at=NULL WHERE payment_id=?",[$methodId,$proof,$id]);
    record_payment_hold_proof((int)($b['checkout_hold_id'] ?? 0));
    log_activity('booking',(int)$b['booking_id'],'Payment proof submitted');log_activity('payment',$id,'Customer submitted payment proof');notify('Payment awaiting verification',$b['reference_no'].' has a submitted proof. Staff verification is still required.','reservations',(int)$b['booking_id']);db()->commit();return ['ok'=>true,'status'=>'Pending Verification'];
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();if($proof&&preg_match('~^storage/proofs/[a-f0-9]{40}\\.(png|jpg|webp)$~D',$proof))@unlink(dirname(__DIR__,2).'/'.$proof);throw $error;}
});
