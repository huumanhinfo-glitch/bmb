<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Session Information</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Kết nối database - MySQL
$host = 'localhost';
$db   = 'bmb_tournaments';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        echo "<h2>Database User Info</h2>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
        echo "<h2>All Users in Database</h2>";
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
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>