<?php
// referee.php - Trang quản lý dành cho trọng tài
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kết nối database - từ Environment Variables
require_once __DIR__ . '/config/env.php';
$dbConfig = Env::getDB();

$host = $dbConfig['host'];
$db   = $dbConfig['name'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$port = $dbConfig['port'];
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=" . $dbConfig['charset'];

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Kết nối database thất bại: " . $e->getMessage());
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (!in_array($user_role, ['admin', 'manager', 'referee'])) {
    die("Access denied. You need admin, manager or referee privileges.");
}

// Xử lý các action
$action = $_GET['action'] ?? 'dashboard';
$message = '';
$success = '';

// Xử lý POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cập nhật kết quả trận đấu
    if (isset($_POST['update_match_score'])) {
        $match_id = intval($_POST['match_id']);
        $score1 = intval($_POST['score1']);
        $score2 = intval($_POST['score2']);
        $winner_id = null;
        
        // Xác định đội thắng
        if ($score1 > $score2) {
            $winner_id = $_POST['team1_id'];
        } elseif ($score2 > $score1) {
            $winner_id = $_POST['team2_id'];
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE Matches 
                SET score1 = ?, score2 = ?, winner_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$score1, $score2, $winner_id, $match_id]);
            
            $success = "Cập nhật kết quả thành công!";
        } catch (Exception $e) {
            $message = "Lỗi khi cập nhật kết quả: " . $e->getMessage();
        }
    }
    
    // Cập nhật trạng thái phân công
    elseif (isset($_POST['update_assignment_status'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $status = $_POST['status'] ?? 'assigned';
        
        try {
            $stmt = $pdo->prepare("
                UPDATE RefereeAssignments 
                SET status = ?, completed_at = ?
                WHERE id = ? AND referee_id = ?
            ");
            
            $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$status, $completed_at, $assignment_id, $user_id]);
            
            $success = "Cập nhật trạng thái thành công!";
        } catch (Exception $e) {
            $message = "Lỗi khi cập nhật trạng thái: " . $e->getMessage();
        }
    }
    
    // Cập nhật thông tin trận đấu
    elseif (isset($_POST['update_match_info'])) {
        $match_id = intval($_POST['match_id']);
        $match_date = $_POST['match_date'] ?? null;
        $court = $_POST['court'] ?? '';
        $arena_id = $_POST['arena_id'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        try {
            $stmt = $pdo->prepare("
                UPDATE Matches 
                SET match_date = ?, court = ?, arena_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$match_date, $court, $arena_id, $match_id]);
            
            $success = "Cập nhật thông tin trận đấu thành công!";
        } catch (Exception $e) {
            $message = "Lỗi khi cập nhật thông tin: " . $e->getMessage();
        }
    }
}

