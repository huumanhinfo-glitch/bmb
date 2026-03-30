<?php
// db.php - MySQL Database Connection
require_once __DIR__ . '/config/env.php';

$dbConfig = Env::getDB();

$host = $dbConfig['host'];
$db   = $dbConfig['name'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$port = $dbConfig['port'];

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=" . $dbConfig['charset'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    // Fallback without SSL for local development
    $optionsNoSSL = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $optionsNoSSL);
    } catch (Exception $e2) {
        die("DB connection failed: " . $e2->getMessage());
    }
}

session_start();
?>
