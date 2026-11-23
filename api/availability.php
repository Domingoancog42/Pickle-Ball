<?php
require __DIR__.'/customer/availability-service.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $date=valid_date($_GET['date'] ?? date('Y-m-d'));$cfg=settings();$max=(new DateTimeImmutable('today'))->modify('+'.(int)$cfg['advance_days'].' days')->format('Y-m-d');
    if($date<date('Y-m-d')||$date>$max)throw new ApiError('Choose a date within the advance booking window.');
    $duration=isset($_GET['duration'])?(int)$_GET['duration']:60;
    if($duration!==60)throw new ApiError('Each court booking must be exactly 1 hour.');
    $cfg['default_duration']='60';$cfg['maximum_duration']='60';$cfg['slot_minutes']='30';
    $courts=rows("SELECT c.court_id,c.court_name,c.description,c.image_path,c.hourly_rate,c.opens_at,c.closes_at,c.is_active,c.court_status,s.name AS type FROM courts c JOIN surface_types s ON s.surface_type_id=c.surface_type_id WHERE c.is_active=1 AND c.court_status='active' ORDER BY c.court_id");
    foreach($courts as &$court){$court['slots']=slots_for_date($court,$date,$duration,availability_context($court,$date,$date));}unset($court);
    json_response(['courts'=>$courts,'date'=>$date,'policies'=>array_intersect_key($cfg,array_flip(['advance_days','default_duration','maximum_duration','slot_minutes']))]);
}catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}catch(Throwable $e){error_log((string)$e);json_response(['error'=>'Live availability is currently unavailable. Please try again later.'],503);}
