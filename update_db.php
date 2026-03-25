<?php
// update_db.php - Cập nhật database từ cấu trúc cũ sang mới
require_once 'db.php';

echo "<h1>Cập nhật Database</h1>";

// Kiểm tra xem có dữ liệu cũ không
try {
    // Kiểm tra xem bảng Teams có cột 'tournament' không
    $columns = $pdo->query("SHOW COLUMNS FROM Teams")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('tournament', $columns)) {
        echo "<p>Đang cập nhật từ cấu trúc cũ sang mới...</p>";
        
        // 1. Tạo bảng Tournaments nếu chưa có
        $pdo->exec("CREATE TABLE IF NOT EXISTS Tournaments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            format ENUM('round_robin', 'knockout', 'combined', 'double_elimination') DEFAULT 'combined',
            start_date DATE,
            end_date DATE,
            location VARCHAR(200),
            total_teams INT DEFAULT 0,
            status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 2. Thêm cột tournament_id vào Teams nếu chưa có
        if (!in_array('tournament_id', $columns)) {
            $pdo->exec("ALTER TABLE Teams ADD COLUMN tournament_id INT AFTER id");
        }
        
        // 3. Lấy danh sách các giải đấu duy nhất từ cột tournament
        $uniqueTournaments = $pdo->query("SELECT DISTINCT tournament FROM Teams WHERE tournament IS NOT NULL AND tournament != ''")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($uniqueTournaments as $tournamentName) {
            // Kiểm tra xem giải đấu đã tồn tại chưa
            $stmt = $pdo->prepare("SELECT id FROM Tournaments WHERE name = ?");
            $stmt->execute([$tournamentName]);
            
            if (!$stmt->fetch()) {
                // Thêm giải đấu mới
                $insertStmt = $pdo->prepare("INSERT INTO Tournaments (name, format, status) VALUES (?, 'combined', 'ongoing')");
                $insertStmt->execute([$tournamentName]);
                $tournamentId = $pdo->lastInsertId();
                
                echo "<p>Đã tạo giải đấu: $tournamentName (ID: $tournamentId)</p>";
            }
        }
        
        // 4. Cập nhật tournament_id cho các đội
        $allTournaments = $pdo->query("SELECT * FROM Tournaments")->fetchAll();
        
        foreach ($allTournaments as $tournament) {
            $updateStmt = $pdo->prepare("UPDATE Teams SET tournament_id = ? WHERE tournament = ?");
            $updateStmt->execute([$tournament['id'], $tournament['name']]);
            
            $affectedRows = $updateStmt->rowCount();
            echo "<p>Đã cập nhật $affectedRows đội cho giải đấu: {$tournament['name']}</p>";
        }
        
        // 5. Xóa cột tournament cũ (tùy chọn)
        // $pdo->exec("ALTER TABLE Teams DROP COLUMN tournament");
        
        echo "<h3 style='color: green;'>Cập nhật thành công!</h3>";
        echo "<p>Bạn có thể xóa cột 'tournament' cũ trong bảng Teams nếu không cần thiết.</p>";
    } else {
        echo "<p>Database đã ở cấu trúc mới. Không cần cập nhật.</p>";
    }
    
    // Kiểm tra và thêm cột tournament_id cho bảng Matches
    try {
        $matchColumns = $pdo->query("SHOW COLUMNS FROM Matches")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tournament_id', $matchColumns)) {
            $pdo->exec("ALTER TABLE Matches ADD COLUMN tournament_id INT AFTER id");
            echo "<p>Đã thêm cột tournament_id vào bảng Matches</p>";
        }
    } catch (Exception $e) {
        echo "<p>Bảng Matches chưa tồn tại hoặc có lỗi: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Lỗi: " . $e->getMessage() . "</h3>";
}

echo "<hr>";
echo "<a href='draw.php'>Quay lại trang Bốc thăm</a> | ";
echo "<a href='tournament_list.php'>Xem danh sách giải đấu</a>";
?>