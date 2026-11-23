<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $id=(int)$user['user_id'];$stats=one("SELECT COUNT(*) AS total,COALESCE(SUM(s.name='Confirmed' AND TIMESTAMP(b.booking_date,b.start_time)>NOW()),0) AS upcoming,COALESCE(SUM(s.name='Completed'),0) AS completed,COALESCE(SUM(s.name='Pending'),0) AS pending FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=?",[$id]);
    $next=scalar("SELECT b.booking_id FROM bookings b JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND s.name='Confirmed' AND TIMESTAMP(b.booking_date,b.start_time)>NOW() ORDER BY b.booking_date,b.start_time LIMIT 1",[$id]);
    $recent=rows('SELECT booking_id FROM bookings WHERE user_id=? ORDER BY created_at DESC,booking_id DESC LIMIT 3',[$id]);
    $favorite=one("SELECT c.court_name,COUNT(*) AS count FROM bookings b JOIN courts c ON c.court_id=b.court_id JOIN booking_statuses s ON s.status_id=b.status_id WHERE b.user_id=? AND s.name='Completed' GROUP BY c.court_id,c.court_name ORDER BY count DESC,c.court_id LIMIT 1",[$id]);
    return ['stats'=>$stats,'next'=>$next?player_booking((int)$next,$id):null,'recent'=>array_map(fn($b)=>player_booking((int)$b['booking_id'],$id),$recent),'favorite'=>$favorite,'user'=>$user];
});
