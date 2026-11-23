<?php
require __DIR__.'/bootstrap.php';require_once dirname(__DIR__).'/booking-addons-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET')return ['items'=>booking_addon_catalog(true)];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$id=!empty($data['product_id'])?positive_id($data['product_id']):null;
    $old=$id?one('SELECT * FROM booking_addon_products WHERE product_id=?',[$id]):null;if($id&&!$old)throw new ApiError('Product not found.',404);
    $name=text_value($data,'name',100);$category=enum_value($data,'category',['equipment','food','snack']);
    $price=$data['unit_price']??null;if(!is_scalar($price)||!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D',(string)$price)||(float)$price<=0)throw new ApiError('Enter a price from 0.01 to 999999.99.');
    $image=safe_image($_FILES['image']??null,'addons')??($old['image_path']??null);
    if(!$image)throw new ApiError('Upload a photo for the new item.');
    if($id)query('UPDATE booking_addon_products SET name=?,category=?,unit_price=?,image_path=?,is_active=?,example_price=0 WHERE product_id=?',[$name,$category,(float)$price,$image,!empty($data['is_active'])?1:0,$id]);
    else{query('INSERT INTO booking_addon_products(product_key,name,category,unit_price,image_path,is_active,example_price) VALUES(?,?,?,?,?,?,0)',['custom-'.bin2hex(random_bytes(8)),$name,$category,(float)$price,$image,!empty($data['is_active'])?1:0]);$id=(int)db()->lastInsertId();}
    log_activity('addon',$id,'Booking add-on updated',$name.' · PHP '.number_format((float)$price,2));
    return ['ok'=>true];
});
