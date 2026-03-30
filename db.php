<?php
// db.php - MySQL Database Connection
require_once __DIR__ . '/config/env.php';

$dbConfig = Env::getDB();

$host = $dbConfig['host'];
$db   = $dbConfig['name'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$port = $dbConfig['port'];

// Check if we're connecting to TiDB Cloud (requires SSL)
$isTiDB = strpos($host, 'tidbcloud') !== false || strpos($host, 'prod.aws.tidb') !== false;

if ($isTiDB) {
    // TiDB Cloud requires SSL
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=" . $dbConfig['charset'] . ";sslmode=verify-full";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
} else {
    // Local MySQL
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=" . $dbConfig['charset'];
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

session_start();
?>
