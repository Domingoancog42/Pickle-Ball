<?php
require __DIR__.'/bootstrap.php';
api_handler(function($admin) {
    if($_SERVER['REQUEST_METHOD']!=='GET') throw new ApiError('Method not allowed.',405);
    return ['items'=>rows("SELECT a.*,u.full_name AS actor_name FROM activity_logs a LEFT JOIN users u ON u.user_id=a.actor_id WHERE a.entity_type='payment' AND a.entity_id=? ORDER BY a.activity_id DESC",[positive_id($_GET['id'] ?? null)])];
});
