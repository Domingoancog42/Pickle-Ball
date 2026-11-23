<?php
// Only these booking operations are public. Account/history endpoints remain protected.
$action=$_GET['action'] ?? '';
if(!is_string($action)||!in_array($action,['available-courts','available-slots','quote','checkout-hold','checkout-proof'],true)){
    http_response_code(404);header('Content-Type: application/json');echo '{"error":"Checkout action not found."}';exit;
}
define('PIKOL_GUEST_CHECKOUT',$action);
require_once __DIR__.'/guest-checkout-service.php';
require __DIR__.'/customer/'.$action.'.php';
