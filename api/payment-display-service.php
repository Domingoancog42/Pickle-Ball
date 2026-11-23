<?php
function configured_customer_methods(): array {
    $defaults=require dirname(__DIR__).'/config/payment-display.php';$result=[];
    foreach(rows('SELECT * FROM payment_methods WHERE customer_managed=1 AND customer_enabled=1 AND removed_at IS NULL ORDER BY payment_method_id') as $method){
        $method['example']=false;$method['qr_crop']=null;
        foreach(['gcash'=>'GCash','bpi'=>'BPI'] as $key=>$name)if($method['qr_image']==='assets/payment/'.$key.'.png')$method['qr_crop']=$defaults[$name]['qr_crop']??null;
        $result[]=$method;
    }
    return $result;
}
