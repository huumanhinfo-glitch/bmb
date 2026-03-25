<?php
// draw.php - Phiên bản sửa lỗi
require_once 'functions.php';

$message = '';
$excelData = [];
$previewMode = false;
$activeTab = 'import';

// Xử lý POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Import CSV
    if (isset($_FILES['excel']) && $_FILES['excel']['error'] === UPLOAD_ERR_OK) {
        $activeTab = 'import';
        $tmp = $_FILES['excel']['tmp_name'];
        $fileType = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
        
        if ($fileType === 'csv') {
            $excelData = parseCSVFile($tmp);
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>Chỉ hỗ trợ file CSV!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        }
        
        if (empty($excelData)) {
            $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>File không có dữ liệu hợp lệ!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        } else {
            $previewMode = true;
            $message = '<div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>Đã load ' . count($excelData) . ' đội từ file. Kiểm tra và xác nhận import.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        }
    }
    
    // Confirm import
    elseif (isset($_POST['confirm_import']) && isset($_POST['teams_data'])) {
        $activeTab = 'teams';
        $teamsData = json_decode($_POST['teams_data'], true);
        $imported = 0;
        $skipped = 0;
        
        foreach ($teamsData as $team) {
            // Kiểm tra trùng tên đội trong cùng giải đấu
            $checkStmt = $pdo->prepare("SELECT id FROM Teams WHERE team_name = ? AND tournament_id = ?");
            $checkStmt->execute([$team['team_name'], $team['tournament_id'] ?? null]);
            
            if ($checkStmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // Insert đội mới
            $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?,?,?,?,?)");
            $stmt->execute([
                $team['team_name'],
                $team['player1'],
                $team['player2'],
                $team['tournament_id'] ?? null,
                $team['skill_level'] ?? null
            ]);
            $imported++;
        }
        
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Import thành công! ' . $imported . ' đội đã được thêm, ' . $skipped . ' đội bị bỏ qua do trùng tên.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
    }
    
    // Bốc thăm chia bảng
    elseif (isset($_POST['action']) && $_POST['action'] === 'draw') {
        $activeTab = 'groups';
        $numGroups = intval($_POST['num_groups'] ?? 4);
        $tournamentId = intval($_POST['tournament_filter'] ?? 0);
        $drawType = $_POST['draw_type'] ?? 'round_robin';
        $knockoutTeams = intval($_POST['knockout_teams'] ?? 4);
        
        if ($numGroups < 1) $numGroups = 4;
        
        if ($tournamentId <= 0) {
            $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>Vui lòng chọn giải đấu!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        } else {
            // Kiểm tra giải đấu có tồn tại không
            $checkTournament = $pdo->prepare("SELECT id, name FROM Tournaments WHERE id = ?");
            $checkTournament->execute([$tournamentId]);
            $tournament = $checkTournament->fetch();
            
            if (!$tournament) {
                $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle me-2"></i>Giải đấu không tồn tại!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
            } else {
                // Xóa bảng cũ và tạo mới theo loại
                if ($drawType === 'knockout') {
                    createKnockoutMatches($tournamentId, $knockoutTeams);
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Đã tạo vòng loại trực tiếp với ' . $knockoutTeams . ' đội cho giải đấu: ' . htmlspecialchars($tournament['name']) . '.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
                } elseif ($drawType === 'group_knockout') {
                    createGroupAndKnockout($numGroups, $tournamentId, $knockoutTeams);
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Đã chia ' . $numGroups . ' bảng và tạo vòng đánh loại với ' . $knockoutTeams . ' đội cho giải: ' . htmlspecialchars($tournament['name']) . '.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
                } else {
                    createGroupsAndMatches($numGroups, null, $tournamentId);
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Đã bốc thăm thành công! Tạo ' . $numGroups . ' bảng đấu vòng tròn cho giải đấu: ' . htmlspecialchars($tournament['name']) . '.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
                }
            }
        }
    }
    
    // Tạo giải đấu mẫu
    elseif (isset($_POST['action']) && $_POST['action'] === 'create_sample') {
        $activeTab = 'teams';
        
        // Kiểm tra xem hàm đã tồn tại chưa để tránh lỗi redeclare
        if (!function_exists('createSampleDataInDraw')) {
            function createSampleDataInDraw() {
                global $pdo;
                
                try {
                    // Tạo 3 giải đấu mẫu
                    $tournaments = [
                        ['BMB Super Cup 2024', 'combined', 'upcoming'],
                        ['Giải Mixed Doubles', 'mixed', 'upcoming'],
                        ['Weekend Giao Lưu', 'social', 'ongoing']
                    ];
                    
                    $tournamentIds = [];
                    foreach ($tournaments as $tournament) {
                        $stmt = $pdo->prepare("INSERT INTO Tournaments (name, format, status) VALUES (?, ?, ?)");
                        $stmt->execute($tournament);
                        $tournamentIds[] = $pdo->lastInsertId();
                    }
                    
                    // Tạo 24 đội mẫu
                    $players = [
                        'Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C', 'Phạm Thị D',
                        'Hoàng Văn E', 'Vũ Thị F', 'Đặng Văn G', 'Bùi Thị H',
                        'Mai Văn I', 'Lý Thị J', 'Trịnh Văn K', 'Đỗ Thị L'
                    ];
                    
                    $skillLevels = ['2.5', '3.0', '3.5', '4.0', '4.5', '5.0'];
                    
                    for ($i = 1; $i <= 24; $i++) {
                        $tournamentId = $tournamentIds[array_rand($tournamentIds)];
                        $player1 = $players[array_rand($players)];
                        $player2 = $players[array_rand($players)];
                        $skill = $skillLevels[array_rand($skillLevels)];
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $teamName = "Team" . str_pad($i, 2, '0', STR_PAD_LEFT);
                        $stmt->execute([$teamName, $player1, $player2, $tournamentId, $skill]);
                    }
                    
                    return true;
                } catch (Exception $e) {
                    error_log("Lỗi tạo dữ liệu mẫu: " . $e->getMessage());
                    return false;
                }
            }
        }
        
        if (createSampleDataInDraw()) {
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Đã tạo dữ liệu mẫu với 3 giải đấu và 24 đội!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-times-circle me-2"></i>Lỗi khi tạo dữ liệu mẫu!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        }
    }
    
    // Xóa dữ liệu
    elseif (isset($_POST['action']) && $_POST['action'] === 'clear_data') {
        $dataType = $_POST['data_type'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            switch ($dataType) {
                case 'teams':
                    $pdo->exec("DELETE FROM Teams");
                    $msg = "Đã xóa tất cả đội";
                    break;
                    
                case 'groups':
                    $pdo->exec("DELETE FROM Matches");
                    $pdo->exec("DELETE FROM Groups");
                    $msg = "Đã xóa tất cả bảng và trận đấu";
                    break;
                    
                case 'matches':
                    $pdo->exec("DELETE FROM Matches");
                    $msg = "Đã xóa tất cả trận đấu";
                    break;
                    
                case 'all':
                    $pdo->exec("DELETE FROM Matches");
                    $pdo->exec("DELETE FROM Groups");
                    $pdo->exec("DELETE FROM Teams");
                    $pdo->exec("DELETE FROM Tournaments");
                    $msg = "Đã xóa tất cả dữ liệu";
                    break;
                    
                default:
                    throw new Exception("Loại dữ liệu không hợp lệ");
            }
            
            $pdo->commit();
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>' . $msg . '!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-times-circle me-2"></i>Lỗi xóa dữ liệu: ' . $e->getMessage() . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        }
    }
}

// Xử lý GET parameters cho tab
if (isset($_GET['tab'])) {
    $activeTab = $_GET['tab'];
}

// Lấy dữ liệu
$teams = fetchAllTeams();
$tournaments = getAllTournaments();
$groups = $pdo->query("SELECT g.*, t.name as tournament_name FROM Groups g LEFT JOIN Tournaments t ON g.tournament_id = t.id ORDER BY t.name, g.group_name")->fetchAll();

// Tính thống kê
$totalTeams = count($teams);
$totalTournaments = count($tournaments);
$totalGroups = count($groups);
$totalMatches = $pdo->query("SELECT COUNT(*) FROM Matches")->fetchColumn();

/**
 * Parse CSV file - Đổi tên để tránh trùng với functions.php
 */
function parseCSVFileInDraw($filePath) {
    global $pdo;
    
    $data = [];
    $row = 0;
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $isFirstRow = true;
        
        while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            
            // Skip empty rows
            if (empty(trim(implode('', $rowData)))) {
                continue;
            }
            
            // Check if first row is header
            if ($isFirstRow) {
                $firstCell = strtolower(trim($rowData[0]));
                if (in_array($firstCell, ['team_name', 'tên đội', 'team', 'đội'])) {
                    $isFirstRow = false;
                    continue;
                }
            }
            
            // Ensure we have at least team name
            if (!empty(trim($rowData[0]))) {
                $teamName = trim($rowData[0]);
                $player1 = trim($rowData[1] ?? '');
                $player2 = trim($rowData[2] ?? '');
                $tournamentName = trim($rowData[3] ?? '');
                $skillLevel = trim($rowData[4] ?? '');
                
                // Tìm tournament_id từ tên giải đấu
                $tournamentId = null;
                if (!empty($tournamentName)) {
                    $stmt = $pdo->prepare("SELECT id FROM Tournaments WHERE name LIKE ?");
                    $stmt->execute(["%$tournamentName%"]);
                    $tournament = $stmt->fetch();
                    $tournamentId = $tournament['id'] ?? null;
                    
                    // Nếu không tìm thấy, tạo giải đấu mới
                    if (!$tournamentId) {
                        $stmt = $pdo->prepare("INSERT INTO Tournaments (name, format, status) VALUES (?, 'combined', 'upcoming')");
                        $stmt->execute([$tournamentName]);
                        $tournamentId = $pdo->lastInsertId();
                    }
                }
                
                $data[] = [
                    'row_num' => $row,
                    'team_name' => $teamName,
                    'player1' => $player1,
                    'player2' => $player2,
                    'tournament_name' => $tournamentName,
                    'tournament_id' => $tournamentId,
                    'skill_level' => $skillLevel,
                ];
            }
        }
        fclose($handle);
    }
    
    return $data;
}

