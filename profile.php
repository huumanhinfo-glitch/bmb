<?php
// profile.php
require_once 'db.php';

if (isset($_POST['login'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: profile.php");
        exit;
    } else {
        $err = '<div class="alert alert-danger">Sai thông tin đăng nhập!</div>';
    }
}
if (isset($_POST['register'])) {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $display = $_POST['display_name'];
    $stmt = $pdo->prepare("INSERT INTO Users (username, password, display_name) VALUES (?,?,?)");
    $stmt->execute([$u, $p, $display]);
    $msg = '<div class="alert alert-success">Đăng ký thành công! Vui lòng đăng nhập.</div>';
}
if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    header("Location: profile.php");
    exit;
}

$user = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2ecc71;
            --accent: #ff6b00;
            --text-dark: #1e293b;
        }
        body {
            background: #f8fafc;
            font-family: 'Open Sans', sans-serif;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            margin-top: 50px;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        .nav-tabs-custom {
            border: none;
            margin-bottom: 30px;
        }
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-dark);
            font-weight: 600;
            padding: 15px 30px;
            border-radius: 10px;
            margin: 0 5px;
        }
        .nav-tabs-custom .nav-link.active {
            background: var(--primary);
            color: white;
        }
        .btn-profile {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-profile:hover {
            background: #e65100;
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">TRỌNG TÀI SỐ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
                <a class="nav-link" href="tournament_list.php"><i class="fas fa-trophy"></i> Giải đấu</a>
                <a class="nav-link" href="matches.php"><i class="fas fa-basketball-ball"></i> Trận đấu</a>
                <a class="nav-link active" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="profile-card">
                    <?php if($user): ?>
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 style="font-family: 'Montserrat', sans-serif; color: var(--text-dark);">
                                <?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?>
                            </h3>
                            <p class="text-muted">Vận động viên Pickleball</p>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h5 class="text-primary">0</h5>
                                        <small class="text-muted">Giải đã tham gia</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h5 class="text-success">0</h5>
                                        <small class="text-muted">Trận thắng</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h5 class="text-warning">#N/A</h5>
                                        <small class="text-muted">Xếp hạng</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-trophy me-2"></i>Xem giải đấu
                            </a>
                            <a href="profile.php?logout=1" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                            </a>
                        </div>
                    <?php else: ?>
                        <ul class="nav nav-tabs nav-tabs-custom justify-content-center" id="profileTab">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#login">
                                    <i class="fas fa-sign-in-alt me-2"></i>ĐĂNG NHẬP
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#register">
                                    <i class="fas fa-user-plus me-2"></i>ĐĂNG KÝ
                                </a>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="login">
                                <?php echo $err ?? ''; ?>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Tên đăng nhập</label>
                                        <input type="text" name="username" class="form-control" placeholder="Nhập username" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Mật khẩu</label>
                                        <input type="password" name="password" class="form-control" placeholder="Nhập password" required>
                                    </div>
                                    <div class="d-grid">
                                        <button name="login" type="submit" class="btn btn-profile">
                                            <i class="fas fa-sign-in-alt me-2"></i>ĐĂNG NHẬP
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="register">
                                <?php echo $msg ?? ''; ?>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Tên hiển thị</label>
                                        <input type="text" name="display_name" class="form-control" placeholder="Tên của bạn" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tên đăng nhập</label>
                                        <input type="text" name="username" class="form-control" placeholder="Chọn username" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Mật khẩu</label>
                                        <input type="password" name="password" class="form-control" placeholder="Chọn password" required>
                                    </div>
                                    <div class="d-grid">
                                        <button name="register" type="submit" class="btn btn-profile">
                                            <i class="fas fa-user-plus me-2"></i>ĐĂNG KÝ TÀI KHOẢN
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Đăng nhập để theo dõi lịch sử thi đấu và đăng ký giải
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>