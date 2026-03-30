<?php
// draw.php - Trang chia bảng đấu (Phiên bản đơn giản)
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

$message = '';
$activeTab = $_GET['tab'] ?? 'teams';
$tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;

// Lấy danh sách giải đấu
$tournaments = getAllTournaments();

// Lấy thông tin giải đấu hiện tại
$tournament = null;
if ($tournamentId > 0) {
    $tournament = getTournamentById($tournamentId);
}

// Lấy danh sách đội
$teams = [];
if ($tournamentId > 0) {
    $teams = fetchTeamsByTournament($tournamentId);
}

// Lấy danh sách bảng
$groups = [];
if ($tournamentId > 0) {
    $groups = getGroupsByTournament($tournamentId);
}

// Lấy trận đấu
$matches = [];
if ($tournamentId > 0) {
    $matches = getMatchesByTournament($tournamentId);
}

// Xử lý import CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_teams'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $imported = 0;
        $skipped = 0;
        
        fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $teamName = trim($row[0] ?? '');
            $player1 = trim($row[1] ?? '');
            $player2 = trim($row[2] ?? '');
            $skill = trim($row[3] ?? '');
            
            if (empty($teamName)) continue;
            
            // Check trùng
            $check = $pdo->prepare("SELECT id FROM Teams WHERE team_name = ? AND tournament_id = ?");
            $check->execute([$teamName, $tournamentId]);
            
            if ($check->fetch()) {
                $skipped++;
                continue;
            }
            
            $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$teamName, $player1, $player2, $tournamentId, $skill]);
            $imported++;
        }
        fclose($handle);
        
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Import thành công: ' . $imported . ' đội, ' . $skipped . ' đội trùng</div>';
        $teams = fetchTeamsByTournament($tournamentId);
    }
}

// Xử lý thêm đội thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $teamName = trim($_POST['team_name'] ?? '');
    $player1 = trim($_POST['player1'] ?? '');
    $player2 = trim($_POST['player2'] ?? '');
    $skill = trim($_POST['skill_level'] ?? '');
    
    if ($teamName && $tournamentId > 0) {
        $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$teamName, $player1, $player2, $tournamentId, $skill]);
        $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã thêm đội: ' . htmlspecialchars($teamName) . '</div>';
        $teams = fetchTeamsByTournament($tournamentId);
    }
}

// Xử lý xóa đội
if (isset($_GET['delete_team'])) {
    $teamId = intval($_GET['delete_team']);
    $pdo->prepare("DELETE FROM Teams WHERE id = ?")->execute([$teamId]);
    $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa đội</div>';
    $teams = fetchTeamsByTournament($tournamentId);
}

// Xử lý bốc thăm chia bảng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw_groups'])) {
    $numGroups = intval($_POST['num_groups'] ?? 4);
    $drawType = $_POST['draw_type'] ?? 'round_robin';
    $knockoutTeams = intval($_POST['knockout_teams'] ?? 4);
    
    if ($tournamentId > 0 && !empty($teams)) {
        try {
            // Xóa dữ liệu cũ
            $pdo->prepare("DELETE FROM Matches WHERE tournament_id = ?")->execute([$tournamentId]);
            $pdo->prepare("DELETE FROM Groups WHERE tournament_id = ?")->execute([$tournamentId]);
            
            if ($drawType === 'round_robin') {
                // Chia bảng vòng tròn
                createGroupsAndMatches($numGroups, null, $tournamentId);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã chia ' . $numGroups . ' bảng vòng tròn!</div>';
            } elseif ($drawType === 'knockout') {
                // Loại trực tiếp
                createKnockoutMatches($tournamentId, $knockoutTeams);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã tạo vòng loại trực tiếp ' . $knockoutTeams . ' đội!</div>';
            } elseif ($drawType === 'combined') {
                // Kết hợp
                createGroupAndKnockout($numGroups, $tournamentId, $knockoutTeams);
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã chia ' . $numGroups . ' bảng và ' . $knockoutTeams . ' đội vào vòng loại!</div>';
            }
            
            $groups = getGroupsByTournament($tournamentId);
            $matches = getMatchesByTournament($tournamentId);
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation me-2"></i>Vui lòng chọn giải đấu và thêm đội trước!</div>';
    }
}

// Xóa bảng đấu
if (isset($_GET['clear_draw']) && $tournamentId > 0) {
    $pdo->prepare("DELETE FROM Matches WHERE tournament_id = ?")->execute([$tournamentId]);
    $pdo->prepare("DELETE FROM Groups WHERE tournament_id = ?")->execute([$tournamentId]);
    $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Đã xóa lịch thi đấu!</div>';
    $groups = getGroupsByTournament($tournamentId);
    $matches = getMatchesByTournament($tournamentId);
}

