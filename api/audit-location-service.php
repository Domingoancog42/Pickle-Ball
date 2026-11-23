<?php
declare(strict_types=1);
if(realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}

function audit_normalize_ip(?string $ip): ?string {
    if(!$ip || !filter_var($ip,FILTER_VALIDATE_IP))return null;
    $binary=inet_pton($ip);
    if(strlen($binary)===16 && substr($binary,0,12)===str_repeat("\0",10)."\xff\xff")$binary=substr($binary,12);
    return inet_ntop($binary);
}
function audit_location_initial(?string $raw): array {
    $ip=audit_normalize_ip($raw);
    if(!$ip)return ['status'=>'unavailable','label'=>'Not available'];
    if($ip==='::1'||str_starts_with($ip,'127.'))return ['status'=>'private','label'=>'Localhost'];
    $public=filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    $binary=inet_pton($ip);
    // PHP's reserved-range filter omits some shared, documentation and multicast ranges.
    if(strlen($binary)===4){
        $number=unpack('N',$binary)[1];
        foreach([['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['192.88.99.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4]] as [$network,$prefix]){
            $mask=(0xffffffff << (32-$prefix))&0xffffffff;
            if(($number&$mask)===(ip2long($network)&$mask)){$public=false;break;}
        }
    }
    if(strlen($binary)===16 && (ord($binary[0])&224)!==32)$public=false;
    if(strlen($binary)===16 && substr($binary,0,4)==="\x20\x01\x0d\xb8")$public=false;
    if(!$public)return ['status'=>'private','label'=>'Private or reserved IP'];
    return ['status'=>'pending','label'=>'Looking up location…'];
}
function audit_location_cached(?string $raw): array {
    $initial=audit_location_initial($raw);
    if($initial['status']!=='pending')return $initial;
    $row=one('SELECT location_data FROM audit_ip_locations WHERE ip_address=? AND expires_at>NOW()',[audit_normalize_ip($raw)]);
    $cached=$row?json_decode($row['location_data'],true):null;
    return is_array($cached)&&isset($cached['status'],$cached['label'])?$cached:$initial;
}
function audit_location_response(string $ip,mixed $body,int $httpStatus): array {
    $unavailable=['status'=>'unavailable','label'=>'Location unavailable'];
    if($httpStatus!==200 || !is_string($body) || strlen($body)>65536)return $unavailable;
    $data=json_decode($body,true);
    if(!is_array($data)||!empty($data['error'])||audit_normalize_ip(is_string($data['ip'] ?? null)?$data['ip']:null)!==$ip)return $unavailable;
    $location=[];
    foreach(['city','region','country_name'] as $field){
        if(!is_string($data[$field] ?? null))continue;
        $value=trim(preg_replace('/[\x00-\x1F\x7F]/u','',$data[$field]) ?? '');
        if($value!=='')$location[]=mb_substr($value,0,150);
    }
    if(!$location)return $unavailable;
    return ['status'=>'available','label'=>implode(', ',array_unique($location))];
}
function audit_location_fetch(string $ip): array {
    if(!function_exists('curl_init'))return ['status'=>'unavailable','label'=>'Location unavailable'];
    // The destination is fixed; only a validated, public IP is placed in the path.
    $request=curl_init('https://ipapi.co/'.rawurlencode($ip).'/json/');
    curl_setopt_array($request,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_MAXFILESIZE=>65536,CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_USERAGENT=>'PikolCastle-Audit/1.0',
    ]);
    $body=curl_exec($request);$status=(int)curl_getinfo($request,CURLINFO_HTTP_CODE);unset($request);
    return audit_location_response($ip,$body,$status);
}
function audit_location_lookup(?string $raw): array {
    $cached=audit_location_cached($raw);
    if($cached['status']!=='pending')return $cached;
    $ip=audit_normalize_ip($raw);
    $result=audit_location_fetch($ip);
    $ttl=$result['status']==='available'?604800:900;
    query('INSERT INTO audit_ip_locations(ip_address,location_data,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE location_data=VALUES(location_data),expires_at=VALUES(expires_at)',[$ip,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$ttl]);
    return $result;
}
