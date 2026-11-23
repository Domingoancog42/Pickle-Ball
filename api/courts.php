<?php
require __DIR__.'/admin/bootstrap.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $items=rows("SELECT c.court_id,c.court_name,c.description,c.image_path,c.hourly_rate,s.name AS type FROM courts c JOIN surface_types s ON s.surface_type_id=c.surface_type_id WHERE c.deleted_at IS NULL AND c.is_active=1 AND c.court_status='active' ORDER BY c.court_id");
    $galleryLogo=null;
    foreach(['assets/images/royalclub.jpg','assets/images/pikol-royal-club.png','assets/images/pikol-royal-club.jpg','assets/images/pikol-royal-club.webp'] as $path){
        if(is_file(dirname(__DIR__).'/'.$path)){$galleryLogo=$path;break;}
    }
    json_response(['items'=>$items,'gallery_logo'=>$galleryLogo]);
}catch(ApiError $error){json_response(['error'=>$error->getMessage()],$error->status);}
catch(Throwable $error){
    error_log('Public court catalog error: '.get_class($error));
    json_response(['error'=>'Court details are currently unavailable. Please try again.'],503);
}
