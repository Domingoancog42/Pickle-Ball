<?php
require_once dirname(__DIR__,2).'/api/admin/bootstrap.php';
try { $admin=admin_user(); }
catch(Throwable $e) { error_log((string)$e); http_response_code(503); exit('The admin database is unavailable. Start MySQL in XAMPP and run: php tools/setup-database.php'); }
if (!$admin) { header('Location: ../index.html#login'); exit; }
function e(mixed $text): string { return htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
