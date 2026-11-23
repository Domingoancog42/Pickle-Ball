<?php
require __DIR__.'/admin/bootstrap.php';
try {
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    session_write_close();$s=settings();$hours=[];
    foreach(['daily','weekday','weekend','holiday'] as $group){
        $open=$group==='daily'?($s['opens_at']??'06:00'):($s[$group.'_opens_at']??($group==='holiday'?'07:00':($s['opens_at']??'06:00')));
        $close=$group==='daily'?($s['closes_at']??'22:00'):($s[$group.'_closes_at']??($group==='holiday'?'21:00':($s['closes_at']??'22:00')));
        $minutes=static fn($t)=>(int)substr($t,0,2)*60+(int)substr($t,3,2);
        $duration=($minutes($close)-$minutes($open)+1440)%1440;
        $label=static fn($t)=>date(substr($t,3,2)==='00'?'gA':'g:iA',strtotime($t));
        $hours[$group]=['opens_at'=>$open,'closes_at'=>$close,'label'=>$label($open).'–'.$label($close),'duration_hours'=>$duration/60,'overnight'=>$close<$open];
    }
    json_response(['hours'=>$hours]);
}catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}
catch(Throwable $e){error_log((string)$e);json_response(['error'=>'Opening hours are temporarily unavailable.'],503);}
