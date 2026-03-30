<?php
// tournament_view.php - Xem chi tiết giải đấu
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

$tournamentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tournamentId) {
    header("Location: tournament_list.php");
    exit;
}

// Lấy thông tin giải đấu
$tournament = getTournamentById($tournamentId);

if (!$tournament) {
    header("Location: tournament_list.php");
    exit;
}

// Thống kê
$teamCount = $pdo->prepare("SELECT COUNT(*) FROM `Teams` WHERE tournament_id = ?");
$teamCount->execute([$tournamentId]);
$teamCount = $teamCount->fetchColumn();

$matchCount = $pdo->prepare("SELECT COUNT(*) FROM `Matches` WHERE tournament_id = ?");
$matchCount->execute([$tournamentId]);
$matchCount = $matchCount->fetchColumn();

$completedMatches = $pdo->prepare("SELECT COUNT(*) FROM `Matches` WHERE tournament_id = ? AND status = 'completed'");
$completedMatches->execute([$tournamentId]);
$completedMatches = $completedMatches->fetchColumn();

// Danh sách đội
$teams = fetchTeamsByTournament($tournamentId);

// Danh sách bảng
$groups = getGroupsByTournament($tournamentId);

// Các trận đấu
$matches = getMatchesByTournament($tournamentId);

// Phân loại trận đấu
$pendingMatches = array_filter($matches, fn($m) => $m['status'] === 'pending');
$liveMatches = array_filter($matches, fn($m) => $m['status'] === 'live');
$completedMatchesList = array_filter($matches, fn($m) => $m['status'] === 'completed');

$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);

$statusLabels = [
    'upcoming' => ['Sắp diễn ra', 'info'],
    'ongoing' => ['Đang diễn ra', 'success'],
    'completed' => ['Hoàn thành', 'secondary']
];

