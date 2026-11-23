<?php
declare(strict_types=1);

// Shared database configuration and PDO connection for APIs and CLI tools.
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__){http_response_code(404);exit;}

function database_config(): array {
    $local=is_file(dirname(__DIR__).'/config/database.local.php')?require dirname(__DIR__).'/config/database.local.php':[];
    $defaults=array_replace(['host'=>'127.0.0.1','port'=>'3306','name'=>'pickleball_booking','user'=>'root','password'=>''],is_array($local)?$local:[]);
    return [
        'host'=>getenv('DB_HOST') ?: $defaults['host'],
        'port'=>getenv('DB_PORT') ?: $defaults['port'],
        'name'=>getenv('DB_NAME') ?: $defaults['name'],
        'user'=>getenv('DB_USER') ?: $defaults['user'],
        'password'=>getenv('DB_PASSWORD')!==false?getenv('DB_PASSWORD'):$defaults['password'],
    ];
}

function hosting_portable(): bool {
    static $config;$config??=require dirname(__DIR__).'/config/hosting.php';
    return !empty($config['portable']);
}

function pdo_connect(bool $selectDatabase=true): PDO {
    $config=database_config();
    $dsn="mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
    if($selectDatabase)$dsn.=";dbname={$config['name']}";
    $multiStatements=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
    $pdo=new PDO($dsn,$config['user'],$config['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        $multiStatements=>false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
    return $pdo;
}

function db(): PDO {
    static $connection;
    return $connection ??= pdo_connect();
}
