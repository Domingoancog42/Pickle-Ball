<?php
$module=$_GET['module'] ?? '';
if(!is_string($module)||!in_array($module,['courts','reservations','customers','payments'],true)){
    header('Location: reservations.php');exit;
}
$page='archived-'.$module;
require __DIR__.'/includes/layout.php';
