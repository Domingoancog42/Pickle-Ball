<?php
require __DIR__.'/bootstrap.php';
customer_handler(function($user){if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);return booking_quote(input(),$user);});
