<?php
require_once 'db.php';

echo "<h2>Kiểm tra cấu trúc database</h2>";

// Kiểm tra bảng Teams
$stmt = $pdo->query("DESCRIBE Teams");
$teams_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Bảng Teams có các cột:</h3>";
echo "<pre>" . print_r($teams_columns, true) . "</pre>";

// Kiểm tra bảng Tournaments
$stmt = $pdo->query("DESCRIBE Tournaments");
$tournament_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Bảng Tournaments có các cột:</h3>";
echo "<pre>" . print_r($tournament_columns, true) . "</pre>";

// Kiểm tra dữ liệu mẫu
echo "<h3>5 đội đầu tiên:</h3>";
$stmt = $pdo->query("SELECT * FROM Teams LIMIT 5");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($teams, true) . "</pre>";

// Kiểm tra giải đấu
echo "<h3>Tất cả giải đấu:</h3>";
$stmt = $pdo->query("SELECT * FROM Tournaments");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($tournaments, true) . "</pre>";
?>