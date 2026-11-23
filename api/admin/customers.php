<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if (!empty($_GET['id'])) {
            $id=positive_id($_GET['id']);
            $customer=one("SELECT user_id,full_name,email,phone,avatar_path,created_at,account_status FROM users WHERE user_id=? AND role='customer'",[$id]);
            if (!$customer) throw new ApiError('Customer not found.',404);
            $customer['completed_bookings']=(int)scalar("SELECT COUNT(*) FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND s.name='Completed'",[$id]);
            $customer['history']=rows(reservation_select().' WHERE b.user_id=? ORDER BY b.booking_date DESC LIMIT 100',[$id]); return $customer;
        }
        $where="u.role='customer' AND ".unarchived_sql('customers','u.user_id'); $params=[];
        if (!empty($_GET['search'])) { $where.=' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $term='%'.text_value($_GET,'search',150).'%'; array_push($params,$term,$term,$term); }
        if (!empty($_GET['status'])) { $status=enum_value($_GET,'status',['active','inactive','disabled']);$where.=' AND u.account_status=?'; $params[]=$status==='disabled'?'inactive':$status; }
        
        $direction=enum_value($_GET+['sort_direction'=>'asc'],'sort_direction',['asc','desc']);
        $order=strtoupper($direction);
        $count=(int)scalar('SELECT COUNT(*) FROM users u WHERE '.$where,$params);[$page,$size,$offset,$pages]=page_window($count,20);
        return ['items'=>rows("SELECT u.user_id,u.full_name,u.email,u.phone,u.avatar_path,u.account_status,u.created_at,(SELECT COUNT(*) FROM bookings b WHERE b.user_id=u.user_id) AS total_bookings FROM users u WHERE $where ORDER BY u.user_id $order LIMIT ? OFFSET ?",[...$params,$size,$offset]),'total'=>$count,'page'=>$page,'pages'=>$pages,'per_page'=>$size,'sort_direction'=>$direction];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input(); $id=positive_id($data['user_id'] ?? null); $status=enum_value($data,'account_status',['active','inactive','disabled']);
    if($status==='disabled')$status='inactive'; // Compatibility with previously opened admin pages.
    db()->beginTransaction();
    try {
        $customer=one("SELECT * FROM users WHERE user_id=? AND role='customer' FOR UPDATE",[$id]);
        if (!$customer) throw new ApiError('Customer not found.',404);
        require_unarchived('customers',$id);
        if($customer['account_status']!==$status){
            query('UPDATE users SET account_status=?,session_version=session_version+? WHERE user_id=?',[$status,$status==='inactive'?1:0,$id]);
            log_activity('customer',$id,'Account '.$status);
        }
        db()->commit(); return ['ok'=>true,'account_status'=>$status];
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
});
