<?php
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
$local=is_file(__DIR__.'/hosting.local.php')?require __DIR__.'/hosting.local.php':[];
$config=array_replace(['portable'=>false],is_array($local)?$local:[]);
if(getenv('PIKOL_PORTABLE')!==false)$config['portable']=filter_var(getenv('PIKOL_PORTABLE'),FILTER_VALIDATE_BOOLEAN);
return $config;
