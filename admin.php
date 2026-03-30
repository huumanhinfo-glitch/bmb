<?php
// admin.php - Trang quản trị (Phiên bản đơn giản)
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

if ($user_role !== 'admin' && $user_role !== 'manager') {
    die("Access denied. Chỉ admin và manager mới truy cập được.");
}

$action = $_GET['action'] ?? 'dashboard';
$message = '';

// Xử lý tạo giải đấu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tournament'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $format = $_POST['format'] ?? 'combined';
    $location = trim($_POST['location'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'upcoming';
    
    if ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO Tournaments (name, description, format, location, start_date, end_date, status, stage) VALUES (?, ?, ?, ?, ?, ?, ?, 'planning')");
            $stmt->execute([$name, $description, $format, $location, $start_date, $end_date, $status]);
            $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã tạo giải đấu!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Xử lý cập nhật giải đấu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tournament'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $format = $_POST['format'] ?? 'combined';
    $location = trim($_POST['location'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'upcoming';
    
    if ($name && $id) {
        try {
            $stmt = $pdo->prepare("UPDATE Tournaments SET name = ?, description = ?, format = ?, location = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description, $format, $location, $start_date, $end_date, $status, $id]);
            $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã cập nhật!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Xử lý xóa giải đấu
if (isset($_GET['delete_tournament'])) {
    $id = intval($_GET['delete_tournament']);
    try {
        $pdo->prepare("DELETE FROM Tournaments WHERE id = ?")->execute([$id]);
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa giải đấu!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
    }
}

// Xử lý thêm/cập nhật đội
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_team'])) {
    $id = intval($_POST['id'] ?? 0);
    $team_name = trim($_POST['team_name'] ?? '');
    $player1 = trim($_POST['player1'] ?? '');
    $player2 = trim($_POST['player2'] ?? '');
    $tournament_id = intval($_POST['tournament_id'] ?? 0);
    $skill_level = trim($_POST['skill_level'] ?? '');
    
    if ($team_name && $player1) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE Teams SET team_name = ?, player1 = ?, player2 = ?, tournament_id = ?, skill_level = ? WHERE id = ?");
                $stmt->execute([$team_name, $player1, $player2, $tournament_id, $skill_level, $id]);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã cập nhật đội!</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$team_name, $player1, $player2, $tournament_id, $skill_level]);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã thêm đội mới!</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Xử lý xóa đội
if (isset($_GET['delete_team'])) {
    $id = intval($_GET['delete_team']);
    try {
        $pdo->prepare("DELETE FROM Teams WHERE id = ?")->execute([$id]);
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa đội!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
    }
}

// Xử lý thêm/cập nhật sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_arena'])) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'available';
    
    if ($name) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE Arenas SET name = ?, location = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $location, $status, $id]);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã cập nhật sân!</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO Arenas (name, location, status) VALUES (?, ?, ?)");
                $stmt->execute([$name, $location, $status]);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã thêm sân mới!</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Xử lý xóa sân
if (isset($_GET['delete_arena'])) {
    $id = intval($_GET['delete_arena']);
    try {
        $pdo->prepare("DELETE FROM Arenas WHERE id = ?")->execute([$id]);
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa sân!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
    }
}

// Xử lý thêm/cập nhật người dùng (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user']) && $user_role === 'admin') {
    $id = intval($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $new_password = $_POST['new_password'] ?? '';
    
    if ($username) {
        try {
            if ($id > 0) {
                if ($new_password) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE Users SET username = ?, display_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $display_name, $email, $role, $password_hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE Users SET username = ?, display_name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $display_name, $email, $role, $id]);
                }
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã cập nhật người dùng!</div>';
            } else {
                if ($new_password) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO Users (username, display_name, email, role, password) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $display_name, $email, $role, $password_hash]);
                    $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã thêm người dùng mới!</div>';
                } else {
                    $message = '<div class="alert alert-warning"><i class="fas fa-exclamation me-2"></i>Cần nhập mật khẩu cho người dùng mới!</div>';
                }
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Xử lý xóa người dùng (admin only)
if (isset($_GET['delete_user']) && $user_role === 'admin') {
    $id = intval($_GET['delete_user']);
    if ($id !== $_SESSION['user_id']) {
        try {
            $pdo->prepare("DELETE FROM Users WHERE id = ?")->execute([$id]);
            $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa người dùng!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation me-2"></i>Không thể xóa chính mình!</div>';
    }
}

// Lấy dữ liệu
$tournaments = getAllTournaments();
$teams = $pdo->query("SELECT t.*, tr.name as tournament_name FROM Teams t LEFT JOIN Tournaments tr ON t.tournament_id = tr.id ORDER BY t.id DESC LIMIT 100")->fetchAll();
$users = $pdo->query("SELECT * FROM Users ORDER BY role, username")->fetchAll();
$arenas = getAllArenas();

// Thống kê
$stats = [
    'tournaments' => count($tournaments),
    'teams' => count($teams),
    'users' => count($users),
    'matches' => $pdo->query("SELECT COUNT(*) FROM Matches")->fetchColumn()
];

$statusOptions = ['upcoming', 'ongoing', 'completed', 'cancelled'];
$formatOptions = ['round_robin', 'knockout', 'combined', 'double_elimination'];

$editTournament = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    $editTournament = getTournamentById($editId);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; border-bottom: 3px solid var(--primary); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; }
        .card-custom { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 15px; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; display: inline-block; }
        .nav-tab:hover, .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); text-decoration: none; }
        .data-table { width: 100%; }
        .data-table th { background: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
        .data-table tr:hover { background: #fafafa; }
    </style>
</head>
<body>
    <?php renderNavbar('admin'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><i class="fas fa-cog me-2"></i>QUẢN TRỊ HỆ THỐNG</h1>
        </div>
    </div>

    <div class="container py-4">
        <?php echo $message; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['tournaments']; ?></div>
                    <div class="stat-label">Giải đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['teams']; ?></div>
                    <div class="stat-label">Đội</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['matches']; ?></div>
                    <div class="stat-label">Trận đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['users']; ?></div>
                    <div class="stat-label">Người dùng</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-tab <?php echo ($action === 'dashboard') ? 'active' : ''; ?>" href="?action=dashboard">
                    <i class="fas fa-home me-1"></i>Tổng quan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($action === 'tournaments') ? 'active' : ''; ?>" href="?action=tournaments">
                    <i class="fas fa-trophy me-1"></i>Giải đấu
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($action === 'teams') ? 'active' : ''; ?>" href="?action=teams">
                    <i class="fas fa-users me-1"></i>Đội
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($action === 'arenas') ? 'active' : ''; ?>" href="?action=arenas">
                    <i class="fas fa-building me-1"></i>Sân
                </a>
            </li>
            <?php if ($user_role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($action === 'users') ? 'active' : ''; ?>" href="?action=users">
                    <i class="fas fa-user-cog me-1"></i>Người dùng
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Nội dung tab -->
        <?php if ($action === 'dashboard'): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-title"><i class="fas fa-plus me-2"></i>Tạo giải đấu mới</div>
                        <form method="post">
                            <input type="hidden" name="create_tournament" value="1">
                            <div class="mb-3">
                                <label class="form-label">Tên giải đấu</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Thể thức</label>
                                    <select name="format" class="form-select">
                                        <option value="combined">Vòng bảng + Loại trực tiếp</option>
                                        <option value="round_robin">Vòng tròn</option>
                                        <option value="knockout">Loại trực tiếp</option>
                                        <option value="double_elimination">Đá hai lần thua</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="upcoming">Sắp diễn ra</option>
                                        <option value="ongoing">Đang diễn ra</option>
                                        <option value="completed">Hoàn thành</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ngày bắt đầu</label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ngày kết thúc</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Địa điểm</label>
                                <input type="text" name="location" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tạo giải đấu</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-title"><i class="fas fa-link me-2"></i>Liên kết nhanh</div>
                        <div class="d-grid gap-2">
                            <a href="draw.php" class="btn btn-outline-primary"><i class="fas fa-random me-2"></i>Bốc thăm chia bảng</a>
                            <a href="match-control.php" class="btn btn-outline-success"><i class="fas fa-whistle me-2"></i>Điều hành trận đấu</a>
                            <a href="tournament_list.php" class="btn btn-outline-info"><i class="fas fa-list me-2"></i>Danh sách giải đấu</a>
                            <a href="matches.php" class="btn btn-outline-warning"><i class="fas fa-bullhorn me-2"></i>Quản lý trận đấu</a>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'tournaments'): ?>
            <div class="card-custom">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-trophy me-2"></i>Danh sách giải đấu</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i>Tạo mới
                    </button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên giải</th>
                            <th>Thể thức</th>
                            <th>Ngày</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tournaments as $t): ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                            <td><?php echo $t['format']; ?></td>
                            <td><?php echo $t['start_date']; ?> - <?php echo $t['end_date']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $t['status'] === 'ongoing' ? 'success' : ($t['status'] === 'upcoming' ? 'info' : 'secondary'); ?>">
                                    <?php echo $t['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="tournament_view.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $t['id']; ?>"><i class="fas fa-edit"></i></button>
                                <a href="?action=tournaments&delete_tournament=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa giải đấu?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Modals -->
            <?php foreach ($tournaments as $t): ?>
            <div class="modal fade" id="editModal<?php echo $t['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Chỉnh sửa giải đấu</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Tên giải đấu</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($t['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mô tả</label>
                                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($t['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Thể thức</label>
                                        <select name="format" class="form-select">
                                            <option value="round_robin" <?php echo $t['format'] === 'round_robin' ? 'selected' : ''; ?>>Vòng tròn</option>
                                            <option value="knockout" <?php echo $t['format'] === 'knockout' ? 'selected' : ''; ?>>Loại trực tiếp</option>
                                            <option value="combined" <?php echo $t['format'] === 'combined' ? 'selected' : ''; ?>>Kết hợp</option>
                                            <option value="double_elimination" <?php echo $t['format'] === 'double_elimination' ? 'selected' : ''; ?>>Loại kép</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Trạng thái</label>
                                        <select name="status" class="form-select">
                                            <option value="upcoming" <?php echo $t['status'] === 'upcoming' ? 'selected' : ''; ?>>Sắp diễn ra</option>
                                            <option value="ongoing" <?php echo $t['status'] === 'ongoing' ? 'selected' : ''; ?>>Đang diễn ra</option>
                                            <option value="completed" <?php echo $t['status'] === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                                            <option value="cancelled" <?php echo $t['status'] === 'cancelled' ? 'selected' : ''; ?>>Hủy</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Địa điểm</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($t['location'] ?? ''); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày bắt đầu</label>
                                        <input type="date" name="start_date" class="form-control" value="<?php echo $t['start_date']; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày kết thúc</label>
                                        <input type="date" name="end_date" class="form-control" value="<?php echo $t['end_date']; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="update_tournament" class="btn btn-primary">Lưu thay đổi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif ($action === 'teams'): ?>
            <div class="card-custom">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users me-2"></i>Danh sách đội</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                        <i class="fas fa-plus me-1"></i>Thêm đội
                    </button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đội</th>
                            <th>Thành viên</th>
                            <th>Giải đấu</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $t): ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($t['team_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(($t['player1'] ?? '') . ' / ' . ($t['player2'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($t['tournament_name'] ?? '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editTeamModal<?php echo $t['id']; ?>"><i class="fas fa-edit"></i></button>
                                <a href="?action=teams&delete_team=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa đội?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Team Modal -->
            <div class="modal fade" id="addTeamModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Thêm đội mới</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="0">
                                <div class="mb-3">
                                    <label class="form-label">Tên đội</label>
                                    <input type="text" name="team_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">VĐV 1</label>
                                    <input type="text" name="player1" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">VĐV 2</label>
                                    <input type="text" name="player2" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Giải đấu</label>
                                    <select name="tournament_id" class="form-select">
                                        <option value="">-- Chọn giải --</option>
                                        <?php foreach ($tournaments as $tr): ?>
                                        <option value="<?php echo $tr['id']; ?>"><?php echo htmlspecialchars($tr['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Trình độ</label>
                                    <input type="text" name="skill_level" class="form-control" placeholder="VD: 3.0, 4.0">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_team" class="btn btn-primary">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Team Modals -->
            <?php foreach ($teams as $t): ?>
            <div class="modal fade" id="editTeamModal<?php echo $t['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Chỉnh sửa đội</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Tên đội</label>
                                    <input type="text" name="team_name" class="form-control" value="<?php echo htmlspecialchars($t['team_name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">VĐV 1</label>
                                    <input type="text" name="player1" class="form-control" value="<?php echo htmlspecialchars($t['player1'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">VĐV 2</label>
                                    <input type="text" name="player2" class="form-control" value="<?php echo htmlspecialchars($t['player2'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Giải đấu</label>
                                    <select name="tournament_id" class="form-select">
                                        <option value="">-- Chọn giải --</option>
                                        <?php foreach ($tournaments as $tr): ?>
                                        <option value="<?php echo $tr['id']; ?>" <?php echo ($t['tournament_id'] ?? '') == $tr['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tr['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Trình độ</label>
                                    <input type="text" name="skill_level" class="form-control" value="<?php echo htmlspecialchars($t['skill_level'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_team" class="btn btn-primary">Lưu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif ($action === 'arenas'): ?>
            <div class="card-custom">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-building me-2"></i>Quản lý sân thi đấu</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addArenaModal">
                        <i class="fas fa-plus me-1"></i>Thêm sân
                    </button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên sân</th>
                            <th>Địa điểm</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arenas as $a): ?>
                        <tr>
                            <td><?php echo $a['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['location'] ?? '-'); ?></td>
                            <td><span class="badge bg-<?php echo $a['status'] === 'available' ? 'success' : 'warning'; ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editArenaModal<?php echo $a['id']; ?>"><i class="fas fa-edit"></i></button>
                                <a href="?action=arenas&delete_arena=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa sân?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Arena Modal -->
            <div class="modal fade" id="addArenaModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Thêm sân mới</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="0">
                                <div class="mb-3">
                                    <label class="form-label">Tên sân</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Địa điểm</label>
                                    <input type="text" name="location" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="available">Sẵn sàng</option>
                                        <option value="maintenance">Bảo trì</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_arena" class="btn btn-primary">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Arena Modals -->
            <?php foreach ($arenas as $a): ?>
            <div class="modal fade" id="editArenaModal<?php echo $a['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Chỉnh sửa sân</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Tên sân</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($a['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Địa điểm</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($a['location'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="available" <?php echo ($a['status'] ?? '') === 'available' ? 'selected' : ''; ?>>Sẵn sàng</option>
                                        <option value="maintenance" <?php echo ($a['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_arena" class="btn btn-primary">Lưu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif ($action === 'users' && $user_role === 'admin'): ?>
            <div class="card-custom">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-cog me-2"></i>Quản lý người dùng</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-1"></i>Thêm người dùng
                    </button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Tên hiển thị</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['display_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'manager' ? 'warning' : ($u['role'] === 'referee' ? 'info' : 'secondary')); ?>">
                                    <?php echo $u['role']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>"><i class="fas fa-edit"></i></button>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=users&delete_user=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa người dùng?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add User Modal -->
            <div class="modal fade" id="addUserModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Thêm người dùng mới</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="0">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tên hiển thị</label>
                                    <input type="text" name="display_name" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vai trò</label>
                                    <select name="role" class="form-select">
                                        <option value="user">User</option>
                                        <option value="referee">Trọng tài</option>
                                        <option value="manager">Quản lý</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_user" class="btn btn-primary">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit User Modals -->
            <?php foreach ($users as $u): ?>
            <div class="modal fade" id="editUserModal<?php echo $u['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Chỉnh sửa người dùng</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($u['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tên hiển thị</label>
                                    <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($u['display_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vai trò</label>
                                    <select name="role" class="form-select">
                                        <option value="user" <?php echo ($u['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="referee" <?php echo ($u['role'] ?? '') === 'referee' ? 'selected' : ''; ?>>Trọng tài</option>
                                        <option value="manager" <?php echo ($u['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Quản lý</option>
                                        <option value="admin" <?php echo ($u['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" name="save_user" class="btn btn-primary">Lưu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
