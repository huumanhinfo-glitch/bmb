<?php
// admin_ajax.php - Xử lý các request AJAX từ admin
session_start();
require_once 'db.php';
require_once 'functions.php';

// Kiểm tra đăng nhập và quyền
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// Chỉ admin và manager mới được phép
if ($user_role !== 'admin' && $user_role !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

$action = $_GET['action'] ?? '';

// Xử lý các action
switch ($action) {
    case 'assign_referee':
        handleAssignReferee();
        break;
        
    case 'update_referee_assignment':
        handleUpdateRefereeAssignment();
        break;
        
    case 'get_referee_assignment':
        handleGetRefereeAssignment();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}

/**
 * Xử lý phân công trọng tài mới
 */
function handleAssignReferee() {
    global $pdo, $user_id, $user_role;
    
    $match_id = $_POST['match_id'] ?? 0;
    $referee_id = $_POST['referee_id'] ?? 0;
    $assignment_type = $_POST['assignment_type'] ?? 'main';
    $status = $_POST['status'] ?? 'assigned';
    
    if (!$match_id || !$referee_id) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đầy đủ thông tin']);
        return;
    }
    
    try {
        // Kiểm tra trọng tài có tồn tại không
        $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
        $stmt->execute([$referee_id]);
        $referee = $stmt->fetch();
        
        if (!$referee || $referee['role'] !== 'referee') {
            echo json_encode(['success' => false, 'message' => 'Người này không phải trọng tài']);
            return;
        }
        
        // Kiểm tra đã phân công chưa
        $check = $pdo->prepare("SELECT id FROM RefereeAssignments WHERE match_id = ? AND referee_id = ?");
        $check->execute([$match_id, $referee_id]);
        
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Trọng tài đã được phân công cho trận này']);
            return;
        }
        
        // Phân công
        $stmt = $pdo->prepare("
            INSERT INTO RefereeAssignments (match_id, referee_id, assignment_type, status) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$match_id, $referee_id, $assignment_type, $status]);
        
        echo json_encode(['success' => true, 'message' => 'Phân công thành công']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}

/**
 * Xử lý cập nhật phân công trọng tài
 */
function handleUpdateRefereeAssignment() {
    global $pdo;
    
    $id = $_POST['id'] ?? 0;
    $assignment_type = $_POST['assignment_type'] ?? 'main';
    $status = $_POST['status'] ?? 'assigned';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE RefereeAssignments 
            SET assignment_type = ?, status = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$assignment_type, $status, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}

/**
 * Lấy thông tin phân công trọng tài theo ID
 */
function handleGetRefereeAssignment() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID']);
        return;
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
}
?>