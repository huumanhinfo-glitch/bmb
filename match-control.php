<?php
// match-control.php - Trang điều khiển trận đấu tích hợp
require_once 'functions.php';

$currentPage = isset($_GET['tab']) ? $_GET['tab'] : 'match';
$matchId = isset($_GET['match_id']) ? intval($_GET['match_id']) : null;
$tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : null;

// Xử lý AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'create_live_match') {
        $team1_p1 = $_POST['team1_p1'] ?? '';
        $team1_p2 = $_POST['team1_p2'] ?? '';
        $team2_p1 = $_POST['team2_p1'] ?? '';
        $team2_p2 = $_POST['team2_p2'] ?? '';
        $winning_score = intval($_POST['winning_score'] ?? 11);
        $first_server = intval($_POST['first_server'] ?? 1);
        $tournament_id = intval($_POST['tournament_id'] ?? 0);
        
        // Tạo team 1
        $team1_id = createLiveTeam($team1_p1, $team1_p2, $tournament_id);
        // Tạo team 2  
        $team2_id = createLiveTeam($team2_p1, $team2_p2, $tournament_id);
        
        // Tạo trận đấu
        $match_id = createLiveMatch($team1_id, $team2_id, $tournament_id, $winning_score, $first_server);
        
        echo json_encode(['success' => true, 'match_id' => $match_id]);
        exit;
    }
    
    if ($_POST['action'] === 'update_score') {
        $match_id = intval($_POST['match_id']);
        $score1 = intval($_POST['score1']);
        $score2 = intval($_POST['score2']);
        $server_team = intval($_POST['server_team']);
        $server_hand = intval($_POST['server_hand']);
        $winner = $_POST['winner'] ? intval($_POST['winner']) : null;
        
        updateLiveMatch($match_id, $score1, $score2, $server_team, $server_hand, $winner);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_match') {
        $match_id = intval($_POST['match_id']);
        deleteLiveMatch($match_id);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Lấy danh sách trận đấu live
$liveMatches = getLiveMatches();

// Lấy tất cả trận đấu để điều khiển
$allMatches = getAllMatchesForControl();

// Lấy danh sách giải đấu
$tournaments = getAllTournaments();

// Lấy thông tin trận đấu cụ thể
$currentMatch = null;
if ($matchId) {
    $currentMatch = getLiveMatchById($matchId);
    // Nếu không tìm thấy, thử tìm trong tất cả trận
    if (!$currentMatch) {
        $currentMatch = getMatchById($matchId);
        if ($currentMatch) {
            // Lấy thêm thông tin players
            $t1 = getTeamById($currentMatch['team1_id']);
            $t2 = getTeamById($currentMatch['team2_id']);
            $currentMatch['t1_p1'] = $t1['player1'] ?? '';
            $currentMatch['t1_p2'] = $t1['player2'] ?? '';
            $currentMatch['t2_p1'] = $t2['player1'] ?? '';
            $currentMatch['t2_p2'] = $t2['player2'] ?? '';
            $currentMatch['tournament_name'] = '';
        }
    }
}

function getTeamById($teamId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Teams WHERE id = ?");
    $stmt->execute([$teamId]);
    return $stmt->fetch() ?: [];
}

function ensureMatchColumns() {
    global $pdo;
    try {
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS winning_score INT DEFAULT 11");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS first_server INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS server_team INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS server_hand INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS status ENUM('pending', 'live', 'completed') DEFAULT 'pending'");
    } catch (Exception $e) {
        // Columns may already exist
    }
}
ensureMatchColumns();

function createLiveTeam($p1, $p2, $tournamentId) {
    global $pdo;
    $teamName = substr($p1, 0, 3) . '-' . substr($p2, 0, 3) . '-' . time();
    $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?, ?, ?, ?, 'Open')");
    $stmt->execute([$teamName, $p1, $p2, $tournamentId > 0 ? $tournamentId : null]);
    return $pdo->lastInsertId();
}

