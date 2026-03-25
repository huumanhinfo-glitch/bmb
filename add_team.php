<?php
// add_team.php
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = trim($_POST['team_name']);
    $player1 = trim($_POST['player1']);
    $player2 = trim($_POST['player2']);
    $tournament = trim($_POST['tournament']);
    $skillLevel = trim($_POST['skill_level']);
    
    if (empty($teamName)) {
        $message = '<div class="alert alert-danger">Vui lòng nhập tên đội!</div>';
    } else {
        // Kiểm tra đội đã tồn tại
        $stmt = $pdo->prepare("SELECT id FROM Teams WHERE team_name = ?");
        $stmt->execute([$teamName]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert alert-warning">Tên đội đã tồn tại!</div>';
        } else {
            // Thêm đội mới
            $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament, skill_level) VALUES (?,?,?,?,?)");
            if ($stmt->execute([$teamName, $player1, $player2, $tournament, $skillLevel])) {
                $message = '<div class="alert alert-success">Đã thêm đội thành công!</div>';
                
                // Reset form nếu cần
                if (isset($_POST['continue'])) {
                    $_POST = [];
                } else {
                    header("Location: draw.php");
                    exit;
                }
            } else {
                $message = '<div class="alert alert-danger">Lỗi khi thêm đội!</div>';
            }
        }
    }
}

$tournaments = $pdo->query("SELECT DISTINCT tournament FROM Teams WHERE tournament IS NOT NULL ORDER BY tournament")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm đội - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Open Sans', sans-serif;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: 50px;
        }
        .card-header {
            background: linear-gradient(135deg, #2ecc71, #ff6b00);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background: #2ecc71;
            border: none;
        }
        .btn-primary:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>THÊM ĐỘI THỦ CÔNG</h4>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Tên đội *</label>
                        <input type="text" class="form-control" name="team_name" value="<?php echo $_POST['team_name'] ?? ''; ?>" required>
                        <div class="form-text">Tên đội không được trùng với đội đã có</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">VĐV 1 *</label>
                            <input type="text" class="form-control" name="player1" value="<?php echo $_POST['player1'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">VĐV 2</label>
                            <input type="text" class="form-control" name="player2" value="<?php echo $_POST['player2'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giải đấu</label>
                            <select class="form-select" name="tournament">
                                <option value="">-- Chọn giải đấu --</option>
                                <?php foreach($tournaments as $tournament): ?>
                                <option value="<?php echo $tournament; ?>" <?php echo (($_POST['tournament'] ?? '') == $tournament) ? 'selected' : ''; ?>>
                                    <?php echo $tournament; ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="BMB Super Cup - Đôi Nam">BMB Super Cup - Đôi Nam</option>
                                <option value="Giải Đôi Nam Nữ (Mixed)">Giải Đôi Nam Nữ (Mixed)</option>
                                <option value="Giao Lưu Cuối Tuần">Giao Lưu Cuối Tuần</option>
                                <option value="Khác">Khác</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trình độ</label>
                            <select class="form-select" name="skill_level">
                                <option value="">-- Chọn trình độ --</option>
                                <option value="2.5" <?php echo (($_POST['skill_level'] ?? '') == '2.5') ? 'selected' : ''; ?>>2.5</option>
                                <option value="3.0" <?php echo (($_POST['skill_level'] ?? '') == '3.0') ? 'selected' : ''; ?>>3.0</option>
                                <option value="3.5" <?php echo (($_POST['skill_level'] ?? '') == '3.5') ? 'selected' : ''; ?>>3.5</option>
                                <option value="4.0" <?php echo (($_POST['skill_level'] ?? '') == '4.0') ? 'selected' : ''; ?>>4.0</option>
                                <option value="Open" <?php echo (($_POST['skill_level'] ?? '') == 'Open') ? 'selected' : ''; ?>>Open</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="save" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu đội
                        </button>
                        <button type="submit" name="continue" class="btn btn-outline-primary">
                            <i class="fas fa-save me-2"></i>Lưu và tiếp tục thêm
                        </button>
                        <a href="draw.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>