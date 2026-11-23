<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/api/mail-service.php';
try{$mail=signup_mailer();if(!$mail->smtpConnect())throw new RuntimeException();$mail->smtpClose();echo "PASS: SMTP connection and authentication. No message sent.\n";}
catch(Throwable){fwrite(STDERR,"SMTP connection/authentication failed. Check the private mail configuration and network access.\n");exit(1);}
