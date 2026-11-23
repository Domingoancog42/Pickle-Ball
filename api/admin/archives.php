<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $where=['records.module=?'];$params=[enum_value($_GET,'module',array_keys(archive_modules()))];
        if(!empty($_GET['search'])){
            $where[]='(records.title LIKE ? OR records.detail LIKE ?)';
            $term='%'.text_value($_GET,'search',150).'%';array_push($params,$term,$term);
        }
        $union="SELECT a.*,c.court_name AS title,CONCAT(v.name,' · ',s.name) AS detail,c.court_status AS status
            FROM record_archives a JOIN courts c ON c.court_id=a.record_id JOIN venues v ON v.venue_id=c.venue_id JOIN surface_types s ON s.surface_type_id=c.surface_type_id WHERE a.module='courts'
            UNION ALL SELECT a.*,b.reference_no,CONCAT(u.full_name,' · ',c.court_name,' · ',b.booking_date),s.name
            FROM record_archives a JOIN bookings b ON b.booking_id=a.record_id JOIN users u ON u.user_id=b.user_id JOIN courts c ON c.court_id=b.court_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE a.module='reservations'
            UNION ALL SELECT a.*,u.full_name,u.email,u.account_status
            FROM record_archives a JOIN users u ON u.user_id=a.record_id AND u.role='customer' WHERE a.module='customers'
            UNION ALL SELECT a.*,COALESCE(p.reference_no,CONCAT('Payment #',p.payment_id)),CONCAT(u.full_name,' · ',b.reference_no,' · PHP ',p.amount),p.status
            FROM record_archives a JOIN payments p ON p.payment_id=a.record_id JOIN bookings b ON b.booking_id=p.booking_id JOIN users u ON u.user_id=b.user_id WHERE a.module='payments'";
        $base=' FROM ('.$union.') records LEFT JOIN users actor ON actor.user_id=records.archived_by WHERE '.implode(' AND ',$where);
        $total=(int)scalar('SELECT COUNT(*)'.$base,$params);[$page,$size,$offset,$pages]=page_window($total);
        return ['items'=>rows('SELECT records.module,records.record_id,records.title,records.detail,records.status,records.archived_at,COALESCE(actor.full_name,\'System\') AS archived_by'.$base." ORDER BY records.archived_at DESC,records.module,records.record_id DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]),
            'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size];
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();
    return change_record_archive(enum_value($data,'module',array_keys(archive_modules())),positive_id($data['id'] ?? null),enum_value($data,'action',['archive','restore']),(int)$admin['user_id']);
});
