<?php
// Environment values override the ignored local configuration.
$local=is_file(__DIR__.'/mail.local.php')?require __DIR__.'/mail.local.php':[];
$value=static fn(string $key,mixed $default='')=>getenv($key)!==false?getenv($key):($local[$key] ?? $default);
return ['host'=>$value('SMTP_HOST','smtp.gmail.com'),'port'=>(int)$value('SMTP_PORT',587),'username'=>$value('SMTP_USERNAME'),'password'=>$value('SMTP_PASSWORD'),'from'=>$value('SMTP_FROM',$value('SMTP_USERNAME')),'from_name'=>$value('SMTP_FROM_NAME','Pikol Castle'),'encryption'=>$value('SMTP_ENCRYPTION','tls'),'auth'=>filter_var($value('SMTP_AUTH',true),FILTER_VALIDATE_BOOLEAN)];
