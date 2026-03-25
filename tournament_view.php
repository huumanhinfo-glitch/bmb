<?php
// tournament_view.php - Phiên bản đã sửa lỗi
require_once 'db.php';
require_once 'functions.php'; // Thêm dòng này nếu cần

$tournamentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lấy thông tin giải đấu
$stmt = $pdo->prepare("SELECT * FROM Tournaments WHERE id = ?");
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    header("Location: tournament_list.php");
    exit;
}

// Lấy số liệu thống kê
$teamCount = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id = ?");
$teamCount->execute([$tournamentId]);
$teamCount = $teamCount->fetchColumn();

$matchCount = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE tournament_id = ?");
$matchCount->execute([$tournamentId]);
$matchCount = $matchCount->fetchColumn();

$completedMatches = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE tournament_id = ? AND score1 IS NOT NULL AND score2 IS NOT NULL");
$completedMatches->execute([$tournamentId]);
$completedMatches = $completedMatches->fetchColumn();

// Lấy danh sách đội
$teams = $pdo->prepare("SELECT * FROM Teams WHERE tournament_id = ? ORDER BY group_name, skill_level DESC, team_name");
$teams->execute([$tournamentId]);
$teams = $teams->fetchAll();

// Lấy danh sách bảng
$groups = $pdo->prepare("SELECT * FROM Groups WHERE tournament_id = ? ORDER BY group_name");
$groups->execute([$tournamentId]);
$groups = $groups->fetchAll();

// Lấy các trận đấu
$matches = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name,
           g.group_name
    FROM Matches m
    LEFT JOIN Teams t1 ON m.team1_id = t1.id
    LEFT JOIN Teams t2 ON m.team2_id = t2.id
    LEFT JOIN Groups g ON m.group_id = g.id
    WHERE m.tournament_id = ?
    ORDER BY m.round, m.id
");
$matches->execute([$tournamentId]);
$matches = $matches->fetchAll();

