<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/mail-service.php';
function email_check(bool $passed,string $message): void {if(!$passed)throw new RuntimeException($message);}
$booking=['booking_id'=>1,'email'=>'preview@example.test','full_name'=>'Kiro','reference_no'=>'PC-912144DC2262','court_name'=>'Court 1','booking_date'=>'2026-11-21','start_time'=>'23:00:00','end_time'=>'00:00:00','court_fee'=>220];
$extras=[['name'=>'C2','quantity'=>1,'unit_price'=>40],['name'=>'Sprite','quantity'=>2,'unit_price'=>40]];
$mail=reservation_confirmation_email($booking,$extras);
foreach(['Hello Kiro,','PC-912144DC2262','Court 1','November 21, 2026','11:00 PM - 12:00 AM (Asia/Manila)','PHP 220.00','Included add-ons','Sprite','Price each'] as $value)email_check(str_contains($mail->Body,$value),'Missing email content: '.$value);
email_check(str_contains($mail->AltBody,'C2 x1 (PHP 40.00 each), Sprite x2 (PHP 40.00 each)'),'Plain-text item quantities or prices changed.');
email_check(str_contains($mail->AltBody,'Payment: PHP 220.00 verified'),'Plain-text verified total changed.');
email_check(str_contains($mail->Body,'cid:pikol-castle-logo'),'Logo must be embedded, not loaded from a website URL.');
email_check($mail->preSend(),'Email MIME could not be built.');
$mime=$mail->getSentMIMEMessage();
foreach(['multipart/alternative','multipart/related','Content-ID: <pikol-castle-logo>','Content-Type: image/png','Content-Disposition: inline'] as $value)email_check(str_contains($mime,$value),'Missing email MIME part: '.$value);
email_check($mail->Subject==='Your Pikol Castle reservation is confirmed - PC-912144DC2262','Reservation subject changed.');
$without=reservation_confirmation_email($booking,[]);
email_check(!str_contains($without->Body,'Included add-ons')&&!str_contains($without->AltBody,'Included add-ons'),'Empty add-ons should not appear.');
$unsafe=reservation_confirmation_email(array_replace($booking,['full_name'=>'Kiro <img src=x onerror=alert(1)>','court_name'=>'Court <script>alert(1)</script>']),[['name'=>'Snack <img src=x>','quantity'=>2,'unit_price'=>40]]);
email_check(!str_contains($unsafe->Body,'<script>')&&!str_contains($unsafe->Body,'<img src=x'),'Customer or item text became HTML.');
email_check(str_contains($unsafe->Body,'&lt;img src=x&gt;'),'Escaped item name is missing.');
echo "PASS: confirmation details, midnight time, saved add-on prices, HTML escaping, optional extras, plain text and embedded-logo MIME. No email sent.\n";
