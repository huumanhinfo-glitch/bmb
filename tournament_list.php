<?php
// tournament_list.php - Danh sách giải đấu
require_once 'db.php';
require_once 'functions.php';

$message = '';
$activeTab = $_GET['tab'] ?? 'all';
$search = $_GET['search'] ?? '';

// Lấy danh sách giải đấu với thống kê
$query = "
    SELECT t.*, 
           (SELECT COUNT(*) FROM Teams WHERE tournament_id = t.id) as team_count,
           (SELECT COUNT(*) FROM Matches WHERE tournament_id = t.id) as match_count,
           (SELECT COUNT(*) FROM Matches WHERE tournament_id = t.id AND status = 'completed') as completed_match_count,
           (SELECT COUNT(*) FROM Groups WHERE tournament_id = t.id) as group_count
    FROM Tournaments t
    ORDER BY 
        CASE t.status 
            WHEN 'ongoing' THEN 1
            WHEN 'upcoming' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
        END,
        t.start_date DESC
";

$tournaments = $pdo->query($query)->fetchAll();

// Search filter
if ($search) {
    $tournaments = array_filter($tournaments, function($t) use ($search) {
        return stripos($t['name'], $search) !== false;
    });
}

// Filter by tab
$tabTournaments = [];
switch ($activeTab) {
    case 'upcoming':
        $tabTournaments = array_filter($tournaments, fn($t) => $t['status'] === 'upcoming');
        break;
    case 'ongoing':
        $tabTournaments = array_filter($tournaments, fn($t) => $t['status'] === 'ongoing');
        break;
    case 'completed':
        $tabTournaments = array_filter($tournaments, fn($t) => $t['status'] === 'completed');
        break;
    default:
        $tabTournaments = $tournaments;
}

// Stats
$stats = [
    'total' => count($tournaments),
    'upcoming' => count(array_filter($tournaments, fn($t) => $t['status'] === 'upcoming')),
    'ongoing' => count(array_filter($tournaments, fn($t) => $t['status'] === 'ongoing')),
    'completed' => count(array_filter($tournaments, fn($t) => $t['status'] === 'completed'))
];

$statusLabels = [
    'upcoming' => ['Sắp diễn ra', 'info', 'clock'],
    'ongoing' => ['Đang diễn ra', 'success', 'play'],
    'completed' => ['Đã hoàn thành', 'secondary', 'check'],
    'cancelled' => ['Đã hủy', 'danger', 'times']
];

$stageLabels = [
    'planning' => 'Lập kế hoạch',
    'registration' => 'Đăng ký',
    'setup' => 'Chuẩn bị',
    'group_stage' => 'Vòng bảng',
    'knockout_stage' => 'Vòng loại',
    'completed' => 'Hoàn thành'
];

$stageColors = [
    'planning' => 'secondary',
    'registration' => 'info',
    'setup' => 'warning',
    'group_stage' => 'primary',
    'knockout_stage' => 'danger',
    'completed' => 'success'
];

