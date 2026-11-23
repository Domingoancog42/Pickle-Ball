<?php
require __DIR__.'/bootstrap.php';
try{
    $user=require_customer();$p=one('SELECT p.proof_path FROM payments p JOIN bookings b ON b.booking_id=p.booking_id WHERE p.payment_id=? AND b.user_id=?',[positive_id($_GET['payment_id'] ?? null),$user['user_id']]);$path=$p['proof_path'] ?? '';
    if(!preg_match('~^storage/proofs/[a-f0-9]{40}\.(jpg|png|webp)$~',$path))throw new ApiError('Proof not found.',404);
    $file=dirname(__DIR__,2).'/'.$path;if(!is_file($file))throw new ApiError('Proof not found.',404);
    header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file));header('Content-Disposition: inline');readfile($file);
}catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}catch(Throwable $e){error_log((string)$e);json_response(['error'=>'The image could not be loaded.'],500);}
