<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
$checks=rows("SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='court_schedules' AND tc.CONSTRAINT_TYPE='CHECK'");
foreach($checks as $check){
    $clause=strtolower(str_replace(['`',' ',"\n","\r",'(',')'],'',$check['CHECK_CLAUSE']));
    if($clause!=='closes_at>opens_at')continue;
    $name=str_replace('`','``',$check['CONSTRAINT_NAME']);
    $syntax=str_contains(strtolower((string)scalar('SELECT VERSION()')),'mariadb')?'DROP CONSTRAINT':'DROP CHECK';
    db()->exec("ALTER TABLE court_schedules $syntax `$name`");
}
echo "Overnight operating hours ready.\n";