// Sử dụng hàm đã đổi tên
if (isset($tmp) && !empty($tmp)) {
    $excelData = parseCSVFileInDraw($tmp);
}

// Helper function for tournament badge colors
function getTournamentBadgeClass($tournamentName) {
    if (!$tournamentName) return 'bg-secondary';
    
    $tournamentName = strtolower($tournamentName);
    if (strpos($tournamentName, 'super cup') !== false) return 'bg-primary';
    if (strpos($tournamentName, 'mixed') !== false) return 'bg-success';
    if (strpos($tournamentName, 'giao lưu') !== false || strpos($tournamentName, 'weekend') !== false) return 'bg-warning';
    
    return 'bg-info';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Bốc thăm & Import - TRỌNG TÀI SỐ</title>
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
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--accent) !important;
        }
        
        .nav-link {
            font-weight: 600;
            color: var(--text-dark) !important;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--accent) !important;
        }
        
        .page-header {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://ppatour.com/wp-content/uploads/2023/12/TX-Open-DJI-Watermarked-scaled-1.webp?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .page-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.2);
            padding: 6px;
            border-radius: 15px;
            min-width: 60px;
            text-align: center;
            backdrop-filter: blur(5px);
        }
        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            font-weight: 200;
            margin-bottom: 3px;
        }
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        .nav-tabs-custom {
            background: white;
            border-radius: 15px;
            padding: 0 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-dark);
            font-weight: 600;
            padding: 20px 25px;
            border-radius: 0;
            margin: 0;
            position: relative;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--accent);
            background: transparent;
        }
        
        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #ff5200;
        }
        
        .tournament-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-dark);
            display: block;
        }
        
        .tournament-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            color: var(--text-dark);
        }
        
        .tournament-name {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 8px;
        }
        
        .tournament-info {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 5px;
        }
        
        .upload-zone {
            border: 3px dashed #ff5200;
            border-radius: 15px;
            padding: 60px 30px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .upload-zone:hover {
            border-color: var(--accent);
            background: rgba(46, 204, 113, 0.05);
        }
        
        .group-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--accent);
        }
        
        .group-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 15px;
            font-size: 1.4rem;
        }
        
        .team-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.3s;
        }
        
        .team-item:hover {
            background: rgba(46, 204, 113, 0.1);
        }
        
        .team-item:last-child {
            border-bottom: none;
        }
        
        .team-rank {
            width: 40px;
            text-align: center;
            font-weight: 700;
            color: var(--accent);
        }
        
        .team-name {
            flex: 1;
            font-weight: 600;
        }
        
        .team-players {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 2px;
        }
        
        .skill-badge {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .form-select-lg, .form-control-lg {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 15px;
            font-size: 1.1rem;
        }
        
        .form-select-lg:focus, .form-control-lg:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
        }
        
        .btn-primary-custom {
            background: var(--accent);
            border: none;
            color: white;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 25px 20px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-dark);
            display: block;
            cursor: pointer;
        }
        
        .action-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            color: var(--text-dark);
        }
        
        .action-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .action-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .action-desc {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .filter-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .page-stats {
                gap: 15px;
            }
            
            .stat-box {
                padding: 15px;
                min-width: 120px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 15px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
 <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm ">
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
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="page-title">QUẢN LÝ DANH SÁCH VĐV </br>& CHIA BẢNG ĐẤU</h1>
                    <p class="lead" style="font-size: 1.2rem; opacity: 0.9;">
                        Nhập danh sách VĐV bằng Excel - TỰ ĐỘNG BỐC THĂM / CHIA BẢNG
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-file-import me-1"></i>Import CSV
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-random me-1"></i>Chia bảng tự động
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar-alt me-1"></i>Tạo lịch thi đấu
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-users me-1"></i>Quản lý đội
                        </span>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="page-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $totalTeams; ?></div>
                            <div class="stat-label">Đội hiện có</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $totalTournaments; ?></div>
                            <div class="stat-label">Giải đấu</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $totalGroups; ?></div>
                            <div class="stat-label">Bảng đấu</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>
        
        <!-- Navigation Tabs -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs border-0" id="drawTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeTab === 'import') ? 'active' : ''; ?>" 
                       data-bs-toggle="tab" href="#importTab" onclick="updateUrlTab('import')">
                        <i class="fas fa-file-import me-2"></i>IMPORT CSV
                        <?php if ($previewMode && !empty($excelData)): ?>
                        <span class="badge bg-warning ms-1"><?php echo count($excelData); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeTab === 'draw') ? 'active' : ''; ?>" 
                       data-bs-toggle="tab" href="#drawTab" onclick="updateUrlTab('draw')">
                        <i class="fas fa-random me-2"></i>BỐC THĂM CHIA BẢNG
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeTab === 'teams') ? 'active' : ''; ?>" 
                       data-bs-toggle="tab" href="#teamsTab" onclick="updateUrlTab('teams')">
                        <i class="fas fa-users me-2"></i>DANH SÁCH ĐỘI
                        <span class="badge bg-primary ms-1"><?php echo $totalTeams; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeTab === 'groups') ? 'active' : ''; ?>" 
                       data-bs-toggle="tab" href="#groupsTab" onclick="updateUrlTab('groups')">
                        <i class="fas fa-layer-group me-2"></i>BẢNG ĐẤU
                        <span class="badge bg-primary ms-1"><?php echo $totalGroups; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeTab === 'tools') ? 'active' : ''; ?>" 
                       data-bs-toggle="tab" href="#toolsTab" onclick="updateUrlTab('tools')">
                        <i class="fas fa-tools me-2"></i>CÔNG CỤ
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- Tab 1: Import CSV -->
            <div class="tab-pane fade <?php echo ($activeTab === 'import') ? 'show active' : ''; ?>" id="importTab">
                <div class="section-card">
                    <h3 class="section-title">IMPORT DANH SÁCH ĐỘI TỪ FILE CSV</h3>
                    
                    <?php if (!$previewMode): ?>
                    <div class="row">
                        <div class="col-lg-8">
                            <form method="post" enctype="multipart/form-data" id="uploadForm">
                                <div class="upload-zone mb-4" id="dropZone">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="mb-2">Kéo thả hoặc click để upload file CSV</h5>
                                    <p class="text-muted mb-4">Định dạng: Tên đội, VĐV1, VĐV2, Giải đấu, Trình độ</p>
                                    <input type="file" class="form-control d-none" name="excel" id="fileInput" accept=".csv" required>
                                    <button type="button" class="btn btn-primary-custom" onclick="document.getElementById('fileInput').click()">
                                        <i class="fas fa-folder-open me-2"></i>CHỌN FILE CSV
                                    </button>
                                </div>
                                
                                <div id="fileName" class="mb-4"></div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary-custom btn-lg py-3">
                                        <i class="fas fa-upload me-2"></i>UPLOAD & PREVIEW
                                    </button>
                                </div>
                            </form>
                            
                            <div class="mt-5">
                                <h5><i class="fas fa-info-circle me-2 text-primary"></i>HƯỚNG DẪN ĐỊNH DẠNG FILE CSV</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tên đội</th>
                                                <th>VĐV 1</th>
                                                <th>VĐV 2</th>
                                                <th>Giải đấu</th>
                                                <th>Trình độ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Team01</td>
                                                <td>Nguyễn Văn A</td>
                                                <td>Trần Thị B</td>
                                                <td>BMB Super Cup</td>
                                                <td>3.0</td>
                                            </tr>
                                            <tr>
                                                <td>Team02</td>
                                                <td>Lê Văn C</td>
                                                <td>Phạm Thị D</td>
                                                <td>Giải Mixed</td>
                                                <td>3.5</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-lightbulb text-warning me-2"></i>TIPS
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-3">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong>File CSV chuẩn:</strong> Dùng Excel hoặc Google Sheets
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong>Dấu phân cách:</strong> Dùng dấu phẩy (,)
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong>Giải đấu mới:</strong> Hệ thống sẽ tự tạo giải mới nếu chưa có
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong>Trình độ:</strong> 2.5, 3.0, 3.5, 4.0, 4.5, 5.0, Open
                                        </li>
                                        <li>
                                            <i class="fas fa-check text-success me-2"></i>
                                            <strong>Preview trước:</strong> Xem trước dữ liệu trước khi import
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Preview CSV Data -->
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">
                                <i class="fas fa-eye me-2"></i>PREVIEW DỮ LIỆU (<?php echo count($excelData); ?> đội)
                            </h4>
                            <span class="badge bg-primary fs-6">Kiểm tra và xác nhận</span>
                        </div>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tên đội</th>
                                        <th>VĐV 1</th>
                                        <th>VĐV 2</th>
                                        <th>Giải đấu</th>
                                        <th>Trình độ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($excelData as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['team_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['player1']); ?></td>
                                        <td><?php echo htmlspecialchars($item['player2']); ?></td>
                                        <td>
                                            <?php if($item['tournament_name']): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($item['tournament_name']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($item['skill_level']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($item['skill_level']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <form method="post" class="w-100">
                                    <input type="hidden" name="teams_data" value='<?php echo json_encode($excelData, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="confirm_import" class="btn btn-primary-custom btn-lg py-3">
                                            <i class="fas fa-check me-2"></i>XÁC NHẬN IMPORT
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-lg-6">
                                <div class="d-grid gap-2">
                                    <a href="draw.php?tab=import" class="btn btn-outline-custom btn-lg py-3">
                                        <i class="fas fa-times me-2"></i>HỦY & QUAY LẠI
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 2: Bốc thăm -->
            <div class="tab-pane fade <?php echo ($activeTab === 'draw') ? 'show active' : ''; ?>" id="drawTab">
                <div class="section-card">
                    <h3 class="section-title">CHIA BẢNG TỰ ĐỘNG</h3>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <form method="post">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-2">
                                            <i class="fas fa-trophy me-2"></i>CHỌN GIẢI ĐẤU
                                        </label>
                                        <select class="form-select form-select-lg" name="tournament_filter" required>
                                            <option value="">-- Chọn giải đấu để bốc thăm --</option>
                                            <?php foreach($tournaments as $tournament): ?>
                                            <option value="<?php echo $tournament['id']; ?>">
                                                <?php echo htmlspecialchars($tournament['name']); ?>
                                                <?php if($tournament['status'] == 'upcoming'): ?>
                                                <span class="badge bg-info float-end">Sắp diễn ra</span>
                                                <?php elseif($tournament['status'] == 'ongoing'): ?>
                                                <span class="badge bg-success float-end">Đang diễn ra</span>
                                                <?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-2">
                                            <i class="fas fa-layer-group me-2"></i>HÌNH THỨC CHIA BẢNG
                                        </label>
                                        <select class="form-select form-select-lg" name="draw_type" id="drawType" required onchange="toggleGroupOptions()">
                                            <option value="round_robin">Vòng tròn (Round Robin)</option>
                                            <option value="group_knockout">Chia bảng + Đánh loại</option>
                                            <option value="knockout">Loại trực tiếp (Knockout)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-4" id="groupOptions">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-2">
                                            <i class="fas fa-layer-group me-2"></i>SỐ LƯỢNG BẢNG
                                        </label>
                                        <input type="number" class="form-control form-control-lg" name="num_groups" value="4" min="1" max="8" required>
                                        <small class="text-muted">Hệ thống sẽ tự động chia đều đội vào các bảng</small>
                                    </div>
                                    <div class="col-md-6" id="knockoutTeamsDiv" style="display:none;">
                                        <label class="form-label fw-bold mb-2">
                                            <i class="fas fa-users me-2"></i>SỐ ĐỘI VÀO ĐÁNH LOẠI
                                        </label>
                                        <select class="form-select form-select-lg" name="knockout_teams">
                                            <option value="4">4 đội</option>
                                            <option value="8">8 đội</option>
                                            <option value="16">16 đội</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <script>
                                function toggleGroupOptions() {
                                    var drawType = document.getElementById('drawType').value;
                                    var groupOptions = document.getElementById('groupOptions');
                                    var knockoutTeamsDiv = document.getElementById('knockoutTeamsDiv');
                                    
                                    if (drawType === 'knockout') {
                                        groupOptions.style.display = 'none';
                                    } else if (drawType === 'group_knockout') {
                                        groupOptions.style.display = 'flex';
                                        knockoutTeamsDiv.style.display = 'block';
                                    } else {
                                        groupOptions.style.display = 'flex';
                                        knockoutTeamsDiv.style.display = 'none';
                                    }
                                }
                                </script>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Lưu ý:</strong> Việc này sẽ xóa toàn bộ bảng đấu cũ và tạo bảng mới cho giải đã chọn.
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" name="action" value="draw" class="btn btn-primary-custom btn-lg py-3">
                                        <i class="fas fa-random me-2"></i>TIẾN HÀNH CHIA BẢNG
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-cogs text-primary me-2"></i>THUẬT TOÁN BỐC THĂM / CHIA BẢNG
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Xáo trộn ngẫu nhiên</strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Chia đều đội vào bảng</strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Tạo lịch thi đấu vòng tròn</strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Đảm bảo mỗi đội đấu đủ các đội khác trong bảng</strong>
                                        </li>
                                        <li>
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Tự động đặt tên bảng A, B, C...</strong>
                                        </li>
                                    </ul>
                                    
                                    <div class="mt-4">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-calculator me-2"></i>TÍNH TOÁN SỐ TRẬN
                                        </h6>
                                        <p class="mb-1">Số trận = n * (n-1) / 2</p>
                                        <small class="text-muted">Với n là số đội trong bảng</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Teams -->
            <div class="tab-pane fade <?php echo ($activeTab === 'teams') ? 'show active' : ''; ?>" id="teamsTab">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">DANH SÁCH ĐỘI HIỆN CÓ</h3>
                        <span class="badge bg-primary fs-6">Tổng: <?php echo $totalTeams; ?> đội</span>
                    </div>
                    
                    <?php if (empty($teams)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>Chưa có đội nào trong hệ thống</h4>
                        <p>Hãy import từ CSV hoặc tạo dữ liệu mẫu!</p>
                        <div class="mt-4">
                            <a href="#importTab" class="btn btn-primary-custom me-2" onclick="switchTab('importTab')">
                                <i class="fas fa-file-import me-2"></i>Import CSV
                            </a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="create_sample">
                                <button type="submit" class="btn btn-outline-custom">
                                    <i class="fas fa-magic me-2"></i>Tạo dữ liệu mẫu
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="filter-container mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-filter me-2"></i>Lọc theo giải đấu
                                </label>
                                <select class="form-select" id="tournamentFilter" onchange="filterTeams()">
                                    <option value="">Tất cả giải đấu</option>
                                    <?php foreach($tournaments as $tournament): ?>
                                    <option value="<?php echo $tournament['id']; ?>">
                                        <?php echo htmlspecialchars($tournament['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-search me-2"></i>Tìm kiếm
                                </label>
                                <input type="text" class="form-control" id="teamSearch" placeholder="Tên đội hoặc VĐV..." onkeyup="filterTeams()">
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary" onclick="exportToCSV()">
                                    <i class="fas fa-download me-2"></i>Xuất CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="teamsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Tên đội</th>
                                    <th>VĐV 1</th>
                                    <th>VĐV 2</th>
                                    <th>Giải đấu</th>
                                    <th>Trình độ</th>
                                    <th>Bảng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($teams as $index => $team): ?>
                                <tr data-tournament="<?php echo $team['tournament_id']; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($team['player1']); ?></td>
                                    <td><?php echo htmlspecialchars($team['player2']); ?></td>
                                    <td>
                                        <?php if($team['tournament_name']): ?>
                                        <span class="badge <?php echo getTournamentBadgeClass($team['tournament_name']); ?>">
                                            <?php echo htmlspecialchars($team['tournament_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($team['skill_level']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($team['skill_level']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($team['group_name']): ?>
                                        <span class="badge bg-warning">Bảng <?php echo $team['group_name']; ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Chưa xếp bảng</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <span class="text-muted">Hiển thị <?php echo count($teams); ?> đội</span>
                        </div>
                        <div>
                            <button class="btn btn-outline-custom" onclick="printTeams()">
                                <i class="fas fa-print me-2"></i>In danh sách
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 4: Groups -->
            <div class="tab-pane fade <?php echo ($activeTab === 'groups') ? 'show active' : ''; ?>" id="groupsTab">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">CÁC BẢNG ĐẤU HIỆN CÓ</h3>
                        <span class="badge bg-primary fs-6">Tổng: <?php echo $totalGroups; ?> bảng</span>
                    </div>
                    
                    <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <h4>Chưa có bảng đấu nào</h4>
                        <p>Hãy bốc thăm để tạo bảng đấu tự động!</p>
                        <a href="#drawTab" class="btn btn-primary-custom mt-3" onclick="switchTab('drawTab')">
                            <i class="fas fa-random me-2"></i>CHIA BẢNG NGAY
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="filter-container mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-filter me-2"></i>Lọc theo giải đấu
                                </label>
                                <select class="form-select" id="groupTournamentFilter" onchange="filterGroups()">
                                    <option value="">Tất cả giải đấu</option>
                                    <?php 
                                    $uniqueTournaments = [];
                                    foreach ($groups as $group) {
                                        $tournamentName = $group['tournament_name'] ?? 'Không xác định';
                                        $tournamentId = $group['tournament_id'] ?? 0;
                                        if (!isset($uniqueTournaments[$tournamentId])) {
                                            $uniqueTournaments[$tournamentId] = $tournamentName;
                                        }
                                    }
                                    
                                    foreach ($uniqueTournaments as $id => $name):
                                    ?>
                                    <option value="<?php echo $id; ?>">
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-primary" onclick="exportGroupsToCSV()">
                                    <i class="fas fa-download me-2"></i>Xuất bảng đấu
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion" id="groupsAccordion">
                        <?php 
                        // Nhóm các bảng theo giải đấu
                        $groupsByTournament = [];
                        foreach ($groups as $group) {
                            $tournamentName = $group['tournament_name'] ?? 'Chưa xác định';
                            $tournamentId = $group['tournament_id'] ?? 0;
                            if (!isset($groupsByTournament[$tournamentId])) {
                                $groupsByTournament[$tournamentId] = [
                                    'name' => $tournamentName,
                                    'groups' => []
                                ];
                            }
                            $groupsByTournament[$tournamentId]['groups'][] = $group;
                        }
                        
                        $accordionIndex = 0;
                        foreach ($groupsByTournament as $tournamentId => $tournamentData): 
                        ?>
                        <div class="accordion-item mb-3" data-tournament="<?php echo $tournamentId; ?>">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php echo $accordionIndex === 0 ? '' : 'collapsed'; ?>" 
                                        type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $accordionIndex; ?>">
                                    <i class="fas fa-trophy me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($tournamentData['name']); ?>
                                    <span class="badge bg-primary ms-2"><?php echo count($tournamentData['groups']); ?> bảng</span>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $accordionIndex; ?>" 
                                 class="accordion-collapse collapse <?php echo $accordionIndex === 0 ? 'show' : ''; ?>" 
                                 data-bs-parent="#groupsAccordion">
                                <div class="accordion-body">
                                    <div class="row">
                                        <?php foreach($tournamentData['groups'] as $group): 
                                            // Lấy đội trong bảng
                                            $stmt = $pdo->prepare("
                                                SELECT DISTINCT t.* 
                                                FROM Teams t
                                                JOIN Matches m ON (t.id = m.team1_id OR t.id = m.team2_id)
                                                WHERE m.group_id = ?
                                                ORDER BY t.team_name
                                            ");
                                            $stmt->execute([$group['id']]);
                                            $groupTeams = $stmt->fetchAll();
                                        ?>
                                        <div class="col-lg-6 mb-4">
                                            <div class="group-card">
                                                <h4 class="group-title">
                                                    <i class="fas fa-chess-board me-2"></i>BẢNG <?php echo $group['group_name']; ?>
                                                    <span class="badge bg-primary float-end"><?php echo count($groupTeams); ?> đội</span>
                                                </h4>
                                                
                                                <div class="team-list">
                                                    <?php foreach($groupTeams as $index => $team): ?>
                                                    <div class="team-item">
                                                        <div class="team-rank">#<?php echo $index + 1; ?></div>
                                                        <div class="flex-grow-1">
                                                            <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                                            <div class="team-players">
                                                                <?php echo htmlspecialchars($team['player1']); ?> 
                                                                <?php if ($team['player2']): ?> & <?php echo htmlspecialchars($team['player2']); ?><?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <?php if ($team['skill_level']): ?>
                                                            <span class="skill-badge"><?php echo $team['skill_level']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <a href="matches.php?group=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                        <i class="fas fa-eye me-1"></i>Xem trận đấu
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                        $accordionIndex++;
                        endforeach; 
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 5: Tools -->
            <div class="tab-pane fade <?php echo ($activeTab === 'tools') ? 'show active' : ''; ?>" id="toolsTab">
                <div class="section-card">
                    <h3 class="section-title">CÔNG CỤ & HÀNH ĐỘNG NHANH</h3>
                    
                    <div class="row mb-5">
                        <div class="col-md-4">
                            <div class="action-card" onclick="switchTab('importTab')">
                                <div class="action-icon">
                                    <i class="fas fa-file-import"></i>
                                </div>
                                <div class="action-title">Import CSV</div>
                                <div class="action-desc">Thêm đội từ file CSV</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="action-card" onclick="switchTab('drawTab')">
                                <div class="action-icon">
                                    <i class="fas fa-random"></i>
                                </div>
                                <div class="action-title">Bốc thăm</div>
                                <div class="action-desc">Chia bảng tự động</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <form method="post" class="h-100">
                                <input type="hidden" name="action" value="create_sample">
                                <button type="submit" class="action-card w-100 h-100 border-0 bg-transparent">
                                    <div class="action-icon">
                                        <i class="fas fa-magic"></i>
                                    </div>
                                    <div class="action-title">Dữ liệu mẫu</div>
                                    <div class="action-desc">Tạo dữ liệu demo</div>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-download text-success me-2"></i>Xuất dữ liệu
                                    </h5>
                                    <div class="d-grid gap-2 mt-3">
                                        <button class="btn btn-outline-success" onclick="exportToCSV()">
                                            <i class="fas fa-users me-2"></i>Xuất danh sách đội
                                        </button>
                                        <button class="btn btn-outline-info" onclick="exportGroupsToCSV()">
                                            <i class="fas fa-layer-group me-2"></i>Xuất danh sách bảng
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="window.location.href='draw.php?export=matches&format=csv'">
                                            <i class="fas fa-basketball-ball me-2"></i>Xuất lịch thi đấu
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-trash text-danger me-2"></i>Xóa dữ liệu
                                    </h5>
                                    <p class="text-muted small mb-3">Các thao tác xóa sẽ xóa vĩnh viễn dữ liệu.</p>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-danger" onclick="confirmDelete('teams')">
                                            <i class="fas fa-users-slash me-2"></i>Xóa tất cả đội
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="confirmDelete('groups')">
                                            <i class="fas fa-layer-group me-2"></i>Xóa tất cả bảng
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="confirmDelete('all')">
                                            <i class="fas fa-bomb me-2"></i>Xóa tất cả dữ liệu
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-top mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <a href="index.php" class="btn btn-outline-primary mb-3">
                        <i class="fas fa-arrow-left me-2"></i>Quay về trang chủ
                    </a>
                    <p class="text-muted small mb-0">&copy; 2026 TRỌNG TÀI SỐ. Quản lý bốc thăm & import.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Drag and drop file upload
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        
        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput.click());
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropZone.style.borderColor = '#2ecc71';
                dropZone.style.backgroundColor = 'rgba(46, 204, 113, 0.1)';
            }
            
            function unhighlight() {
                dropZone.style.borderColor = '#e2e8f0';
                dropZone.style.backgroundColor = '#f8fafc';
            }
            
            dropZone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                updateFileName();
            }
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', updateFileName);
        }
        
        function updateFileName() {
            if (fileInput.files.length) {
                fileName.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-file me-2"></i>
                        File đã chọn: <strong>${fileInput.files[0].name}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }
        }
        
        // Switch tab function
        function switchTab(tabId) {
            const tab = document.querySelector(`a[href="#${tabId}"]`);
            if (tab) {
                new bootstrap.Tab(tab).show();
                updateUrlTab(tabId.replace('Tab', ''));
            }
        }
        
        // Update URL with tab parameter
        function updateUrlTab(tabName) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }
        
        // Kích hoạt tab từ URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                const tabId = tabParam + 'Tab';
                const tab = document.querySelector(`a[href="#${tabId}"]`);
                if (tab) {
                    new bootstrap.Tab(tab).show();
                }
            }
            
            // Lưu tab active vào URL
            const tabEls = document.querySelectorAll('a[data-bs-toggle="tab"]');
            tabEls.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    const tabName = e.target.getAttribute('href').replace('#', '').replace('Tab', '');
                    updateUrlTab(tabName);
                });
            });
            
            <?php if ($previewMode): ?>
            // Nếu đang ở chế độ preview, chuyển sang tab import
            setTimeout(() => {
                const importTab = document.querySelector('a[href="#importTab"]');
                if (importTab) {
                    new bootstrap.Tab(importTab).show();
                }
            }, 100);
            <?php endif; ?>
        });
        
        // Filter teams
        function filterTeams() {
            const tournamentFilter = document.getElementById('tournamentFilter').value;
            const searchFilter = document.getElementById('teamSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#teamsTable tbody tr');
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                const tournamentId = row.getAttribute('data-tournament');
                const teamName = row.cells[1].textContent.toLowerCase();
                const player1 = row.cells[2].textContent.toLowerCase();
                const player2 = row.cells[3].textContent.toLowerCase();
                
                let show = true;
                
                // Filter by tournament
                if (tournamentFilter && tournamentId != tournamentFilter) {
                    show = false;
                }
                
                // Filter by search
                if (show && searchFilter) {
                    if (!teamName.includes(searchFilter) && 
                        !player1.includes(searchFilter) && 
                        !player2.includes(searchFilter)) {
                        show = false;
                    }
                }
                
                if (show) {
                    row.style.display = '';
                    visibleCount++;
                    row.cells[0].textContent = visibleCount;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count display
            const countDisplay = document.querySelector('#teamsTab .text-muted');
            if (countDisplay) {
                countDisplay.textContent = `Hiển thị ${visibleCount} đội`;
            }
        }
        
        // Filter groups
        function filterGroups() {
            const tournamentFilter = document.getElementById('groupTournamentFilter').value;
            const accordionItems = document.querySelectorAll('#groupsAccordion .accordion-item');
            
            accordionItems.forEach(item => {
                const tournamentId = item.getAttribute('data-tournament');
                
                if (tournamentFilter && tournamentId != tournamentFilter) {
                    item.style.display = 'none';
                } else {
                    item.style.display = '';
                }
            });
        }
        
        // Export to CSV
        function exportToCSV() {
            Swal.fire({
                title: 'Xuất danh sách đội',
                text: 'Bạn muốn xuất danh sách đội ra file CSV?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Xuất CSV',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'draw.php?export=teams&format=csv';
                }
            });
        }
        
        function exportGroupsToCSV() {
            Swal.fire({
                title: 'Xuất danh sách bảng đấu',
                text: 'Bạn muốn xuất danh sách bảng đấu ra file CSV?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Xuất CSV',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'draw.php?export=groups&format=csv';
                }
            });
        }
        
        // Print teams
        function printTeams() {
            window.print();
        }
        
        // Confirm delete
        function confirmDelete(type) {
            const messages = {
                'teams': 'Bạn có chắc chắn muốn xóa TẤT CẢ đội?',
                'groups': 'Bạn có chắc chắn muốn xóa TẤT CẢ bảng đấu?',
                'all': 'Bạn có chắc chắn muốn xóa TOÀN BỘ dữ liệu?'
            };
            
            Swal.fire({
                title: 'Xác nhận xóa',
                html: `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h4>${messages[type]}</h4>
                        <p class="text-danger"><strong>Hành động này không thể hoàn tác!</strong></p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xác nhận xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#dc3545',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'draw.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'clear_data';
                    form.appendChild(actionInput);
                    
                    const dataTypeInput = document.createElement('input');
                    dataTypeInput.type = 'hidden';
                    dataTypeInput.name = 'data_type';
                    dataTypeInput.value = type;
                    form.appendChild(dataTypeInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Form validation for draw
        document.addEventListener('DOMContentLoaded', function() {
            const drawForm = document.querySelector('form[action*="draw"]');
            if (drawForm) {
                drawForm.addEventListener('submit', function(e) {
                    const tournament = drawForm.querySelector('[name="tournament_filter"]');
                    const numGroups = drawForm.querySelector('[name="num_groups"]');
                    
                    if (!tournament.value) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Thiếu thông tin',
                            text: 'Vui lòng chọn giải đấu!'
                        });
                        tournament.focus();
                        return;
                    }
                    
                    if (parseInt(numGroups.value) < 2 || parseInt(numGroups.value) > 8) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Số bảng không hợp lệ',
                            text: 'Số bảng phải từ 2 đến 8!'
                        });
                        numGroups.focus();
                        return;
                    }
                    
                    // Show loading
                    const submitBtn = drawForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
                    submitBtn.disabled = true;
                    
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                });
            }
        });
    </script>
</body>
</html>