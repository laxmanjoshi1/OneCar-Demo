<?php
// Run from terminal: php backend/scripts/create_admin.php admin@example.com "A-long-unique-password" "Admin Name"
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { fwrite(STDERR, "Copy backend/config.example.php to backend/config.php and set DB credentials first.\n"); exit(1); }
$config=require $configFile; $c=$config['db'];
if ($argc < 3) { fwrite(STDERR, "Usage: php backend/scripts/create_admin.php email password [name]\n"); exit(1); }
$email=strtolower(trim($argv[1]));$password=$argv[2];$name=trim($argv[3]??'OneCar Administrator');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Use a valid email and a password at least 12 characters long.\n");exit(1);}
$pdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',$c['host'],$c['port'],$c['name'],$c['charset']),$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$hash=password_hash($password,PASSWORD_DEFAULT);
$q=$pdo->prepare('INSERT INTO admins(full_name,email,password_hash,role,active) VALUES(?,?,?,\'admin\',1) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),password_hash=VALUES(password_hash),active=1');$q->execute([$name,$email,$hash]);echo "Admin account created/updated for {$email}.\n";
