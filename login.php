<?php
// login.php - Trang đăng nhập
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['display_name'] = $user['display_name'];
            
            header("Location: index.php");
            exit;
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Tên đăng nhập hoặc mật khẩu không đúng!</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Vui lòng nhập tên đăng nhập và mật khẩu!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; border-radius: 15px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .login-logo { font-size: 2.5rem; color: var(--accent); text-align: center; margin-bottom: 10px; }
        .login-title { font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 30px; color: var(--text-dark); }
        .form-control { border-radius: 10px; padding: 12px 15px; border: 2px solid #e2e8f0; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46,204,113,0.1); }
        .btn-login { background: var(--primary); border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-login:hover { background: #27ae60; }
        .login-footer { text-align: center; margin-top: 20px; }
        .login-footer a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo"><i class="fas fa-trophy"></i></div>
        <h1 class="login-title">TRỌNG TÀI SỐ</h1>
        
        <?php echo $message; ?>
        
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required autofocus>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
            </button>
        </form>
        
        <div class="login-footer">
            <a href="index.php"><i class="fas fa-arrow-left me-1"></i>Quay lại trang chủ</a>
        </div>
    </div>
</body>
</html>
