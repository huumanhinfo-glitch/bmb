<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Session Information</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Kết nối database - từ Environment Variables
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
    echo "<p style='color:green'>✓ Connected to TiDB Cloud!</p>";
} catch (Exception $e) {
    $optionsNoSSL = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $pdo = new PDO($dsn, $user, $pass, $optionsNoSSL);
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    echo "<h2>Database User Info</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
}

echo "<h2>All Users</h2>";
$all_users = $pdo->query("SELECT id, username, role FROM Users")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
foreach ($all_users as $u) {
    echo "<tr>";
    echo "<td>{$u['id']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>{$u['role']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
