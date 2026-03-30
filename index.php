<?php
// index.php - Trang chủ
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

// Lấy thống kê
$totalTournaments = $pdo->query("SELECT COUNT(*) FROM `Tournaments`")->fetchColumn();
$totalTeams = $pdo->query("SELECT COUNT(*) FROM `Teams`")->fetchColumn();
$totalMatches = $pdo->query("SELECT COUNT(*) FROM `Matches`")->fetchColumn();
$completedMatches = $pdo->query("SELECT COUNT(*) FROM `Matches` WHERE status = 'completed'")->fetchColumn();

// Giải đấu đang diễn ra - với thông tin thống kê
$ongoingTournaments = $pdo->query("
    SELECT 
        t.*,
        (SELECT COUNT(*) FROM `Teams` WHERE tournament_id = t.id) as team_count,
        (SELECT COUNT(DISTINCT group_name) FROM `Teams` WHERE tournament_id = t.id AND group_name IS NOT NULL AND group_name != '') as group_count,
        (SELECT COUNT(*) FROM `Matches` WHERE tournament_id = t.id) as match_count
    FROM `Tournaments` t
    WHERE t.status = 'ongoing' 
    ORDER BY t.start_date DESC LIMIT 6
")->fetchAll();

// Giải đấu sắp diễn ra
$upcomingTournaments = $pdo->query("SELECT * FROM `Tournaments` WHERE status = 'upcoming' ORDER BY start_date ASC LIMIT 3")->fetchAll();

// Trận đấu gần đây
$recentMatches = $pdo->query("
    SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name, tr.name as tournament_name
    FROM `Matches` m
    LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
    LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
    LEFT JOIN `Tournaments` tr ON m.tournament_id = tr.id
    WHERE m.status = 'completed'
    ORDER BY m.id DESC LIMIT 5
")->fetchAll();

$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);

$statusLabels = [
    'upcoming' => ['Sắp diễn ra', 'info'],
    'ongoing' => ['Đang diễn ra', 'success'],
    'completed' => ['Hoàn thành', 'secondary']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRỌNG TÀI SỐ - Quản lý giải đấu Pickleball</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .hero { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 60px 0; }
        .hero-title { font-size: 2.5rem; font-weight: 800; }
        .hero-subtitle { font-size: 1.2rem; opacity: 0.9; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; text-align: center; border-bottom: 4px solid var(--primary); transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.9rem; color: #64748b; text-transform: uppercase; }
        .section-title { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 20px; }
        .tournament-card { background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .tournament-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .tournament-card-inner { padding: 0; }
        .tournament-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); padding: 10px 15px; }
        .tournament-body { padding: 15px; }
        .tournament-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); text-decoration: none; margin-bottom: 12px; }
        .tournament-title:hover { color: var(--accent); }
        .tournament-info { display: flex; flex-direction: column; gap: 8px; }
        .info-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; }
        .info-item i { width: 16px; color: var(--accent); }
        .match-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid var(--primary); }
        .match-card:last-child { margin-bottom: 0; }
        .team-name { font-weight: 600; }
        .score { font-size: 1.3rem; font-weight: 700; color: var(--accent); }
        .vs-text { color: #94a3b8; font-weight: 600; }
        .feature-box { background: white; border-radius: 12px; padding: 30px; text-align: center; transition: all 0.2s; }
        .feature-box:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .feature-icon { font-size: 2.5rem; color: var(--primary); margin-bottom: 15px; }
        .feature-title { font-weight: 700; margin-bottom: 10px; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: #27ae60; }
    </style>
</head>
<body>
    <?php renderNavbar('home'); ?>

    <!-- Hero Section -->
    <div class="hero">
        <div class="container text-center">
            <h1 class="hero-title"><i class="fas fa-trophy me-3"></i>TRỌNG TÀI SỐ</h1>
            <p class="hero-subtitle">Hệ thống quản lý giải đấu Pickleball chuyên nghiệp</p>
            <div class="mt-4">
                <a href="tournament_list.php" class="btn btn-light btn-lg me-2"><i class="fas fa-trophy me-2"></i>Xem giải đấu</a>
                <?php if ($isAdmin): ?>
                <a href="create_tournament.php" class="btn btn-warning btn-lg"><i class="fas fa-plus me-2"></i>Tạo giải mới</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <!-- Stats -->
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalTournaments; ?></div>
                    <div class="stat-label"><i class="fas fa-trophy me-1"></i>Giải đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalTeams; ?></div>
                    <div class="stat-label"><i class="fas fa-users me-1"></i>Đội tham gia</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalMatches; ?></div>
                    <div class="stat-label"><i class="fas fa-bullhorn me-1"></i>Trận đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completedMatches; ?></div>
                    <div class="stat-label"><i class="fas fa-check-circle me-1"></i>Hoàn thành</div>
                </div>
            </div>
        </div>

        <!-- Ongoing Tournaments -->
        <div class="row">
            <div class="col-12 mb-3">
                <h3 class="section-title"><i class="fas fa-play text-success me-2"></i>Giải đang diễn ra</h3>
            </div>
            <?php if (empty($ongoingTournaments)): ?>
                <div class="col-12">
                    <div class="text-muted text-center py-4">Không có giải đang diễn ra</div>
                </div>
            <?php else: ?>
                <?php foreach ($ongoingTournaments as $t): ?>
                <div class="col-md-4 mb-4">
                    <a href="tournament_view.php?id=<?php echo $t['id']; ?>" class="tournament-card d-block text-decoration-none h-100">
                        <div class="tournament-card-inner">
                            <div class="tournament-header">
                                <span class="badge bg-success">Đang diễn ra</span>
                            </div>
                            <div class="tournament-body">
                                <h5 class="tournament-title"><?php echo htmlspecialchars($t['name']); ?></h5>
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($t['location'] ?? 'Chưa cập nhật'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo date('d/m/Y', strtotime($t['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($t['end_date'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $t['team_count']; ?> cặp VĐV</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-layer-group"></i>
                                        <span><?php echo $t['group_count']; ?> bảng đấu</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-table-tennis"></i>
                                        <span><?php echo $t['match_count']; ?> trận đấu</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-flag"></i>
                                        <span class="badge bg-<?php echo $statusLabels[$t['status']][1] ?? 'secondary'; ?>">
                                            <?php echo $statusLabels[$t['status']][0] ?? $t['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Upcoming Tournaments -->
            <div class="col-md-6 mb-4">
                <h3 class="section-title"><i class="fas fa-clock text-info me-2"></i>Giải sắp diễn ra</h3>
                <?php if (empty($upcomingTournaments)): ?>
                    <div class="text-muted text-center py-4">Không có giải sắp diễn ra</div>
                <?php else: ?>
                    <?php foreach ($upcomingTournaments as $t): ?>
                        <a href="tournament_view.php?id=<?php echo $t['id']; ?>" class="tournament-card d-block text-decoration-none">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="tournament-title"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i><?php echo $t['start_date']; ?> - <?php echo $t['end_date']; ?>
                                    </small>
                                </div>
                                <span class="badge bg-info">Sắp diễn ra</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="tournament_list.php?tab=upcoming" class="btn btn-outline-primary mt-2">Xem tất cả <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>

        <!-- Recent Matches -->
        <?php 
        $col1 = array_slice($recentMatches, 0, ceil(count($recentMatches) / 3));
        $col2 = array_slice($recentMatches, ceil(count($recentMatches) / 3), ceil(count($recentMatches) / 3));
        $col3 = array_slice($recentMatches, ceil(count($recentMatches) * 2 / 3));
        ?>
        <div class="row mt-4">
            <div class="col-12">
                <h3 class="section-title"><i class="fas fa-history me-2"></i>Trận đấu gần đây</h3>
            </div>
            <?php if (empty($recentMatches)): ?>
                <div class="col-12">
                    <div class="text-muted text-center py-4">Chưa có trận đấu nào</div>
                </div>
            <?php else: ?>
                <div class="col-md-4">
                    <?php foreach ($col1 as $m): ?>
                        <div class="match-card">
                            <div class="row align-items-center">
                                <div class="col-5">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team1_name'] ?? 'TBD'); ?></div>
                                </div>
                                <div class="col-2 text-center">
                                    <span class="score"><?php echo $m['score1'] ?? 0; ?> - <?php echo $m['score2'] ?? 0; ?></span>
                                </div>
                                <div class="col-5 text-end">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team2_name'] ?? 'TBD'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['tournament_name'] ?? ''); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-4">
                    <?php foreach ($col2 as $m): ?>
                        <div class="match-card">
                            <div class="row align-items-center">
                                <div class="col-5">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team1_name'] ?? 'TBD'); ?></div>
                                </div>
                                <div class="col-2 text-center">
                                    <span class="score"><?php echo $m['score1'] ?? 0; ?> - <?php echo $m['score2'] ?? 0; ?></span>
                                </div>
                                <div class="col-5 text-end">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team2_name'] ?? 'TBD'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['tournament_name'] ?? ''); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-4">
                    <?php foreach ($col3 as $m): ?>
                        <div class="match-card">
                            <div class="row align-items-center">
                                <div class="col-5">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team1_name'] ?? 'TBD'); ?></div>
                                </div>
                                <div class="col-2 text-center">
                                    <span class="score"><?php echo $m['score1'] ?? 0; ?> - <?php echo $m['score2'] ?? 0; ?></span>
                                </div>
                                <div class="col-5 text-end">
                                    <div class="team-name"><?php echo htmlspecialchars($m['team2_name'] ?? 'TBD'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['tournament_name'] ?? ''); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Features -->
        <div class="row mt-5">
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon"><i class="fas fa-trophy"></i></div>
                    <div class="feature-title">Quản lý giải đấu</div>
                    <p class="text-muted">Tạo và quản lý nhiều giải đấu cùng lúc với các thể thức khác nhau</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon"><i class="fas fa-random"></i></div>
                    <div class="feature-title">Bốc thăm tự động</div>
                    <p class="text-muted">Chia bảng và tạo lịch thi đấu tự động với thuật toán công bằng</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon"><i class="fas fa-live"></i></div>
                    <div class="feature-title">Cập nhật trực tiếp</div>
                    <p class="text-muted">Theo dõi và cập nhật tỷ số trận đấu real-time</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 TRỌNG TÀI SỐ - Quản lý giải đấu Pickleball</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
