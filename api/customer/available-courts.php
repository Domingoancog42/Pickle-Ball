<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $items=rows("SELECT c.court_id,c.court_name,c.description,c.image_path,c.hourly_rate,c.opens_at,c.closes_at,s.name AS surface_type FROM courts c JOIN surface_types s ON s.surface_type_id=c.surface_type_id WHERE c.is_active=1 AND c.court_status='active' ORDER BY c.court_id");
    foreach($items as &$court){$court['is_active']=1;$court['court_status']='active';$court['availability']=court_availability($court);}unset($court);
    $cfg=settings();$cfg['default_duration']='60';$cfg['maximum_duration']='60';$cfg['slot_minutes']='30';return ['items'=>$items,'addons'=>booking_addon_catalog(),'methods'=>customer_methods(),'policies'=>array_intersect_key($cfg,array_flip(['venue_name','booking_policies','default_duration','maximum_duration','slot_minutes','advance_days','cancellation_hours','opens_at','closes_at']))];
});
