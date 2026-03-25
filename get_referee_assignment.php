<?php
// get_referee_assignment.php - Lấy thông tin phân công trọng tài
session_start();
require_once 'db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM RefereeAssignments WHERE id = ?");
    $stmt->execute([$id]);
    $assignment = $stmt->fetch();
    
    if ($assignment) {
        echo json_encode(['success' => true, 'data' => $assignment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy phân công']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>