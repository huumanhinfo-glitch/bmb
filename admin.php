<?php
// admin.php - Trang quản trị hệ thống (Phiên bản tối ưu)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// FIX: Thêm session_start()


// FIX: Include các file cần thiết
require_once 'db.php';
require_once 'functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Chuyển hướng đến trang login
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

// Lấy thông tin user từ database để đảm bảo role chính xác
$stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Sử dụng role từ database (chính xác hơn từ session)
$user_role = $current_user['role'];
$_SESSION['role'] = $user_role;

// Kiểm tra quyền: chỉ admin và manager được phép truy cập
if ($user_role !== 'admin' && $user_role !== 'manager') {
    echo "<h2>Access Denied</h2>";
    echo "<p>Your role: <strong>$user_role</strong></p>";
    echo "<p>Required roles: admin or manager</p>";
    echo "<p>Current user ID: $user_id</p>";
    echo "<p>Username: " . htmlspecialchars($current_user['username']) . "</p>";
    echo "<p><a href='logout.php'>Logout</a> | <a href='index.php'>Home</a></p>";
    exit;
}
// Xử lý các action
$action = $_GET['action'] ?? 'dashboard';
$message = '';
$success = '';

// Xử lý POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý tạo giải đấu
    if (isset($_POST['create_tournament'])) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $format = $_POST['format'] ?? 'combined';
        $location = $_POST['location'] ?? '';
        $status = $_POST['status'] ?? 'upcoming';
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        
        if ($name) {
            $owner_id = ($user_role === 'admin') ? ($_POST['owner_id'] ?? $user_id) : $user_id;
            
            $stmt = $pdo->prepare("
                INSERT INTO Tournaments (name, description, format, location, status, start_date, end_date, owner_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            try {
                $stmt->execute([$name, $description, $format, $location, $status, $startDate, $endDate, $owner_id]);
                $tournament_id = $pdo->lastInsertId();
                
                // Nếu là manager, thêm vào bảng quản lý
                if ($user_role === 'manager') {
                    $stmt = $pdo->prepare("
                        INSERT INTO TournamentManagers (tournament_id, user_id, permission_level) 
                        VALUES (?, ?, 'full')
                    ");
                    $stmt->execute([$tournament_id, $user_id]);
                }
                
                $success = "Tạo giải đấu '$name' thành công!";
            } catch (Exception $e) {
                $message = "Lỗi khi tạo giải đấu: " . $e->getMessage();
            }
        }
    }
    
    // Xử lý tạo user (chỉ admin)
    elseif (isset($_POST['create_user']) && $user_role === 'admin') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $displayName = $_POST['display_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        if ($username && $password) {
            // Kiểm tra username đã tồn tại chưa
            $check = $pdo->prepare("SELECT id FROM Users WHERE username = ?");
            $check->execute([$username]);
            
            if ($check->fetch()) {
                $message = "Username đã tồn tại!";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO Users (username, password, display_name, email, phone, role) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([$username, $passwordHash, $displayName, $email, $phone, $role]);
                    $success = "Tạo người dùng '$username' thành công!";
                } catch (Exception $e) {
                    $message = "Lỗi khi tạo user: " . $e->getMessage();
                }
            }
        }
    }
    
    // Xử lý tạo sân đấu
    elseif (isset($_POST['create_arena'])) {
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $capacity = $_POST['capacity'] ?? null;
        $status = $_POST['status'] ?? 'available';
        $description = $_POST['description'] ?? '';
        
        if ($name) {
            $stmt = $pdo->prepare("
                INSERT INTO Arena (name, location, capacity, status, description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            try {
                $stmt->execute([$name, $location, $capacity, $status, $description]);
                $success = "Tạo sân thi đấu '$name' thành công!";
            } catch (Exception $e) {
                $message = "Lỗi khi tạo sân: " . $e->getMessage();
            }
        }
    }
}






// Xử lý tạo phân công trọng tài
elseif (isset($_POST['assign_referee'])) {
    $match_id = $_POST['match_id'] ?? 0;
    $referee_id = $_POST['referee_id'] ?? 0;
    $assignment_type = $_POST['assignment_type'] ?? 'main';
    $status = $_POST['status'] ?? 'assigned';
    
    if ($match_id && $referee_id) {
        // Kiểm tra trọng tài có tồn tại không
        $checkReferee = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
        $checkReferee->execute([$referee_id]);
        $referee = $checkReferee->fetch();
        
        if (!$referee || $referee['role'] !== 'referee') {
            $message = "Người này không phải trọng tài!";
        } else {
            // Kiểm tra đã phân công chưa
            $check = $pdo->prepare("SELECT id FROM RefereeAssignments WHERE match_id = ? AND referee_id = ?");
            $check->execute([$match_id, $referee_id]);
            
            if ($check->fetch()) {
                $message = "Trọng tài đã được phân công cho trận đấu này!";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO RefereeAssignments (match_id, referee_id, assignment_type, status) 
                    VALUES (?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([$match_id, $referee_id, $assignment_type, $status]);
                    $success = "Phân công trọng tài thành công!";
                } catch (Exception $e) {
                    $message = "Lỗi khi phân công trọng tài: " . $e->getMessage();
                }
            }
        }
    } else {
        $message = "Vui lòng chọn trận đấu và trọng tài!";
    }
}

// Xử lý cập nhật phân công trọng tài
elseif (isset($_POST['update_referee_assignment'])) {
    $id = $_POST['id'] ?? 0;
    $assignment_type = $_POST['assignment_type'] ?? 'main';
    $status = $_POST['status'] ?? 'assigned';
    
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE RefereeAssignments 
            SET assignment_type = ?, status = ? 
            WHERE id = ?
        ");
        
        try {
            $stmt->execute([$assignment_type, $status, $id]);
            $success = "Cập nhật phân công trọng tài thành công!";
        } catch (Exception $e) {
            $message = "Lỗi khi cập nhật phân công trọng tài: " . $e->getMessage();
        }
    }
}




