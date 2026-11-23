<?php
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
function booking_addon_catalog(bool $all=false): array {
    return rows('SELECT product_id,product_key,name,category,unit_price,image_path,is_active,example_price FROM booking_addon_products'.($all?'':' WHERE is_active=1').' ORDER BY category,product_id');
}
function booking_addon_selection(mixed $input,bool $lock=false): array {
    if(!is_array($input)||!array_is_list($input)||count($input)>50)throw new ApiError('Choose up to 50 different add-ons.');
    $items=[];$seen=[];
    foreach($input as $item){
        if(!is_array($item))throw new ApiError('Choose valid add-ons.');
        $id=positive_id($item['product_id']??null);$qty=filter_var($item['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10]]);
        if(!$qty||isset($seen[$id]))throw new ApiError('Choose a quantity from 1 to 10 for each add-on.');
        $seen[$id]=true;
        $product=one('SELECT * FROM booking_addon_products WHERE product_id=? AND is_active=1'.($lock?' FOR UPDATE':''),[$id]);
        if(!$product)throw new ApiError('An add-on is no longer available. Review your selections.',409);
        $items[]=['product_id'=>$id,'name'=>$product['name'],'quantity'=>$qty,'unit_price'=>(float)$product['unit_price'],'image_path'=>$product['image_path'],'line_total'=>round($qty*(float)$product['unit_price'],2)];
    }
    return $items;
}
function booking_addon_items(int $booking): array {
    return rows('SELECT product_id,name,quantity,unit_price,ROUND(quantity*unit_price,2) AS line_total FROM booking_addon_items WHERE booking_id=? ORDER BY product_id',[$booking]);
}
