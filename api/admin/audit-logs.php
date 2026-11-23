<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/audit-location-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Audit records are read-only.',405);
    if(isset($_GET['id'])){
        $row=one('SELECT * FROM audit_logs WHERE id=?',[positive_id($_GET['id'])]);if(!$row)throw new ApiError('Audit record not found.',404);
        $row['changes']=$row['change_data']?json_decode($row['change_data'],true):null;unset($row['change_data']);
        $row['location']=audit_location_cached($row['ip_address']);return $row;
    }
    $where=['1=1'];$params=[];
    foreach(['search'=>['actor_name','actor_email'],'action'=>['action','description']] as $key=>$columns)if(!empty($_GET[$key])){
        $term='%'.text_value($_GET,$key,150).'%';$where[]='('.implode(' OR ',array_map(fn($c)=>$c.' LIKE ?',$columns)).')';foreach($columns as $_)$params[]=$term;
    }
    foreach(['role'=>'actor_role','result'=>'result_status','entity'=>'entity_type'] as $key=>$column)if(!empty($_GET[$key])){$where[]=$column.'=?';$params[]=text_value($_GET,$key,50);}
    if(!empty($_GET['from'])){$where[]='created_at>=?';$params[]=valid_date($_GET['from']).' 00:00:00';}
    if(!empty($_GET['to'])){$where[]='created_at<=?';$params[]=valid_date($_GET['to']).' 23:59:59';}
    $base=' FROM audit_logs WHERE '.implode(' AND ',$where);$total=(int)scalar('SELECT COUNT(*)'.$base,$params);[$page,$size,$offset,$pages]=page_window($total,10);
    $items=rows('SELECT id,user_id,actor_name,actor_email,actor_role,action,description,ip_address,browser,operating_system,device_type,result_status,created_at'.$base." ORDER BY id DESC LIMIT ? OFFSET ?",[...$params,$size,$offset]);
    $locations=[];
    foreach($items as &$item){$ip=$item['ip_address'] ?? '';$item['location']=$locations[$ip] ??= audit_location_cached($ip);}unset($item);
    return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$size];
});