function createLiveMatch($team1Id, $team2Id, $tournamentId, $winningScore, $firstServer) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO Matches (team1_id, team2_id, tournament_id, round, match_type, score1, score2, winning_score, first_server, server_team, server_hand, status) VALUES (?, ?, ?, 'Trực tiếp', 'live', 0, 0, ?, ?, 1, 'live')");
    $stmt->execute([$team1Id, $team2Id, $tournamentId > 0 ? $tournamentId : null, $winningScore, $firstServer]);
    return $pdo->lastInsertId();
}

function getLiveMatches() {
    global $pdo;
    return $pdo->query("
        SELECT m.*, 
               t1.team_name as team1_name, t1.player1 as t1_p1, t1.player2 as t1_p2,
               t2.team_name as team2_name, t2.player1 as t2_p1, t2.player2 as t2_p2,
               tr.name as tournament_name
        FROM Matches m
        LEFT JOIN Teams t1 ON m.team1_id = t1.id
        LEFT JOIN Teams t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        WHERE m.match_type = 'live' OR m.status IN ('live', 'completed')
        ORDER BY m.created_at DESC
        LIMIT 50
    ")->fetchAll();
}

function getAllMatchesForControl() {
    global $pdo;
    return $pdo->query("
        SELECT m.*, 
               t1.team_name as team1_name, t1.player1 as t1_p1, t1.player2 as t1_p2,
               t2.team_name as team2_name, t2.player1 as t2_p1, t2.player2 as t2_p2,
               tr.name as tournament_name
        FROM Matches m
        LEFT JOIN Teams t1 ON m.team1_id = t1.id
        LEFT JOIN Teams t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        ORDER BY m.id DESC
        LIMIT 100
    ")->fetchAll();
}

function getLiveMatchById($matchId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
               t1.team_name as team1_name, t1.player1 as t1_p1, t1.player2 as t1_p2,
               t2.team_name as team2_name, t2.player1 as t2_p1, t2.player2 as t2_p2,
               tr.name as tournament_name
        FROM Matches m
        LEFT JOIN Teams t1 ON m.team1_id = t1.id
        LEFT JOIN Teams t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch();
}

function updateLiveMatch($matchId, $score1, $score2, $serverTeam, $serverHand, $winner) {
    global $pdo;
    // Get the match to find the team IDs
    $match = getMatchById($matchId);
    if (!$match) return;
    
    $winnerId = null;
    if ($winner) {
        $winnerId = $winner == 1 ? $match['team1_id'] : $match['team2_id'];
    }
    
    $stmt = $pdo->prepare("UPDATE Matches SET score1 = ?, score2 = ?, server_team = ?, server_hand = ?, winner_id = ?, status = ? WHERE id = ?");
    $status = $winner ? 'completed' : ($match['status'] ?? 'live');
    $stmt->execute([$score1, $score2, $serverTeam, $serverHand, $winnerId, $status, $matchId]);
}

function deleteLiveMatch($matchId) {
    global $pdo;
    $match = getMatchById($matchId);
    if ($match) {
        $pdo->prepare("DELETE FROM Matches WHERE id = ?")->execute([$matchId]);
        $pdo->prepare("DELETE FROM Teams WHERE id = ?")->execute([$match['team1_id']]);
        $pdo->prepare("DELETE FROM Teams WHERE id = ?")->execute([$match['team2_id']]);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điều khiển trận đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2ecc71;
            --accent: #ff6b00;
            --blue: #3498db;
            --red: #e74c3c;
            --yellow: #f1c40f;
            --text-dark: #1e293b;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            font-family: 'Open Sans', sans-serif;
        }
        
        .navbar-dark {
            background: rgba(0,0,0,0.3) !important;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 1.5rem;
            color: var(--accent) !important;
        }
        
        .main-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .tabs-container {
            background: white;
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-dark);
            font-weight: 700;
            padding: 15px 25px;
            border-radius: 10px;
            margin: 0 3px;
            transition: all 0.3s;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .nav-tabs-custom .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white !important;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 12px;
            font-weight: 600;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            color: white;
            padding: 15px 30px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.3s;
            text-transform: uppercase;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .btn-danger-custom {
            background: var(--red);
            border: none;
            color: white;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .match-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .match-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: var(--primary);
        }
        
        .match-header-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .status-live {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fee2e2;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid #fecaca;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--red);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-text {
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--red);
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d1fae5;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid #a7f3d0;
        }
        
        .status-completed .status-text {
            color: var(--primary);
        }
        
        .team-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
        }
        
        .team-row:first-child {
            border-bottom: 1px solid #f1f5f9;
        }
        
        .team-row.serving {
            background: #eff6ff;
        }
        
        .team-info {
            display: flex;
            flex-direction: column;
        }
        
        .team-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        
        .team-name.winner {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .team-score {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            min-width: 60px;
            text-align: right;
        }
        
        .team-score.team1 {
            color: var(--blue);
        }
        
        .team-score.team2 {
            color: var(--accent);
        }
        
        .server-indicator {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .server-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s;
        }
        
        .server-dot.active {
            background: var(--primary);
            animation: pulse 1s infinite;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-view {
            background: #e2e8f0;
            color: var(--text-dark);
        }
        
        .btn-view:hover {
            background: var(--blue);
            color: white;
        }
        
        .btn-play {
            background: var(--blue);
            color: white;
        }
        
        .btn-play:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: var(--red);
        }
        
        .btn-delete:hover {
            background: var(--red);
            color: white;
        }
        
        /* Match Control Styles */
        .match-control-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .scoreboard {
            display: grid;
            grid-template-columns: 1fr 100px 1fr;
            gap: 20px;
            padding: 30px;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            align-items: center;
        }
        
        .team-scoreboard {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            background: rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .team-scoreboard.team1 {
            border: 3px solid var(--blue);
        }
        
        .team-scoreboard.team2 {
            border: 3px solid var(--accent);
        }
        
        .team-scoreboard.serving {
            background: rgba(46, 204, 113, 0.2);
            box-shadow: 0 0 30px rgba(46, 204, 113, 0.3);
        }
        
        .score-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 5rem;
            font-weight: 900;
            color: white;
            line-height: 1;
        }
        
        .team1 .score-number {
            color: var(--blue);
        }
        
        .team2 .score-number {
            color: var(--accent);
        }
        
        .team-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin-top: 10px;
            text-transform: uppercase;
        }
        
        .vs-divider {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .vs-text {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 900;
            color: white;
            opacity: 0.5;
        }
        
        .server-info {
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 600;
        }
        
        .control-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 25px;
            background: #f8fafc;
        }
        
        .control-btn {
            padding: 20px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
        }
        
        .btn-score {
            background: var(--primary);
            color: white;
        }
        
        .btn-score:hover {
            background: #27ae60;
            transform: scale(1.02);
        }
        
        .btn-fault {
            background: var(--yellow);
            color: var(--text-dark);
        }
        
        .btn-fault:hover {
            background: #f39c12;
            transform: scale(1.02);
        }
        
        .btn-undo {
            background: var(--blue);
            color: white;
        }
        
        .btn-undo:hover {
            background: #2980b9;
            transform: scale(1.02);
        }
        
        .btn-reset {
            background: var(--red);
            color: white;
        }
        
        .btn-reset:hover {
            background: #c0392b;
            transform: scale(1.02);
        }
        
        .players-info {
            display: flex;
            justify-content: space-between;
            padding: 20px 30px;
            background: white;
            border-top: 2px solid #e2e8f0;
        }
        
        .players-team {
            text-align: center;
            flex: 1;
        }
        
        .players-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .player-name {
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .winner-banner {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            padding: 20px;
            text-align: center;
            color: var(--text-dark);
        }
        
        .winner-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .scoreboard {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .control-buttons {
                grid-template-columns: 1fr;
            }
            
            .score-number {
                font-size: 3.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-basketball-ball me-2"></i>TRỌNG TÀI SỐ
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Trang chủ</a>
                    <a class="nav-link" href="tournament_list.php"><i class="fas fa-trophy me-1"></i> Giải đấu</a>
                    <a class="nav-link" href="matches.php"><i class="fas fa-list me-1"></i> Danh sách trận</a>
                    <a class="nav-link active" href="match-control.php"><i class="fas fa-gamepad me-1"></i> Điều khiển trận</a>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="tabs-container">
            <ul class="nav nav-tabs-custom nav-fill" id="matchTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'match' || $currentPage === 'create' ? 'active' : ''; ?>" 
                       href="match-control.php?tab=create">
                        <i class="fas fa-plus-circle me-2"></i>TẠO TRẬN ĐẤU
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'history' ? 'active' : ''; ?>" 
                       href="match-control.php?tab=history">
                        <i class="fas fa-history me-2"></i>LỊCH SỬ TRẬN ĐẤU
                        <span class="badge bg-primary ms-2"><?php echo count($liveMatches); ?></span>
                    </a>
                </li>
                <?php if ($currentMatch): ?>
                <li class="nav-item">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tv me-2"></i>TRẬN ĐẤU #<?php echo $currentMatch['id']; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($currentPage === 'create' || $currentPage === 'match'): ?>
        <!-- TAB 1: TẠO TRẬN ĐẤU -->
        <div class="row">
            <div class="col-lg-6">
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>Tạo trận đấu mới
                    </h3>
                    <form id="createMatchForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Đội 1 - Player 1</label>
                                <input type="text" class="form-control" name="team1_p1" placeholder="Tên cầu thủ 1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Đội 1 - Player 2</label>
                                <input type="text" class="form-control" name="team1_p2" placeholder="Tên cầu thủ 2" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Đội 2 - Player 1</label>
                                <input type="text" class="form-control" name="team2_p1" placeholder="Tên cầu thủ 1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Đội 2 - Player 2</label>
                                <input type="text" class="form-control" name="team2_p2" placeholder="Tên cầu thủ 2" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Điểm thắng</label>
                                <select class="form-select" name="winning_score">
                                    <option value="11">11 điểm</option>
                                    <option value="15">15 điểm</option>
                                    <option value="21">21 điểm</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Đội phát bóng đầu</label>
                                <select class="form-select" name="first_server">
                                    <option value="1">Đội 1</option>
                                    <option value="2">Đội 2</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Giải đấu (tùy chọn)</label>
                                <select class="form-select" name="tournament_id">
                                    <option value="0">Không có</option>
                                    <?php foreach($tournaments as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100">
                            <i class="fas fa-play me-2"></i>Tạo và bắt đầu trận
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-clock me-2 text-warning"></i>Trận đấu đang chờ
                    </h3>
                    <?php if (empty($liveMatches)): ?>
                    <div class="empty-state">
                        <i class="fas fa-basketball-ball"></i>
                        <h5>Chưa có trận đấu nào</h5>
                        <p>Tạo trận đấu mới để bắt đầu</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach($liveMatches as $match): ?>
                        <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($match['t1_p1']); ?> & <?php echo htmlspecialchars($match['t1_p2']); ?></strong>
                                <span class="text-muted">vs</span>
                                <strong><?php echo htmlspecialchars($match['t2_p1']); ?> & <?php echo htmlspecialchars($match['t2_p2']); ?></strong>
                            </div>
                            <span class="badge bg-primary">Vào điều khiển</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($currentPage === 'history'): ?>
        <!-- TAB 2: LỊCH SỬ TRẬN ĐẤU -->
        <div class="section-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title mb-0">
                    <i class="fas fa-history me-2 text-primary"></i>Lịch sử trận đấu
                </h3>
                <span class="badge bg-primary fs-6"><?php echo count($allMatches); ?> trận</span>
            </div>
            
            <?php if (empty($allMatches)): ?>
            <div class="empty-state">
                <i class="fas fa-basketball-ball"></i>
                <h4>Chưa có trận đấu nào</h4>
                <p>Tạo trận đấu mới để bắt đầu</p>
                <a href="match-control.php?tab=create" class="btn btn-primary-custom mt-3">
                    <i class="fas fa-plus me-2"></i>Tạo trận đấu
                </a>
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach($allMatches as $match): 
                    $isLive = $match['status'] === 'live';
                    $isCompleted = $match['status'] === 'completed' || $match['winner_id'];
                    $serverTeam = $match['server_team'] ?? 1;
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="match-card">
                        <!-- Header with status -->
                        <div class="match-header-status">
                            <div>
                                <?php if ($isLive): ?>
                                <div class="status-live">
                                    <div class="status-dot"></div>
                                    <span class="status-text">LIVE</span>
                                </div>
                                <?php elseif ($isCompleted): ?>
                                <div class="status-completed">
                                    <span class="status-text">Hoàn thành</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="action-buttons">
                                <?php if ($isCompleted): ?>
                                <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" 
                                   class="btn-action btn-view" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                <a href="match-control.php?tab=control&match_id=<?php echo $match['id']; ?>" 
                                   class="btn-action btn-play" title="Điều khiển">
                                    <i class="fas fa-play"></i>
                                </a>
                                <button class="btn-action btn-delete" title="Xóa" 
                                        onclick="deleteMatch(<?php echo $match['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Team 1 -->
                        <div class="team-row <?php echo $serverTeam == 1 ? 'serving' : ''; ?>">
                            <div class="team-info">
                                <?php 
                                $t1p1 = $match['t1_p1'] ?: ($match['team1_name'] ?? 'Đội 1');
                                $t1p2 = $match['t1_p2'] ?? '';
                                ?>
                                <?php if ($match['winner_id'] == $match['team1_id']): ?>
                                <div class="team-name winner">
                                    <i class="fas fa-trophy text-warning me-1"></i>
                                    <?php echo htmlspecialchars($t1p1); ?>
                                </div>
                                <div class="team-name winner"><?php echo htmlspecialchars($t1p2); ?></div>
                                <?php else: ?>
                                <div class="team-name"><?php echo htmlspecialchars($t1p1); ?></div>
                                <div class="team-name"><?php echo htmlspecialchars($t1p2); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="team-score team1"><?php echo $match['score1']; ?></div>
                                <div class="server-indicator">
                                    <div class="server-dot <?php echo $serverTeam == 1 ? 'active' : ''; ?>"></div>
                                    <div class="server-dot <?php echo $serverTeam == 1 && ($match['server_hand'] ?? 1) >= 2 ? 'active' : ''; ?>"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Team 2 -->
                        <div class="team-row <?php echo $serverTeam == 2 ? 'serving' : ''; ?>">
                            <div class="team-info">
                                <?php 
                                $t2p1 = $match['t2_p1'] ?: ($match['team2_name'] ?? 'Đội 2');
                                $t2p2 = $match['t2_p2'] ?? '';
                                ?>
                                <?php if ($match['winner_id'] == $match['team2_id']): ?>
                                <div class="team-name winner">
                                    <i class="fas fa-trophy text-warning me-1"></i>
                                    <?php echo htmlspecialchars($t2p1); ?>
                                </div>
                                <div class="team-name winner"><?php echo htmlspecialchars($t2p2); ?></div>
                                <?php else: ?>
                                <div class="team-name"><?php echo htmlspecialchars($t2p1); ?></div>
                                <div class="team-name"><?php echo htmlspecialchars($t2p2); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="team-score team2"><?php echo $match['score2']; ?></div>
                                <div class="server-indicator">
                                    <div class="server-dot <?php echo $serverTeam == 2 ? 'active' : ''; ?>"></div>
                                    <div class="server-dot <?php echo $serverTeam == 2 && ($match['server_hand'] ?? 1) >= 2 ? 'active' : ''; ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($currentPage === 'control' && $currentMatch): ?>
        <!-- TAB 3: ĐIỀU KHIỂN TRẬN ĐẤU -->
        <div class="match-control-container">
            <?php if ($currentMatch['status'] === 'completed'): ?>
            <div class="winner-banner">
                <div class="winner-text">
                    <i class="fas fa-trophy me-2"></i>
                    <?php 
                    $winnerName = '';
                    if ($currentMatch['winner_id'] == $currentMatch['team1_id']) {
                        $winnerName = $currentMatch['t1_p1'] . ' & ' . $currentMatch['t1_p2'];
                    } else {
                        $winnerName = $currentMatch['t2_p1'] . ' & ' . $currentMatch['t2_p2'];
                    }
                    echo htmlspecialchars($winnerName) . ' THẮNG!';
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="scoreboard" id="scoreboard">
                <div class="team-scoreboard team1" id="team1Board">
                    <div class="score-number" id="score1"><?php echo $currentMatch['score1']; ?></div>
                    <div class="team-label">ĐỘI 1</div>
                </div>
                
                <div class="vs-divider">
                    <div class="vs-text">VS</div>
                    <div class="server-info" id="serverInfo">
                        Server: Đội <?php echo $currentMatch['server_team'] ?? 1; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #94a3b8;">
                        Thắng: <?php echo $currentMatch['winning_score'] ?? 11; ?> điểm
                    </div>
                </div>
                
                <div class="team-scoreboard team2" id="team2Board">
                    <div class="score-number" id="score2"><?php echo $currentMatch['score2']; ?></div>
                    <div class="team-label">ĐỘI 2</div>
                </div>
            </div>
            
            <div class="players-info">
                <div class="players-team">
                    <div class="players-title">ĐỘI 1</div>
                    <div class="player-name"><?php echo htmlspecialchars($currentMatch['t1_p1']); ?></div>
                    <div class="player-name"><?php echo htmlspecialchars($currentMatch['t1_p2']); ?></div>
                </div>
                <div class="players-team">
                    <div class="players-title">TRẬN #<?php echo $currentMatch['id']; ?></div>
                    <div class="player-name"><?php echo $currentMatch['tournament_name'] ?? 'Trận tự do'; ?></div>
                </div>
                <div class="players-team">
                    <div class="players-title">ĐỘI 2</div>
                    <div class="player-name"><?php echo htmlspecialchars($currentMatch['t2_p1']); ?></div>
                    <div class="player-name"><?php echo htmlspecialchars($currentMatch['t2_p2']); ?></div>
                </div>
            </div>
            
            <?php if ($currentMatch['status'] !== 'completed'): ?>
            <div class="control-buttons">
                <button class="control-btn btn-score" onclick="scorePoint()">
                    <i class="fas fa-plus-circle me-2"></i>ĐỘI PHÁT THẮNG
                </button>
                <button class="control-btn btn-fault" onclick="fault()">
                    <i class="fas fa-times-circle me-2"></i>PHẠM LỖI
                </button>
                <button class="control-btn btn-undo" onclick="undo()">
                    <i class="fas fa-undo me-2"></i>HOÀN TÁC
                </button>
                <button class="control-btn btn-reset" onclick="resetMatch()">
                    <i class="fas fa-redo me-2"></i>RESET TRẬN
                </button>
            </div>
            <?php else: ?>
            <div class="control-buttons">
                <a href="match-control.php?tab=history" class="control-btn btn-undo" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-arrow-left me-2"></i>QUAY VỀ DANH SÁCH
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Game State
        let initialWinner = <?php echo $currentMatch && $currentMatch['winner_id'] ? ($currentMatch['winner_id'] == $currentMatch['team1_id'] ? '1' : '2') : 'null'; ?>;
        let gameState = {
            score1: <?php echo $currentMatch ? (intval($currentMatch['score1'] ?? 0)) : 0; ?>,
            score2: <?php echo $currentMatch ? (intval($currentMatch['score2'] ?? 0)) : 0; ?>,
            serverTeam: <?php echo $currentMatch ? (intval($currentMatch['server_team'] ?? 1)) : 1; ?>,
            serverHand: <?php echo $currentMatch ? (intval($currentMatch['server_hand'] ?? 1)) : 1; ?>,
            winningScore: <?php echo $currentMatch ? (intval($currentMatch['winning_score'] ?? 11)) : 11; ?>,
            isFirstServeOfMatch: true,
            winner: initialWinner,
            history: []
        };

        const matchId = <?php echo $currentMatch ? $currentMatch['id'] : 0; ?>;

        function updateDisplay() {
            document.getElementById('score1').textContent = gameState.score1;
            document.getElementById('score2').textContent = gameState.score2;
            
            document.getElementById('team1Board').classList.toggle('serving', gameState.serverTeam === 1);
            document.getElementById('team2Board').classList.toggle('serving', gameState.serverTeam === 2);
            
            document.getElementById('serverInfo').textContent = 'Server: Đội ' + gameState.serverTeam + ' (tay ' + gameState.serverHand + ')';
        }

        function saveToServer() {
            fetch('match-control.php?tab=control', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=update_score&match_id=' + matchId + 
                      '&score1=' + gameState.score1 + 
                      '&score2=' + gameState.score2 + 
                      '&server_team=' + gameState.serverTeam + 
                      '&server_hand=' + gameState.serverHand +
                      '&winner=' + (gameState.winner || '')
            });
        }

        function scorePoint() {
            if (gameState.winner) return;
            
            gameState.history.push({...gameState});
            
            const scoringTeam = gameState.serverTeam;
            if (scoringTeam === 1) gameState.score1++;
            else gameState.score2++;
            
            // Check winner
            if (gameState.score1 >= gameState.winningScore && gameState.score1 - gameState.score2 >= 2) {
                gameState.winner = 1;
            } else if (gameState.score2 >= gameState.winningScore && gameState.score2 - gameState.score1 >= 2) {
                gameState.winner = 2;
            }
            
            // Switch positions on scoring team's side
            // (simple version - just swap server hand)
            if (!gameState.winner) {
                gameState.serverHand = gameState.serverHand === 1 ? 2 : 1;
            }
            
            updateDisplay();
            saveToServer();
            
            if (gameState.winner) {
                alert('Đội ' + gameState.winner + ' thắng!');
                location.reload();
            }
        }

        function fault() {
            if (gameState.winner) return;
            
            gameState.history.push({...gameState});
            
            if (gameState.isFirstServeOfMatch) {
                gameState.isFirstServeOfMatch = false;
                gameState.serverTeam = gameState.serverTeam === 1 ? 2 : 1;
                gameState.serverHand = 1;
            } else {
                if (gameState.serverHand === 1) {
                    gameState.serverHand = 2;
                } else {
                    gameState.serverTeam = gameState.serverTeam === 1 ? 2 : 1;
                    gameState.serverHand = 1;
                }
            }
            
            updateDisplay();
            saveToServer();
        }

        function undo() {
            if (gameState.history.length === 0) return;
            const prev = gameState.history.pop();
            gameState.score1 = prev.score1;
            gameState.score2 = prev.score2;
            gameState.serverTeam = prev.serverTeam;
            gameState.serverHand = prev.serverHand;
            gameState.winner = prev.winner;
            gameState.isFirstServeOfMatch = prev.isFirstServeOfMatch;
            
            updateDisplay();
            saveToServer();
        }

        function resetMatch() {
            if (!confirm('Reset trận đấu?')) return;
            gameState = {
                score1: 0,
                score2: 0,
                serverTeam: 1,
                serverHand: 1,
                winningScore: gameState.winningScore,
                isFirstServeOfMatch: true,
                winner: null,
                history: []
            };
            updateDisplay();
            saveToServer();
        }

        // Create match form
        document.getElementById('createMatchForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'create_live_match');
            
            const response = await fetch('match-control.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                window.location.href = 'match-control.php?tab=control&match_id=' + data.match_id;
            }
        });

        // Delete match
        function deleteMatch(id) {
            if (!confirm('Xóa trận đấu này?')) return;
            
            fetch('match-control.php?tab=history', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_match&match_id=' + id
            }).then(() => location.reload());
        }

        // Initial display
        updateDisplay();
    </script>
</body>
</html>