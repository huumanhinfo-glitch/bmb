<?php
// matches.php - Phiên bản mới với giao diện hiện đại
require_once 'functions.php';

$tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$groupFilter = isset($_GET['group']) ? intval($_GET['group']) : null;
$matchId = isset($_GET['match']) ? intval($_GET['match']) : null;

// Lấy danh sách giải đấu
$tournaments = getAllTournaments();

// Lấy thông tin giải đấu nếu có tournament_id
$tournament = null;
if ($tournamentId > 0) {
    $tournament = getTournamentById($tournamentId);
    if (!$tournament) {
        $tournamentId = 0;
    }
}

// Lấy trận đấu
$matches = [];
if ($tournamentId > 0) {
    $matches = getMatchesByTournament($tournamentId);
} else {
    // Lấy tất cả trận đấu
    $matches = getAllMatches();
}

// Lấy danh sách bảng đấu
$groups = [];
if ($tournamentId > 0) {
    $groups = getGroupsByTournament($tournamentId);
} else {
    $groups = fetchAllGroups();
}

// Lọc theo bảng nếu có
if ($groupFilter && $groupFilter > 0) {
    $matches = array_filter($matches, function($match) use ($groupFilter) {
        return $match['group_id'] == $groupFilter;
    });
}

// Tính thống kê
$totalMatches = count($matches);
$completedMatches = 0;
$upcomingMatches = 0;

foreach ($matches as $match) {
    if ($match['score1'] !== null && $match['score2'] !== null) {
        $completedMatches++;
    } else {
        $upcomingMatches++;
    }
}

// Lấy thông tin trận đấu cụ thể nếu có
$selectedMatch = null;
if ($matchId > 0) {
    $selectedMatch = getMatchById($matchId);
}

// Xử lý POST request để cập nhật kết quả
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_score'])) {
    $matchIdToUpdate = intval($_POST['match_id']);
    $score1 = intval($_POST['score1']);
    $score2 = intval($_POST['score2']);
    
    if (updateMatchScore($matchIdToUpdate, $score1, $score2)) {
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Đã cập nhật kết quả trận đấu!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
        
        // Refresh matches
        if ($tournamentId > 0) {
            $matches = getMatchesByTournament($tournamentId);
        } else {
            $matches = getAllMatches();
        }
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle me-2"></i>Có lỗi xảy ra khi cập nhật kết quả!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
    }
}

