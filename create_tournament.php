<?php
// create_tournament.php
require_once 'db.php';

// Kiểm tra đăng nhập admin
if (empty($_SESSION['is_admin'])) {
    header("Location: admin.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $format = $_POST['format'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    if (empty($name)) {
        $message = '<div class="alert alert-danger">Vui lòng nhập tên giải đấu!</div>';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Tournaments (name, description, format, start_date, end_date, location, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, 
                $description, 
                $format, 
                $start_date ?: null, 
                $end_date ?: null, 
                $location, 
                $status
            ]);
            
            $tournamentId = $pdo->lastInsertId();
            
            // Tạo dữ liệu mẫu nếu được chọn
            if (isset($_POST['create_sample_data'])) {
                createSampleTournamentData($tournamentId, $format);
            }
            
            $message = '<div class="alert alert-success">Đã tạo giải đấu thành công! <a href="tournament_view.php?id=' . $tournamentId . '">Xem giải đấu</a></div>';
            
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Lỗi: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo giải đấu mới - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .card-header {
            background: linear-gradient(135deg, #2ecc71, #ff6b00);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
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
    <nav class="navbar navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="font-family: 'Montserrat', sans-serif; font-weight: 800; color: #ff6b00;">
                TRỌNG TÀI SỐ
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="tournament_list.php"><i class="fas fa-arrow-left me-1"></i> Quay lại</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>TẠO GIẢI ĐẤU MỚI</h4>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                
                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên giải đấu *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Thể thức thi đấu *</label>
                            <select class="form-select" name="format" required>
                                <option value="combined">Vòng bảng + Loại trực tiếp</option>
                                <option value="round_robin">Vòng tròn tính điểm</option>
                                <option value="knockout">Loại trực tiếp</option>
                                <option value="double_elimination">Loại kép</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Mô tả về giải đấu..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="date" class="form-control" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Địa điểm</label>
                            <input type="text" class="form-control" name="location" placeholder="Ví dụ: Cụm sân TRỌNG TÀI SỐ">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trạng thái *</label>
                            <select class="form-select" name="status" required>
                                <option value="upcoming">Sắp diễn ra</option>
                                <option value="ongoing">Đang diễn ra</option>
                                <option value="completed">Đã kết thúc</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_sample_data" id="create_sample_data">
                            <label class="form-check-label" for="create_sample_data">
                                Tạo dữ liệu mẫu (16 đội, 4 bảng, lịch thi đấu)
                            </label>
                            <div class="form-text">Tự động tạo đội và lịch thi đấu mẫu để demo</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>TẠO GIẢI ĐẤU
                        </button>
                        <a href="tournament_list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>HỦY
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Hàm tạo dữ liệu mẫu cho giải đấu
function createSampleTournamentData($tournamentId, $format) {
    global $pdo;
    
    // Tạo 16 đội mẫu
    $teams = [];
    $teamNames = [
        'BMB Đội 1', 'BMB Đội 2', 'BMB Đội 3', 'BMB Đội 4',
        'BMB Đội 5', 'BMB Đội 6', 'BMB Đội 7', 'BMB Đội 8',
        'BMB Đội 9', 'BMB Đội 10', 'BMB Đội 11', 'BMB Đội 12',
        'BMB Đội 13', 'BMB Đội 14', 'BMB Đội 15', 'BMB Đội 16'
    ];
    
    $players = [
        'Nguyễn Văn Anh', 'Trần Minh Bảo', 'Lê Công Danh', 'Phạm Đức Huy',
        'Hoàng Kim Long', 'Vũ Minh Quân', 'Đặng Quốc Thắng', 'Bùi Tuấn Kiệt',
        'Ngô Hữu Nghĩa', 'Đỗ Xuân Phong', 'Mai Quang Hải', 'Lý Văn Sơn',
        'Trịnh Hoàng Nam', 'Cao Tiến Dũng', 'Vương Mạnh Cường', 'Lưu Đình Trọng'
    ];
    
    $skillLevels = ['2.5', '3.0', '3.5', '4.0'];
    
    foreach ($teamNames as $index => $teamName) {
        $player1 = $players[$index];
        $player2 = $players[($index + 1) % count($players)];
        $skillLevel = $skillLevels[$index % count($skillLevels)];
        $seed = $index + 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO Teams (tournament_id, team_name, player1, player2, skill_level, seed) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tournamentId, $teamName, $player1, $player2, $skillLevel, $seed]);
        
        $teams[] = $pdo->lastInsertId();
    }
    
    // Nếu là thể thức có vòng bảng, tạo bảng đấu
    if ($format != 'knockout') {
        // Tạo 4 bảng A, B, C, D
        $groups = [];
        foreach (['A', 'B', 'C', 'D'] as $groupName) {
            $stmt = $pdo->prepare("INSERT INTO Groups (tournament_id, group_name) VALUES (?, ?)");
            $stmt->execute([$tournamentId, $groupName]);
            $groupId = $pdo->lastInsertId();
            $groups[$groupName] = $groupId;
            
            // Phân đội vào bảng (4 đội mỗi bảng)
            $groupTeams = array_slice($teams, (ord($groupName) - 65) * 4, 4);
            foreach ($groupTeams as $teamId) {
                $pdo->prepare("UPDATE Teams SET group_name = ? WHERE id = ?")
                    ->execute([$groupName, $teamId]);
            }
            
            // Tạo lịch thi đấu vòng tròn cho bảng
            if (count($groupTeams) >= 2) {
                createRoundRobinMatches($tournamentId, $groupId, $groupTeams);
            }
        }
    }
    
    // Nếu là thể thức loại trực tiếp, tạo bracket
    if ($format == 'knockout' || $format == 'combined') {
        createKnockoutBracket($tournamentId, $teams);
    }
}

function createRoundRobinMatches($tournamentId, $groupId, $teamIds) {
    global $pdo;
    
    $n = count($teamIds);
    $rounds = $n - 1;
    
    for ($round = 1; $round <= $rounds; $round++) {
        for ($i = 0; $i < $n / 2; $i++) {
            $team1 = $teamIds[$i];
            $team2 = $teamIds[$n - 1 - $i];
            
            $matchDate = date('Y-m-d H:i:s', strtotime("+{$round} days"));
            
            $stmt = $pdo->prepare("
                INSERT INTO Matches (tournament_id, team1_id, team2_id, group_id, round, match_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tournamentId, 
                $team1, 
                $team2, 
                $groupId, 
                "Vòng {$round}", 
                $matchDate
            ]);
        }
        
        // Xoay vòng
        $last = array_pop($teamIds);
        array_splice($teamIds, 1, 0, $last);
    }
}

function createKnockoutBracket($tournamentId, $teamIds) {
    global $pdo;
    
    // Số đội phải là lũy thừa của 2 (8, 16, 32...)
    $count = count($teamIds);
    if ($count < 2) return;
    
    // Tạo các trận đấu loại trực tiếp
    shuffle($teamIds);
    
    // Xác định các vòng
    if ($count >= 8) {
        // Có tứ kết
        createKnockoutRound($tournamentId, $teamIds, 'quarter', 'Tứ kết');
    } elseif ($count >= 4) {
        // Chỉ có bán kết
        createKnockoutRound($tournamentId, $teamIds, 'semi', 'Bán kết');
    }
}

function createKnockoutRound($tournamentId, $teamIds, $type, $roundName) {
    global $pdo;
    
    $matchDate = date('Y-m-d H:i:s', strtotime('+1 week'));
    
    for ($i = 0; $i < count($teamIds); $i += 2) {
        if (isset($teamIds[$i + 1])) {
            $stmt = $pdo->prepare("
                INSERT INTO Matches (tournament_id, team1_id, team2_id, match_type, round, match_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tournamentId, 
                $teamIds[$i], 
                $teamIds[$i + 1], 
                $type, 
                $roundName, 
                $matchDate
            ]);
        }
    }
}
?>