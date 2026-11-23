<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/live-service.php';
db()->exec("CREATE TABLE IF NOT EXISTS live_versions (
 table_name VARCHAR(64) NOT NULL PRIMARY KEY,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$tables=array_unique(array_merge(...array_values(live_groups('admin'))));
foreach($tables as $table){
    if(!scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]))continue;
    query('INSERT IGNORE INTO live_versions(table_name) VALUES(?)',[$table]);
    foreach(['INSERT'=>'ai','UPDATE'=>'au','DELETE'=>'ad'] as $event=>$suffix){
        $name='pikol_live_'.$table.'_'.$suffix;
        if(scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?',[$name]))continue;
        db()->exec("CREATE TRIGGER `$name` AFTER $event ON `$table` FOR EACH ROW INSERT INTO live_versions(table_name,revision) VALUES('$table',1) ON DUPLICATE KEY UPDATE revision=revision+1");
    }
}
echo "Live change tracking ready. Existing records preserved.\n";
