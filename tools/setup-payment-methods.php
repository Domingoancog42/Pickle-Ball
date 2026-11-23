<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
foreach(['account_name'=>"VARCHAR(100) NOT NULL DEFAULT ''",'account_number'=>"VARCHAR(50) NOT NULL DEFAULT ''",'qr_image'=>'VARCHAR(255) NULL','instructions'=>'TEXT NULL','customer_enabled'=>'TINYINT NOT NULL DEFAULT 0','customer_managed'=>'TINYINT NOT NULL DEFAULT 0','removed_at'=>'DATETIME NULL'] as $column=>$type){
    if(!scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_methods' AND COLUMN_NAME=?",[$column]))db()->exec("ALTER TABLE payment_methods ADD `$column` $type");
}
$saved=settings();$display=require dirname(__DIR__).'/config/payment-display.php';
if(!isset($saved['payment_methods_configured'])){
foreach(['gcash'=>'GCash','bpi'=>'BPI'] as $key=>$name){
    query('INSERT IGNORE INTO payment_methods(name) VALUES(?)',[$name]);
    query('UPDATE payment_methods SET account_name=?,account_number=?,qr_image=?,instructions=?,customer_enabled=?,customer_managed=1 WHERE name=? AND customer_managed=0',[$saved['payment_'.$key.'_account_name']??$display[$name]['account_name'],$saved['payment_'.$key.'_account_number']??$display[$name]['account_number'],'assets/payment/'.$key.'.png',$saved['manual_'.$key.'_instructions']??'',($saved['manual_'.$key.'_enabled']??'0')==='1'?1:0,$name]);
}
query("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('payment_methods_configured','1')");
}
echo "Flexible payment methods ready. Existing accounts and payment history preserved.\n";