$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
?>
<?php require_once 'components/template.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Giải đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; margin-top: 5px; }
        .stat-card.ongoing { border-bottom: 4px solid var(--primary); }
        .stat-card.upcoming { border-bottom: 4px solid #0dcaf0; }
        .stat-card.completed { border-bottom: 4px solid #6c757d; }
        .filter-bar { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .tournament-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .tournament-card:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .tournament-title { font-size: 1.2rem; font-weight: 700; color: var(--text-dark); text-decoration: none; }
        .tournament-title:hover { color: var(--accent); }
        .tournament-meta { font-size: 0.85rem; color: #64748b; }
        .tournament-stats { display: flex; gap: 15px; margin: 10px 0; }
        .tournament-stat { display: flex; align-items: center; gap: 5px; font-size: 0.85rem; color: #64748b; }
        .tournament-stat i { color: var(--primary); }
        .stage-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 20px; background: #e9ecef; }
        .action-btn { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; }
        .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .badge-status { font-size: 0.75rem; padding: 5px 10px; }
    </style>
</head>
<body>
    <?php renderNavbar('tournaments'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title"><i class="fas fa-trophy me-2"></i>DANH SÁCH GIẢI ĐẤU</h1>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($isAdmin): ?>
                        <a href="create_tournament.php" class="btn btn-light">
                            <i class="fas fa-plus me-1"></i>Tạo giải mới
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Tổng giải</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card upcoming">
                    <div class="stat-number"><?php echo $stats['upcoming']; ?></div>
                    <div class="stat-label"><i class="fas fa-clock me-1"></i>Sắp diễn ra</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card ongoing">
                    <div class="stat-number"><?php echo $stats['ongoing']; ?></div>
                    <div class="stat-label"><i class="fas fa-play me-1"></i>Đang diễn ra</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label"><i class="fas fa-check me-1"></i>Đã hoàn thành</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="get" class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm giải đấu..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
                </div>
            </form>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'all') ? 'active' : ''; ?>" href="?tab=all">
                    Tất cả <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'upcoming') ? 'active' : ''; ?>" href="?tab=upcoming">
                    <i class="fas fa-clock me-1"></i>Sắp diễn ra <span class="badge bg-info"><?php echo $stats['upcoming']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'ongoing') ? 'active' : ''; ?>" href="?tab=ongoing">
                    <i class="fas fa-play me-1 text-success"></i>Đang diễn ra <span class="badge bg-success"><?php echo $stats['ongoing']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'completed') ? 'active' : ''; ?>" href="?tab=completed">
                    <i class="fas fa-check-circle me-1"></i>Hoàn thành <span class="badge bg-secondary"><?php echo $stats['completed']; ?></span>
                </a>
            </li>
        </ul>

        <!-- Tournament List -->
        <?php if (empty($tabTournaments)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h4>Không có giải đấu nào</h4>
                <?php if ($isAdmin): ?>
                    <a href="create_tournament.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus me-1"></i>Tạo giải đấu mới
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($tabTournaments as $tournament): ?>
                <?php 
                    $statusInfo = $statusLabels[$tournament['status']] ?? ['Không xác định', 'secondary', 'question'];
                    $progress = $tournament['match_count'] > 0 ? round($tournament['completed_match_count'] / $tournament['match_count'] * 100) : 0;
                ?>
                <div class="tournament-card">
                    <div class="row">
                        <div class="col-md-8">
                            <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="tournament-title">
                                <?php echo htmlspecialchars($tournament['name']); ?>
                            </a>
                            
                            <div class="tournament-meta mt-1">
                                <span class="badge bg-<?php echo $statusInfo[1]; ?> badge-status">
                                    <i class="fas fa-<?php echo $statusInfo[2]; ?> me-1"></i><?php echo $statusInfo[0]; ?>
                                </span>
                                <span class="stage-badge ms-2">
                                    <i class="fas fa-flag me-1"></i><?php echo $stageLabels[$tournament['stage']] ?? 'Không xác định'; ?>
                                </span>
                            </div>

                            <div class="tournament-stats mt-2">
                                <div class="tournament-stat">
                                    <i class="fas fa-users"></i> <?php echo $tournament['team_count']; ?> đội
                                </div>
                                <div class="tournament-stat">
                                    <i class="fas fa-layer-group"></i> <?php echo $tournament['group_count']; ?> bảng
                                </div>
                                <div class="tournament-stat">
                                    <i class="fas fa-bullhorn"></i> <?php echo $tournament['match_count']; ?> trận
                                </div>
                                <div class="tournament-stat">
                                    <i class="fas fa-check"></i> <?php echo $tournament['completed_match_count']; ?> đã đấu
                                </div>
                            </div>

                            <div class="tournament-meta">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo $tournament['start_date']; ?> - <?php echo $tournament['end_date']; ?>
                                <?php if ($tournament['location']): ?>
                                    <span class="ms-3"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($tournament['location']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($tournament['status'] === 'ongoing'): ?>
                                <div class="mt-2">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <small class="text-muted">Tiến độ: <?php echo $progress; ?>%</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex flex-md-column gap-2 justify-content-md-end">
                                <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="btn btn-outline-primary action-btn">
                                    <i class="fas fa-eye me-1"></i>Xem
                                </a>
                                <a href="matches.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-outline-secondary action-btn">
                                    <i class="fas fa-bullhorn me-1"></i>Trận đấu
                                </a>
                                <a href="match-control.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-outline-success action-btn">
                                    <i class="fas fa-whistle me-1"></i>Điều hành
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="tournament_dashboard.php?id=<?php echo $tournament['id']; ?>" class="btn btn-warning action-btn">
                                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