// Tạo các bảng mới
function createNewTables() {
    global $pdo;
    
    try {
        // 1. Tạo bảng Arena nếu chưa có
        $pdo->exec("CREATE TABLE IF NOT EXISTS Arena (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(200),
            capacity INT,
            status ENUM('available', 'maintenance', 'unavailable') DEFAULT 'available',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 2. Cập nhật bảng Users để thêm các cột mới
        $columns = $pdo->query("SHOW COLUMNS FROM Users")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('email', $columns)) {
            $pdo->exec("ALTER TABLE Users ADD COLUMN email VARCHAR(100) AFTER display_name");
        }
        if (!in_array('phone', $columns)) {
            $pdo->exec("ALTER TABLE Users ADD COLUMN phone VARCHAR(20) AFTER email");
        }
        if (!in_array('tournament_permissions', $columns)) {
            $pdo->exec("ALTER TABLE Users ADD COLUMN tournament_permissions TEXT AFTER role");
        }
        if (!in_array('is_active', $columns)) {
            $pdo->exec("ALTER TABLE Users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER tournament_permissions");
        }
        
        // Cập nhật role enum nếu cần
        $pdo->exec("ALTER TABLE Users MODIFY COLUMN role ENUM('admin', 'manager', 'referee', 'user') DEFAULT 'user'");
        
        // 3. Cập nhật bảng Tournaments để thêm owner_id
        $columns = $pdo->query("SHOW COLUMNS FROM Tournaments")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('owner_id', $columns)) {
            $pdo->exec("ALTER TABLE Tournaments ADD COLUMN owner_id INT AFTER status");
        }
        
        // 4. Tạo bảng TournamentManagers
        $pdo->exec("CREATE TABLE IF NOT EXISTS TournamentManagers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT NOT NULL,
            user_id INT NOT NULL,
            permission_level ENUM('full', 'limited') DEFAULT 'full',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_manager_tournament (tournament_id, user_id),
            FOREIGN KEY (tournament_id) REFERENCES Tournaments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
        )");
        
        // 5. Cập nhật bảng Matches để thêm arena_id
        $columns = $pdo->query("SHOW COLUMNS FROM Matches")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('arena_id', $columns)) {
            $pdo->exec("ALTER TABLE Matches ADD COLUMN arena_id INT DEFAULT NULL AFTER court");
        }
        
        // 6. Tạo bảng RefereeAssignments
        $pdo->exec("CREATE TABLE IF NOT EXISTS RefereeAssignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            referee_id INT NOT NULL,
            assignment_type ENUM('main', 'assistant') DEFAULT 'main',
            status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            UNIQUE KEY unique_match_referee (match_id, referee_id),
            FOREIGN KEY (match_id) REFERENCES Matches(id) ON DELETE CASCADE,
            FOREIGN KEY (referee_id) REFERENCES Users(id) ON DELETE CASCADE
        )");
        
        // Thêm dữ liệu mẫu cho Arena nếu chưa có
        $check = $pdo->query("SELECT COUNT(*) FROM Arena")->fetchColumn();
        if ($check == 0) {
            $pdo->exec("
                INSERT INTO Arena (name, location, capacity, status, description) VALUES
                ('Sân chính Diamon', 'Diamon Pickleball Arena', 100, 'available', 'Sân chính thi đấu'),
                ('Sân phụ 1', 'Diamon Pickleball Arena', 50, 'available', 'Sân phụ tập luyện'),
                ('Sân phụ 2', 'Diamon Pickleball Arena', 50, 'available', 'Sân phụ thi đấu')
            ");
        }
        
    } catch (Exception $e) {
        // Không hiển thị lỗi nếu bảng đã tồn tại
    }
}

// Lấy dữ liệu cho dashboard
$totalUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
$totalTournaments = $pdo->query("SELECT COUNT(*) FROM Tournaments")->fetchColumn();
$totalTeams = $pdo->query("SELECT COUNT(*) FROM Teams")->fetchColumn();
$totalMatches = $pdo->query("SELECT COUNT(*) FROM Matches")->fetchColumn();
$totalArenas = $pdo->query("SELECT COUNT(*) FROM Arena")->fetchColumn();

