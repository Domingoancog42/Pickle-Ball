<?php
require_once dirname(__DIR__,2).'/api/admin/bootstrap.php';
try{$player=session_user();}catch(Throwable $e){error_log((string)$e);http_response_code(503);exit('The player workspace is unavailable. Please check that MySQL is running.');}
if(!$player){header('Location: ../index.html#login');exit;}
if($player['role']!=='customer'){header('Location: ../admin/index.php');exit;}
function e(mixed $text): string{return htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
