<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
require_once dirname(__DIR__).'/api/payment-qr-service.php';

function qr_check(bool $passed,string $message): void {if(!$passed)throw new RuntimeException($message);}
function qr_test_upload(string $path): array {return ['tmp_name'=>$path,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK];}
function qr_expect_rejected(?array $file): void {
    try{validate_payment_qr_upload($file);}catch(ApiError $error){qr_check($error->status===422,'Expected an invalid-image error.');return;}
    throw new RuntimeException('A non-QR or invalid upload was accepted.');
}

// QR fixture: khanamiryan/php-qrcode-detector-decoder 2.0.3, tests/qrcodes/hello_world.png (MIT/Apache-2.0).
$fixture=__DIR__.'/fixtures/payment-qr.png';
validate_payment_qr_upload(qr_test_upload($fixture));
$image=imagecreatefrompng($fixture);$temporary=[];
try{
    foreach(['jpeg','webp','transparent','rotated','large'] as $format){
        $path=tempnam(sys_get_temp_dir(),'pikol-qr-');$temporary[]=$path;
        if($format==='jpeg')imagejpeg($image,$path,95);
        elseif($format==='webp')imagewebp($image,$path,95);
        elseif($format==='rotated')imagepng(imagerotate($image,90,0),$path);
        elseif($format==='large')imagepng(imagescale($image,1800,1800,IMG_NEAREST_NEIGHBOUR),$path);
        else{$transparent=imagecreatefrompng($fixture);imagecolortransparent($transparent,imagecolorat($transparent,0,0));imagepng($transparent,$path);}
        validate_payment_qr_upload(qr_test_upload($path));
    }
    foreach(['assets/images/logo.png','assets/images/addons/water.png','assets/images/addons/coca-cola.jpg'] as $photo)qr_expect_rejected(qr_test_upload(dirname(__DIR__).'/'.$photo));
    qr_expect_rejected(null);
    qr_expect_rejected(array_replace(qr_test_upload($fixture),['size'=>3*1024*1024+1]));
    qr_expect_rejected(array_replace(qr_test_upload($fixture),['error'=>UPLOAD_ERR_PARTIAL]));
    $path=tempnam(sys_get_temp_dir(),'pikol-qr-');$temporary[]=$path;file_put_contents($path,'not an image');qr_expect_rejected(qr_test_upload($path));
    imagepng(imagecrop($image,['x'=>0,'y'=>0,'width'=>intdiv(imagesx($image),2),'height'=>imagesy($image)]),$path);qr_expect_rejected(qr_test_upload($path));
    imagepng(imagecreatetruecolor(6001,1),$path);qr_expect_rejected(qr_test_upload($path));
}finally{foreach($temporary as $path)unlink($path);}
echo "PASS: QR images decode as PNG/JPEG/WebP, including rotation, transparency and resizing; ordinary pictures are rejected.\n";
