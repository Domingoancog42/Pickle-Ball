<?php
require __DIR__.'/admin/bootstrap.php';require_once __DIR__.'/booking-addons-service.php';
try{if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);json_response(['items'=>booking_addon_catalog()]);}
catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}catch(Throwable $e){error_log((string)$e);json_response(['error'=>'Add-ons are temporarily unavailable.'],503);}
