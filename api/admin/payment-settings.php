<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/payment-qr-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']==='GET')return ['methods'=>rows('SELECT * FROM payment_methods WHERE customer_managed=1 AND removed_at IS NULL ORDER BY payment_method_id')];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();
    if(($data['action']??'')==='validate-qr'){
        validate_payment_qr_upload($_FILES['qr_image']??null);
        return ['ok'=>true];
    }
    $id=empty($data['payment_method_id'])?null:positive_id($data['payment_method_id']);
    if(($data['action']??'')==='remove'){
        if(!$id)throw new ApiError('Choose a payment method to remove.');
        db()->beginTransaction();
        $method=one('SELECT * FROM payment_methods WHERE payment_method_id=? AND customer_managed=1 AND removed_at IS NULL FOR UPDATE',[$id]);
        if(!$method)throw new ApiError('Payment method not found.',404);
        query('UPDATE payment_methods SET customer_enabled=0,removed_at=NOW() WHERE payment_method_id=?',[$id]);
        audit_event('Payment method removed','Removed bank or wallet from settings and new reservations. Existing payment history preserved.','settings',$id);
        db()->commit();return ['ok'=>true];
    }
    $enabled=enum_value($data+['customer_enabled'=>'0'],'customer_enabled',['0','1']);
    $name=text_value($data,'name',50);$account=text_value($data,'account_name',100,$enabled==='1');$number=text_value($data,'account_number',50,$enabled==='1');
    $instructions=text_value($data,'instructions',2000,false);
    if(in_array(mb_strtolower($name),['cash','online payment gateway'],true))throw new ApiError('Choose a bank or wallet name. Cash and Online Payment Gateway are reserved.');
    if($number!==''&&!preg_match('/^\+?[0-9][0-9 ()-]*$/D',$number))throw new ApiError('Enter an account number using digits, spaces, parentheses or hyphens.');
    $qrUpload=$_FILES['qr_image']??null;
    if($qrUpload&&($qrUpload['error']??null)!==UPLOAD_ERR_NO_FILE)validate_payment_qr_upload($qrUpload);
    $newQr=null;db()->beginTransaction();
    try{
        if($id){$existing=one('SELECT * FROM payment_methods WHERE payment_method_id=? AND customer_managed=1 AND removed_at IS NULL FOR UPDATE',[$id]);if(!$existing)throw new ApiError('Payment method not found.',404);}
        else{
            $existing=one('SELECT * FROM payment_methods WHERE name=? FOR UPDATE',[$name]);
            if($existing&&$existing['customer_managed']&&!$existing['removed_at'])throw new ApiError('A payment method with this name already exists.',409);
            $id=$existing['payment_method_id']??null;
        }
        $newQr=safe_image($_FILES['qr_image']??null,'payment-qr');
        $qr=$newQr??($existing['qr_image']??null);
        if($enabled==='1'&&(!$qr||!is_file(dirname(__DIR__,2).'/'.$qr)))throw new ApiError('Upload a QR image before enabling this payment method.');
        $values=[$name,$account,$number,$qr,$instructions,(int)$enabled];
        if($id)query('UPDATE payment_methods SET name=?,account_name=?,account_number=?,qr_image=?,instructions=?,customer_enabled=?,customer_managed=1,removed_at=NULL WHERE payment_method_id=?',[...$values,$id]);
        else{query('INSERT INTO payment_methods(name,account_name,account_number,qr_image,instructions,customer_enabled,customer_managed) VALUES(?,?,?,?,?,?,1)',$values);$id=(int)db()->lastInsertId();}
        audit_event($existing?'Payment method updated':'Payment method added','Bank or wallet account and QR settings saved.','settings',(int)$id);
        db()->commit();return ['ok'=>true,'method'=>one('SELECT * FROM payment_methods WHERE payment_method_id=?',[$id])];
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        if($newQr&&is_file(dirname(__DIR__,2).'/'.$newQr))unlink(dirname(__DIR__,2).'/'.$newQr);
        if($e instanceof PDOException&&$e->getCode()==='23000')throw new ApiError('A payment method with this name already exists.',409);
        throw $e;
    }
});