// Tính bảng xếp hạng
$standings = calculateTournamentStandings($tournamentId);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tournament['name']); ?> - TRỌNG TÀI SỐ</title>
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
            color: var(--accent);
        }
        .tournament-header {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://images.unsplash.com/photo-1546519638-68e109498ffc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        .tournament-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.0rem;
            margin-bottom: 1rem;
        }
        .tournament-stats {
            display: flex;
            justify-content: center;
            gap: 15px;
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
        .match-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }
        .match-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .match-round {
            font-weight: 700;
            color: var(--accent);
        }
        .match-date {
            color: #64748b;
            font-size: 0.9rem;
        }
        .match-teams {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .team-card {
            flex: 1;
            text-align: center;
            padding: 10px;
        }
        .team-name-large {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .score-display {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent);
            margin: 0 20px;
        }
        .vs-text {
            font-size: 1.2rem;
            font-weight: 700;
            color: #64748b;
            margin: 0 20px;
        }
        .bracket-diagram {
            overflow-x: auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .bracket-stage {
            display: inline-flex;
            flex-direction: column;
            margin-right: 60px;
        }
        .bracket-stage:last-child {
            margin-right: 0;
        }
        .bracket-stage-title {
            text-align: center;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 20px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 5px;
        }
        .bracket-match {
            width: 200px;
            margin-bottom: 40px;
            position: relative;
        }
        .bracket-team {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            background: white;
            position: relative;
        }
        .bracket-team.winner {
            background: rgba(46, 204, 113, 0.1);
            border-color: var(--primary);
        }
        .bracket-team:first-child {
            border-bottom: none;
            border-radius: 5px 5px 0 0;
        }
        .bracket-team:last-child {
            border-radius: 0 0 5px 5px;
        }
        .bracket-connector {
            position: absolute;
            right: -30px;
            top: 50%;
            width: 30px;
            height: 2px;
            background: #e2e8f0;
        }
        .bracket-connector::after {
            content: '';
            position: absolute;
            right: 0;
            top: -9px;
            width: 2px;
            height: 20px;
            background: #e2e8f0;
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
        
        /* Fix for mobile */
        @media (max-width: 768px) {
            .tournament-title {
                font-size: 2.5rem;
            }
            .tournament-stats {
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
                margin-bottom: 10px;
            }
            .score-display, .vs-text {
                margin: 15px 0;
            }
            .bracket-stage {
                margin-right: 30px;
            }
            .bracket-match {
                width: 150px;
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
                <a class="nav-link active" href="tournament_list.php"><i class="fas fa-trophy"></i> Giải đấu</a>
                <a class="nav-link" href="matches.php"><i class="fas fa-basketball-ball"></i> Trận đấu</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </ul>
            </div>
        </div>
    </nav>

    <div class="tournament-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="tournament-title"><img src="uploads/winner.png" width="70px"> <?php echo htmlspecialchars($tournament['name']); ?></h1>
                    <p class="lead" style="font-size: 1.2rem; opacity: 0.9;">
                        <?php echo htmlspecialchars($tournament['description'] ?: 'Giải đấu pickleball chuyên nghiệp được tổ chức bởi TRỌNG TÀI SỐ'); ?>
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar me-1"></i>
                            <?php 
                            if ($tournament['start_date']) {
                                echo date('d/m/Y', strtotime($tournament['start_date']));
                                if ($tournament['end_date']) {
                                    echo ' - ' . date('d/m/Y', strtotime($tournament['end_date']));
                                }
                            } else {
                                echo 'Đang cập nhật';
                            }
                            ?>
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Đang cập nhật'; ?>
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-flag me-1"></i>
                            <?php 
                            $formatText = [
                                'round_robin' => 'Vòng tròn',
                                'knockout' => 'Loại trực tiếp',
                                'combined' => 'Vòng bảng + Loại trực tiếp',
                                'double_elimination' => 'Loại kép'
                            ];
                            echo $formatText[$tournament['format']] ?? 'Đang cập nhật';
                            ?>
                        </span>
                        <span class="badge px-3 py-2 
                            <?php 
                            $statusClass = [
                                'upcoming' => 'bg-info',
                                'ongoing' => 'bg-success',
                                'completed' => 'bg-secondary',
                                'cancelled' => 'bg-danger'
                            ];
                            echo $statusClass[$tournament['status']] ?? 'bg-secondary';
                            ?>">
                            <?php 
                            $statusText = [
                                'upcoming' => 'Sắp diễn ra',
                                'ongoing' => 'Đang diễn ra',
                                'completed' => 'Đã kết thúc',
                                'cancelled' => 'Đã hủy'
                            ];
                            echo $statusText[$tournament['status']] ?? 'Đang cập nhật';
                            ?>
                        </span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="tournament-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $teamCount; ?></div>
                            <div class="stat-label">Đội tham gia</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo count($groups); ?></div>
                            <div class="stat-label">Bảng đấu</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $completedMatches; ?>/<?php echo $matchCount; ?></div>
                            <div class="stat-label">Trận đấu</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Navigation Tabs -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs border-0" id="tournamentTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#overview">
                        <i class="fas fa-info-circle me-2"></i>TỔNG QUAN
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#teams">
                        <i class="fas fa-users me-2"></i>ĐỘI THAM GIA (<?php echo $teamCount; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#groups">
                        <i class="fas fa-layer-group me-2"></i>BẢNG ĐẤU (<?php echo count($groups); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#matches">
                        <i class="fas fa-basketball-ball me-2"></i>TRẬN ĐẤU (<?php echo $matchCount; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#standings">
                        <i class="fas fa-medal me-2"></i>BẢNG XẾP HẠNG
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#bracket">
                        <i class="fas fa-sitemap me-2"></i>SƠ ĐỒ ĐẤU LOẠI
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- Tab 1: Overview -->
            <div class="tab-pane fade show active" id="overview">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="section-card">
                            <h3 class="section-title">THÔNG TIN GIẢI ĐẤU</h3>
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <h5><i class="fas fa-calendar-alt text-primary me-2"></i>THỜI GIAN</h5>
                                    <p>
                                        <?php if ($tournament['start_date']): ?>
                                            <strong>Ngày bắt đầu:</strong> <?php echo date('d/m/Y', strtotime($tournament['start_date'])); ?><br>
                                            <?php if ($tournament['end_date']): ?>
                                                <strong>Ngày kết thúc:</strong> <?php echo date('d/m/Y', strtotime($tournament['end_date'])); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Đang cập nhật
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <h5><i class="fas fa-map-marker-alt text-success me-2"></i>ĐỊA ĐIỂM</h5>
                                    <p><?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Đang cập nhật'; ?></p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <h5><i class="fas fa-flag text-warning me-2"></i>THỂ THỨC</h5>
                                    <p>
                                        <?php 
                                        $formatDetails = [
                                            'round_robin' => 'Tất cả đội đấu vòng tròn một lượt. Xếp hạng dựa trên điểm số.',
                                            'knockout' => 'Thể thức loại trực tiếp. Thua là bị loại.',
                                            'combined' => 'Vòng bảng (vòng tròn) + Đấu loại trực tiếp cho đội xuất sắc nhất.',
                                            'double_elimination' => 'Thể thức loại kép: Mỗi đội phải thua 2 lần mới bị loại.'
                                        ];
                                        echo $formatDetails[$tournament['format']] ?? 'Đang cập nhật';
                                        ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <h5><i class="fas fa-trophy text-danger me-2"></i>GIẢI THƯỞNG</h5>
                                    <p>Thông tin giải thưởng đang được cập nhật...</p>
                                </div>
                            </div>
                            
                            <h5 class="mt-4"><i class="fas fa-file-alt me-2"></i>MÔ TẢ CHI TIẾT</h5>
                            <p><?php echo nl2br(htmlspecialchars($tournament['description'] ?: 'Giải đấu được tổ chức với sự tham gia của các vận động viên pickleball xuất sắc nhất. Các trận đấu diễn ra với tinh thần fair-play, cạnh tranh cao và đầy kịch tính.')); ?></p>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="section-card">
                            <h3 class="section-title">THỐNG KÊ NHANH</h3>
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-users text-primary me-2"></i>Tổng số đội</span>
                                    <span class="badge bg-primary rounded-pill"><?php echo $teamCount; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-layer-group text-success me-2"></i>Số bảng đấu</span>
                                    <span class="badge bg-success rounded-pill"><?php echo count($groups); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-basketball-ball text-warning me-2"></i>Tổng số trận</span>
                                    <span class="badge bg-warning rounded-pill"><?php echo $matchCount; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-check-circle text-info me-2"></i>Trận đã hoàn thành</span>
                                    <span class="badge bg-info rounded-pill"><?php echo $completedMatches; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-clock text-secondary me-2"></i>Trận chưa đấu</span>
                                    <span class="badge bg-secondary rounded-pill"><?php echo $matchCount - $completedMatches; ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h5><i class="fas fa-download me-2"></i>DỮ LIỆU</h5>
                                <div class="d-grid gap-2">
                                    <a href="export_teams.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-file-csv me-2"></i>Xuất danh sách đội
                                    </a>
                                    <a href="export_matches.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-success">
                                        <i class="fas fa-file-excel me-2"></i>Xuất lịch thi đấu
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Teams -->
            <div class="tab-pane fade" id="teams">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">DANH SÁCH ĐỘI THAM GIA</h3>
                        <span class="badge bg-primary fs-6">Tổng: <?php echo $teamCount; ?> đội</span>
                    </div>
                    
                    <?php if (empty($teams)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>Chưa có đội nào tham gia</h4>
                        <p>Hãy thêm đội vào giải đấu này!</p>
                        <a href="draw.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Thêm đội
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Tên đội</th>
                                    <th>VĐV 1</th>
                                    <th>VĐV 2</th>
                                    <th>Bảng</th>
                                    <th>Trình độ</th>
                                    <th>Hạt giống</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($teams as $index => $team): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($team['player1']); ?></td>
                                    <td><?php echo htmlspecialchars($team['player2']); ?></td>
                                    <td>
                                        <?php if ($team['group_name']): ?>
                                        <span class="badge bg-info">Bảng <?php echo $team['group_name']; ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Chưa xếp bảng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($team['skill_level']): ?>
                                        <span class="badge bg-warning"><?php echo $team['skill_level']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $team['seed'] ?: 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 3: Groups -->
            <div class="tab-pane fade" id="groups">
                <div class="section-card">
                    <h3 class="section-title">CÁC BẢNG ĐẤU</h3>
                    
                    <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <h4>Chưa có bảng đấu nào</h4>
                        <p>Hãy tạo bảng đấu cho giải đấu này!</p>
                        <a href="draw.php" class="btn btn-primary">
                            <i class="fas fa-random me-2"></i>Bốc thăm chia bảng
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($groups as $group): 
                            // Lấy đội trong bảng
                            $stmt = $pdo->prepare("SELECT * FROM Teams WHERE tournament_id = ? AND group_name = ? ORDER BY seed, team_name");
                            $stmt->execute([$tournamentId, $group['group_name']]);
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
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 4: Matches -->
            <div class="tab-pane fade" id="matches">
                <div class="section-card">
                    <h3 class="section-title">LỊCH THI ĐẤU & KẾT QUẢ</h3>
                    
                    <?php if (empty($matches)): ?>
                    <div class="empty-state">
                        <i class="fas fa-basketball-ball"></i>
                        <h4>Chưa có trận đấu nào</h4>
                        <p>Hãy tạo lịch thi đấu cho giải đấu này!</p>
                        <a href="draw.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-2"></i>Tạo lịch thi đấu
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
                        <div class="col-12 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">
                                        <i class="fas fa-flag me-2"></i><?php echo $roundName; ?>
                                        <span class="badge bg-primary float-end"><?php echo count($roundMatches); ?> trận</span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach($roundMatches as $match): ?>
                                    <div class="match-card">
                                        <div class="match-header">
                                            <span class="match-round">
                                                <i class="fas fa-<?php echo $match['group_name'] ? 'layer-group' : 'flag'; ?> me-1"></i>
                                                <?php if ($match['group_name']): ?>
                                                    Bảng <?php echo $match['group_name']; ?>
                                                <?php else: ?>
                                                    Loại trực tiếp
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Đã hoàn thành
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Chưa diễn ra
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="match-teams">
                                            <div class="team-card">
                                                <div class="team-name-large"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                            </div>
                                            
                                            <div class="text-center">
                                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                                <div class="score-display">
                                                    <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php if ($match['score1'] > $match['score2']): ?>
                                                    <i class="fas fa-trophy text-warning"></i> <?php echo htmlspecialchars($match['team1_name']); ?> thắng
                                                    <?php elseif ($match['score1'] < $match['score2']): ?>
                                                    <i class="fas fa-trophy text-warning"></i> <?php echo htmlspecialchars($match['team2_name']); ?> thắng
                                                    <?php else: ?>
                                                    <i class="fas fa-handshake text-info"></i> Hòa
                                                    <?php endif; ?>
                                                </small>
                                                <?php else: ?>
                                                <div class="vs-text">VS</div>
                                                <small class="text-muted">Chưa diễn ra</small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="team-card">
                                                <div class="team-name-large"><?php echo htmlspecialchars($match['team2_name']); ?></div>
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

            <!-- Tab 5: Standings -->
            <div class="tab-pane fade" id="standings">
                <div class="section-card">
                    <h3 class="section-title">BẢNG XẾP HẠNG</h3>
                    
                    <?php if (empty($standings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-table"></i>
                        <h4>Chưa có dữ liệu xếp hạng</h4>
                        <p>Hãy nhập kết quả các trận đấu để tính bảng xếp hạng!</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($standings as $groupName => $teams): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-medal me-2"></i>BẢNG <?php echo $groupName; ?>
                                        <span class="badge bg-light text-primary float-end"><?php echo count($teams); ?> đội</span>
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="60">#</th>
                                                    <th>Đội</th>
                                                    <th>Trận</th>
                                                    <th>T-H-B</th>
                                                    <th>Điểm</th>
                                                    <th>HS</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $position = 1; ?>
                                                <?php foreach($teams as $team): ?>
                                                <tr class="<?php echo $position <= 2 ? 'table-success' : ''; ?>">
                                                    <td>
                                                        <?php if ($position == 1): ?>
                                                        <span class="badge bg-warning">🏆</span>
                                                        <?php elseif ($position == 2): ?>
                                                        <span class="badge bg-secondary">🥈</span>
                                                        <?php else: ?>
                                                        <strong><?php echo $position; ?></strong>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                                    <td><?php echo $team['matches']; ?></td>
                                                    <td><?php echo $team['wins']; ?>-<?php echo $team['draws']; ?>-<?php echo $team['losses']; ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $team['points']; ?></span></td>
                                                    <td><?php echo $team['goal_diff']; ?></td>
                                                </tr>
                                                <?php $position++; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 6: Bracket -->
            <div class="tab-pane fade" id="bracket">
                <div class="section-card">
                    <h3 class="section-title">SƠ ĐỒ ĐẤU LOẠI TRỰC TIẾP</h3>
                    
                    <?php 
                    // Lấy các trận đấu loại trực tiếp
                    $knockoutMatches = $pdo->prepare("
                        SELECT m.*, 
                               t1.team_name as team1_name, 
                               t2.team_name as team2_name,
                               t1.player1 as team1_player1,
                               t1.player2 as team1_player2,
                               t2.player1 as team2_player1,
                               t2.player2 as team2_player2
                        FROM Matches m
                        LEFT JOIN Teams t1 ON m.team1_id = t1.id
                        LEFT JOIN Teams t2 ON m.team2_id = t2.id
                        WHERE m.tournament_id = ? AND (m.match_type = 'knockout' OR m.group_id IS NULL)
                        ORDER BY m.round, m.id
                    ");
                    $knockoutMatches->execute([$tournamentId]);
                    $knockoutMatches = $knockoutMatches->fetchAll();
                    
                    // Hàm hiển thị tên VĐV
                    function formatTeamPlayers($teamName, $player1, $player2) {
                        $players = [];
                        if (!empty($player1)) $players[] = strtoupper($player1);
                        if (!empty($player2)) $players[] = strtoupper($player2);
                        if (empty($players)) return strtoupper($teamName);
                        return implode(' / ', $players);
                    }
                    
                    if (empty($knockoutMatches)):
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-sitemap"></i>
                        <h4>Không có sơ đồ đấu loại</h4>
                        <p>
                            <?php if ($tournament['format'] == 'round_robin'): ?>
                            Giải đấu này chỉ thi đấu theo thể thức vòng tròn, không có đấu loại trực tiếp.
                            <?php else: ?>
                            Chưa tạo sơ đồ đấu loại trực tiếp. Hãy tạo từ trang quản trị!
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="bracket-diagram">
                        <div class="d-flex">
                            <?php 
                            // Phân loại trận đấu theo round
                            $matchesByRound = [];
                            foreach ($knockoutMatches as $match) {
                                $round = $match['round'] ?: 'Chưa xác định';
                                $matchesByRound[$round][] = $match;
                            }
                            
                            // Xác định vòng đấu
                            $roundOrder = [];
                            foreach ($matchesByRound as $roundName => $roundMatches) {
                                $roundOrder[$roundName] = count($roundMatches);
                            }
                            
                            // Sắp xếp theo số lượng trận (ít nhất đến nhiều nhất - từ vòng đầu đến chung kết)
                            asort($roundOrder);
                            
                            $roundCount = count($roundOrder);
                            $currentRound = 0;
                            
                            foreach ($roundOrder as $roundName => $matchCount):
                                $currentRound++;
                            ?>
                            <div class="bracket-stage">
                                <div class="bracket-stage-title">
                                    <?php 
                                    // Xác định tên vòng dựa trên số lượng trận
                                    if ($matchCount == 1) {
                                        echo "CHUNG KẾT";
                                    } elseif ($matchCount == 2) {
                                        echo "BÁN KẾT";
                                    } elseif ($matchCount == 4) {
                                        echo "TỨ KẾT";
                                    } else {
                                        echo $roundName;
                                    }
                                    ?>
                                </div>
                                <?php 
                                $roundMatches = $matchesByRound[$roundName];
                                foreach($roundMatches as $match): 
                                    // Xác định đội thắng dựa trên điểm số
                                    $team1Winner = ($match['score1'] !== null && $match['score2'] !== null && $match['score1'] > $match['score2']);
                                    $team2Winner = ($match['score1'] !== null && $match['score2'] !== null && $match['score2'] > $match['score1']);
                                ?>
                                <div class="bracket-match">
                                    <div class="bracket-team <?php echo $team1Winner ? 'winner' : ''; ?>" style="font-weight: bold; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars(formatTeamPlayers($match['team1_name'], $match['team1_player1'], $match['team1_player2'])); ?>
                                        <?php if ($match['score1'] !== null): ?>
                                        <small class="float-end"><?php echo $match['score1']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bracket-team <?php echo $team2Winner ? 'winner' : ''; ?>" style="font-weight: bold; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars(formatTeamPlayers($match['team2_name'], $match['team2_player1'], $match['team2_player2'])); ?>
                                        <?php if ($match['score2'] !== null): ?>
                                        <small class="float-end"><?php echo $match['score2']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($currentRound < $roundCount): ?>
                                    <div class="bracket-connector"></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Nhà vô địch (nếu có trận chung kết đã xong) -->
                            <?php 
                            $finalMatch = null;
                            foreach ($knockoutMatches as $match) {
                                if (($match['round'] == 'Chung kết' || $match['round'] == 'Final') && 
                                    $match['score1'] !== null && $match['score2'] !== null) {
                                    $finalMatch = $match;
                                    break;
                                }
                            }
                            
                            if ($finalMatch):
                                $isTeam1Winner = $finalMatch['score1'] > $finalMatch['score2'];
                                $winnerName = $isTeam1Winner 
                                    ? formatTeamPlayers($finalMatch['team1_name'], $finalMatch['team1_player1'], $finalMatch['team1_player2'])
                                    : formatTeamPlayers($finalMatch['team2_name'], $finalMatch['team2_player1'], $finalMatch['team2_player2']);
                            ?>
                            <div class="bracket-stage">
                                <div class="bracket-stage-title">NHÀ VÔ ĐỊCH</div>
                                <div class="bracket-match">
                                    <div class="bracket-team winner" style="height: 80px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem;">
                                        <i class="fas fa-trophy me-2 text-warning"></i>
                                        <?php echo htmlspecialchars($winnerName); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-top mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <a href="tournament_list.php" class="btn btn-outline-primary mb-3">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách giải đấu
                    </a>
                    <p class="text-muted small mb-0">&copy; 2026 TRỌNG TÀI SỐ. Giải đấu: <?php echo htmlspecialchars($tournament['name']); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Kích hoạt tab từ URL hash
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>

<?php
// Hàm tính bảng xếp hạng cho giải đấu
function calculateTournamentStandings($tournamentId) {
    global $pdo;
    
    // Lấy tất cả trận đấu vòng bảng của giải
    $sql = "
        SELECT m.*, g.group_name
        FROM Matches m
        LEFT JOIN Groups g ON m.group_id = g.id
        WHERE m.tournament_id = ? AND m.group_id IS NOT NULL
        ORDER BY g.group_name, m.round
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tournamentId]);
    $matches = $stmt->fetchAll();
    
    $standings = [];
    $teamDetails = [];
    
    foreach ($matches as $m) {
        if (!isset($m['group_name']) || $m['group_name'] === null) {
            continue;
        }
        
        $g = $m['group_name'];
        $t1 = $m['team1_id'];
        $t2 = $m['team2_id'];
        
        if (!isset($standings[$g])) {
            $standings[$g] = [];
        }
        
        // Lấy thông tin đội nếu chưa có
        if (!isset($teamDetails[$t1])) {
            $teamInfo = $pdo->prepare("SELECT * FROM Teams WHERE id = ?");
            $teamInfo->execute([$t1]);
            $teamDetails[$t1] = $teamInfo->fetch();
        }
        if (!isset($teamDetails[$t2])) {
            $teamInfo = $pdo->prepare("SELECT * FROM Teams WHERE id = ?");
            $teamInfo->execute([$t2]);
            $teamDetails[$t2] = $teamInfo->fetch();
        }
        
        // Khởi tạo thống kê
        if (!isset($standings[$g][$t1])) {
            $standings[$g][$t1] = [
                'team_id' => $t1,
                'team_name' => $teamDetails[$t1]['team_name'] ?? 'Unknown',
                'matches' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'scored' => 0,
                'conceded' => 0,
                'goal_diff' => 0
            ];
        }
        if (!isset($standings[$g][$t2])) {
            $standings[$g][$t2] = [
                'team_id' => $t2,
                'team_name' => $teamDetails[$t2]['team_name'] ?? 'Unknown',
                'matches' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'scored' => 0,
                'conceded' => 0,
                'goal_diff' => 0
            ];
        }
        
        // Cập nhật kết quả
        if ($m['score1'] !== null && $m['score2'] !== null) {
            $standings[$g][$t1]['matches']++;
            $standings[$g][$t2]['matches']++;
            
            $standings[$g][$t1]['scored'] += $m['score1'];
            $standings[$g][$t1]['conceded'] += $m['score2'];
            $standings[$g][$t2]['scored'] += $m['score2'];
            $standings[$g][$t2]['conceded'] += $m['score1'];
            
            $standings[$g][$t1]['goal_diff'] = $standings[$g][$t1]['scored'] - $standings[$g][$t1]['conceded'];
            $standings[$g][$t2]['goal_diff'] = $standings[$g][$t2]['scored'] - $standings[$g][$t2]['conceded'];
            
            if ($m['score1'] > $m['score2']) {
                $standings[$g][$t1]['wins']++;
                $standings[$g][$t1]['points'] += 3;
                $standings[$g][$t2]['losses']++;
            } elseif ($m['score1'] < $m['score2']) {
                $standings[$g][$t2]['wins']++;
                $standings[$g][$t2]['points'] += 3;
                $standings[$g][$t1]['losses']++;
            } else {
                $standings[$g][$t1]['draws']++;
                $standings[$g][$t2]['draws']++;
                $standings[$g][$t1]['points'] += 1;
                $standings[$g][$t2]['points'] += 1;
            }
        }
    }
    
    // Sắp xếp
    foreach ($standings as $group => &$teams) {
        uasort($teams, function($a, $b) {
            if ($a['points'] != $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['goal_diff'] != $b['goal_diff']) {
                return $b['goal_diff'] <=> $a['goal_diff'];
            }
            return $b['scored'] <=> $a['scored'];
        });
    }
    
    return $standings;
}
?>