// Lấy dữ liệu cho dashboard
// 1. Phân công trọng tài
$assignments = $pdo->prepare("
    SELECT ra.*, 
           m.*,
           t1.team_name as team1_name, t2.team_name as team2_name,
           t1.id as team1_id, t2.id as team2_id,
           g.group_name,
           a.name as arena_name,
           tr.name as tournament_name
    FROM RefereeAssignments ra
    JOIN Matches m ON ra.match_id = m.id
    LEFT JOIN Teams t1 ON m.team1_id = t1.id
    LEFT JOIN Teams t2 ON m.team2_id = t2.id
    LEFT JOIN Groups g ON m.group_id = g.id
    LEFT JOIN Arena a ON m.arena_id = a.id
    LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
    WHERE ra.referee_id = ?
    ORDER BY 
        CASE ra.status 
            WHEN 'assigned' THEN 1
            WHEN 'completed' THEN 2
            WHEN 'cancelled' THEN 3
            ELSE 4
        END,
        m.match_date ASC
");
$assignments->execute([$user_id]);
$assignments = $assignments->fetchAll();

// 2. Thống kê
$stats = [
    'total_assignments' => count($assignments),
    'assigned' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'today_matches' => 0
];

foreach ($assignments as $assignment) {
    $stats[$assignment['status']]++;
    if (date('Y-m-d', strtotime($assignment['match_date'])) == date('Y-m-d')) {
        $stats['today_matches']++;
    }
}

// 3. Lấy danh sách sân đấu
$arenas = $pdo->query("SELECT * FROM Arena WHERE status = 'available' ORDER BY name")->fetchAll();

// Lấy thông tin chi tiết cho edit
$editData = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    if ($action === 'matches') {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   t1.team_name as team1_name, t2.team_name as team2_name,
                   t1.id as team1_id, t2.id as team2_id,
                   g.group_name,
                   a.name as arena_name
            FROM Matches m
            LEFT JOIN Teams t1 ON m.team1_id = t1.id
            LEFT JOIN Teams t2 ON m.team2_id = t2.id
            LEFT JOIN Groups g ON m.group_id = g.id
            LEFT JOIN Arena a ON m.arena_id = a.id
            WHERE m.id = ?
        ");
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
    <title>Trang Trọng tài - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --accent: #2ecc71;
            --text-dark: #1e293b;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #28a745;
            --info: #17a2b8;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Open Sans', sans-serif;
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
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary);
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
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
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
        
        .nav-link-referee {
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
        
        .nav-link-referee:hover, .nav-link-referee.active {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .nav-link-referee i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .referee-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-bottom: 4px solid var(--primary);
        }
        
        .referee-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .referee-subtitle {
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
        
        .btn-referee {
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-referee-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-referee-primary:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-referee-success {
            background: var(--success);
            color: white;
        }
        
        .btn-referee-success:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-referee-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-referee-warning:hover {
            background: #e0a800;
            color: #212529;
            transform: translateY(-2px);
        }
        
        .btn-referee-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-referee-danger:hover {
            background: #c82333;
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
        
        .alert-referee {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success);
            color: #155724;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger);
            color: #721c24;
        }
        
        .badge-referee {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-referee-primary {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary);
        }
        
        .badge-referee-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .badge-referee-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .badge-referee-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .badge-referee-info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .match-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }
        
        .match-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .match-teams {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .team {
            text-align: center;
            flex: 1;
        }
        
        .team-name {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .team-players {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .vs {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 20px;
        }
        
        .match-score {
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .match-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .match-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        @media (max-width: 768px) {
            .stats-number {
                font-size: 1.5rem;
            }
            
            .nav-link-referee span {
                display: none;
            }
            
            .nav-link-referee i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .match-teams {
                flex-direction: column;
            }
            
            .vs {
                margin: 10px 0;
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
                        <i class="fas fa-whistle"></i>
                        <span>TRỌNG TÀI</span>
                    </div>
                    
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($current_user['display_name'] ?? $current_user['username']); ?></div>
                        <div class="user-role">
                            <span class="badge-referee badge-referee-primary">
                                Trọng tài
                            </span>
                        </div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a href="?action=dashboard" class="nav-link-referee <?php echo $action === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        
                        <a href="?action=assignments" class="nav-link-referee <?php echo $action === 'assignments' ? 'active' : ''; ?>">
                            <i class="fas fa-tasks"></i>
                            <span>Phân công của tôi</span>
                            <?php if ($stats['total_assignments'] > 0): ?>
                            <span class="badge-referee badge-referee-primary float-end"><?php echo $stats['total_assignments']; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="?action=today_matches" class="nav-link-referee <?php echo $action === 'today_matches' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day"></i>
                            <span>Trận hôm nay</span>
                            <?php if ($stats['today_matches'] > 0): ?>
                            <span class="badge-referee badge-referee-warning float-end"><?php echo $stats['today_matches']; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="?action=arenas" class="nav-link-referee <?php echo $action === 'arenas' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Sân thi đấu</span>
                        </a>
                        
                        <a href="?action=profile" class="nav-link-referee <?php echo $action === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i>
                            <span>Hồ sơ của tôi</span>
                        </a>
                        
                        <a href="index.php" class="nav-link-referee">
                            <i class="fas fa-home"></i>
                            <span>Về trang chủ</span>
                        </a>
                        
                        <a href="logout.php" class="nav-link-referee">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9 col-md-8">
                <!-- Header -->
                <div class="referee-header">
                    <h1 class="referee-title">
                        <i class="fas fa-whistle me-2"></i>
                        <?php 
                        $titles = [
                            'dashboard' => 'Dashboard - Tổng quan',
                            'assignments' => 'Phân công của tôi',
                            'today_matches' => 'Trận đấu hôm nay',
                            'arenas' => 'Sân thi đấu',
                            'profile' => 'Hồ sơ cá nhân'
                        ];
                        echo $titles[$action] ?? 'Trang Trọng tài';
                        ?>
                    </h1>
                    <p class="referee-subtitle">
                        <i class="fas fa-user me-1"></i>
                        Đăng nhập với quyền: <strong>Trọng tài</strong>
                    </p>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                <div class="alert-referee alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                <div class="alert-referee alert-danger">
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
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['total_assignments']; ?></div>
                            <div class="stats-label">Tổng phân công</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['today_matches']; ?></div>
                            <div class="stats-label">Trận hôm nay</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['completed']; ?></div>
                            <div class="stats-label">Đã hoàn thành</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['assigned']; ?></div>
                            <div class="stats-label">Đang chờ</div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Matches -->
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-forward me-2"></i>Trận đấu sắp tới
                    </h3>
                    
                    <?php if (empty($assignments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bạn chưa có phân công trận đấu nào.
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php 
                        $upcoming = array_filter($assignments, function($a) {
                            return $a['status'] === 'assigned' && 
                                   strtotime($a['match_date']) >= time();
                        });
                        $upcoming = array_slice($upcoming, 0, 3);
                        ?>
                        
                        <?php if (empty($upcoming)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Không có trận đấu sắp tới.
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach($upcoming as $match): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="match-card">
                                    <div class="match-header">
                                        <span class="badge-referee badge-referee-primary">
                                            <?php echo $match['assignment_type'] === 'main' ? 'Trọng tài chính' : 'Trọng tài phụ'; ?>
                                        </span>
                                        <span class="badge-referee badge-referee-warning">
                                            <?php echo date('H:i', strtotime($match['match_date'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="match-teams">
                                        <div class="team">
                                            <div class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                        </div>
                                        <div class="vs">VS</div>
                                        <div class="team">
                                            <div class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="match-info">
                                        <div><i class="fas fa-trophy me-1"></i> <?php echo htmlspecialchars($match['tournament_name']); ?></div>
                                        <div><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['arena_name'] ?? 'Chưa xác định'); ?></div>
                                    </div>
                                    
                                    <div class="match-actions">
                                        <a href="?action=assignments&edit=<?php echo $match['id']; ?>" class="btn btn-referee btn-referee-primary btn-sm w-100">
                                            <i class="fas fa-edit me-1"></i> Cập nhật
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Completed Matches -->
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-history me-2"></i>Trận đấu gần đây
                    </h3>
                    
                    <?php 
                    $completed = array_filter($assignments, function($a) {
                        return $a['status'] === 'completed';
                    });
                    $completed = array_slice($completed, 0, 5);
                    ?>
                    
                    <?php if (empty($completed)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Chưa có trận đấu nào hoàn thành.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Trận đấu</th>
                                    <th>Kết quả</th>
                                    <th>Giải đấu</th>
                                    <th>Ngày thi đấu</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($completed as $match): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($match['team1_name']); ?> 
                                        <strong>vs</strong> 
                                        <?php echo htmlspecialchars($match['team2_name']); ?>
                                    </td>
                                    <td>
                                        <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                        <span class="badge-referee badge-referee-success">
                                            <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge-referee badge-referee-warning">Chưa có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($match['tournament_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($match['match_date'])); ?></td>
                                    <td>
                                        <a href="?action=assignments&view=<?php echo $match['id']; ?>" class="btn btn-sm btn-referee btn-referee-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- My Assignments -->
                <?php if ($action === 'assignments'): ?>
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-tasks me-2"></i>Phân công của tôi (<?php echo $stats['total_assignments']; ?>)
                    </h3>
                    
                    <?php if (empty($assignments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bạn chưa được phân công trận đấu nào.
                    </div>
                    <?php else: ?>
                    <!-- Filter buttons -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-referee btn-referee-primary btn-sm" onclick="filterMatches('all')">Tất cả</button>
                        <button type="button" class="btn btn-referee btn-referee-warning btn-sm" onclick="filterMatches('assigned')">Đã phân công</button>
                        <button type="button" class="btn btn-referee btn-referee-success btn-sm" onclick="filterMatches('completed')">Đã hoàn thành</button>
                        <button type="button" class="btn btn-referee btn-referee-danger btn-sm" onclick="filterMatches('cancelled')">Đã hủy</button>
                    </div>
                    
                    <!-- Match cards -->
                    <div class="row" id="matches-container">
                        <?php foreach($assignments as $match): 
                            $statusClass = [
                                'assigned' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ][$match['status']] ?? 'info';
                        ?>
                        <div class="col-lg-6 col-md-12 mb-3 match-item" data-status="<?php echo $match['status']; ?>">
                            <div class="match-card">
                                <div class="match-header">
                                    <span class="badge-referee badge-referee-<?php echo $statusClass; ?>">
                                        <?php 
                                        $statusText = [
                                            'assigned' => 'Đã phân công',
                                            'completed' => 'Đã hoàn thành',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        echo $statusText[$match['status']] ?? 'Unknown';
                                        ?>
                                    </span>
                                    <span class="badge-referee badge-referee-primary">
                                        <?php echo $match['assignment_type'] === 'main' ? 'Trọng tài chính' : 'Trọng tài phụ'; ?>
                                    </span>
                                </div>
                                
                                <div class="match-teams">
                                    <div class="team">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                        <?php if ($match['score1'] !== null): ?>
                                        <div class="team-score">
                                            <span class="badge-referee badge-referee-primary"><?php echo $match['score1']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vs">VS</div>
                                    <div class="team">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                                        <?php if ($match['score2'] !== null): ?>
                                        <div class="team-score">
                                            <span class="badge-referee badge-referee-primary"><?php echo $match['score2']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                <div class="match-score">
                                    <?php echo $match['score1']; ?> : <?php echo $match['score2']; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="match-info">
                                    <div><i class="fas fa-trophy me-1"></i> <?php echo htmlspecialchars($match['tournament_name']); ?></div>
                                    <div><i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y H:i', strtotime($match['match_date'])); ?></div>
                                    <div><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['arena_name'] ?? $match['court'] ?? 'Chưa xác định'); ?></div>
                                </div>
                                
                                <div class="match-actions">
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-referee btn-referee-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#updateScoreModal<?php echo $match['id']; ?>">
                                            <i class="fas fa-edit me-1"></i> Kết quả
                                        </button>
                                        
                                        <button type="button" class="btn btn-referee btn-referee-warning btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#updateInfoModal<?php echo $match['id']; ?>">
                                            <i class="fas fa-info-circle me-1"></i> Thông tin
                                        </button>
                                        
                                        <button type="button" class="btn btn-referee btn-referee-success btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $match['id']; ?>">
                                            <i class="fas fa-check me-1"></i> Trạng thái
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal cập nhật kết quả -->
                        <div class="modal fade" id="updateScoreModal<?php echo $match['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Cập nhật kết quả</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <input type="hidden" name="team1_id" value="<?php echo $match['team1_id']; ?>">
                                            <input type="hidden" name="team2_id" value="<?php echo $match['team2_id']; ?>">
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-5">
                                                    <h5><?php echo htmlspecialchars($match['team1_name']); ?></h5>
                                                </div>
                                                <div class="col-2">
                                                    <h5>VS</h5>
                                                </div>
                                                <div class="col-5">
                                                    <h5><?php echo htmlspecialchars($match['team2_name']); ?></h5>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-5">
                                                    <input type="number" class="form-control text-center" name="score1" 
                                                           value="<?php echo $match['score1'] ?? 0; ?>" min="0" required>
                                                </div>
                                                <div class="col-2 text-center">
                                                    <h4>:</h4>
                                                </div>
                                                <div class="col-5">
                                                    <input type="number" class="form-control text-center" name="score2" 
                                                           value="<?php echo $match['score2'] ?? 0; ?>" min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" name="update_match_score" class="btn btn-primary">Cập nhật</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal cập nhật thông tin -->
                        <div class="modal fade" id="updateInfoModal<?php echo $match['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-warning text-white">
                                        <h5 class="modal-title">Cập nhật thông tin trận đấu</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Thời gian thi đấu</label>
                                                <input type="datetime-local" class="form-control" name="match_date" 
                                                       value="<?php echo $match['match_date'] ? date('Y-m-d\TH:i', strtotime($match['match_date'])) : ''; ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Sân đấu</label>
                                                <select class="form-select" name="arena_id">
                                                    <option value="">-- Chọn sân --</option>
                                                    <?php foreach($arenas as $arena): ?>
                                                    <option value="<?php echo $arena['id']; ?>" <?php echo $match['arena_id'] == $arena['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($arena['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Thông tin sân</label>
                                                <input type="text" class="form-control" name="court" 
                                                       value="<?php echo htmlspecialchars($match['court'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" name="update_match_info" class="btn btn-warning">Cập nhật</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal cập nhật trạng thái -->
                        <div class="modal fade" id="updateStatusModal<?php echo $match['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Cập nhật trạng thái phân công</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($match['status'] ?? 'N/A'); ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Trạng thái</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="assigned" <?php echo $match['status'] === 'assigned' ? 'selected' : ''; ?>>Đã phân công</option>
                                                    <option value="completed" <?php echo $match['status'] === 'completed' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                                                    <option value="cancelled" <?php echo $match['status'] === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" name="update_assignment_status" class="btn btn-success">Cập nhật</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Today's Matches -->
                <?php if ($action === 'today_matches'): ?>
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-calendar-day me-2"></i>Trận đấu hôm nay (<?php echo $stats['today_matches']; ?>)
                    </h3>
                    
                    <?php 
                    $today_matches = array_filter($assignments, function($a) {
                        return date('Y-m-d', strtotime($a['match_date'])) == date('Y-m-d');
                    });
                    ?>
                    
                    <?php if (empty($today_matches)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Không có trận đấu nào trong ngày hôm nay.
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($today_matches as $match): 
                            $statusClass = [
                                'assigned' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ][$match['status']] ?? 'info';
                        ?>
                        <div class="col-lg-6 col-md-12 mb-3">
                            <div class="match-card">
                                <div class="match-header">
                                    <span class="badge-referee badge-referee-<?php echo $statusClass; ?>">
                                        <?php 
                                        $statusText = [
                                            'assigned' => 'Đã phân công',
                                            'completed' => 'Đã hoàn thành',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        echo $statusText[$match['status']] ?? 'Unknown';
                                        ?>
                                    </span>
                                    <span class="badge-referee badge-referee-primary">
                                        <?php echo date('H:i', strtotime($match['match_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="match-teams">
                                    <div class="team">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                        <?php if ($match['score1'] !== null): ?>
                                        <div class="team-score">
                                            <span class="badge-referee badge-referee-primary"><?php echo $match['score1']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vs">VS</div>
                                    <div class="team">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                                        <?php if ($match['score2'] !== null): ?>
                                        <div class="team-score">
                                            <span class="badge-referee badge-referee-primary"><?php echo $match['score2']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="match-info">
                                    <div><i class="fas fa-trophy me-1"></i> <?php echo htmlspecialchars($match['tournament_name']); ?></div>
                                    <div><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['arena_name'] ?? $match['court'] ?? 'Chưa xác định'); ?></div>
                                    <div><i class="fas fa-user-tie me-1"></i> <?php echo $match['assignment_type'] === 'main' ? 'Trọng tài chính' : 'Trọng tài phụ'; ?></div>
                                </div>
                                
                                <div class="match-actions">
                                    <div class="d-flex justify-content-between">
                                        <?php if ($match['status'] === 'assigned'): ?>
                                        <button type="button" class="btn btn-referee btn-referee-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#updateScoreModalToday<?php echo $match['id']; ?>">
                                            <i class="fas fa-edit me-1"></i> Cập nhật kết quả
                                        </button>
                                        <?php endif; ?>
                                        
                                        <a href="?action=assignments&view=<?php echo $match['id']; ?>" class="btn btn-referee btn-referee-warning btn-sm">
                                            <i class="fas fa-eye me-1"></i> Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($match['status'] === 'assigned'): ?>
                        <!-- Modal cho trận hôm nay -->
                        <div class="modal fade" id="updateScoreModalToday<?php echo $match['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Cập nhật kết quả</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <input type="hidden" name="team1_id" value="<?php echo $match['team1_id']; ?>">
                                            <input type="hidden" name="team2_id" value="<?php echo $match['team2_id']; ?>">
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-5">
                                                    <h5><?php echo htmlspecialchars($match['team1_name']); ?></h5>
                                                </div>
                                                <div class="col-2">
                                                    <h5>VS</h5>
                                                </div>
                                                <div class="col-5">
                                                    <h5><?php echo htmlspecialchars($match['team2_name']); ?></h5>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-5">
                                                    <input type="number" class="form-control text-center" name="score1" 
                                                           value="<?php echo $match['score1'] ?? 0; ?>" min="0" required>
                                                </div>
                                                <div class="col-2 text-center">
                                                    <h4>:</h4>
                                                </div>
                                                <div class="col-5">
                                                    <input type="number" class="form-control text-center" name="score2" 
                                                           value="<?php echo $match['score2'] ?? 0; ?>" min="0" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" name="update_match_score" class="btn btn-primary">Cập nhật</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Arenas -->
                <?php if ($action === 'arenas'): ?>
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-map-marker-alt me-2"></i>Danh sách Sân thi đấu
                    </h3>
                    
                    <div class="row">
                        <?php foreach($arenas as $arena): ?>
                        <div class="col-md-4 mb-3">
                            <div class="match-card">
                                <div class="match-header">
                                    <span class="badge-referee <?php 
                                        if ($arena['status'] === 'available') echo 'badge-referee-success';
                                        elseif ($arena['status'] === 'maintenance') echo 'badge-referee-warning';
                                        else echo 'badge-referee-danger';
                                    ?>">
                                        <?php 
                                        $statusText = [
                                            'available' => 'Có sẵn',
                                            'maintenance' => 'Bảo trì',
                                            'unavailable' => 'Không có sẵn'
                                        ];
                                        echo $statusText[$arena['status']] ?? 'Unknown';
                                        ?>
                                    </span>
                                </div>
                                
                                <h5 class="text-center mb-3"><?php echo htmlspecialchars($arena['name']); ?></h5>
                                
                                <div class="match-info">
                                    <div><i class="fas fa-location-dot me-2"></i> <?php echo htmlspecialchars($arena['location']); ?></div>
                                    <div><i class="fas fa-users me-2"></i> Sức chứa: <?php echo $arena['capacity'] ?: 'Không giới hạn'; ?></div>
                                    <?php if ($arena['description']): ?>
                                    <div class="mt-2">
                                        <small><i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($arena['description']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Profile -->
                <?php if ($action === 'profile'): ?>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-container">
                            <h3 class="form-title">
                                <i class="fas fa-user me-2"></i>Hồ sơ cá nhân
                            </h3>
                            
                            <div class="text-center mb-4">
                                <div class="user-avatar" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h4 class="mt-3"><?php echo htmlspecialchars($current_user['display_name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($current_user['username']); ?></p>
                                <span class="badge-referee badge-referee-primary">Trọng tài</span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-phone me-2"></i>Số điện thoại</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-calendar-alt me-2"></i>Ngày tham gia</label>
                                <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="form-container">
                            <h3 class="form-title">
                                <i class="fas fa-chart-bar me-2"></i>Thống kê công việc
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <div class="stats-icon">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div class="stats-number"><?php echo $stats['total_assignments']; ?></div>
                                        <div class="stats-label">Tổng phân công</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <div class="stats-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stats-number"><?php echo $stats['completed']; ?></div>
                                        <div class="stats-label">Đã hoàn thành</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <div class="stats-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stats-number"><?php echo $stats['assigned']; ?></div>
                                        <div class="stats-label">Đang chờ</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <div class="stats-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="stats-number"><?php echo $stats['today_matches']; ?></div>
                                        <div class="stats-label">Trận hôm nay</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h5><i class="fas fa-history me-2"></i>Hoạt động gần đây</h5>
                                <?php 
                                $recent_activity = array_slice($assignments, 0, 5);
                                if (!empty($recent_activity)):
                                ?>
                                <div class="list-group">
                                    <?php foreach($recent_activity as $activity): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['team1_name']); ?> vs <?php echo htmlspecialchars($activity['team2_name']); ?></h6>
                                            <small><?php echo date('H:i d/m', strtotime($activity['match_date'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge-referee <?php echo $activity['status'] === 'completed' ? 'badge-referee-success' : 'badge-referee-warning'; ?>">
                                                <?php echo $activity['status'] === 'completed' ? 'Đã hoàn thành' : 'Đã phân công'; ?>
                                            </span>
                                            <?php if ($activity['score1'] !== null): ?>
                                            <span class="badge-referee badge-referee-primary ms-2">
                                                Kết quả: <?php echo $activity['score1']; ?> - <?php echo $activity['score2']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Chưa có hoạt động nào.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Lọc trận đấu theo trạng thái
        function filterMatches(status) {
            const matches = document.querySelectorAll('.match-item');
            matches.forEach(match => {
                if (status === 'all' || match.dataset.status === status) {
                    match.style.display = 'block';
                } else {
                    match.style.display = 'none';
                }
            });
            
            // Cập nhật trạng thái nút
            document.querySelectorAll('.btn-referee').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-referee');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Hiển thị xác nhận khi cập nhật trạng thái thành "đã hoàn thành"
        document.addEventListener('DOMContentLoaded', function() {
            const statusForms = document.querySelectorAll('form[name="update_assignment_status"]');
            statusForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const status = this.querySelector('select[name="status"]').value;
                    if (status === 'completed') {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Xác nhận hoàn thành?',
                            text: 'Bạn có chắc chắn trận đấu đã hoàn thành?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Xác nhận',
                            cancelButtonText: 'Hủy'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    }
                });
            });
        });
        
        // Format datetime-local input
        document.addEventListener('DOMContentLoaded', function() {
            const datetimeInputs = document.querySelectorAll('input[type="datetime-local"]');
            datetimeInputs.forEach(input => {
                if (!input.value) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    
                    input.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            });
        });
    </script>
</body>
</html>