<?php
// matches.php - Trang quản lý trận đấu
require_once 'db.php';
require_once 'functions.php';

$message = '';
$activeTab = $_GET['tab'] ?? 'all';
$tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$statusFilter = $_GET['status'] ?? '';
$groupFilter = isset($_GET['group']) ? intval($_GET['group']) : 0;

// Lấy danh sách giải đấu
$tournaments = getAllTournaments();

// Lấy thông tin giải đấu hiện tại
$tournament = null;
if ($tournamentId > 0) {
    $tournament = getTournamentById($tournamentId);
}

// Lấy danh sách bảng đấu
$groups = [];
if ($tournamentId > 0) {
    $groups = getGroupsByTournament($tournamentId);
} else {
    $groups = fetchAllGroups();
}

// Lấy trận đấu theo filter
$matches = [];
if ($tournamentId > 0) {
    $matches = getMatchesByTournament($tournamentId);
} else {
    $matches = getAllMatches();
}

// Lọc theo status
if ($statusFilter) {
    $matches = array_filter($matches, function($m) use ($statusFilter) {
        return $m['status'] === $statusFilter;
    });
}

// Lọc theo group
if ($groupFilter > 0) {
    $matches = array_filter($matches, function($m) use ($groupFilter) {
        return $m['group_id'] == $groupFilter;
    });
}

// Lọc theo tab
$tabMatches = [];
switch ($activeTab) {
    case 'pending':
        $tabMatches = array_filter($matches, function($m) { return $m['status'] === 'pending'; });
        break;
    case 'live':
        $tabMatches = array_filter($matches, function($m) { return $m['status'] === 'live'; });
        break;
    case 'completed':
        $tabMatches = array_filter($matches, function($m) { return $m['status'] === 'completed'; });
        break;
    default:
        $tabMatches = $matches;
}

// Tính thống kê
$stats = [
    'total' => count($matches),
    'pending' => count(array_filter($matches, fn($m) => $m['status'] === 'pending')),
    'live' => count(array_filter($matches, fn($m) => $m['status'] === 'live')),
    'completed' => count(array_filter($matches, fn($m) => $m['status'] === 'completed'))
];

// Xử lý cập nhật kết quả
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_score'])) {
    $matchId = intval($_POST['match_id']);
    $score1 = intval($_POST['score1']);
    $score2 = intval($_POST['score2']);
    
    if (updateMatchScore($matchId, $score1, $score2)) {
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã cập nhật kết quả!</div>';
        header("Location: matches.php?tournament_id=$tournamentId&tab=$activeTab");
        exit;
    }
}

// Xử lý cập nhật status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $matchId = intval($_POST['match_id']);
    $newStatus = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE Matches SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $matchId]);
    header("Location: matches.php?tournament_id=$tournamentId&tab=$activeTab");
    exit;
}

$stageLabels = [
    'planning' => 'Lập kế hoạch',
    'registration' => 'Đăng ký',
    'setup' => 'Chuẩn bị',
    'group_stage' => 'Vòng bảng',
    'knockout_stage' => 'Vòng loại',
    'completed' => 'Hoàn thành'
];

