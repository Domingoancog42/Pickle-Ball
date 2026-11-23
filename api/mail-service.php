<?php
require_once dirname(__DIR__).'/vendor/autoload.php';
function signup_mailer(): \PHPMailer\PHPMailer\PHPMailer {
    $config=require dirname(__DIR__).'/config/mail.php';
    $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->Host=$config['host'];$mail->Port=$config['port'];$mail->SMTPAuth=$config['auth'];
    $mail->Username=$config['username'];$mail->Password=$config['password'];
    $encryption=$config['encryption']==='none'?'':$config['encryption'];
    $mail->SMTPSecure=$encryption;$mail->SMTPAutoTLS=$encryption!=='';
    $mail->Timeout=20;$mail->CharSet='UTF-8';$mail->SMTPDebug=0;
    $mail->setFrom($config['from'],$config['from_name']);return $mail;
}
function send_temporary_password(string $email,string $name,string $temporary): void {
    $mail=signup_mailer();$mail->addAddress($email,$name);$mail->Subject='Your Pikol Castle temporary password';
    $safe=htmlspecialchars($name,ENT_QUOTES,'UTF-8');
    $mail->isHTML(true);$mail->Body='<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:28px;background:#18252a;color:#e4edef;border-radius:14px"><h1 style="color:#30c9bc">Welcome to Pikol Castle</h1><p>Hello '.$safe.',</p><p>Use your email and the temporary password below in the website login form.</p><p style="padding:18px;background:#26383e;font-size:22px;letter-spacing:2px">'.$temporary.'</p><p>This password expires in 1 hour. Your first login will require you to choose a new password. After saving it, log in again with your new password to open your dashboard.</p><p>Keep this password private. If it expires, submit the signup form again to request a replacement.</p></div>';
    $mail->AltBody="Hello {$name},\n\nYour Pikol Castle temporary password: {$temporary}\n\nIt expires in 1 hour. Log in with your email and this password, choose a new password, then log in again with the new password to access your dashboard. If it expires, submit signup again for a replacement.";
    $mail->send();
}
function send_password_reset_code(string $email,string $name,string $code,bool $accountSecurity=false): void {
    $mail=signup_mailer();$mail->addAddress($email,$name);$mail->Subject='Your Pikol Castle password reset code';$mail->isHTML(true);
    $mail->Body='<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:28px;background:#18252a;color:#e4edef;border-radius:14px"><h1 style="color:#30c9bc">Reset your password</h1><p>Enter this code in the Pikol Castle password reset form:</p><p style="padding:18px;background:#26383e;font-size:30px;letter-spacing:8px">'.$code.'</p><p>This code expires in 10 minutes. Do not share it. If you did not request a reset, you can ignore this email.</p></div>';
    if($accountSecurity){$mail->Subject='Verify your Pikol Castle password change';$mail->Body=str_replace(['Reset your password','password reset form'],['Confirm your password change','Account security form'],$mail->Body);}
    $mail->AltBody="Your Pikol Castle password ".($accountSecurity?'change':'reset')." code: {$code}\n\nExpires in 10 minutes. Do not share it. Ignore this email if you did not request it.";$mail->send();
}

