<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/database-export-service.php';
$path=$argv[1]??'';
if(!$path||!is_dir(dirname($path)))throw new RuntimeException('Provide an SQL destination in an existing private output directory.');
$stream=database_export_stream(true);$output=fopen($path,'xb');
if(!$output){fclose($stream);throw new RuntimeException('Export destination already exists or cannot be written.');}
try{if(stream_copy_to_stream($stream,$output)===false)throw new RuntimeException('Export failed.');fwrite($output,"\n-- Automatic server backups are unavailable on InfinityFree free hosting.\nUPDATE backup_settings SET automatic_backup_enabled=0,scheduler_last_seen=NULL WHERE id=1;\n");}
finally{fclose($output);fclose($stream);}
echo "InfinityFree SQL export ready (no triggers, views, database creation, or DEFINER clauses).\n";
