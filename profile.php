<?php
// profile.php - Trang hồ sơ người dùng
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

$message = '';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $display_name = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE Users SET display_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$display_name, $email, $phone, $user_id]);
        
        $_SESSION['display_name'] = $display_name;
        $user['display_name'] = $display_name;
        $user['email'] = $email;
        $user['phone'] = $phone;
        
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Cập nhật thành công!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!password_verify($current_password, $user['password'])) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Mật khẩu hiện tại không đúng!</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Mật khẩu mới phải có ít nhất 6 ký tự!</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Mật khẩu xác nhận không khớp!</div>';
    } else {
        try {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE Users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đổi mật khẩu thành công!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}

// Lấy thống kê của user
$myTeams = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id IN (SELECT id FROM Tournaments WHERE owner_id = ?)");
$myTeams->execute([$user_id]);
$myTeams = $myTeams->fetchColumn();

$myMatches = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE tournament_id IN (SELECT id FROM Tournaments WHERE owner_id = ?)");
$myMatches->execute([$user_id]);
$myMatches = $myMatches->fetchColumn();

$roleLabels = [
    'admin' => ['Quản trị viên', 'danger'],
    'manager' => ['Quản lý giải đấu', 'warning'],
    'referee' => ['Trọng tài', 'info'],
    'user' => ['Người dùng', 'secondary']
];
$roleInfo = $roleLabels[$user['role']] ?? ['Người dùng', 'secondary'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .profile-card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; margin: 0 auto 15px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; border-bottom: 3px solid var(--primary); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>
    <?php renderNavbar(''); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container text-center">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h1 class="page-title"><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h1>
            <p class="mb-0">
                <span class="badge bg-<?php echo $roleInfo[1]; ?>"><?php echo $roleInfo[0]; ?></span>
            </p>
        </div>
    </div>

    <div class="container py-4">
        <?php echo $message; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $myTeams; ?></div>
                    <div class="stat-label">Đội của tôi</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $myMatches; ?></div>
                    <div class="stat-label">Trận đấu</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                    <div class="stat-label">Ngày tham gia</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Thông tin cá nhân -->
            <div class="col-md-6">
                <div class="profile-card">
                    <h5 class="mb-3"><i class="fas fa-user-circle me-2"></i>Thông tin cá nhân</h5>
                    <form method="post">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Tên đăng nhập không thể thay đổi</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên hiển thị</label>
                            <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Điện thoại</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Lưu thông tin</button>
                    </form>
                </div>
            </div>

            <!-- Đổi mật khẩu -->
            <div class="col-md-6">
                <div class="profile-card">
                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Đổi mật khẩu</h5>
                    <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i>Đổi mật khẩu</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="profile-card mt-4">
            <h5 class="mb-3"><i class="fas fa-link me-2"></i>Liên kết nhanh</h5>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="tournament_list.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-trophy me-2"></i>Giải đấu
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="matches.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-bullhorn me-2"></i>Trận đấu
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="match-control.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-whistle me-2"></i>Điều hành
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="logout.php" class="btn btn-outline-danger w-100">
                        <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