function reservation_confirmation_email(array $booking,array $extras): \PHPMailer\PHPMailer\PHPMailer {
    $mail=signup_mailer();
    $mail->addAddress($booking['email'],$booking['full_name']);
    $mail->Subject='Your Pikol Castle reservation is confirmed - '.$booking['reference_no'];
    $details=[
        'Reservation'=>$booking['reference_no'],
        'Court'=>$booking['court_name'],
        'Date'=>date('F j, Y',strtotime($booking['booking_date'])),
        'Time'=>date('g:i A',strtotime($booking['start_time'])).' - '.date('g:i A',strtotime($booking['end_time'])).' (Asia/Manila)',
    ];
    $safe=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $name=$safe($booking['full_name']);$reference=$safe($booking['reference_no']);
    $amount='PHP '.number_format((float)$booking['court_fee'],2);
    $rows='';
    foreach($details as $label=>$value){
        $rows.='<tr><th scope="row" align="left" valign="top" style="width:88px;padding:13px 12px 13px 0;border-bottom:1px solid #e2ebe7;font-size:13px;line-height:1.6;font-weight:400;color:#526b63">'.$safe($label).'</th><td valign="top" style="padding:13px 0;border-bottom:1px solid #e2ebe7;font-size:14px;line-height:1.6;font-weight:600;color:#183d31;word-break:break-word">'.$safe($value).'</td></tr>';
    }
    $addons='';
    if($extras){
        $addonRows='';
        foreach($extras as $item){
            $addonRows.='<tr><td style="padding:12px 8px 12px 0;border-bottom:1px solid #e2ebe7;font-size:14px;line-height:1.5;color:#183d31;word-break:break-word">'.$safe($item['name']).'</td><td align="center" style="padding:12px 8px;border-bottom:1px solid #e2ebe7;font-size:14px;line-height:1.5;color:#183d31">'.(int)$item['quantity'].'</td><td align="right" style="padding:12px 0 12px 8px;border-bottom:1px solid #e2ebe7;font-size:13px;line-height:1.5;color:#183d31;white-space:nowrap">PHP '.number_format((float)$item['unit_price'],2).'</td></tr>';
        }
        $addons=<<<HTML
        <h2 style="margin:28px 0 6px;font-size:18px;line-height:1.4;color:#183d31">Included add-ons</h2>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#526b63">Included in your verified payment.</p>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed">
          <thead><tr><th scope="col" align="left" style="padding:8px 8px 8px 0;border-bottom:1px solid #cadbd3;font-size:12px;color:#526b63">Item</th><th scope="col" width="32" style="width:32px;padding:8px;border-bottom:1px solid #cadbd3;font-size:12px;color:#526b63">Qty</th><th scope="col" align="right" width="90" style="width:90px;padding:8px 0 8px 8px;border-bottom:1px solid #cadbd3;font-size:12px;color:#526b63">Price each</th></tr></thead>
          <tbody>{$addonRows}</tbody>
        </table>
        HTML;
    }
    $logo='';$logoPath=dirname(__DIR__).'/assets/images/logo.png';
    if(is_file($logoPath)){
        $mail->addEmbeddedImage($logoPath,'pikol-castle-logo','pikol-castle-logo.png','base64','image/png');
        $logo='<td width="64" valign="middle" style="width:64px;padding-right:16px"><img src="cid:pikol-castle-logo" alt="Pikol Castle logo" width="64" height="64" style="display:block;width:64px;height:64px;border:0;border-radius:12px"></td>';
    }
    $followup='Keep this email and your reservation reference. Contact the venue if you need help with your booking.';
    $mail->isHTML(true);
    $mail->Body=<<<HTML
    <!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your reservation is confirmed</title></head>
    <body style="margin:0;padding:0;background-color:#edf4f0;font-family:Arial,Helvetica,sans-serif;color:#183d31">
      <div aria-hidden="true" style="display:none;max-height:0;overflow:hidden;mso-hide:all">Your payment is verified. Reservation {$reference} is confirmed.</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#edf4f0" style="width:100%;background-color:#edf4f0">
        <tr><td align="center" style="padding:24px 12px">
          <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:600px;border:1px solid #d4e3da;border-radius:16px;background-color:#ffffff">
            <tr><td bgcolor="#0b2420" style="padding:24px;background-color:#0b2420;border-radius:15px 15px 0 0">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>{$logo}<td valign="middle"><p style="margin:0;font-size:22px;line-height:1.3;font-weight:700;color:#ffffff">Pikol Castle</p><p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#bbd6cb">Court booking confirmation</p></td></tr></table>
            </td></tr>
            <tr><td style="padding:28px 24px;word-break:break-word">
              <h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;font-weight:700;color:#183d31">Your reservation is confirmed</h1>
              <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#183d31">Hello {$name},</p>
              <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#526b63">We have verified your payment. Your court reservation is confirmed.</p>
              <table width="100%" cellpadding="0" cellspacing="0" border="0" aria-label="Reservation details" style="width:100%;border-collapse:collapse;table-layout:fixed"><tbody>{$rows}</tbody></table>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:24px"><tr><td bgcolor="#e7f5ed" style="padding:18px 20px;border-radius:12px;background-color:#e7f5ed"><p style="margin:0 0 6px;font-size:13px;line-height:1.5;font-weight:700;color:#226044">Payment verified</p><p style="margin:0;font-size:26px;line-height:1.3;font-weight:700;color:#123e2c">{$amount}</p></td></tr></table>
              {$addons}
              <p style="margin:28px 0 0;padding-top:20px;border-top:1px solid #e2ebe7;font-size:13px;line-height:1.7;color:#526b63">{$followup}</p>
            </td></tr>
            <tr><td bgcolor="#f5f9f6" style="padding:18px 24px;border-top:1px solid #e2ebe7;border-radius:0 0 15px 15px;background-color:#f5f9f6"><p style="margin:0;font-size:13px;line-height:1.5;font-weight:700;color:#183d31">Pikol Castle</p><p style="margin:4px 0 0;font-size:12px;line-height:1.5;color:#526b63">Reservation confirmation</p></td></tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
    $plain="Your reservation is confirmed\n\nHello {$booking['full_name']},\n\nWe have verified your payment. Your court reservation is confirmed.\n\n";
    $details['Payment']=$amount.' verified';
    if($extras)$details['Included add-ons']=implode(', ',array_map(fn($item)=>$item['name'].' x'.$item['quantity'].' (PHP '.number_format((float)$item['unit_price'],2).' each)',$extras));
    foreach($details as $label=>$value)$plain.=$label.': '.$value."\n";
    $mail->AltBody=$plain."\n".$followup;
    return $mail;
}

function send_reservation_confirmation(array $booking): void {
    reservation_confirmation_email($booking,booking_addon_items((int)$booking['booking_id']))->send();
}
