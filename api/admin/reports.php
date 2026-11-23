<?php
require __DIR__.'/report-types-service.php';
api_handler(function($admin){
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    return selected_report($_GET);
});