$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chia bảng đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .card-custom { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 15px; }
        .team-item { padding: 10px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .team-item:last-child { border-bottom: none; }
        .team-name { font-weight: 600; }
        .team-players { font-size: 0.85rem; color: #64748b; }
        .group-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 15px; border-left: 4px solid var(--primary); }
        .group-title { font-weight: 700; color: var(--accent); margin-bottom: 10px; }
        .match-item { padding: 8px 12px; background: #f8fafc; border-radius: 6px; margin-bottom: 6px; font-size: 0.9rem; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; display: inline-block; }
        .nav-tab:hover, .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <?php renderNavbar('draw'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><i class="fas fa-random me-2"></i>CHIA BẢNG ĐẤU</h1>
            <p class="mt-2">
                <?php if ($tournament): ?>
                    <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($tournament['name']); ?>
                    <span class="ms-3"><i class="fas fa-users me-1"></i><?php echo count($teams); ?> đội</span>
                <?php else: ?>
                    Chọn giải đấu để bắt đầu
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="container py-4">
        <?php echo $message; ?>

        <!-- Chọn giải đấu -->
        <div class="card-custom">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Chọn giải đấu</label>
                    <select class="form-select" onchange="window.location.href='?tournament_id='+this.value">
                        <option value="0">-- Chọn giải đấu --</option>
                        <?php foreach ($tournaments as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($tournamentId == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['status']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($tournamentId > 0): ?>
                        <a href="tournament_view.php?id=<?php echo $tournamentId; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>Xem giải đấu
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($tournamentId > 0): ?>
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'teams') ? 'active' : ''; ?>" href="?tournament_id=<?php echo $tournamentId; ?>&tab=teams">
                    <i class="fas fa-users me-1"></i>Đội (<?php echo count($teams); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'draw') ? 'active' : ''; ?>" href="?tournament_id=<?php echo $tournamentId; ?>&tab=draw">
                    <i class="fas fa-random me-1"></i>Bốc thăm
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-tab <?php echo ($activeTab === 'groups') ? 'active' : ''; ?>" href="?tournament_id=<?php echo $tournamentId; ?>&tab=groups">
                    <i class="fas fa-layer-group me-1"></i>Bảng đấu (<?php echo count($groups); ?>)
                </a>
            </li>
        </ul>

        <!-- Tab: Danh sách đội -->
        <?php if ($activeTab === 'teams'): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-title"><i class="fas fa-plus-circle me-2"></i>Thêm đội mới</div>
                        <form method="post">
                            <input type="hidden" name="add_team" value="1">
                            <div class="mb-2">
                                <input type="text" name="team_name" class="form-control" placeholder="Tên đội *" required>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="player1" class="form-control" placeholder="VĐV 1 *" required>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="player2" class="form-control" placeholder="VĐV 2 *" required>
                            </div>
                            <div class="mb-3">
                                <select name="skill_level" class="form-select">
                                    <option value="">Chọn trình độ</option>
                                    <option value="2.0">2.0</option>
                                    <option value="2.5">2.5</option>
                                    <option value="3.0">3.0</option>
                                    <option value="3.5">3.5</option>
                                    <option value="4.0">4.0</option>
                                    <option value="4.5">4.5</option>
                                    <option value="5.0">5.0</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Thêm đội</button>
                        </form>
                    </div>
                    
                    <div class="card-custom">
                        <div class="card-title"><i class="fas fa-file-upload me-2"></i>Import từ CSV</div>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="import_teams" value="1">
                            <input type="file" name="csv_file" class="form-control mb-2" accept=".csv" required>
                            <small class="text-muted d-block mb-2">Định dạng: Tên đội, VĐV1, VĐV2, Trình độ</small>
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>Import</button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-title d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list me-2"></i>Danh sách đội</span>
                            <span class="badge bg-primary"><?php echo count($teams); ?> đội</span>
                        </div>
                        <?php if (empty($teams)): ?>
                            <p class="text-muted text-center py-3">Chưa có đội nào. Thêm đội hoặc import CSV.</p>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($teams as $team): ?>
                                    <div class="team-item">
                                        <div>
                                            <?php echo renderTeamPlayers($team); ?>
                                        </div>
                                        <div>
                                            <?php if ($team['skill_level']): ?>
                                                <span class="badge bg-info"><?php echo $team['skill_level']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($team['group_name']): ?>
                                                <span class="badge bg-success"><?php echo $team['group_name']; ?></span>
                                            <?php endif; ?>
                                            <a href="?tournament_id=<?php echo $tournamentId; ?>&tab=teams&delete_team=<?php echo $team['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa đội này?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tab: Bốc thăm -->
        <?php if ($activeTab === 'draw'): ?>
            <div class="card-custom">
                <div class="card-title"><i class="fas fa-random me-2"></i>Bốc thăm chia bảng</div>
                
                <?php if (count($teams) < 2): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cần ít nhất 2 đội để bốc thăm. Hiện có: <?php echo count($teams); ?> đội
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="draw_groups" value="1">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Thể thức</label>
                                <select name="draw_type" class="form-select" id="drawType" onchange="toggleOptions()">
                                    <option value="round_robin">Vòng tròn (Chia bảng)</option>
                                    <option value="knockout">Loại trực tiếp</option>
                                    <option value="combined">Vòng bảng + Loại trực tiếp</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="numGroupsDiv">
                                <label class="form-label">Số bảng</label>
                                <select name="num_groups" class="form-select">
                                    <?php for($i=2; $i<=8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($i==4) ? 'selected' : ''; ?>><?php echo $i; ?> bảng</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="knockoutTeamsDiv" style="display:none;">
                                <label class="form-label">Số đội vào loại trực tiếp</label>
                                <select name="knockout_teams" class="form-select">
                                    <?php 
                                    $teamCount = count($teams);
                                    for($i=2; $i<=min(16, $teamCount); $i*=2): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($i==4) ? 'selected' : ''; ?>><?php echo $i; ?> đội</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Hiện có <strong><?php echo count($teams); ?></strong> đội. 
                            Bốc thăm sẽ xóa lịch thi đấu cũ (nếu có).
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-random me-2"></i>Bốc thăm ngay
                            </button>
                            <a href="?tournament_id=<?php echo $tournamentId; ?>&clear_draw=1" class="btn btn-outline-danger" onclick="return confirm('Xóa lịch thi đấu hiện tại?')">
                                <i class="fas fa-trash me-1"></i>Xóa lịch thi đấu
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Xem trước lịch thi đấu hiện có -->
            <?php if (!empty($matches)): ?>
                <div class="card-custom mt-4">
                    <div class="card-title"><i class="fas fa-calendar me-2"></i>Lịch thi đấu hiện tại</div>
                    <p class="text-muted"><?php echo count($matches); ?> trận đấu đã được tạo</p>
                    <a href="matches.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Xem chi tiết
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Tab: Bảng đấu -->
        <?php if ($activeTab === 'groups'): ?>
            <?php if (empty($groups)): ?>
                <div class="card-custom">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-layer-group" style="font-size: 3rem; opacity: 0.5;"></i>
                        <h5 class="mt-3">Chưa có bảng đấu</h5>
                        <p>Vui lòng bốc thăm chia bảng trước</p>
                        <a href="?tournament_id=<?php echo $tournamentId; ?>&tab=draw" class="btn btn-primary">
                            <i class="fas fa-random me-1"></i>Bốc thăm ngay
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($groups as $group): 
                        $groupMatches = array_filter($matches, fn($m) => $m['group_id'] == $group['id']);
                        $groupTeams = array_filter($teams, fn($t) => $t['group_name'] == $group['group_name']);
                    ?>
                        <div class="col-md-6">
                            <div class="group-card">
                                <div class="group-title">
                                    <i class="fas fa-layer-group me-2"></i>Bảng <?php echo $group['group_name']; ?>
                                    <span class="badge bg-primary ms-2"><?php echo count($groupTeams); ?> đội</span>
                                </div>
                                
                                <!-- Danh sách đội -->
                                <div class="mb-3">
                                    <small class="text-muted fw-bold">Đội tham gia:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($groupTeams as $gt): ?>
                                            <span class="badge bg-light text-dark" style="font-size: 0.8rem; padding: 6px 10px;">
                                                <strong><?php echo htmlspecialchars($gt['player1'] ?? ''); ?></strong>
                                                <span style="color: #999;">&</span>
                                                <strong><?php echo htmlspecialchars($gt['player2'] ?? ''); ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Lịch thi đấu -->
                                <?php if (!empty($groupMatches)): ?>
                                    <div>
                                        <small class="text-muted fw-bold">Lịch thi đấu:</small>
                                        <?php foreach (array_slice($groupMatches, 0, 5) as $gm): ?>
                                            <?php 
                                            $t1 = getTeamInfo($gm['team1_id']);
                                            $t2 = getTeamInfo($gm['team2_id']);
                                            ?>
                                            <div class="match-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars(($t1['player1'] ?? '')); ?></div>
                                                    <span class="badge bg-secondary">vs</span>
                                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars(($t2['player1'] ?? '')); ?></div>
                                                </div>
                                                <?php if ($gm['score1'] !== null): ?>
                                                    <span class="badge bg-success ms-1"><?php echo $gm['score1']; ?>-<?php echo $gm['score2']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($groupMatches) > 5): ?>
                                            <small class="text-muted">... và <?php echo count($groupMatches) - 5; ?> trận nữa</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-hand-point-up me-2"></i>Vui lòng chọn giải đấu để bắt đầu
        </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleOptions() {
        var drawType = document.getElementById('drawType').value;
        var numGroupsDiv = document.getElementById('numGroupsDiv');
        var knockoutTeamsDiv = document.getElementById('knockoutTeamsDiv');
        
        if (drawType === 'knockout') {
            numGroupsDiv.style.display = 'none';
            knockoutTeamsDiv.style.display = 'block';
        } else if (drawType === 'combined') {
            numGroupsDiv.style.display = 'block';
            knockoutTeamsDiv.style.display = 'block';
        } else {
            numGroupsDiv.style.display = 'block';
            knockoutTeamsDiv.style.display = 'none';
        }
    }
    toggleOptions();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