// Lấy dữ liệu theo quyền
if ($user_role === 'admin') {
    $users = $pdo->query("SELECT * FROM Users ORDER BY role DESC, username")->fetchAll();
    $tournaments = $pdo->query("SELECT t.*, u.username as owner_name FROM Tournaments t LEFT JOIN Users u ON t.owner_id = u.id ORDER BY t.created_at DESC")->fetchAll();
} else {
    $users = $pdo->query("SELECT * FROM Users WHERE role IN ('manager', 'referee', 'user') ORDER BY role DESC, username")->fetchAll();
    $stmt = $pdo->prepare("
    SELECT DISTINCT t.*, u.username as owner_name 
    FROM Tournaments t 
    LEFT JOIN Users u ON t.owner_id = u.id
    LEFT JOIN TournamentManagers tm ON t.id = tm.tournament_id
    WHERE t.owner_id = ? OR tm.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$tournaments = $stmt->fetchAll();
}

$teams = $pdo->query("SELECT t.*, tr.name as tournament_name FROM Teams t LEFT JOIN Tournaments tr ON t.tournament_id = tr.id ORDER BY t.created_at DESC")->fetchAll();
$matches = $pdo->query("SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name, g.group_name FROM Matches m LEFT JOIN Teams t1 ON m.team1_id = t1.id LEFT JOIN Teams t2 ON m.team2_id = t2.id LEFT JOIN Groups g ON m.group_id = g.id ORDER BY m.created_at DESC")->fetchAll();
$arenas = $pdo->query("SELECT * FROM Arena ORDER BY status, name")->fetchAll();

// Lấy bản ghi cụ thể cho edit
$editData = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    if ($action === 'users') {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    } elseif ($action === 'tournaments') {
        $stmt = $pdo->prepare("SELECT * FROM Tournaments WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    } elseif ($action === 'arenas') {
        $stmt = $pdo->prepare("SELECT * FROM Arena WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2ecc71;
            --accent: #ff6b00;
            --text-dark: #1e293b;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --manager: #9b59b6;
            --referee: #3498db;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .logo {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--accent);
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .user-info {
            padding: 15px 0;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin: 0 auto 10px;
        }
        
        .user-name {
            font-weight: 600;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .user-role {
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .nav-link-admin {
            display: block;
            padding: 12px 15px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            border-left: 4px solid transparent;
            transition: all 0.3s;
            margin-bottom: 5px;
            border-radius: 8px;
        }
        
        .nav-link-admin:hover, .nav-link-admin.active {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .nav-link-admin i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .admin-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-bottom: 4px solid var(--primary);
        }
        
        .admin-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .admin-subtitle {
            color: #64748b;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-top: 4px solid var(--primary);
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .stats-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn-admin {
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-admin-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-admin-primary:hover {
            background: #27ae60;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-admin-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-admin-danger:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-admin-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-admin-warning:hover {
            background: #e0a800;
            color: #212529;
            transform: translateY(-2px);
        }
        
        .btn-admin-manager {
            background: var(--manager);
            color: white;
        }
        
        .btn-admin-manager:hover {
            background: #8e44ad;
            color: white;
            transform: translateY(-2px);
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .form-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }
        
        .alert-admin {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--primary);
            color: #155724;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger);
            color: #721c24;
        }
        
        .badge-admin {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-admin-primary {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
        }
        
        .badge-admin-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .badge-admin-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        .badge-admin-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .badge-admin-manager {
            background: rgba(155, 89, 182, 0.1);
            color: var(--manager);
        }
        
        .badge-admin-referee {
            background: rgba(52, 152, 219, 0.1);
            color: var(--referee);
        }
        
        @media (max-width: 768px) {
            .stats-number {
                font-size: 1.5rem;
            }
            
            .nav-link-admin span {
                display: none;
            }
            
            .nav-link-admin i {
                margin-right: 0;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 col-md-4">
                <div class="sidebar">
                    <div class="logo">
                        <i class="fas fa-user-shield"></i>
                        <span>TRỌNG TÀI SỐ ADMIN</span>
                    </div>
                    
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($current_user['display_name'] ?? $current_user['username']); ?></div>
                        <div class="user-role">
                            <span class="badge-admin 
                                <?php 
                                if ($user_role === 'admin') echo 'badge-admin-danger';
                                elseif ($user_role === 'manager') echo 'badge-admin-manager';
                                elseif ($user_role === 'referee') echo 'badge-admin-referee';
                                else echo 'badge-admin-info';
                                ?>
                            ">
                                <?php echo ucfirst($user_role); ?>
                            </span>
                        </div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a href="?action=dashboard" class="nav-link-admin <?php echo $action === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <a href="?action=users" class="nav-link-admin <?php echo $action === 'users' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Quản lý User</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="?action=tournaments" class="nav-link-admin <?php echo $action === 'tournaments' ? 'active' : ''; ?>">
                            <i class="fas fa-trophy"></i>
                            <span>Quản lý Giải đấu</span>
                        </a>
                        
                        <a href="?action=teams" class="nav-link-admin <?php echo $action === 'teams' ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i>
                            <span>Quản lý Đội</span>
                        </a>
                        
                        <a href="?action=groups" class="nav-link-admin <?php echo $action === 'groups' ? 'active' : ''; ?>">
                            <i class="fas fa-layer-group"></i>
                            <span>Quản lý Bảng đấu</span>
                        </a>
                        
                        <a href="?action=matches" class="nav-link-admin <?php echo $action === 'matches' ? 'active' : ''; ?>">
                            <i class="fas fa-basketball-ball"></i>
                            <span>Quản lý Trận đấu</span>
                        </a>
                        
                        <a href="?action=arenas" class="nav-link-admin <?php echo $action === 'arenas' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Quản lý Sân đấu</span>
                        </a>
                        
                        <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
                        <a href="?action=referee_assignments" class="nav-link-admin <?php echo $action === 'referee_assignments' ? 'active' : ''; ?>">
                            <i class="fas fa-whistle"></i>
                            <span>Phân công Trọng tài</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <a href="?action=settings" class="nav-link-admin <?php echo $action === 'settings' ? 'active' : ''; ?>">
                            <i class="fas fa-cogs"></i>
                            <span>Cài đặt hệ thống</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="index.php" class="nav-link-admin">
                            <i class="fas fa-home"></i>
                            <span>Về trang chủ</span>
                        </a>
                        
                        <a href="logout.php" class="nav-link-admin">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9 col-md-8">
                <!-- Header -->
                <div class="admin-header">
                    <h1 class="admin-title">
                        <i class="fas fa-user-shield me-2"></i>
                        <?php 
                        $titles = [
                            'dashboard' => 'Dashboard - Tổng quan hệ thống',
                            'users' => 'Quản lý Người dùng',
                            'tournaments' => 'Quản lý Giải đấu',
                            'teams' => 'Quản lý Đội',
                            'groups' => 'Quản lý Bảng đấu',
                            'matches' => 'Quản lý Trận đấu',
                            'arenas' => 'Quản lý Sân thi đấu',
                            'referee_assignments' => 'Phân công Trọng tài',
                            'settings' => 'Cài đặt hệ thống'
                        ];
                        echo $titles[$action] ?? 'Quản trị hệ thống';
                        ?>
                    </h1>
                    <p class="admin-subtitle">
                        <i class="fas fa-user me-1"></i>
                        Đăng nhập với quyền: 
                        <strong>
                            <?php 
                            if ($user_role === 'admin') echo 'Administrator';
                            elseif ($user_role === 'manager') echo 'Quản lý giải đấu';
                            elseif ($user_role === 'referee') echo 'Trọng tài';
                            else echo 'Người dùng';
                            ?>
                        </strong>
                    </p>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                <div class="alert-admin alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                <div class="alert-admin alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Dashboard -->
                <?php if ($action === 'dashboard'): ?>
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-number"><?php echo $totalUsers; ?></div>
                            <div class="stats-label">Người dùng</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stats-number"><?php echo $totalTournaments; ?></div>
                            <div class="stats-label">Giải đấu</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="stats-number"><?php echo $totalTeams; ?></div>
                            <div class="stats-label">Đội tham gia</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-basketball-ball"></i>
                            </div>
                            <div class="stats-number"><?php echo $totalMatches; ?></div>
                            <div class="stats-label">Trận đấu</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="stats-number"><?php echo $totalArenas; ?></div>
                            <div class="stats-label">Sân thi đấu</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-bolt me-2"></i>Hành động nhanh
                    </h3>
                    <div class="row">
                        <?php if ($user_role === 'admin'): ?>
                        <div class="col-md-4 mb-3">
                            <a href="?action=users" class="btn btn-admin btn-admin-primary w-100 py-3">
                                <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-3">
                            <a href="?action=tournaments" class="btn btn-admin btn-admin-primary w-100 py-3">
                                <i class="fas fa-plus-circle me-2"></i>Tạo giải đấu mới
                            </a>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <a href="?action=arenas" class="btn btn-admin btn-admin-manager w-100 py-3">
                                <i class="fas fa-map-marker-alt me-2"></i>Thêm sân thi đấu
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-container">
                            <h3 class="table-title">
                                <i class="fas fa-history me-2"></i>Giải đấu của bạn (<?php echo count($tournaments); ?>)
                            </h3>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                                                        <thead>
                                        <tr>
                                            <th>Tên giải</th>
                                            <th>Trạng thái</th>
                                            <th>Chủ sở hữu</th>
                                            <th>Ngày tạo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($tournaments, 0, 5) as $tournament): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tournament['name']); ?></td>
                                            <td>
                                                <?php 
                                                $statusBadges = [
                                                    'upcoming' => 'badge-admin badge-admin-info',
                                                    'ongoing' => 'badge-admin badge-admin-primary',
                                                    'completed' => 'badge-admin badge-admin-success',
                                                    'cancelled' => 'badge-admin badge-admin-danger'
                                                ];
                                                $statusText = [
                                                    'upcoming' => 'Sắp diễn ra',
                                                    'ongoing' => 'Đang diễn ra',
                                                    'completed' => 'Đã kết thúc',
                                                    'cancelled' => 'Đã hủy'
                                                ];
                                                ?>
                                                <span class="<?php echo $statusBadges[$tournament['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                                    <?php echo $statusText[$tournament['status']] ?? 'Unknown'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($tournament['owner_name'] ?? 'N/A'); ?>
                                                <?php if ($tournament['owner_id'] == $user_id): ?>
                                                <span class="badge-admin badge-admin-primary">Bạn</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($tournament['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="table-container">
                            <h3 class="table-title">
                                <i class="fas fa-user-friends me-2"></i>Người dùng gần đây
                            </h3>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Tên hiển thị</th>
                                            <th>Vai trò</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($users, 0, 5) as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                            <td>
                                                <?php 
                                                $roleBadges = [
                                                    'admin' => 'badge-admin-danger',
                                                    'manager' => 'badge-admin-manager',
                                                    'referee' => 'badge-admin-referee',
                                                    'user' => 'badge-admin-info'
                                                ];
                                                $roleText = [
                                                    'admin' => 'Admin',
                                                    'manager' => 'Quản lý',
                                                    'referee' => 'Trọng tài',
                                                    'user' => 'User'
                                                ];
                                                ?>
                                                <span class="badge-admin <?php echo $roleBadges[$user['role']] ?? 'badge-admin-info'; ?>">
                                                    <?php echo $roleText[$user['role']] ?? 'User'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
        <?php endif; ?>

        <!-- Users Management (Admin only) -->
        <?php if ($action === 'users' && $user_role === 'admin'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-user-plus me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa User' : 'Tạo User mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['username']) : ''; ?>" 
                                   <?php echo $editData ? 'readonly' : 'required'; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo $editData ? 'Mật khẩu mới (để trống nếu không đổi)' : 'Mật khẩu *'; ?></label>
                            <input type="password" class="form-control" name="password" <?php echo !$editData ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên hiển thị</label>
                            <input type="text" class="form-control" name="display_name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['display_name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['phone']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role">
                                <option value="user" <?php echo ($editData && $editData['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                <option value="manager" <?php echo ($editData && $editData['role'] === 'manager') ? 'selected' : ''; ?>>Quản lý</option>
                                <option value="referee" <?php echo ($editData && $editData['role'] === 'referee') ? 'selected' : ''; ?>>Trọng tài</option>
                                <option value="admin" <?php echo ($editData && $editData['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_user" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-save me-2"></i>Cập nhật User
                            </button>
                            <a href="?action=users" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_user" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Tạo User
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách User (<?php echo count($users); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Tên hiển thị</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                    <td>
                                        <?php 
                                        $roleBadges = [
                                            'admin' => 'badge-admin-danger',
                                            'manager' => 'badge-admin-manager',
                                            'referee' => 'badge-admin-referee',
                                            'user' => 'badge-admin-info'
                                        ];
                                        $roleText = [
                                            'admin' => 'Admin',
                                            'manager' => 'Quản lý',
                                            'referee' => 'Trọng tài',
                                            'user' => 'User'
                                        ];
                                        ?>
                                        <span class="badge-admin <?php echo $roleBadges[$user['role']] ?? 'badge-admin-info'; ?>">
                                            <?php echo $roleText[$user['role']] ?? 'User'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="?action=users&edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('user', <?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tournaments Management -->
        <?php if ($action === 'tournaments'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-trophy me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa Giải đấu' : 'Tạo Giải đấu mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên giải đấu *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thể thức</label>
                                <select class="form-select" name="format" required>
                                    <option value="round_robin" <?php echo ($editData && $editData['format'] === 'round_robin') ? 'selected' : ''; ?>>Vòng tròn</option>
                                    <option value="knockout" <?php echo ($editData && $editData['format'] === 'knockout') ? 'selected' : ''; ?>>Loại trực tiếp</option>
                                    <option value="combined" <?php echo ($editData && $editData['format'] === 'combined') ? 'selected' : ''; ?>>Kết hợp</option>
                                    <option value="double_elimination" <?php echo ($editData && $editData['format'] === 'double_elimination') ? 'selected' : ''; ?>>Loại kép</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status" required>
                                    <option value="upcoming" <?php echo ($editData && $editData['status'] === 'upcoming') ? 'selected' : ''; ?>>Sắp diễn ra</option>
                                    <option value="ongoing" <?php echo ($editData && $editData['status'] === 'ongoing') ? 'selected' : ''; ?>>Đang diễn ra</option>
                                    <option value="completed" <?php echo ($editData && $editData['status'] === 'completed') ? 'selected' : ''; ?>>Đã kết thúc</option>
                                    <option value="cancelled" <?php echo ($editData && $editData['status'] === 'cancelled') ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày bắt đầu</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo $editData ? $editData['start_date'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày kết thúc</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo $editData ? $editData['end_date'] : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa điểm</label>
                            <input type="text" class="form-control" name="location" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['location']) : ''; ?>">
                        </div>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <div class="mb-3">
                            <label class="form-label">Chủ sở hữu (Owner)</label>
                            <select class="form-select" name="owner_id">
                                <option value="">-- Chọn chủ sở hữu --</option>
                                <?php 
                                $owners = $pdo->query("SELECT id, username, display_name FROM Users WHERE role IN ('admin', 'manager') ORDER BY username")->fetchAll();
                                foreach ($owners as $owner): 
                                ?>
                                <option value="<?php echo $owner['id']; ?>" <?php echo ($editData && $editData['owner_id'] == $owner['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($owner['display_name'] ? $owner['display_name'] . ' (' . $owner['username'] . ')' : $owner['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_tournament" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-save me-2"></i>Cập nhật giải
                            </button>
                            <a href="?action=tournaments" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_tournament" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Tạo giải đấu
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách Giải đấu (<?php echo count($tournaments); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên giải</th>
                                    <th>Thể thức</th>
                                    <th>Trạng thái</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Số đội</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tournaments as $tournament): 
                                    // Đếm số đội trong giải đấu
                                    $teamCountStmt = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id = ?");
                                    $teamCountStmt->execute([$tournament['id']]);
                                    $teamCount = $teamCountStmt->fetchColumn();
                                    
                                    // Kiểm tra quyền quản lý
                                    $can_manage = false;
                                    if ($user_role === 'admin') {
                                        $can_manage = true;
                                    } elseif ($tournament['owner_id'] == $user_id) {
                                        $can_manage = true;
                                    } else {
                                        // Kiểm tra trong TournamentManagers
                                        $checkManager = $pdo->prepare("SELECT COUNT(*) FROM TournamentManagers WHERE tournament_id = ? AND user_id = ?");
                                        $checkManager->execute([$tournament['id'], $user_id]);
                                        $can_manage = $checkManager->fetchColumn() > 0;
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $tournament['id']; ?></td>
                                    <td>
                                        <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Kết hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'upcoming' => 'badge-admin badge-admin-info',
                                            'ongoing' => 'badge-admin badge-admin-primary',
                                            'completed' => 'badge-admin badge-admin-success',
                                            'cancelled' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'upcoming' => 'Sắp diễn ra',
                                            'ongoing' => 'Đang diễn ra',
                                            'completed' => 'Đã kết thúc',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$tournament['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$tournament['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($tournament['owner_name'] ?? 'N/A'); ?>
                                        <?php if ($tournament['owner_id'] == $user_id): ?>
                                        <span class="badge-admin badge-admin-primary">Bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $teamCount; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($tournament['created_at'])); ?></td>
                                    <td>
                                        <?php if ($can_manage): ?>
                                        <a href="?action=tournaments&edit_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('tournament', <?php echo $tournament['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted">Không có quyền</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teams Management -->
        <?php if ($action === 'teams'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-users-cog me-2"></i>Quản lý Đội (<?php echo count($teams); ?>)
            </h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đội</th>
                            <th>VĐV 1</th>
                            <th>VĐV 2</th>
                            <th>Giải đấu</th>
                            <th>Trình độ</th>
                            <th>Bảng</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teams as $team): 
                            // Kiểm tra quyền
                            $can_manage = false;
                            if ($user_role === 'admin') {
                                $can_manage = true;
                            } elseif ($team['tournament_id']) {
                                // Kiểm tra xem có phải chủ sở hữu hoặc manager không
                                $checkOwner = $pdo->prepare("SELECT owner_id FROM Tournaments WHERE id = ?");
                                $checkOwner->execute([$team['tournament_id']]);
                                $tournament = $checkOwner->fetch();
                                
                                if ($tournament) {
                                    if ($tournament['owner_id'] == $user_id) {
                                        $can_manage = true;
                                    } else {
                                        $checkManager = $pdo->prepare("SELECT COUNT(*) FROM TournamentManagers WHERE tournament_id = ? AND user_id = ?");
                                        $checkManager->execute([$team['tournament_id'], $user_id]);
                                        $can_manage = $checkManager->fetchColumn() > 0;
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo $team['id']; ?></td>
                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td><?php echo htmlspecialchars($team['player1']); ?></td>
                            <td><?php echo htmlspecialchars($team['player2']); ?></td>
                            <td><?php echo htmlspecialchars($team['tournament_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if($team['skill_level']): ?>
                                <span class="badge-admin badge-admin-info"><?php echo $team['skill_level']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($team['group_name']): ?>
                                <span class="badge-admin badge-admin-warning"><?php echo $team['group_name']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($team['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="?action=edit_team&id=<?php echo $team['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('team', <?php echo $team['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Groups Management -->
        <?php if ($action === 'groups'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-layer-group me-2"></i>Quản lý Bảng đấu
            </h3>
            
            <?php
            // Lấy danh sách bảng đấu
            $groups = $pdo->query("SELECT g.*, t.name as tournament_name FROM Groups g LEFT JOIN Tournaments t ON g.tournament_id = t.id ORDER BY g.created_at DESC")->fetchAll();
            ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên bảng</th>
                            <th>Giải đấu</th>
                            <th>Số đội</th>
                            <th>Số trận</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($groups as $group): 
                            // Đếm số đội trong bảng
                            $teamCount = $pdo->prepare("SELECT COUNT(DISTINCT t.id) FROM Teams t INNER JOIN Matches m ON (t.id = m.team1_id OR t.id = m.team2_id) WHERE m.group_id = ?");
                            $teamCount->execute([$group['id']]);
                            $teamCount = $teamCount->fetchColumn();
                            
                            // Đếm số trận trong bảng
                            $matchCount = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE group_id = ?");
                            $matchCount->execute([$group['id']]);
                            $matchCount = $matchCount->fetchColumn();
                            
                            // Kiểm tra quyền
                            $can_manage = false;
                            if ($user_role === 'admin') {
                                $can_manage = true;
                            } elseif ($group['tournament_id']) {
                                $checkOwner = $pdo->prepare("SELECT owner_id FROM Tournaments WHERE id = ?");
                                $checkOwner->execute([$group['tournament_id']]);
                                $tournament = $checkOwner->fetch();
                                
                                if ($tournament) {
                                    if ($tournament['owner_id'] == $user_id) {
                                        $can_manage = true;
                                    } else {
                                        $checkManager = $pdo->prepare("SELECT COUNT(*) FROM TournamentManagers WHERE tournament_id = ? AND user_id = ?");
                                        $checkManager->execute([$group['tournament_id'], $user_id]);
                                        $can_manage = $checkManager->fetchColumn() > 0;
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo $group['id']; ?></td>
                            <td><strong>Bảng <?php echo $group['group_name']; ?></strong></td>
                            <td><?php echo htmlspecialchars($group['tournament_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $teamCount; ?></td>
                            <td><?php echo $matchCount; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($group['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="matches.php?group=<?php echo $group['id']; ?>" class="btn btn-sm btn-admin btn-admin-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('group', <?php echo $group['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Matches Management -->
        <?php if ($action === 'matches'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-basketball-ball me-2"></i>Quản lý Trận đấu (<?php echo count($matches); ?>)
            </h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Trận đấu</th>
                            <th>Kết quả</th>
                            <th>Bảng/Vòng</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($matches as $match): 
                            // Kiểm tra quyền
                            $can_manage = false;
                            if ($user_role === 'admin') {
                                $can_manage = true;
                            } elseif ($match['tournament_id']) {
                                $checkOwner = $pdo->prepare("SELECT owner_id FROM Tournaments WHERE id = ?");
                                $checkOwner->execute([$match['tournament_id']]);
                                $tournament = $checkOwner->fetch();
                                
                                if ($tournament) {
                                    if ($tournament['owner_id'] == $user_id) {
                                        $can_manage = true;
                                    } else {
                                        $checkManager = $pdo->prepare("SELECT COUNT(*) FROM TournamentManagers WHERE tournament_id = ? AND user_id = ?");
                                        $checkManager->execute([$match['tournament_id'], $user_id]);
                                        $can_manage = $checkManager->fetchColumn() > 0;
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo $match['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($match['team1_name']); ?> 
                                <strong>vs</strong> 
                                <?php echo htmlspecialchars($match['team2_name']); ?>
                            </td>
                            <td>
                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                <span class="badge-admin badge-admin-primary">
                                    <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                </span>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Chưa có kết quả</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($match['group_name']): ?>
                                <span class="badge-admin badge-admin-info">Bảng <?php echo $match['group_name']; ?></span>
                                <br>
                                <small><?php echo $match['round']; ?></small>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Loại trực tiếp</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                <span class="badge-admin badge-admin-success">Đã hoàn thành</span>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Chưa diễn ra</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($match['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="matches.php?match=<?php echo $match['id']; ?>" class="btn btn-sm btn-admin btn-admin-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('match', <?php echo $match['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Arena Management -->
        <?php if ($action === 'arenas'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa Sân đấu' : 'Thêm Sân đấu mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên sân *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa điểm</label>
                            <input type="text" class="form-control" name="location" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['location']) : ''; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sức chứa</label>
                                <input type="number" class="form-control" name="capacity" 
                                       value="<?php echo $editData ? $editData['capacity'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status" required>
                                    <option value="available" <?php echo ($editData && $editData['status'] === 'available') ? 'selected' : ''; ?>>Có sẵn</option>
                                    <option value="maintenance" <?php echo ($editData && $editData['status'] === 'maintenance') ? 'selected' : ''; ?>>Bảo trì</option>
                                    <option value="unavailable" <?php echo ($editData && $editData['status'] === 'unavailable') ? 'selected' : ''; ?>>Không có sẵn</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_arena" class="btn btn-admin btn-admin-manager">
                                <i class="fas fa-save me-2"></i>Cập nhật sân
                            </button>
                            <a href="?action=arenas" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_arena" class="btn btn-admin btn-admin-manager">
                                <i class="fas fa-plus me-2"></i>Thêm sân đấu
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách Sân thi đấu (<?php echo count($arenas); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên sân</th>
                                    <th>Địa điểm</th>
                                    <th>Sức chứa</th>
                                    <th>Trạng thái</th>
                                    <th>Số trận đấu</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($arenas as $arena): 
                                    // Đếm số trận đấu sử dụng sân này
                                    $matchCount = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE arena_id = ?");
                                    $matchCount->execute([$arena['id']]);
                                    $matchCount = $matchCount->fetchColumn();
                                ?>
                                <tr>
                                    <td><?php echo $arena['id']; ?></td>
                                    <td><?php echo htmlspecialchars($arena['name']); ?></td>
                                    <td><?php echo htmlspecialchars($arena['location']); ?></td>
                                    <td><?php echo $arena['capacity'] ?: 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'available' => 'badge-admin badge-admin-primary',
                                            'maintenance' => 'badge-admin badge-admin-warning',
                                            'unavailable' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'available' => 'Có sẵn',
                                            'maintenance' => 'Bảo trì',
                                            'unavailable' => 'Không có sẵn'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$arena['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$arena['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $matchCount; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($arena['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="?action=arenas&edit_id=<?php echo $arena['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('arena', <?php echo $arena['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Referee Assignments Management -->
        <?php if ($action === 'referee_assignments' && ($user_role === 'admin' || $user_role === 'manager')): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-whistle me-2"></i>Quản lý Phân công Trọng tài
            </h3>
            
            <?php
            // Lấy danh sách phân công trọng tài
            try {
                $referee_assignments = $pdo->query("
                    SELECT ra.*, m.id as match_id, 
                           t1.team_name as team1_name, t2.team_name as team2_name,
                           u.username as referee_username, u.display_name as referee_name
                    FROM RefereeAssignments ra
                    JOIN Matches m ON ra.match_id = m.id
                    LEFT JOIN Teams t1 ON m.team1_id = t1.id
                    LEFT JOIN Teams t2 ON m.team2_id = t2.id
                    JOIN Users u ON ra.referee_id = u.id
                    ORDER BY ra.assigned_at DESC
                ")->fetchAll();
            } catch (Exception $e) {
                $referee_assignments = [];
                echo "<div class='alert alert-warning'>Chưa có dữ liệu phân công trọng tài.</div>";
            }
            ?>
            
            <?php if (!empty($referee_assignments)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Trận đấu</th>
                            <th>Trọng tài</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Ngày phân công</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($referee_assignments as $assignment): ?>
                        <tr>
                            <td><?php echo $assignment['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($assignment['team1_name']); ?> 
                                <strong>vs</strong> 
                                <?php echo htmlspecialchars($assignment['team2_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($assignment['referee_name']); ?>
                                <br>
                                <small><?php echo htmlspecialchars($assignment['referee_username']); ?></small>
                            </td>
                            <td>
                                <span class="badge-admin <?php echo $assignment['assignment_type'] === 'main' ? 'badge-admin-primary' : 'badge-admin-info'; ?>">
                                    <?php echo $assignment['assignment_type'] === 'main' ? 'Chính' : 'Phụ'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $statusBadges = [
                                    'assigned' => 'badge-admin badge-admin-warning',
                                    'completed' => 'badge-admin badge-admin-success',
                                    'cancelled' => 'badge-admin badge-admin-danger'
                                ];
                                $statusText = [
                                    'assigned' => 'Đã phân công',
                                    'completed' => 'Đã hoàn thành',
                                    'cancelled' => 'Đã hủy'
                                ];
                                ?>
                                <span class="<?php echo $statusBadges[$assignment['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                    <?php echo $statusText[$assignment['status']] ?? 'Unknown'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-admin btn-admin-warning" onclick="editRefereeAssignment(<?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-admin btn-admin-danger" onclick="confirmDelete('referee_assignment', <?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Form thêm phân công trọng tài -->
            <div class="form-container mt-4">
                <h4 class="form-title">
                    <i class="fas fa-user-plus me-2"></i>Phân công Trọng tài mới
                </h4>
                
                <form id="assignRefereeForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chọn Trận đấu *</label>
                            <select class="form-select" name="match_id" required>
                                <option value="">-- Chọn trận đấu --</option>
                                <?php 
                                $matches_list = $pdo->query("
                                    SELECT m.id, t1.team_name as team1, t2.team_name as team2, t.name as tournament_name
                                    FROM Matches m
                                    LEFT JOIN Teams t1 ON m.team1_id = t1.id
                                    LEFT JOIN Teams t2 ON m.team2_id = t2.id
                                    LEFT JOIN Tournaments t ON m.tournament_id = t.id
                                    ORDER BY m.match_date DESC
                                ")->fetchAll();
                                foreach ($matches_list as $match): 
                                ?>
                                <option value="<?php echo $match['id']; ?>">
                                    <?php echo htmlspecialchars(($match['tournament_name'] ?? 'Giải') . ': ' . $match['team1'] . ' vs ' . $match['team2']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chọn Trọng tài *</label>
                            <select class="form-select" name="referee_id" required>
                                <option value="">-- Chọn trọng tài --</option>
                                <?php 
                                $referees = $pdo->query("SELECT id, username, display_name FROM Users WHERE role = 'referee' ORDER BY username")->fetchAll();
                                foreach ($referees as $referee): 
                                ?>
                                <option value="<?php echo $referee['id']; ?>">
                                    <?php echo htmlspecialchars($referee['display_name'] ? $referee['display_name'] . ' (' . $referee['username'] . ')' : $referee['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="assignment_type" required>
                                <option value="main">Trọng tài chính</option>
                                <option value="assistant">Trọng tài phụ</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" required>
                                <option value="assigned">Đã phân công</option>
                                <option value="completed">Đã hoàn thành</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-admin btn-admin-referee" onclick="assignReferee()">
                    <i class="fas fa-user-plus me-2"></i>Phân công Trọng tài
                </button>
            </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Settings (Admin only) -->
        <?php if ($action === 'settings' && $user_role === 'admin'): ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-database me-2"></i>Quản lý dữ liệu
                    </h3>
                    
                    <div class="alert-admin alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Các thao tác này sẽ xóa dữ liệu vĩnh viễn!
                    </div>
                    
                    <div class="mb-4">
                        <h5><i class="fas fa-trash me-2 text-danger"></i>Xóa dữ liệu</h5>
                        <div class="d-grid gap-2 mb-3">
                            <button type="button" class="btn btn-admin btn-admin-danger" onclick="confirmClearData('teams')">
                                <i class="fas fa-users-slash me-2"></i>Xóa tất cả đội
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-danger" onclick="confirmClearData('matches')">
                                <i class="fas fa-basketball-ball me-2"></i>Xóa tất cả trận đấu
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-danger" onclick="confirmClearData('groups')">
                                <i class="fas fa-layer-group me-2"></i>Xóa tất cả bảng đấu
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-danger" onclick="confirmClearData('arenas')">
                                <i class="fas fa-map-marker-alt me-2"></i>Xóa tất cả sân đấu
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-cogs me-2"></i>Cài đặt hệ thống
                    </h3>
                    
                    <div class="mb-4">
                        <h5><i class="fas fa-download me-2"></i>Xuất dữ liệu</h5>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-admin btn-admin-primary" onclick="exportData('users')">
                                <i class="fas fa-file-csv me-2"></i>Xuất danh sách User
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-primary" onclick="exportData('tournaments')">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Giải đấu
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-primary" onclick="exportData('teams')">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Đội
                            </button>
                            <button type="button" class="btn btn-admin btn-admin-primary" onclick="exportData('arenas')">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Sân đấu
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-info-circle me-2"></i>Thông tin hệ thống
            </h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Database:</strong> MySQL
                    </div>
                    <div class="mb-3">
                        <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong>Memory Usage:</strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB
                    </div>
                    <div class="mb-3">
                        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds
                    </div>
                    <div class="mb-3">
                        <strong>Upload Max Filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa bản ghi này?</p>
                <input type="hidden" id="deleteId" value="">
                <input type="hidden" id="deleteType" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" onclick="performDelete()">Xóa</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa phân công trọng tài -->
<div class="modal fade" id="editRefereeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Chỉnh sửa Phân công Trọng tài</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRefereeForm">
                    <input type="hidden" id="editAssignmentId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Vai trò</label>
                        <select class="form-select" id="editAssignmentType" required>
                            <option value="main">Trọng tài chính</option>
                            <option value="assistant">Trọng tài phụ</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" id="editAssignmentStatus" required>
                            <option value="assigned">Đã phân công</option>
                            <option value="completed">Đã hoàn thành</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveRefereeAssignment()">Lưu</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Xác nhận xóa
    function confirmDelete(type, id) {
        document.getElementById('deleteType').value = type;
        document.getElementById('deleteId').value = id;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    // Thực hiện xóa
    function performDelete() {
        const type = document.getElementById('deleteType').value;
        const id = document.getElementById('deleteId').value;
        
        // Gửi request xóa
        const formData = new FormData();
        formData.append('id', id);
        
        let action = '';
        switch(type) {
            case 'user':
                action = 'delete_user';
                break;
            case 'tournament':
                action = 'delete_tournament';
                break;
            case 'team':
                action = 'delete_team';
                break;
            case 'group':
                action = 'delete_group';
                break;
            case 'match':
                action = 'delete_match';
                break;
            case 'arena':
                action = 'delete_arena';
                break;
            case 'referee_assignment':
                action = 'delete_referee_assignment';
                break;
        }
        
        formData.append(action, '1');
        
        fetch('admin.php?action=' + type + 's', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Đóng modal
            const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
            deleteModal.hide();
            
            // Reload trang
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa');
        });
    }
    
    // Xóa dữ liệu hàng loạt
    function confirmClearData(type) {
        Swal.fire({
            title: 'Bạn có chắc chắn?',
            text: `Tất cả dữ liệu ${type} sẽ bị xóa vĩnh viễn!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `clear_data.php?type=${type}`;
            }
        });
    }
    
    // Xuất dữ liệu
    function exportData(type) {
        window.location.href = `export.php?type=${type}`;
    }
    
    // Phân công trọng tài
    function assignReferee() {
        const form = document.getElementById('assignRefereeForm');
        const formData = new FormData(form);
        
        fetch('admin.php?action=referee_assignments&assign=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công',
                    text: 'Đã phân công trọng tài'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: data.message || 'Có lỗi xảy ra'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: 'Có lỗi xảy ra khi phân công'
            });
        });
    }
    
    // Chỉnh sửa phân công trọng tài
    function editRefereeAssignment(id) {
        document.getElementById('editAssignmentId').value = id;
        
        // TODO: Load dữ liệu hiện tại
        // Hiện tại chỉ hiển thị modal, cần bổ sung load dữ liệu
        
        const editModal = new bootstrap.Modal(document.getElementById('editRefereeModal'));
        editModal.show();
    }
    
    // Lưu phân công trọng tài
    function saveRefereeAssignment() {
        const id = document.getElementById('editAssignmentId').value;
        const type = document.getElementById('editAssignmentType').value;
        const status = document.getElementById('editAssignmentStatus').value;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('assignment_type', type);
        formData.append('status', status);
        formData.append('update_referee_assignment', '1');
        
        fetch('admin.php?action=referee_assignments', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công',
                    text: 'Đã cập nhật phân công trọng tài'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: data.message || 'Có lỗi xảy ra'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: 'Có lỗi xảy ra khi cập nhật'
            });
        });
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-admin');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
<script src="admin_ajax.js"></script>
</body>
</html>