<?php
// api/save_tab.php
session_start();

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['tab'])) {
        $_SESSION['activeDrawTab'] = $data['tab'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No tab specified']);
    }
}
?>