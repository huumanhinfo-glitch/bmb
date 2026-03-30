<?php
// tournament_dashboard.php - Dashboard điều hành giải đấu
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

if ($user_role !== 'admin' && $user_role !== 'manager') {
    die("Access denied");
}

$tournamentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tournamentId) {
    die("Thiếu ID giải đấu");
}

$tournament = getTournamentById($tournamentId);
if (!$tournament) {
    die("Giải đấu không tồn tại");
}

$stageLabels = [
    'planning' => 'Lập kế hoạch',
    'registration' => 'Đăng ký',
    'setup' => 'Chuẩn bị',
    'group_stage' => 'Vòng bảng',
    'knockout_stage' => 'Vòng loại trực tiếp',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'advance_stage') {
            advanceTournamentStage($tournamentId);
            header("Location: tournament_dashboard.php?id=$tournamentId");
            exit;
        }
        
        if ($_POST['action'] === 'update_match_status') {
            $matchId = intval($_POST['match_id']);
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE Matches SET status = ? WHERE id = ?");
            $stmt->execute([$status, $matchId]);
        }
    }
}

$pendingMatches = getPendingMatches($tournamentId);
$completedMatches = getCompletedMatches($tournamentId);
$groups = getGroupsByTournament($tournamentId);
$teams = fetchTeamsByTournament($tournamentId);
$categories = getTournamentCategories($tournamentId);
$arenas = getAllArenas();
$nextMatch = getNextScheduledMatch($tournamentId);

renderHeader('Dashboard Điều Hành - ' . htmlspecialchars($tournament['name']), '');
?>

<style>
    .stage-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        border: 1px solid #e2e8f0;
    }
    .stage-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .stage-badge-custom {
        font-size: 1.1rem;
        padding: 8px 16px;
    }
    .match-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #2ecc71;
    }
    .match-card.pending { border-left-color: #ffc107; }
    .match-card.live { border-left-color: #dc3545; animation: pulse 2s infinite; }
    .match-card.completed { border-left-color: #6c757d; }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .score-display {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .court-indicator {
        background: #e9ecef;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .quick-link-btn {
        padding: 12px 20px;
        border-radius: 10px;
    }
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>DASHBOARD ĐIỀU HÀNH</h1>
                <p class="mb-0 mt-2"><?php echo htmlspecialchars($tournament['name']); ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-<?php echo $stageColors[$tournament['stage']] ?? 'secondary'; ?> stage-badge-custom">
                    <?php echo $stageLabels[$tournament['stage']] ?? 'Không xác định'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teams); ?></div>
                <div class="stat-label">Đội tham dự</div>
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
                <div class="stat-number"><?php echo count($pendingMatches); ?></div>
                <div class="stat-label">Trận chờ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($completedMatches); ?></div>
                <div class="stat-label">Trận hoàn thành</div>
            </div>
        </div>
    </div>

    <!-- Stage Management -->
    <div class="card-custom">
        <h5 class="mb-3"><i class="fas fa-tasks me-2"></i>Quản lý giai đoạn</h5>
        <div class="row mt-3">
            <?php foreach ($stageLabels as $stage => $label): ?>
                <div class="col-md-2 col-6 mb-2">
                    <div class="card <?php echo ($tournament['stage'] === $stage) ? 'border-success' : ''; ?>">
                        <div class="card-body text-center py-2">
                            <span class="badge bg-<?php echo $stageColors[$stage]; ?>">
                                <?php echo $label; ?>
                            </span>
                            <?php if ($tournament['stage'] === $stage): ?>
                                <div class="mt-2"><i class="fas fa-check-circle text-success"></i></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($tournament['stage'] !== 'completed'): ?>
            <form method="post" class="mt-3">
                <input type="hidden" name="action" value="advance_stage">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-forward me-1"></i>Chuyển sang giai đoạn tiếp theo
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="row">
        <!-- Pending Matches -->
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Trận đấu chờ</h5>
                <?php if (empty($pendingMatches)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-calendar-check fa-2x mb-3"></i>
                        <p class="mb-0">Không có trận đấu nào đang chờ</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($pendingMatches, 0, 5) as $match): ?>
                        <div class="match-card pending">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($match['team1_name'] ?? 'TBD'); ?></strong>
                                    <span class="mx-2">vs</span>
                                    <strong><?php echo htmlspecialchars($match['team2_name'] ?? 'TBD'); ?></strong>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="update_match_status">
                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                    <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                        <option value="pending" selected>Chờ</option>
                                        <option value="live">Đang đấu</option>
                                        <option value="completed">Hoàn thành</option>
                                    </select>
                                </form>
                            </div>
                            <small class="text-muted">
                                <?php echo $match['group_name'] ?? ''; ?> | 
                                <?php echo $match['round'] ?? ''; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($pendingMatches) > 5): ?>
                        <a href="match-control.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-sm btn-outline-primary">
                            Xem tất cả (<?php echo count($pendingMatches); ?>)
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Matches -->
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3"><i class="fas fa-check-circle me-2"></i>Trận đấu hoàn thành gần nhất</h5>
                <?php if (empty($completedMatches)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-clipboard-list fa-2x mb-3"></i>
                        <p class="mb-0">Chưa có trận đấu nào hoàn thành</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($completedMatches, 0, 5) as $match): ?>
                        <div class="match-card completed">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($match['team1_name'] ?? 'TBD'); ?></strong>
                                    <span class="score-display mx-2">
                                        <?php echo $match['score1'] ?? 0; ?> - <?php echo $match['score2'] ?? 0; ?>
                                    </span>
                                    <strong><?php echo htmlspecialchars($match['team2_name'] ?? 'TBD'); ?></strong>
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $match['group_name'] ?? ''; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card-custom">
        <h5 class="mb-3"><i class="fas fa-link me-2"></i>Liên kết nhanh</h5>
        <div class="row">
            <div class="col-md-3 mb-2">
                <a href="draw.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-primary w-100 quick-link-btn">
                    <i class="fas fa-random me-1"></i>Bốc thăm chia bảng
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="match-control.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-success w-100 quick-link-btn">
                    <i class="fas fa-whistle me-1"></i>Điều hành trận đấu
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="admin.php?action=tournaments&edit_id=<?php echo $tournamentId; ?>" class="btn btn-outline-warning w-100 quick-link-btn">
                    <i class="fas fa-edit me-1"></i>Chỉnh sửa giải đấu
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="tournament_view.php?id=<?php echo $tournamentId; ?>" class="btn btn-outline-info w-100 quick-link-btn">
                    <i class="fas fa-eye me-1"></i>Xem trang công khai
                </a>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
