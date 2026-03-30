<?php
// match-control.php - Điều hành trận đấu
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

$message = '';
$matchId = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;
$tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$action = $_GET['action'] ?? 'list';

// Xử lý AJAX cập nhật tỷ số
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_score') {
        $matchId = intval($_POST['match_id']);
        $score1 = intval($_POST['score1']);
        $score2 = intval($_POST['score2']);
        $status = $_POST['status'] ?? 'pending';
        
        $stmt = $pdo->prepare("UPDATE Matches SET score1 = ?, score2 = ?, status = ?, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$score1, $score2, $status, $matchId]);
        
        if ($status === 'completed') {
            $winnerId = $score1 > $score2 ? $_POST['team1_id'] : $_POST['team2_id'];
            $pdo->prepare("UPDATE Matches SET winner_id = ? WHERE id = ?")->execute([$winnerId, $matchId]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'start_match') {
        $matchId = intval($_POST['match_id']);
        $stmt = $pdo->prepare("UPDATE Matches SET status = 'live' WHERE id = ?");
        $stmt->execute([$matchId]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_match') {
        $matchId = intval($_POST['match_id']);
        $stmt = $pdo->prepare("DELETE FROM Matches WHERE id = ?");
        $stmt->execute([$matchId]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Lấy danh sách giải đấu
$tournaments = getAllTournaments();

// Lấy thông tin giải đấu hiện tại
$tournament = null;
if ($tournamentId > 0) {
    $tournament = getTournamentById($tournamentId);
}

// Lấy danh sách trận đấu
$matches = [];
if ($tournamentId > 0) {
    $matches = getMatchesByTournament($tournamentId);
} else {
    $matches = getAllMatches();
}

// Lấy trận đấu cụ thể
$currentMatch = null;
if ($matchId > 0) {
    $currentMatch = getMatchById($matchId);
    if ($currentMatch) {
        $t1 = getTeamInfo($currentMatch['team1_id']);
        $t2 = getTeamInfo($currentMatch['team2_id']);
    }
}

// Phân loại trận đấu
$pendingMatches = array_filter($matches, fn($m) => $m['status'] === 'pending');
$liveMatches = array_filter($matches, fn($m) => $m['status'] === 'live');
$completedMatches = array_filter($matches, fn($m) => $m['status'] === 'completed');

$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điều hành trận đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .match-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 10px; border-left: 4px solid var(--primary); transition: all 0.2s; }
        .match-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .match-card.live { border-left-color: #dc3545; animation: pulse 2s infinite; }
        .match-card.completed { border-left-color: #6c757d; opacity: 0.8; }
        .match-card.pending { border-left-color: #ffc107; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(220,53,69,0.4); } 50% { box-shadow: 0 0 0 10px rgba(220,53,69,0); } }
        .team-name { font-weight: 700; font-size: 1.1rem; }
        .team-players { font-size: 0.85rem; color: #64748b; }
        .score-display { font-size: 3rem; font-weight: 800; color: var(--accent); }
        .score-input { width: 80px; text-align: center; font-size: 2rem; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 10px; padding: 10px; }
        .score-input:focus { border-color: var(--primary); outline: none; }
        .control-btn { width: 50px; height: 50px; border-radius: 50%; font-size: 1.2rem; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; }
        .nav-tab:hover, .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); text-decoration: none; }
        .filter-bar { background: white; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php renderNavbar('control'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title"><i class="fas fa-whistle me-2"></i>ĐIỀU HÀNH TRẬN ĐẤU</h1>
                    <?php if ($tournament): ?>
                        <p class="mb-0 mt-2"><?php echo htmlspecialchars($tournament['name']); ?></p>
                    <?php else: ?>
                        <p class="mb-0 mt-2">Tất cả giải đấu</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="matches.php<?php echo $tournamentId ? "?tournament_id=$tournamentId" : ''; ?>" class="btn btn-light">
                        <i class="fas fa-list me-1"></i>Danh sách trận
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Filter -->
        <div class="filter-bar">
            <form method="get" class="row g-3">
                <div class="col-md-6">
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
            </form>
        </div>

        <div class="row">
            <!-- Danh sách trận đấu -->
            <div class="col-md-5">
                <h5 class="mb-3"><i class="fas fa-list me-2"></i>Danh sách trận đấu</h5>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-tab <?php echo ($action === 'list') ? 'active' : ''; ?>" href="?action=list&tournament_id=<?php echo $tournamentId; ?>">
                            Tất cả (<?php echo count($matches); ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-tab <?php echo ($action === 'live') ? 'active' : ''; ?>" href="?action=live&tournament_id=<?php echo $tournamentId; ?>">
                            <span class="text-danger">●</span> Live (<?php echo count($liveMatches); ?>)
                        </a>
                    </li>
                </ul>

                <?php 
                $displayMatches = $action === 'live' ? $liveMatches : $matches;
                if (empty($displayMatches)): 
                ?>
                    <div class="text-muted text-center py-4">Không có trận đấu</div>
                <?php else: ?>
                    <?php foreach ($displayMatches as $m): ?>
                        <?php 
                        $t1 = getTeamInfo($m['team1_id']);
                        $t2 = getTeamInfo($m['team2_id']);
                        $isActive = $matchId === $m['id'];
                        ?>
                        <a href="?match_id=<?php echo $m['id']; ?>&tournament_id=<?php echo $tournamentId; ?>" 
                           class="text-decoration-none">
                            <div class="match-card <?php echo $m['status']; ?> <?php echo $isActive ? 'border border-primary' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="min-width: 120px;">
                                        <?php echo renderTeamPlayers($t1, '', 'font-weight: 700; font-size: 0.95rem;'); ?>
                                    </div>
                                    <div class="text-center">
                                        <?php if ($m['status'] === 'completed' || $m['score1'] !== null): ?>
                                            <div class="score-display" style="font-size: 1.5rem;"><?php echo $m['score1'] ?? 0; ?> - <?php echo $m['score2'] ?? 0; ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">vs</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end" style="min-width: 120px;">
                                        <?php echo renderTeamPlayers($t2, '', 'font-weight: 700; font-size: 0.95rem; text-align: right;'); ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Chi tiết trận đấu -->
            <div class="col-md-7">
                <?php if ($currentMatch): ?>
                    <h5 class="mb-3"><i class="fas fa-gamepad me-2"></i>Điều hành trận đấu</h5>
                    
                    <div class="card-custom" style="background: white; border-radius: 12px; padding: 25px;">
                        <!-- Hai đội -->
                        <div class="row align-items-center text-center mb-4">
                            <div class="col-5">
                                <?php echo renderTeamPlayers($t1, '', 'font-weight: 700; font-size: 1.2rem;'); ?>
                                <input type="hidden" id="team1_id" value="<?php echo $currentMatch['team1_id']; ?>">
                            </div>
                            <div class="col-2">
                                <div class="text-muted fw-bold">VS</div>
                            </div>
                            <div class="col-5">
                                <?php echo renderTeamPlayers($t2, '', 'font-weight: 700; font-size: 1.2rem; text-align: right;'); ?>
                                <input type="hidden" id="team2_id" value="<?php echo $currentMatch['team2_id']; ?>">
                            </div>
                        </div>

                        <!-- Tỷ số -->
                        <div class="row align-items-center justify-content-center mb-4">
                            <div class="col-4 text-center">
                                <input type="number" id="score1" class="score-input" value="<?php echo $currentMatch['score1'] ?? 0; ?>" min="0">
                            </div>
                            <div class="col-2 text-center">
                                <span style="font-size: 2rem; color: #94a3b8;">-</span>
                            </div>
                            <div class="col-4 text-center">
                                <input type="number" id="score2" class="score-input" value="<?php echo $currentMatch['score2'] ?? 0; ?>" min="0">
                            </div>
                        </div>

                        <!-- Nút điều khiển -->
                        <div class="text-center">
                            <input type="hidden" id="match_id" value="<?php echo $matchId; ?>">
                            
                            <?php if ($currentMatch['status'] === 'pending'): ?>
                                <button class="btn btn-success btn-lg me-2" onclick="startMatch()">
                                    <i class="fas fa-play me-1"></i>Bắt đầu trận đấu
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-primary btn-lg me-2" onclick="updateScore()">
                                <i class="fas fa-save me-1"></i>Lưu tỷ số
                            </button>
                            
                            <?php if ($currentMatch['status'] !== 'completed'): ?>
                                <button class="btn btn-success btn-lg" onclick="finishMatch()">
                                    <i class="fas fa-check me-1"></i>Hoàn thành trận
                                </button>
                            <?php endif; ?>
                            
                            <hr>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteMatch()">
                                <i class="fas fa-trash me-1"></i>Xóa trận đấu
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-hand-pointer-up" style="font-size: 3rem; opacity: 0.5;"></i>
                        <h5 class="mt-3">Chọn một trận đấu để điều hành</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function updateScore() {
        const matchId = document.getElementById('match_id').value;
        const score1 = document.getElementById('score1').value;
        const score2 = document.getElementById('score2').value;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=update_score&match_id=' + matchId + '&score1=' + score1 + '&score2=' + score2 + '&status=pending'
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
    }
    
    function startMatch() {
        const matchId = document.getElementById('match_id').value;
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=start_match&match_id=' + matchId
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
    }
    
    function finishMatch() {
        const matchId = document.getElementById('match_id').value;
        const score1 = document.getElementById('score1').value;
        const score2 = document.getElementById('score2').value;
        const team1Id = document.getElementById('team1_id').value;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=update_score&match_id=' + matchId + '&score1=' + score1 + '&score2=' + score2 + '&status=completed&team1_id=' + team1Id
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
    }
    
    function deleteMatch() {
        if (!confirm('Bạn có chắc muốn xóa trận đấu này?')) return;
        const matchId = document.getElementById('match_id').value;
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_match&match_id=' + matchId
        }).then(r => r.json()).then(data => {
            if (data.success) window.location.href = 'match-control.php?tournament_id=<?php echo $tournamentId; ?>';
        });
    }
    </script>
</body>
</html>