$statusLabels = [
    'pending' => ['Chờ', 'secondary'],
    'live' => ['Đang đấu', 'danger'],
    'completed' => ['Hoàn thành', 'success']
];
?>
<?php require_once 'components/template.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Trận đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .stat-card { background: white; border-radius: 10px; padding: 15px; text-center: center; border-left: 4px solid var(--primary); }
        .stat-card.live { border-left-color: #dc3545; }
        .stat-card.completed { border-left-color: #6c757d; }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; }
        .filter-bar { background: white; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .match-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid var(--primary); transition: all 0.2s; }
        .match-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .match-card.live { border-left-color: #dc3545; animation: pulse 2s infinite; }
        .match-card.completed { border-left-color: #6c757d; opacity: 0.85; }
        .match-card.pending { border-left-color: #ffc107; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(220,53,69,0.4); } 50% { box-shadow: 0 0 0 10px rgba(220,53,69,0); } }
        .team-name { font-weight: 600; font-size: 1rem; }
        .team-players { font-size: 0.8rem; color: #64748b; }
        .score { font-size: 1.8rem; font-weight: 800; color: var(--accent); }
        .vs-text { font-weight: 600; color: #94a3b8; }
        .badge-status { font-size: 0.75rem; padding: 5px 10px; }
        .quick-btn { padding: 8px 15px; border-radius: 8px; font-size: 0.85rem; }
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; }
        .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .stage-badge { font-size: 0.75rem; padding: 4px 10px; background: #e9ecef; border-radius: 20px; }
    </style>
</head>
<body>
    <?php require_once 'components/template.php'; renderNavbar('matches'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="fas fa-bullhorn me-2"></i>QUẢN LÝ TRẬN ĐẤU
                    </h1>
                    <?php if ($tournament): ?>
                        <p class="mb-0 mt-2">
                            <strong><?php echo htmlspecialchars($tournament['name']); ?></strong>
                            <span class="stage-badge ms-2"><?php echo $stageLabels[$tournament['stage']] ?? 'Không xác định'; ?></span>
                        </p>
                    <?php else: ?>
                        <p class="mb-0 mt-2">Tất cả giải đấu</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($tournamentId > 0): ?>
                        <a href="match-control.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-light">
                            <i class="fas fa-gamepad me-1"></i>Điều hành trận đấu
                        </a>
                        <a href="tournament_dashboard.php?id=<?php echo $tournamentId; ?>" class="btn btn-warning">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <?php echo $message; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Tổng trận</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Chờ thi đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card live">
                    <div class="stat-number"><?php echo $stats['live']; ?></div>
                    <div class="stat-label">Đang đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Hoàn thành</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Giải đấu</label>
                    <select name="tournament_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Tất cả giải đấu</option>
                        <?php foreach ($tournaments as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($tournamentId == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($tournamentId > 0 && !empty($groups)): ?>
                <div class="col-md-3">
                    <label class="form-label">Bảng đấu</label>
                    <select name="group" class="form-select" onchange="this.form.submit()">
                        <option value="0">Tất cả bảng</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?php echo $g['id']; ?>" <?php echo ($groupFilter == $g['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['group_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả</option>
                        <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Chờ</option>
                        <option value="live" <?php echo ($statusFilter === 'live') ? 'selected' : ''; ?>>Đang đấu</option>
                        <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Hoàn thành</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="matches.php<?php echo $tournamentId ? "?tournament_id=$tournamentId" : ''; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Xóa lọc
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'all') ? 'active' : ''; ?>" href="?tab=all&tournament_id=<?php echo $tournamentId; ?>&status=<?php echo $statusFilter; ?>">
                    Tất cả <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'pending') ? 'active' : ''; ?>" href="?tab=pending&tournament_id=<?php echo $tournamentId; ?>&status=<?php echo $statusFilter; ?>">
                    <i class="fas fa-clock me-1"></i>Chờ <span class="badge bg-warning"><?php echo $stats['pending']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'live') ? 'active' : ''; ?>" href="?tab=live&tournament_id=<?php echo $tournamentId; ?>&status=<?php echo $statusFilter; ?>">
                    <i class="fas fa-circle text-danger me-1"></i>Đang đấu <span class="badge bg-danger"><?php echo $stats['live']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'completed') ? 'active' : ''; ?>" href="?tab=completed&tournament_id=<?php echo $tournamentId; ?>&status=<?php echo $statusFilter; ?>">
                    <i class="fas fa-check-circle me-1"></i>Hoàn thành <span class="badge bg-success"><?php echo $stats['completed']; ?></span>
                </a>
            </li>
        </ul>

        <!-- Match List -->
        <?php if (empty($tabMatches)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>Không có trận đấu nào</h4>
                <p>
                    <?php if ($tournamentId > 0): ?>
                        <a href="draw.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-primary">
                            <i class="fas fa-random me-1"></i>Bốc thăm chia bảng
                        </a>
                    <?php else: ?>
                        Chọn một giải đấu để xem trận đấu
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php 
            $col1 = array_slice($tabMatches, 0, ceil(count($tabMatches) / 2));
            $col2 = array_slice($tabMatches, ceil(count($tabMatches) / 2));
            ?>
            <div class="row">
                <div class="col-md-6">
                    <?php foreach ($col1 as $match): ?>
                        <?php 
                            $statusInfo = $statusLabels[$match['status']] ?? ['Chờ', 'secondary'];
                            $t1 = getTeamInfo($match['team1_id']);
                            $t2 = getTeamInfo($match['team2_id']);
                        ?>
                        <div class="match-card <?php echo $match['status']; ?>">
                            <div class="row align-items-center">
                                <div class="col-2 text-center">
                                    <span class="badge bg-<?php echo $statusInfo[1]; ?> badge-status"><?php echo $statusInfo[0]; ?></span>
                                    <?php if ($match['group_name']): ?>
                                        <div class="mt-2 small text-muted"><?php echo $match['group_name']; ?></div>
                                    <?php endif; ?>
                                    <?php if ($match['round']): ?>
                                        <div class="small text-muted"><?php echo $match['round']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-4">
                                    <?php echo renderTeamPlayers($t1); ?>
                                </div>
                                <div class="col-2 text-center">
                                    <div class="score">
                                        <?php if ($match['status'] === 'completed' || $match['score1'] !== null): ?>
                                            <?php echo $match['score1'] ?? 0; ?> - <?php echo $match['score2'] ?? 0; ?>
                                        <?php else: ?>
                                            vs
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-3 text-end">
                                    <?php echo renderTeamPlayers($t2); ?>
                                </div>
                                <div class="col-1 text-end">
                                    <?php if ($match['status'] === 'pending'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" selected>Chờ</option>
                                                <option value="live">Đang đấu</option>
                                                <option value="completed">Hoàn thành</option>
                                            </select>
                                        </form>
                                    <?php elseif ($match['status'] === 'live'): ?>
                                        <a href="match-control.php?match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-danger">
                                            <i class="fas fa-gamepad"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="matches.php?match=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <?php foreach ($col2 as $match): ?>
                        <?php 
                            $statusInfo = $statusLabels[$match['status']] ?? ['Chờ', 'secondary'];
                            $t1 = getTeamInfo($match['team1_id']);
                            $t2 = getTeamInfo($match['team2_id']);
                        ?>
                        <div class="match-card <?php echo $match['status']; ?>">
                            <div class="row align-items-center">
                                <div class="col-2 text-center">
                                    <span class="badge bg-<?php echo $statusInfo[1]; ?> badge-status"><?php echo $statusInfo[0]; ?></span>
                                    <?php if ($match['group_name']): ?>
                                        <div class="mt-2 small text-muted"><?php echo $match['group_name']; ?></div>
                                    <?php endif; ?>
                                    <?php if ($match['round']): ?>
                                        <div class="small text-muted"><?php echo $match['round']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-4">
                                    <?php echo renderTeamPlayers($t1); ?>
                                </div>
                                <div class="col-2 text-center">
                                    <div class="score">
                                        <?php if ($match['status'] === 'completed' || $match['score1'] !== null): ?>
                                            <?php echo $match['score1'] ?? 0; ?> - <?php echo $match['score2'] ?? 0; ?>
                                        <?php else: ?>
                                            vs
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-3 text-end">
                                    <?php echo renderTeamPlayers($t2); ?>
                                </div>
                                <div class="col-1 text-end">
                                    <?php if ($match['status'] === 'pending'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" selected>Chờ</option>
                                                <option value="live">Đang đấu</option>
                                                <option value="completed">Hoàn thành</option>
                                            </select>
                                        </form>
                                    <?php elseif ($match['status'] === 'live'): ?>
                                        <a href="match-control.php?match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-danger">
                                            <i class="fas fa-gamepad"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="matches.php?match=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
