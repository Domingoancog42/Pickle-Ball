<?php
require_once __DIR__.'/admin/bootstrap.php';

/** Build a consistent SQL download without command-line utilities or persistent storage. */
function database_export_stream(bool $portable=false) {
    $out=fopen('php://temp/maxmemory:5242880','w+b');
    if(!$out)throw new RuntimeException('Could not prepare the database download.');
    $write=static function(string $sql)use($out):void {if(fwrite($out,$sql)!==strlen($sql))throw new RuntimeException('Could not finish the database download.');};
    $identifier=static fn(string $name):string=>'`'.str_replace('`','``',$name).'`';
    $attribute=defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')?constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY'):constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY');
    $pdo=db();$buffered=$pdo->getAttribute($attribute);
    try {
        $objects=rows('SHOW FULL TABLES');$tables=[];$views=[];
        foreach($objects as $object){$values=array_values($object);if($values[1]==='BASE TABLE')$tables[]=$values[0];elseif($values[1]==='VIEW')$views[]=$values[0];}
        if($portable&&$views)throw new ApiError('This hosting export supports base tables only. Export database views separately.');
        $triggers=$portable?[]:rows('SHOW TRIGGERS');
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $write("-- Pikol Castle database export\n-- Created ".date('c')."\n-- Schema, table data, views and triggers. Uploaded files are separate.\n\nSET @PIKOL_OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET @PIKOL_OLD_SQL_MODE=@@SQL_MODE;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        foreach($views as $view)$write('DROP VIEW IF EXISTS '.$identifier($view).";\n");
        foreach(array_reverse($tables) as $table)$write('DROP TABLE IF EXISTS '.$identifier($table).";\n");
        foreach($tables as $table){
            $name=$identifier($table);$schema=one('SHOW CREATE TABLE '.$name);
            $write("\n".$schema['Create Table'].";\n");
            $columns=rows('SHOW COLUMNS FROM '.$name);
            $columns=array_values(array_filter($columns,fn($column)=>!preg_match('/(?:VIRTUAL|STORED) GENERATED/i',$column['Extra'])));
            if(!$columns)continue;
            $columnSql=implode(',',array_map(fn($column)=>$identifier($column['Field']),$columns));
            $pdo->setAttribute($attribute,false);
            $statement=$pdo->query('SELECT '.$columnSql.' FROM '.$name);
            try {while($record=$statement->fetch(PDO::FETCH_NUM)){
                $values=array_map(static fn($value)=>$value===null?'NULL':"X'".bin2hex((string)$value)."'",$record);
                $write('INSERT INTO '.$name.' ('.$columnSql.') VALUES ('.implode(',',$values).");\n");
            }}finally{$statement->closeCursor();$pdo->setAttribute($attribute,$buffered);}
        }
        $withoutDefiner=static fn(string $sql):string=>preg_replace('/\bDEFINER\s*=\s*`(?:``|[^`])*`@`(?:``|[^`])*`\s*/i','',$sql);
        foreach($views as $view){$schema=one('SHOW CREATE VIEW '.$identifier($view));$write("\n".$withoutDefiner($schema['Create View']).";\n");}
        if($triggers){
            $delimiter='__PIKOL_END_'.bin2hex(random_bytes(8)).'__';$write("\nDELIMITER ".$delimiter."\n");
            foreach($triggers as $trigger){$schema=one('SHOW CREATE TRIGGER '.$identifier($trigger['Trigger']));$write($withoutDefiner($schema['SQL Original Statement']).$delimiter."\n");}
            $write("DELIMITER ;\n");
        }
        $write("\nSET SQL_MODE=@PIKOL_OLD_SQL_MODE;\nSET FOREIGN_KEY_CHECKS=@PIKOL_OLD_FOREIGN_KEY_CHECKS;\n-- Export complete.\n");
        $pdo->commit();rewind($out);return $out;
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fclose($out);throw $error;}
    finally{$pdo->setAttribute($attribute,$buffered);}
}