// Xử lý tạo lịch thi đấu loại trực tiếp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_knockout'])) {
    if ($tournamentId > 0) {
        $knockoutMatches = createKnockoutStage(null, $tournamentId);
        if ($knockoutMatches) {
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Đã tạo lịch thi đấu loại trực tiếp!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
            $matches = getMatchesByTournament($tournamentId);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Trận đấu - TRỌNG TÀI SỐ</title>
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
                        url('https://images.unsplash.com/photo-1761644541691-2a746c638881?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 3.5rem;
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
            padding: 15px;
            border-radius: 15px;
            min-width: 80px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            font-weight: 300;
            margin-bottom: 5px;
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
            border-bottom: 3px solid var(--primary);
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
        
        .match-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .match-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .match-card.completed {
            border-left: 4px solid var(--primary);
        }
        
        .match-card.upcoming {
            border-left: 4px solid #ffc107;
        }
        
        .match-teams {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        
        .match-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .team-card {
            flex: 1;
            text-align: center;
            padding: 8px;
            min-width: 100px;
        }
        
        .team-name {
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.3;
        }
        
        .team-players {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .score-display {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--accent);
            margin: 0 15px;
        }
        
        .vs-text {
            font-size: 1rem;
            font-weight: 700;
            color: #94a3b8;
            margin: 0 10px;
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
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
        
        .match-details-modal .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .match-details-modal .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .score-input {
            width: 80px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .score-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
        }
        
        .filter-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .tournament-select {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tournament-select:hover {
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .tournament-select.active {
            background-color: rgba(46, 204, 113, 0.2);
            border-color: var(--primary);
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
            
            .match-teams {
                flex-direction: column;
            }
            
            .team-card {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .score-display, .vs-text {
                margin: 15px 0;
            }
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
                <a class="nav-link active" href="matches.php"><i class="fas fa-basketball-ball"></i> Trận đấu</a>
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
                    <h1 class="page-title">QUẢN LÝ TRẬN ĐẤU</h1>
                    <p class="lead" style="font-size: 1.2rem; opacity: 0.9;">
                        <?php if ($tournament): ?>
                        <?php echo htmlspecialchars($tournament['name']); ?> - Cập nhật kết quả, theo dõi lịch thi đấu
                        <?php else: ?>
                        Xem và quản lý tất cả trận đấu pickleball
                        <?php endif; ?>
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-basketball-ball me-1"></i>Trận đấu
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-edit me-1"></i>Cập nhật kết quả
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar-alt me-1"></i>Lịch thi đấu
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-chart-line me-1"></i>Thống kê
                        </span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="page-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $totalMatches; ?></div>
                            <div class="stat-label">Tổng trận</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $completedMatches; ?></div>
                            <div class="stat-label">Đã hoàn thành</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $upcomingMatches; ?></div>
                            <div class="stat-label">Sắp diễn ra</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($message)) echo $message; ?>
        
        <!-- Tournament Selection -->
        <div class="filter-container">
            <h4 class="mb-4">
                <i class="fas fa-trophy me-2 text-primary"></i>
                <?php if ($tournament): ?>
                Đang xem: <strong><?php echo htmlspecialchars($tournament['name']); ?></strong>
                <a href="matches.php" class="btn btn-sm btn-outline-custom ms-3">
                    <i class="fas fa-times me-1"></i>Xem tất cả
                </a>
                <?php else: ?>
                CHỌN GIẢI ĐẤU ĐỂ XEM TRẬN ĐẤU
                <?php endif; ?>
            </h4>
            
            <div class="row">
                <?php foreach($tournaments as $t): ?>
                <div class="col-md-4 col-lg-3 mb-3">
                    <a href="matches.php?tournament_id=<?php echo $t['id']; ?>" 
                       class="tournament-card tournament-select <?php echo $tournamentId == $t['id'] ? 'active' : ''; ?>">
                        <div class="tournament-name"><?php echo htmlspecialchars($t['name']); ?></div>
                        <div class="tournament-info">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo $t['status'] == 'upcoming' ? 'Sắp diễn ra' : 
                                   ($t['status'] == 'ongoing' ? 'Đang diễn ra' : 'Đã kết thúc'); ?>
                        </div>
                        <div class="tournament-info">
                            <i class="fas fa-users me-1"></i>
                            <?php 
                            $teamCount = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id = ?");
                            $teamCount->execute([$t['id']]);
                            echo $teamCount->fetchColumn(); ?> đội
                        </div>
                        <div class="text-end mt-2">
                            <span class="badge bg-primary">
                                <i class="fas fa-eye me-1"></i>Xem trận đấu
                            </span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($tournaments)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Chưa có giải đấu nào. Hãy tạo giải đấu mới!
                        <a href="draw.php" class="btn btn-sm btn-primary ms-3">
                            <i class="fas fa-plus me-1"></i>Tạo giải đấu
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($tournamentId > 0): ?>
        <!-- Tournament Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="section-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs me-2 text-primary"></i>
                                THAO TÁC CHO GIẢI: 
                                <span class="text-primary"><?php echo htmlspecialchars($tournament['name']); ?></span>
                            </h5>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2 justify-content-end">
                                <!-- Group Filter -->
                                <select class="form-select" style="width: auto;" onchange="if(this.value) window.location.href='matches.php?tournament_id=<?php echo $tournamentId; ?>&group='+this.value">
                                    <option value="">Tất cả bảng</option>
                                    <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo $groupFilter == $group['id'] ? 'selected' : ''; ?>>
                                        Bảng <?php echo $group['group_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Create Knockout Button -->
                                <form method="post" class="d-inline">
                                    <button type="submit" name="create_knockout" class="btn btn-outline-custom" 
                                            onclick="return confirm('Tạo lịch thi đấu loại trực tiếp? Điều này sẽ xóa các trận loại trực tiếp cũ!')">
                                        <i class="fas fa-sitemap me-1"></i>Tạo đấu loại
                                    </button>
                                </form>
                                
                                <!-- View Tournament Button -->
                                <a href="tournament_view.php?id=<?php echo $tournamentId; ?>" class="btn btn-primary-custom">
                                    <i class="fas fa-external-link-alt me-1"></i>Xem chi tiết giải
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs border-0" id="matchesTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#all-matches">
                        <i class="fas fa-list me-2"></i>TẤT CẢ TRẬN ĐẤU
                        <span class="badge bg-primary ms-2"><?php echo $totalMatches; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="match-control.php?tab=history" style="color: #e74c3c !important;">
                        <i class="fas fa-satellite-dish me-2"></i>TRỰC TIẾP
                        <span class="badge bg-danger ms-2"><i class="fas fa-circle fa-xs me-1"></i>LIVE</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#completed">
                        <i class="fas fa-check-circle me-2"></i>ĐÃ HOÀN THÀNH
                        <span class="badge bg-success ms-2"><?php echo $completedMatches; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#upcoming">
                        <i class="fas fa-clock me-2"></i>SẮP DIỄN RA
                        <span class="badge bg-warning ms-2"><?php echo $upcomingMatches; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#by-groups">
                        <i class="fas fa-layer-group me-2"></i>THEO BẢNG
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- Tab 1: All Matches -->
            <div class="tab-pane fade show active" id="all-matches">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">
                            <?php if ($tournament): ?>
                            TẤT CẢ TRẬN ĐẤU: <?php echo htmlspecialchars($tournament['name']); ?>
                            <?php else: ?>
                            TẤT CẢ TRẬN ĐẤU
                            <?php endif; ?>
                        </h3>
                        <span class="badge bg-primary fs-6">Tổng: <?php echo $totalMatches; ?> trận</span>
                    </div>
                    
                    <?php if (empty($matches)): ?>
                    <div class="empty-state">
                        <i class="fas fa-basketball-ball"></i>
                        <h4>Chưa có trận đấu nào</h4>
                        <p>Hãy tạo lịch thi đấu cho giải đấu!</p>
                        <a href="draw.php" class="btn btn-primary-custom mt-3">
                            <i class="fas fa-random me-2"></i>Tạo lịch thi đấu
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php 
                        // Nhóm trận đấu theo round
                        $matchesByRound = [];
                        foreach ($matches as $match) {
                            $round = $match['round'] ?: 'Chưa xác định';
                            $matchesByRound[$round][] = $match;
                        }
                        
                        foreach ($matchesByRound as $roundName => $roundMatches): 
                        ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">
                                        <i class="fas fa-flag me-2"></i><?php echo $roundName; ?>
                                        <span class="badge bg-primary float-end"><?php echo count($roundMatches); ?> trận</span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach($roundMatches as $match): 
                                        $t1p1 = $match['team1_player1'] ?? '';
                                        $t1p2 = $match['team1_player2'] ?? '';
                                        $t2p1 = $match['team2_player1'] ?? '';
                                        $t2p2 = $match['team2_player2'] ?? '';
                                        $isCompleted = $match['score1'] !== null && $match['score2'] !== null;
                                    ?>
                                    <div class="match-card <?php echo $isCompleted ? 'completed' : 'upcoming'; ?>">
                                        <div class="match-teams">
                                            <div class="team-card text-center">
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="team-name <?php echo $isCompleted && $match['score1'] > $match['score2'] ? 'text-success fw-bold' : ''; ?>">
                                                        <?php if ($isCompleted && $match['score1'] > $match['score2']): ?>
                                                        <i class="fas fa-trophy text-warning me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($t1p1 ?: $match['team1_name']); ?>
                                                    </span>
                                                    <span class="team-name <?php echo $isCompleted && $match['score1'] > $match['score2'] ? 'text-success fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($t1p2 ?: '-'); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($match['skill_level1'])): ?>
                                                <div class="mt-2">
                                                    <span class="badge bg-info-subtle text-info"><?php echo $match['skill_level1']; ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-center">
                                                <?php if ($isCompleted): ?>
                                                <div class="score-display">
                                                    <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                                </div>
                                                <?php else: ?>
                                                <div class="vs-text">VS</div>
                                                <small class="text-muted">Chưa đấu</small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="team-card text-center">
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="team-name <?php echo $isCompleted && $match['score2'] > $match['score1'] ? 'text-success fw-bold' : ''; ?>">
                                                        <?php if ($isCompleted && $match['score2'] > $match['score1']): ?>
                                                        <i class="fas fa-trophy text-warning me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($t2p1 ?: $match['team2_name']); ?>
                                                    </span>
                                                    <span class="team-name <?php echo $isCompleted && $match['score2'] > $match['score1'] ? 'text-success fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($t2p2 ?: '-'); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($match['skill_level2'])): ?>
                                                <div class="mt-2">
                                                    <span class="badge bg-info-subtle text-info"><?php echo $match['skill_level2']; ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="match-footer mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-<?php echo $match['group_name'] ? 'layer-group' : 'flag'; ?> me-1"></i>
                                                <?php if ($match['group_name']): ?>
                                                    Bảng <?php echo $match['group_name']; ?> - <?php echo $roundName; ?>
                                                <?php else: ?>
                                                    <?php echo $match['match_type'] ?: 'Loại trực tiếp'; ?> - <?php echo $roundName; ?>
                                                <?php endif; ?>
                                            </small>
                                            <div>
                                                <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-gamepad"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-secondary ms-1" 
                                                        data-bs-toggle="modal" data-bs-target="#matchModal"
                                                        onclick="loadMatchDetails(<?php echo $match['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Tab 2: Completed Matches -->
            <div class="tab-pane fade" id="completed">
                <div class="section-card">
                    <h3 class="section-title">CÁC TRẬN ĐÃ HOÀN THÀNH</h3>
                    <?php 
                    $completedMatchesList = array_filter($matches, function($match) {
                        return $match['score1'] !== null && $match['score2'] !== null;
                    });
                    if (empty($completedMatchesList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>Chưa có trận đấu nào hoàn thành</h4>
                        <p>Hãy cập nhật kết quả các trận đấu!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Trận đấu</th>
                                    <th>Kết quả</th>
                                    <th>Bảng/Vòng</th>
                                    <th>Ngày thi đấu</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($completedMatchesList as $index => $match): 
                                    $t1p1 = $match['team1_player1'] ?? '';
                                    $t1p2 = $match['team1_player2'] ?? '';
                                    $t2p1 = $match['team2_player1'] ?? '';
                                    $t2p2 = $match['team2_player2'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="<?php echo $match['score1'] > $match['score2'] ? 'text-success fw-bold' : ''; ?>">
                                                <?php if ($match['score1'] > $match['score2']): ?><i class="fas fa-trophy text-warning me-1"></i><?php endif; ?>
                                                <?php echo htmlspecialchars($t1p1 ?: $match['team1_name']); ?> / <?php echo htmlspecialchars($t1p2 ?: '-'); ?>
                                            </span>
                                            <span class="<?php echo $match['score2'] > $match['score1'] ? 'text-success fw-bold' : ''; ?>">
                                                <?php if ($match['score2'] > $match['score1']): ?><i class="fas fa-trophy text-warning me-1"></i><?php endif; ?>
                                                <?php echo htmlspecialchars($t2p1 ?: $match['team2_name']); ?> / <?php echo htmlspecialchars($t2p2 ?: '-'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary fs-6">
                                            <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($match['group_name']): ?>
                                        <span class="badge bg-info">Bảng <?php echo $match['group_name']; ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-warning"><?php echo $match['match_type'] ?: 'Loại trực tiếp'; ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <small><?php echo $match['round']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($match['match_date']): ?>
                                        <?php echo date('d/m/Y', strtotime($match['match_date'])); ?>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-gamepad"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#matchModal"
                                                onclick="loadMatchDetails(<?php echo $match['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab 3: Upcoming Matches -->
            <div class="tab-pane fade" id="upcoming">
                <div class="section-card">
                    <h3 class="section-title">CÁC TRẬN SẮP DIỄN RA</h3>
                    <?php 
                    $upcomingMatchesList = array_filter($matches, function($match) {
                        return $match['score1'] === null || $match['score2'] === null;
                    });
                    if (empty($upcomingMatchesList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <h4>Không có trận đấu sắp diễn ra</h4>
                        <p>Tất cả trận đấu đã hoàn thành!</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($upcomingMatchesList as $match): 
                            $t1p1 = $match['team1_player1'] ?? '';
                            $t1p2 = $match['team1_player2'] ?? '';
                            $t2p1 = $match['team2_player1'] ?? '';
                            $t2p2 = $match['team2_player2'] ?? '';
                        ?>
                        <div class="col-lg-6 mb-4">
                            <div class="match-card upcoming">
                                <div class="match-teams">
                                    <div class="team-card text-center">
                                        <div class="d-flex flex-column gap-1">
                                            <span class="team-name"><?php echo htmlspecialchars($t1p1 ?: $match['team1_name']); ?></span>
                                            <span class="team-name"><?php echo htmlspecialchars($t1p2 ?: '-'); ?></span>
                                        </div>
                                        <?php if (!empty($match['skill_level1'])): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-info-subtle text-info"><?php echo $match['skill_level1']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-center">
                                        <div class="vs-text">VS</div>
                                        <small class="text-muted">
                                            <?php if ($match['group_name']): ?>
                                                Bảng <?php echo $match['group_name']; ?>
                                            <?php else: ?>
                                                <?php echo $match['match_type'] ?: 'Loại trực tiếp'; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="team-card text-center">
                                        <div class="d-flex flex-column gap-1">
                                            <span class="team-name"><?php echo htmlspecialchars($t2p1 ?: $match['team2_name']); ?></span>
                                            <span class="team-name"><?php echo htmlspecialchars($t2p2 ?: '-'); ?></span>
                                        </div>
                                        <?php if (!empty($match['skill_level2'])): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-info-subtle text-info"><?php echo $match['skill_level2']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="match-footer mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><?php echo $match['round']; ?></small>
                                    <div>
                                        <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-gamepad"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary ms-1" 
                                                data-bs-toggle="modal" data-bs-target="#matchModal"
                                                onclick="loadMatchDetails(<?php echo $match['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Tab 4: By Groups -->
            <div class="tab-pane fade" id="by-groups">
                <div class="section-card">
                    <h3 class="section-title">TRẬN ĐẤU THEO BẢNG</h3>
                    
                    <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <h4>Chưa có bảng đấu nào</h4>
                        <p>Hãy bốc thăm để tạo bảng đấu!</p>
                        <a href="draw.php" class="btn btn-primary-custom mt-3">
                            <i class="fas fa-random me-2"></i>Bốc thăm chia bảng
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($groups as $group): 
                            // Lấy trận đấu theo bảng
                            $groupMatches = array_filter($matches, function($match) use ($group) {
                                return $match['group_id'] == $group['id'];
                            });
                        ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chess-board me-2"></i>
                                        BẢNG <?php echo $group['group_name']; ?>
                                        <span class="badge bg-light text-primary float-end">
                                            <?php echo count($groupMatches); ?> trận
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($groupMatches)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-basketball-ball fa-2x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">Chưa có trận đấu nào trong bảng này</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach($groupMatches as $match): 
                                            $t1p1 = $match['team1_player1'] ?? '';
                                            $t1p2 = $match['team1_player2'] ?? '';
                                            $t2p1 = $match['team2_player1'] ?? '';
                                            $t2p2 = $match['team2_player2'] ?? '';
                                            $isCompleted = $match['score1'] !== null && $match['score2'] !== null;
                                        ?>
                                        <div class="match-card <?php echo $isCompleted ? 'completed' : 'upcoming'; ?>">
                                            <div class="match-teams">
                                                <div class="team-card text-center">
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="team-name <?php echo $isCompleted && $match['score1'] > $match['score2'] ? 'text-success fw-bold' : ''; ?>">
                                                            <?php if ($isCompleted && $match['score1'] > $match['score2']): ?><i class="fas fa-trophy text-warning me-1"></i><?php endif; ?>
                                                            <?php echo htmlspecialchars($t1p1 ?: $match['team1_name']); ?>
                                                        </span>
                                                        <span class="team-name <?php echo $isCompleted && $match['score1'] > $match['score2'] ? 'text-success fw-bold' : ''; ?>">
                                                            <?php echo htmlspecialchars($t1p2 ?: '-'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-center">
                                                    <?php if ($isCompleted): ?>
                                                    <div class="score-display" style="font-size: 1.5rem;">
                                                        <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="vs-text" style="font-size: 1rem;">VS</div>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?php echo $match['round']; ?></small>
                                                </div>
                                                
                                                <div class="team-card text-center">
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="team-name <?php echo $isCompleted && $match['score2'] > $match['score1'] ? 'text-success fw-bold' : ''; ?>">
                                                            <?php if ($isCompleted && $match['score2'] > $match['score1']): ?><i class="fas fa-trophy text-warning me-1"></i><?php endif; ?>
                                                            <?php echo htmlspecialchars($t2p1 ?: $match['team2_name']); ?>
                                                        </span>
                                                        <span class="team-name <?php echo $isCompleted && $match['score2'] > $match['score1'] ? 'text-success fw-bold' : ''; ?>">
                                                            <?php echo htmlspecialchars($t2p2 ?: '-'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-center mt-2">
                                                <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-gamepad"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <div class="text-center mt-3">
                                        <a href="matches.php?tournament_id=<?php echo $tournamentId ?: ''; ?>&group=<?php echo $group['id']; ?>" 
                                           class="btn btn-outline-custom btn-sm">
                                            <i class="fas fa-external-link-alt me-1"></i>Xem chi tiết bảng
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Match Details Modal -->
    <div class="modal fade match-details-modal" id="matchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CHI TIẾT TRẬN ĐẤU</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="matchDetails">
                    <!-- Content will be loaded by JavaScript -->
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
                    <p class="text-muted small mb-0">&copy; 2026 TRỌNG TÀI SỐ. Quản lý trận đấu.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load match details via AJAX
        async function loadMatchDetails(matchId) {
            const matchDetails = document.getElementById('matchDetails');
            matchDetails.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Đang tải thông tin trận đấu...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`get_match_details.php?id=${matchId}`);
                const html = await response.text();
                matchDetails.innerHTML = html;
                
                // Re-attach event listeners for the form
                const form = matchDetails.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!validateScoreForm(this)) {
                            e.preventDefault();
                        }
                    });
                }
            } catch (error) {
                matchDetails.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Có lỗi xảy ra khi tải thông tin trận đấu. Vui lòng thử lại!
                    </div>
                `;
            }
        }
        
        // Validate score form
        function validateScoreForm(form) {
            const score1 = form.querySelector('[name="score1"]');
            const score2 = form.querySelector('[name="score2"]');
            
            if (!score1 || !score2) return true;
            
            const val1 = parseInt(score1.value);
            const val2 = parseInt(score2.value);
            
            if (isNaN(val1) || isNaN(val2)) {
                alert('Vui lòng nhập số hợp lệ cho cả hai đội!');
                return false;
            }
            
            if (val1 < 0 || val2 < 0) {
                alert('Điểm số không thể âm!');
                return false;
            }
            
            if (val1 === val2) {
                if (!confirm('Kết quả hòa. Bạn có chắc chắn?')) {
                    return false;
                }
            }
            
            return true;
        }
        
        // Auto-update scores
        document.addEventListener('DOMContentLoaded', function() {
            // Kích hoạt tab từ URL hash
            const hash = window.location.hash;
            if (hash) {
                const tabTrigger = document.querySelector(`a[href="${hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
            
            // Lưu tab active vào URL
            const tabEls = document.querySelectorAll('a[data-bs-toggle="tab"]');
            tabEls.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    history.pushState(null, null, e.target.hash);
                });
            });
            
            // Auto-refresh page every 30 seconds if on matches page
            setTimeout(() => {
                if (window.location.pathname.includes('matches.php')) {
                    // Only refresh if no modal is open
                    if (!document.querySelector('.modal.show')) {
                        window.location.reload();
                    }
                }
            }, 30000);
            
            // Load match details if match ID is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const matchId = urlParams.get('match');
            if (matchId) {
                loadMatchDetails(matchId);
                const modal = new bootstrap.Modal(document.getElementById('matchModal'));
                modal.show();
            }
        });
        
        // Quick score update
        function quickUpdateScore(matchId, team, action) {
            const input1 = document.querySelector(`#score1_${matchId}`);
            const input2 = document.querySelector(`#score2_${matchId}`);
            
            if (!input1 || !input2) return;
            
            let score1 = parseInt(input1.value) || 0;
            let score2 = parseInt(input2.value) || 0;
            
            if (team === 1) {
                if (action === 'plus') score1++;
                else if (action === 'minus' && score1 > 0) score1--;
            } else {
                if (action === 'plus') score2++;
                else if (action === 'minus' && score2 > 0) score2--;
            }
            
            input1.value = score1;
            input2.value = score2;
        }
    </script>
</body>
</html>