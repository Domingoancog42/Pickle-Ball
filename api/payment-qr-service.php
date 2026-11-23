<?php
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}

function validate_payment_qr_upload(?array $file): void {
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)throw new ApiError('Choose an image containing your complete payment QR code.');
    if(($file['error']??null)!==UPLOAD_ERR_OK||($file['size']??0)>3*1024*1024)throw new ApiError('Choose a QR image smaller than 3 MB.');
    $path=$file['tmp_name'];$info=@getimagesize($path);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if(!$info||!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new ApiError('Upload a valid JPEG, PNG or WebP image containing a QR code.');
    if($info[0]>6000||$info[1]>6000||$info[0]*$info[1]>9000000)throw new ApiError('The QR image is too large. Crop it to the complete QR code and try again.');
    require_once dirname(__DIR__).'/vendor/autoload.php';
    if(!extension_loaded('gd')||!class_exists(\Zxing\QrReader::class))throw new ApiError('QR validation is unavailable. Ask the administrator to enable PHP GD and install the QR reader.',503);
    try{
        $image=@imagecreatefromstring(file_get_contents($path));
        if(!$image)throw new RuntimeException('Unreadable image.');
        // Bound decoder memory and composite transparent QR backgrounds onto white.
        $scale=min(1,1000/max($info[0],$info[1]));
        $width=max(1,(int)round($info[0]*$scale));$height=max(1,(int)round($info[1]*$scale));
        $sample=imagecreatetruecolor($width,$height);
        imagefill($sample,0,0,imagecolorallocate($sample,255,255,255));
        imagecopyresampled($sample,$image,0,0,0,0,$width,$height,$info[0],$info[1]);
        $decoded=(new \Zxing\QrReader($sample,\Zxing\QrReader::SOURCE_TYPE_RESOURCE,false))->text();
        if(!is_string($decoded)||$decoded==='')throw new RuntimeException('No readable QR code.');
    }catch(Throwable $error){throw new ApiError('No readable QR code found. Upload a clear image showing the complete QR code.');}
}
