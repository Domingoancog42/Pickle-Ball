<?php
require __DIR__.'/bootstrap.php';
try {
    require_admin(); $payment=one('SELECT proof_path FROM payments WHERE payment_id=?',[positive_id($_GET['payment_id'] ?? null)]);
    $path=$payment['proof_path'] ?? '';
    if (!preg_match('~^storage/proofs/[a-f0-9]{40}\.(jpg|png|webp)$~',$path)) throw new ApiError('Proof not found.',404);
    $file=dirname(__DIR__,2).'/'.$path; if (!is_file($file)) throw new ApiError('Proof not found.',404);
    header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file)); header('Content-Disposition: inline; filename="payment-proof.'.pathinfo($file,PATHINFO_EXTENSION).'"'); readfile($file);
} catch(ApiError $e) { json_response(['error'=>$e->getMessage()],$e->status); }
