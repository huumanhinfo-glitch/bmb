<?php
// api/delete_team.php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $teamId = $_GET['id'] ?? 0;
        
        if (!$teamId) {
            echo json_encode(['success' => false, 'message' => 'ID đội không hợp lệ']);
            exit;
        }
        
        // Kiểm tra đội có trong trận đấu nào không
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as match_count 
            FROM Matches 
            WHERE team1_id = ? OR team2_id = ?
        ");
        $checkStmt->execute([$teamId, $teamId]);
        $result = $checkStmt->fetch();
        
        if ($result['match_count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Không thể xóa đội đã có trong trận đấu'
            ]);
            exit;
        }
        
        // Xóa đội
        $deleteStmt = $pdo->prepare("DELETE FROM Teams WHERE id = ?");
        $deleteStmt->execute([$teamId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Đội đã được xóa thành công'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi database: ' . $e->getMessage()
    ]);
}
?>