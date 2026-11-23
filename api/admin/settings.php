<?php
require __DIR__.'/booking-service.php';
api_handler(function($admin) {
    if ($_SERVER['REQUEST_METHOD']==='GET') return ['settings'=>settings(),'profile'=>$admin];
    $data=input(); $action=$data['action'] ?? '';
    if ($action==='profile') {
        $name=text_value($data,'full_name',100); $email=strtolower(text_value($data,'email',150)); $phone=text_value($data,'phone',20,false);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid email.');
        $existing=one('SELECT password_hash FROM users WHERE user_id=?',[$admin['user_id']]);
        if (!security_password_verify($data['current_password'] ?? '',$existing['password_hash'])) throw new ApiError('Your current password is incorrect.',403);
        if (isset($data['new_password']) && !is_string($data['new_password'])) throw new ApiError('Enter a valid password.');
        if(!empty($data['new_password']))security_password_valid($data['new_password']);
        $avatar=safe_image($_FILES['avatar'] ?? null,'avatars') ?? $admin['avatar_path'];
        db()->beginTransaction();
        try {
            query('UPDATE users SET full_name=?,email=?,phone=?,avatar_path=? WHERE user_id=?',[$name,$email,$phone,$avatar,$admin['user_id']]);
            if (!empty($data['new_password'])) query('UPDATE users SET password_hash=? WHERE user_id=?',[security_password_hash($data['new_password']),$admin['user_id']]);
            if(!empty($data['new_password']))security_password_changed((int)$admin['user_id']);
            log_activity('admin',(int)$admin['user_id'],'Profile updated'); db()->commit();
        } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); if($e instanceof PDOException && $e->getCode()==='23000') throw new ApiError('That email is already in use.',409); throw $e; }
        if (!empty($data['new_password'])) { session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['session_version']=(int)scalar('SELECT session_version FROM users WHERE user_id=?',[$admin['user_id']]); }
        return ['ok'=>true,'csrf'=>csrf_token(),'profile'=>admin_user()];
    }
    $config=[];
    foreach(['venue_name'=>100,'business_description'=>3000,'contact_phone'=>20,'contact_email'=>150,'venue_address'=>500,'booking_policies'=>5000] as $key=>$max) $config[$key]=text_value($data,$key,$max,$key==='venue_name');
    if ($config['contact_email']!=='' && !filter_var($config['contact_email'],FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid contact email.');
    $config['opens_at']=substr(valid_time($data['opens_at'] ?? ''),0,5); $config['closes_at']=substr(valid_time($data['closes_at'] ?? ''),0,5);
    if ($config['closes_at']===$config['opens_at']) throw new ApiError('Opening and closing times must be different.');
    foreach(['weekday','weekend','holiday'] as $group){
        if(!isset($data[$group.'_opens_at'])&&!isset($data[$group.'_closes_at']))continue;
        $open=substr(valid_time($data[$group.'_opens_at']??''),0,5);$close=substr(valid_time($data[$group.'_closes_at']??''),0,5);
        if($open===$close)throw new ApiError(ucfirst($group).' opening and closing times must be different.');
        $config[$group.'_opens_at']=$open;$config[$group.'_closes_at']=$close;
    }
    if(isset($data['holiday_dates'])){
        $dates=preg_split('/[\s,]+/',text_value($data,'holiday_dates',5000,false),-1,PREG_SPLIT_NO_EMPTY);
        foreach($dates as $date)valid_date($date);
        $config['holiday_dates']=implode(',',array_unique($dates));
    }
    // Booking rules are managed internally; business-settings saves preserve them.
    db()->beginTransaction();
    try {
        rows('SELECT court_id FROM courts ORDER BY court_id FOR UPDATE');
        foreach($config as $key=>$value) query('INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
        foreach(rows("SELECT b.*,c.* FROM bookings b JOIN courts c ON c.court_id=b.court_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE s.name IN ('Pending','Confirmed') AND TIMESTAMP(b.booking_date,b.end_time)>NOW()") as $booking) {
            if (!slot_in_operating_hours($booking,$booking['booking_date'],$booking['start_time'],$booking['end_time'])) throw new ApiError('The new business hours conflict with existing reservations.',409);
        }
        log_activity('settings',0,'Business settings updated'); db()->commit(); return ['ok'=>true];
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
});