$stageLabels = [
    'planning' => 'Lập kế hoạch',
    'registration' => 'Đăng ký',
    'setup' => 'Chuẩn bị',
    'group_stage' => 'Vòng bảng',
    'knockout_stage' => 'Vòng loại',
    'completed' => 'Hoàn thành'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tournament['name']); ?> - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 40px 0; }
        .page-title { font-size: 2rem; font-weight: 700; margin: 0; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; border-bottom: 3px solid var(--primary); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; }
        .card-custom { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 15px; }
        .team-item { padding: 10px; border-bottom: 1px solid #f0f0f0; }
        .team-item:last-child { border-bottom: none; }
        .team-name { font-weight: 600; }
        .team-players { font-size: 0.85rem; color: #64748b; }
        .match-item { padding: 12px; border-left: 3px solid var(--primary); background: #fafafa; margin-bottom: 8px; border-radius: 0 8px 8px 0; }
        .match-item.live { border-left-color: #dc3545; background: #fff5f5; }
        .match-item.completed { border-left-color: #6c757d; opacity: 0.8; }
        .score { font-size: 1.3rem; font-weight: 700; color: var(--accent); }
        .badge-status { font-size: 0.75rem; padding: 5px 10px; }
        .quick-btn { padding: 8px 15px; border-radius: 8px; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; }
        .nav-tab:hover, .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); text-decoration: none; }
        .countdown-container { background: rgba(255,255,255,0.15); border-radius: 15px; padding: 15px 25px; backdrop-filter: blur(5px); }
        .countdown-box { display: inline-block; text-align: center; margin: 0 10px; }
        .countdown-number { font-size: 2rem; font-weight: 800; line-height: 1; }
        .countdown-label { font-size: 0.7rem; text-transform: uppercase; opacity: 0.9; }
    </style>
</head>
<body>
    <?php require_once 'components/template.php'; renderNavbar('tournaments'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title"><?php echo htmlspecialchars($tournament['name']); ?></h1>
                    <p class="mb-0 mt-2">
                        <?php 
                            $statusInfo = $statusLabels[$tournament['status']] ?? ['Không xác định', 'secondary'];
                        ?>
                        <span class="badge bg-<?php echo $statusInfo[1]; ?>"><?php echo $statusInfo[0]; ?></span>
                        <span class="stage-badge ms-2"><?php echo $stageLabels[$tournament['stage']] ?? 'Không xác định'; ?></span>
                    </p>
                    <p class="mt-2">
                        <i class="fas fa-calendar me-1"></i><?php echo $tournament['start_date']; ?> - <?php echo $tournament['end_date']; ?>
                        <?php if ($tournament['location']): ?>
                            <span class="ms-3"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($tournament['location']); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($tournament['status'] === 'upcoming'): ?>
                    <div class="countdown-container mb-3" id="countdown" data-start="<?php echo $tournament['start_date']; ?>">
                        <div class="countdown-box">
                            <div class="countdown-number" id="days">00</div>
                            <div class="countdown-label">Ngày</div>
                        </div>
                        <div class="countdown-box">
                            <div class="countdown-number" id="hours">00</div>
                            <div class="countdown-label">Giờ</div>
                        </div>
                        <div class="countdown-box">
                            <div class="countdown-number" id="minutes">00</div>
                            <div class="countdown-label">Phút</div>
                        </div>
                        <div class="countdown-box">
                            <div class="countdown-number" id="seconds">00</div>
                            <div class="countdown-label">Giây</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-md-end gap-2 flex-wrap">
                        <?php if ($tournament['status'] === 'upcoming'): ?>
                        <a href="register.php?id=<?php echo $tournamentId; ?>" class="btn btn-success quick-btn">
                            <i class="fas fa-edit me-1"></i>Đăng ký
                        </a>
                        <?php endif; ?>
                        <a href="matches.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-primary quick-btn">
                            <i class="fas fa-bullhorn me-1"></i>Xem trận đấu
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="draw.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-warning quick-btn">
                            <i class="fas fa-random me-1"></i>Bốc thăm
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $teamCount; ?></div>
                    <div class="stat-label">Đội tham gia</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($groups); ?></div>
                    <div class="stat-label">Bảng đấu</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $matchCount; ?></div>
                    <div class="stat-label">Tổng trận</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completedMatches; ?></div>
                    <div class="stat-label">Hoàn thành</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Danh sách đội -->
            <div class="col-md-4">
                <div class="card-custom">
                    <div class="card-title"><i class="fas fa-users me-2"></i>Danh sách đội (<?php echo $teamCount; ?>)</div>
                    <?php if (empty($teams)): ?>
                        <p class="text-muted">Chưa có đội nào</p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($teams as $team): ?>
                                <div class="team-item">
                                    <?php echo renderTeamPlayers($team); ?>
                                    <?php if ($team['group_name']): ?>
                                        <span class="badge bg-secondary"><?php echo $team['group_name']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trận đấu -->
            <div class="col-md-8">
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-tab active" href="#matches" data-bs-toggle="tab">
                            Tất cả <span class="badge bg-secondary"><?php echo count($matches); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-tab" href="#pending" data-bs-toggle="tab">
                            Chờ <span class="badge bg-warning"><?php echo count($pendingMatches); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-tab" href="#live" data-bs-toggle="tab">
                            Đang đấu <span class="badge bg-danger"><?php echo count($liveMatches); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-tab" href="#completed" data-bs-toggle="tab">
                            Hoàn thành <span class="badge bg-success"><?php echo count($completedMatchesList); ?></span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="matches">
                        <?php echo renderMatchList($matches); ?>
                    </div>
                    <div class="tab-pane fade" id="pending">
                        <?php echo renderMatchList($pendingMatches); ?>
                    </div>
                    <div class="tab-pane fade" id="live">
                        <?php echo renderMatchList($liveMatches); ?>
                    </div>
                    <div class="tab-pane fade" id="completed">
                        <?php echo renderMatchList($completedMatchesList); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 TRỌNG TÀI SỐ</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        var countdownEl = document.getElementById('countdown');
        if (!countdownEl) return;
        
        var startDate = countdownEl.dataset.start;
        var targetDate = new Date(startDate + 'T00:00:00').getTime();
        
        function updateCountdown() {
            var now = new Date().getTime();
            var distance = targetDate - now;
            
            if (distance < 0) {
                document.getElementById('days').textContent = '00';
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                return;
            }
            
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    })();
    </script>
</body>
</html>

<?php
function renderMatchList($matches) {
    if (empty($matches)) {
        return '<p class="text-muted text-center py-4">Không có trận đấu</p>';
    }
    
    $html = '';
    foreach ($matches as $match) {
        $statusClass = $match['status'];
        $t1 = getTeamInfo($match['team1_id']);
        $t2 = getTeamInfo($match['team2_id']);
        
        $html .= '<div class="match-item ' . $statusClass . '">';
        $html .= '<div class="row align-items-center">';
        $html .= '<div class="col-md-4">';
        $html .= renderTeamPlayers($t1, '', 'font-weight: 700; font-size: 1rem;');
        $html .= '</div>';
        $html .= '<div class="col-md-4 text-center">';
        
        if ($match['status'] === 'completed' || $match['score1'] !== null) {
            $html .= '<span class="score">' . ($match['score1'] ?? 0) . ' - ' . ($match['score2'] ?? 0) . '</span>';
        } else {
            $html .= '<span class="text-muted">vs</span>';
        }
        
        if ($match['group_name']) {
            $html .= '<br><small class="text-muted">' . $match['group_name'] . '</small>';
        }
        
        $html .= '</div>';
        $html .= '<div class="col-md-4 text-end">';
        $html .= renderTeamPlayers($t2, '', 'font-weight: 700; font-size: 1rem; text-align: right;');
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    return $html;
}
?>
