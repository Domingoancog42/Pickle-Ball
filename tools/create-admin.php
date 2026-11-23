<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/api/connection-pdo.php';
$config=database_config();
$email=trim(readline('Admin email: '));
$name=trim(readline('Full name: '));
echo "Choose a unique password (12+ characters). Input is visible in this terminal.\n";
$password=readline('Password: ');
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || $name==='' || mb_strlen($name)>100 || strlen($password)<12 || strlen($password)>72) { fwrite(STDERR,"Invalid email, name, or password. Passwords must be 12–72 bytes.\n"); exit(1); }
$pdo=db();
$q=$pdo->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,'admin')");
try { $q->execute([$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT)]); echo "Administrator created. Open /pikolcastle/index.html#login in XAMPP.\n"; }
catch (PDOException $e) { fwrite(STDERR,"Could not create the administrator. Check whether that email already exists.\n"); exit(1); }
