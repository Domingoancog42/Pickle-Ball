<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
function sql_check(bool $passed,string $message):void {if(!$passed)throw new RuntimeException($message);}
sql_check(!db()->getAttribute(PDO::ATTR_EMULATE_PREPARES),'Native prepared statements must be enabled.');
foreach(["' OR 1=1 -- ","'; DROP TABLE users; --",'1 UNION SELECT password_hash FROM users',"O'Brien",'0 OR SLEEP(1)'] as $value){
    sql_check(scalar('SELECT ? AS literal_value',[$value])===$value,'A bound value was not preserved literally.');
}
sql_check(scalar('SELECT :value',['value'=>"O'Brien"])==="O'Brien",'Named parameters failed.');
sql_check(scalar('SELECT ?', [null])===null,'NULL binding failed.');
sql_check((int)scalar('SELECT ?', [true])===1,'Boolean binding failed.');
sql_check((string)scalar('SELECT ?', [123.45])==='123.45','Decimal binding failed.');
sql_check(count(rows('SELECT user_id FROM users ORDER BY user_id LIMIT ? OFFSET ?',[1,0]))<=1,'Integer pagination binding failed.');
$blocked=false;try{db()->query('SELECT 1; SELECT 2');}catch(PDOException){$blocked=true;}
sql_check($blocked,'Multiple SQL statements must be disabled.');
$blocked=false;try{unarchived_sql('customers','u.user_id OR 1=1');}catch(LogicException){$blocked=true;}
sql_check($blocked,'Unexpected SQL identifiers must be rejected.');
echo "PASS: native prepares, literal injection payloads, typed/named parameters, bound pagination, stacked-statement rejection and identifier allowlists. No records modified.\n";
