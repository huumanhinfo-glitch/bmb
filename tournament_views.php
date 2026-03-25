<?php
// tournament_view.php - Phiên bản mới với giao diện mobile
require_once 'db.php';
require_once 'functions.php';

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
           g.group_name,
           t1.player1 as team1_player1,
           t1.player2 as team1_player2,
           t2.player1 as team2_player1,
           t2.player2 as team2_player2
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

// Format text
$formatText = [
    'round_robin' => 'Vòng tròn',
    'knockout' => 'Loại trực tiếp',
    'combined' => 'Vòng bảng + Loại trực tiếp',
    'double_elimination' => 'Loại kép'
];

$statusText = [
    'upcoming' => 'Sắp diễn ra',
    'ongoing' => 'Đang diễn ra',
    'completed' => 'Đã kết thúc',
    'cancelled' => 'Đã hủy'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($tournament['name']); ?> - Tournament View</title>
    <style>
        :root {
            --bg-dark: #121212;
            --card-bg: #1e1e1e;
            --overlay-bg: #1c1c1e;
            --input-bg: #2a2a2a;
            --primary: #4ade80;
            --primary-dark: #22c55e;
            --secondary: #3b82f6;
            --accent: #ff6b00;
            --text-white: #ffffff;
            --text-grey: #a0a0a0;
            --winner-gold: #fbbf24;
            --loser-red: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #000;
            color: var(--text-white);
            font-family: -apple-system, system-ui, sans-serif;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* --- App Container --- */
        .app-container {
            width: 100%;
            max-width: 450px;
            min-height: 100vh;
            background-color: var(--bg-dark);
            margin: 0 auto;
            position: relative;
        }

        /* --- Header --- */
        .header {
            background: linear-gradient(135deg, var(--accent), #ff8c42);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .back-btn {
            color: white;
            font-size: 24px;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .tournament-title {
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
            margin: 5px 0;
            line-height: 1.3;
        }

        .tournament-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            background: rgba(255,255,255,0.3);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }

        /* --- Tab Navigation --- */
        .tab-navigation {
            position: sticky;
            top: 130px;
            z-index: 90;
            background: var(--card-bg);
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            border-bottom: 1px solid #333;
        }

        .tab-navigation::-webkit-scrollbar {
            display: none;
        }

        .tab {
            flex: 1;
            min-width: 80px;
            padding: 15px 10px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-grey);
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* --- Content Area --- */
        .content {
            padding: 20px;
            padding-bottom: 100px;
        }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid #333;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-grey);
        }

        /* --- Section Cards --- */
        .section-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-white);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title .badge {
            background: var(--secondary);
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 10px;
        }

        /* --- Team List --- */
        .team-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .team-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            border: 1px solid #333;
            transition: transform 0.1s;
        }

        .team-item:active {
            transform: scale(0.98);
        }

        .team-rank {
            width: 40px;
            text-align: center;
            font-weight: 800;
            font-size: 18px;
            color: var(--accent);
        }

        .team-info {
            flex: 1;
        }

        .team-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .team-players {
            font-size: 12px;
            color: var(--text-grey);
        }

        .team-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .team-tag {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.2);
            color: var(--secondary);
        }

        /* --- Group Card --- */
        .group-card {
            margin-bottom: 20px;
        }

        .group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }

        .group-name {
            font-weight: 700;
            color: var(--accent);
            font-size: 16px;
        }

        /* --- Match Card --- */
        .match-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid #333;
            transition: all 0.2s;
        }

        .match-card.completed {
            border-color: var(--primary);
        }

        .match-card.upcoming {
            border-color: var(--secondary);
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }

        .match-round {
            font-size: 12px;
            color: var(--accent);
            font-weight: 600;
        }

        .match-status {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 600;
        }

        .match-status.completed {
            background: rgba(74, 222, 128, 0.2);
            color: var(--primary);
        }

        .match-status.upcoming {
            background: rgba(59, 130, 246, 0.2);
            color: var(--secondary);
        }

        .match-teams {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .team-display {
            flex: 1;
            text-align: center;
            padding: 10px;
        }

        .team-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--input-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            margin: 0 auto 8px;
            color: var(--text-white);
        }

        .team-name-match {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .team-players-match {
            font-size: 11px;
            color: var(--text-grey);
        }

        .match-vs {
            padding: 0 15px;
            text-align: center;
        }

        .vs-text {
            font-size: 14px;
            color: var(--text-grey);
            font-weight: 700;
        }

        .score-display {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
            margin: 5px 0;
        }

        /* --- Standings Table --- */
        .standings-table {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
        }

        .table-header {
            display: grid;
            grid-template-columns: 40px 1fr 40px 40px 50px;
            padding: 15px;
            background: #2a2a2a;
            font-size: 12px;
            color: var(--text-grey);
            font-weight: 600;
        }

        .table-row {
            display: grid;
            grid-template-columns: 40px 1fr 40px 40px 50px;
            padding: 15px;
            border-bottom: 1px solid #333;
            align-items: center;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-row.top-3 {
            background: rgba(251, 191, 36, 0.05);
        }

        .rank {
            font-weight: 800;
            text-align: center;
            font-size: 16px;
        }

        .rank-1 { color: var(--winner-gold); }
        .rank-2 { color: #94a3b8; }
        .rank-3 { color: #f59e0b; }

        /* --- Bracket View --- */
        .bracket-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 20px;
            margin: 0 -20px;
            padding: 0 20px;
        }

        .bracket-stage {
            display: inline-flex;
            flex-direction: column;
            margin-right: 30px;
            vertical-align: top;
        }

        .bracket-stage:last-child {
            margin-right: 0;
        }

        .stage-title {
            text-align: center;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 15px;
            padding: 8px 12px;
            background: rgba(255, 107, 0, 0.1);
            border-radius: 8px;
            font-size: 13px;
        }

        .bracket-match {
            width: 180px;
            margin-bottom: 25px;
            position: relative;
        }

        .bracket-team {
            padding: 10px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid #333;
            position: relative;
            font-size: 13px;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bracket-team.winner {
            background: rgba(74, 222, 128, 0.1);
            border-color: var(--primary);
        }

        .bracket-team:first-child {
            border-bottom: none;
            border-radius: 6px 6px 0 0;
        }

        .bracket-team:last-child {
            border-radius: 0 0 6px 6px;
        }

        .bracket-score {
            font-weight: 700;
            font-size: 14px;
            color: var(--accent);
        }

        /* --- Empty State --- */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-grey);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .empty-text {
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* --- Modal System --- */
        .overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: flex-end;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            width: 100%;
            background: var(--overlay-bg);
            border-radius: 20px 20px 0 0;
            padding: 25px 20px;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            max-height: 85vh;
            overflow-y: auto;
        }

        .overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-handle {
            width: 40px;
            height: 5px;
            background: #444;
            border-radius: 10px;
            margin: -10px auto 20px auto;
        }

        .close-x {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            color: var(--text-grey);
            cursor: pointer;
            z-index: 10;
        }

        /* --- Match Detail Modal --- */
        .match-detail-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .match-round-badge {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .match-date {
            font-size: 13px;
            color: var(--text-grey);
        }

        .match-teams-detail {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }

        .team-detail {
            text-align: center;
        }

        .team-avatar-lg {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--input-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            margin: 0 auto 10px;
        }

        .team-name-lg {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .team-players-lg {
            font-size: 12px;
            color: var(--text-grey);
        }

        .score-display-lg {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent);
            text-align: center;
        }

        .vs-text-lg {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-grey);
            text-align: center;
        }

        .score-breakdown {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
        }

        .score-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #333;
        }

        .score-row:last-child {
            border-bottom: none;
        }

        .map-name {
            font-size: 14px;
            color: var(--text-white);
        }

        .map-score {
            font-weight: 700;
            font-size: 16px;
        }

        .map-score.winner {
            color: var(--primary);
        }

        .map-score.loser {
            color: var(--loser-red);
        }

        /* --- FAB --- */
        .fab-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 100;
        }

        .fab {
            width: 50px;
            height: 50px;
            border-radius: 25px;
            background: var(--accent);
            color: white;
            border: none;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fab:active {
            transform: scale(0.95);
        }

        /* --- Responsive --- */
        @media (max-width: 400px) {
            .app-container {
                max-width: 100%;
            }
            
            .tab {
                min-width: 70px;
                padding: 12px 8px;
                font-size: 12px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .bracket-match {
                width: 160px;
            }
        }

        /* --- Loading --- */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-grey);
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--card-bg);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Header -->
    <div class="header">
        <div class="header-top">
            <a href="tournament_list.php" class="back-btn">‹</a>
            <div></div> <!-- Spacer -->
        </div>
        
        <h1 class="tournament-title"><?php echo htmlspecialchars($tournament['name']); ?></h1>
        
        <div class="tournament-meta">
            <div class="meta-item">
                <span>👥</span>
                <span><?php echo $teamCount; ?> đội</span>
            </div>
            <div class="meta-item">
                <span>🏆</span>
                <span><?php echo $formatText[$tournament['format']] ?? 'Không xác định'; ?></span>
            </div>
            <div class="meta-item">
                <span class="status-badge"><?php echo $statusText[$tournament['status']] ?? 'Không xác định'; ?></span>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <div class="tab active" onclick="switchTab('overview')">📊 Tổng quan</div>
        <div class="tab" onclick="switchTab('teams')">👥 Đội</div>
        <div class="tab" onclick="switchTab('groups')">📋 Bảng</div>
        <div class="tab" onclick="switchTab('matches')">⚡ Trận đấu</div>
        <div class="tab" onclick="switchTab('standings')">🏆 Xếp hạng</div>
        <div class="tab" onclick="switchTab('bracket')">🎯 Đấu loại</div>
    </div>

    <!-- Content Area -->
    <div class="content">
        <!-- Tab 1: Overview -->
        <div id="overviewTab" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $teamCount; ?></div>
                    <div class="stat-label">Đội</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($groups); ?></div>
                    <div class="stat-label">Bảng</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $completedMatches; ?>/<?php echo $matchCount; ?></div>
                    <div class="stat-label">Trận</div>
                </div>
            </div>

            <div class="section-card">
                <h3 class="section-title">📝 Thông tin giải đấu</h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <div style="font-size: 12px; color: var(--text-grey); margin-bottom: 5px;">Thời gian</div>
                        <div style="font-weight: 600;">
                            <?php if ($tournament['start_date']): ?>
                                <?php echo date('d/m/Y', strtotime($tournament['start_date'])); ?>
                                <?php if ($tournament['end_date']): ?>
                                    - <?php echo date('d/m/Y', strtotime($tournament['end_date'])); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Đang cập nhật
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-grey); margin-bottom: 5px;">Địa điểm</div>
                        <div style="font-weight: 600;"><?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Đang cập nhật'; ?></div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-grey); margin-bottom: 5px;">Thể thức</div>
                        <div style="font-weight: 600;"><?php echo $formatText[$tournament['format']] ?? 'Không xác định'; ?></div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-grey); margin-bottom: 5px;">Mô tả</div>
                        <div style="font-size: 14px; line-height: 1.5;">
                            <?php echo nl2br(htmlspecialchars($tournament['description'] ?: 'Giải đấu được tổ chức với sự tham gia của các vận động viên pickleball xuất sắc nhất.')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Teams -->
        <div id="teamsTab" class="tab-content" style="display: none;">
            <div class="section-card">
                <h3 class="section-title">
                    Danh sách đội
                    <span class="badge"><?php echo $teamCount; ?> đội</span>
                </h3>
                
                <?php if (empty($teams)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <div class="empty-text">Chưa có đội nào tham gia</div>
                    <button class="fab" style="width: auto; padding: 0 20px; height: 40px; font-size: 14px; border-radius: 20px;">Thêm đội</button>
                </div>
                <?php else: ?>
                <div class="team-list">
                    <?php foreach($teams as $index => $team): ?>
                    <div class="team-item" onclick="showTeamDetail(<?php echo $team['id']; ?>)">
                        <div class="team-rank">#<?php echo $index + 1; ?></div>
                        <div class="team-info">
                            <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                            <div class="team-players">
                                <?php echo htmlspecialchars($team['player1']); ?>
                                <?php if ($team['player2']): ?> & <?php echo htmlspecialchars($team['player2']); ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="team-tags">
                            <?php if ($team['group_name']): ?>
                            <span class="team-tag">Bảng <?php echo $team['group_name']; ?></span>
                            <?php endif; ?>
                            <?php if ($team['skill_level']): ?>
                            <span class="team-tag"><?php echo $team['skill_level']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 3: Groups -->
        <div id="groupsTab" class="tab-content" style="display: none;">
            <div class="section-card">
                <h3 class="section-title">
                    Các bảng đấu
                    <span class="badge"><?php echo count($groups); ?> bảng</span>
                </h3>
                
                <?php if (empty($groups)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-text">Chưa có bảng đấu nào</div>
                    <button class="fab" style="width: auto; padding: 0 20px; height: 40px; font-size: 14px; border-radius: 20px;">Chia bảng</button>
                </div>
                <?php else: ?>
                <div class="group-list">
                    <?php foreach($groups as $group): 
                        $stmt = $pdo->prepare("SELECT * FROM Teams WHERE tournament_id = ? AND group_name = ? ORDER BY seed, team_name");
                        $stmt->execute([$tournamentId, $group['group_name']]);
                        $groupTeams = $stmt->fetchAll();
                    ?>
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-name">Bảng <?php echo $group['group_name']; ?></div>
                            <div style="font-size: 12px; color: var(--text-grey);">
                                <?php echo count($groupTeams); ?> đội
                            </div>
                        </div>
                        
                        <div class="team-list">
                            <?php foreach($groupTeams as $index => $team): ?>
                            <div class="team-item">
                                <div class="team-rank">#<?php echo $index + 1; ?></div>
                                <div class="team-info">
                                    <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                    <div class="team-players">
                                        <?php echo htmlspecialchars($team['player1']); ?>
                                        <?php if ($team['player2']): ?> & <?php echo htmlspecialchars($team['player2']); ?><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($team['seed']): ?>
                                <span class="team-tag">Hạt giống <?php echo $team['seed']; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 4: Matches -->
        <div id="matchesTab" class="tab-content" style="display: none;">
            <div class="section-card">
                <h3 class="section-title">
                    Lịch thi đấu
                    <span class="badge"><?php echo $matchCount; ?> trận</span>
                </h3>
                
                <?php if (empty($matches)): ?>
                <div class="empty-state">
                    <div class="empty-icon">⚡</div>
                    <div class="empty-text">Chưa có trận đấu nào</div>
                    <button class="fab" style="width: auto; padding: 0 20px; height: 40px; font-size: 14px; border-radius: 20px;">Tạo lịch</button>
                </div>
                <?php else: ?>
                <div class="matches-list">
                    <?php 
                    $todayMatches = [];
                    $upcomingMatches = [];
                    $completedMatchesList = [];
                    
                    foreach ($matches as $match) {
                        if ($match['score1'] !== null && $match['score2'] !== null) {
                            $completedMatchesList[] = $match;
                        } else {
                            $upcomingMatches[] = $match;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($completedMatchesList)): ?>
                    <div style="font-size: 14px; color: var(--text-grey); margin-bottom: 15px; font-weight: 600;">Đã hoàn thành</div>
                    <?php foreach($completedMatchesList as $match): ?>
                    <div class="match-card completed" onclick="showMatchDetail(<?php echo $match['id']; ?>)">
                        <div class="match-header">
                            <div class="match-round">
                                <?php if ($match['group_name']): ?>
                                Bảng <?php echo $match['group_name']; ?>
                                <?php else: ?>
                                <?php echo htmlspecialchars($match['round']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="match-status completed">Hoàn thành</div>
                        </div>
                        <div class="match-teams">
                            <div class="team-display">
                                <div class="team-avatar"><?php echo substr($match['team1_name'] ?? 'T1', 0, 2); ?></div>
                                <div class="team-name-match"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                            </div>
                            <div class="match-vs">
                                <div class="score-display"><?php echo $match['score1']; ?> - <?php echo $match['score2']; ?></div>
                                <div class="vs-text">VS</div>
                            </div>
                            <div class="team-display">
                                <div class="team-avatar"><?php echo substr($match['team2_name'] ?? 'T2', 0, 2); ?></div>
                                <div class="team-name-match"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($upcomingMatches)): ?>
                    <div style="font-size: 14px; color: var(--text-grey); margin: 25px 0 15px; font-weight: 600;">Sắp diễn ra</div>
                    <?php foreach($upcomingMatches as $match): ?>
                    <div class="match-card upcoming" onclick="showMatchDetail(<?php echo $match['id']; ?>)">
                        <div class="match-header">
                            <div class="match-round">
                                <?php if ($match['group_name']): ?>
                                Bảng <?php echo $match['group_name']; ?>
                                <?php else: ?>
                                <?php echo htmlspecialchars($match['round']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="match-status upcoming">Sắp diễn ra</div>
                        </div>
                        <div class="match-teams">
                            <div class="team-display">
                                <div class="team-avatar"><?php echo substr($match['team1_name'] ?? 'T1', 0, 2); ?></div>
                                <div class="team-name-match"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                            </div>
                            <div class="match-vs">
                                <div class="vs-text">VS</div>
                                <div style="font-size: 11px; color: var(--text-grey); margin-top: 5px;">Chưa diễn ra</div>
                            </div>
                            <div class="team-display">
                                <div class="team-avatar"><?php echo substr($match['team2_name'] ?? 'T2', 0, 2); ?></div>
                                <div class="team-name-match"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 5: Standings -->
        <div id="standingsTab" class="tab-content" style="display: none;">
            <div class="section-card">
                <h3 class="section-title">Bảng xếp hạng</h3>
                
                <?php if (empty($standings)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <div class="empty-text">Chưa có dữ liệu xếp hạng</div>
                </div>
                <?php else: ?>
                <div class="standings-list">
                    <?php foreach($standings as $groupName => $teams): ?>
                    <div style="margin-bottom: 30px;">
                        <div style="font-size: 16px; font-weight: 700; color: var(--accent); margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid var(--accent);">
                            Bảng <?php echo $groupName; ?>
                        </div>
                        
                        <div class="standings-table">
                            <div class="table-header">
                                <div>#</div>
                                <div>Đội</div>
                                <div>T</div>
                                <div>Đ</div>
                                <div>HS</div>
                            </div>
                            
                            <?php $position = 1; ?>
                            <?php foreach($teams as $team): ?>
                            <div class="table-row <?php echo $position <= 3 ? 'top-3' : ''; ?>">
                                <div class="rank rank-<?php echo $position; ?>">
                                    <?php echo $position; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                </div>
                                <div style="text-align: center; font-weight: 600;"><?php echo $team['matches']; ?></div>
                                <div style="text-align: center; color: var(--primary); font-weight: 700;"><?php echo $team['points']; ?></div>
                                <div style="text-align: center; font-weight: 600; color: <?php echo $team['goal_diff'] > 0 ? 'var(--primary)' : ($team['goal_diff'] < 0 ? 'var(--loser-red)' : 'var(--text-white)'); ?>">
                                    <?php echo $team['goal_diff'] > 0 ? '+' : ''; ?><?php echo $team['goal_diff']; ?>
                                </div>
                            </div>
                            <?php $position++; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 6: Bracket -->
        <div id="bracketTab" class="tab-content" style="display: none;">
            <div class="section-card">
                <h3 class="section-title">Sơ đồ đấu loại</h3>
                
                <?php 
                $knockoutMatches = $pdo->prepare("
                    SELECT m.*, 
                           t1.team_name as team1_name, 
                           t2.team_name as team2_name
                    FROM Matches m
                    LEFT JOIN Teams t1 ON m.team1_id = t1.id
                    LEFT JOIN Teams t2 ON m.team2_id = t2.id
                    WHERE m.tournament_id = ? AND m.group_id IS NULL
                    ORDER BY m.round, m.id
                ");
                $knockoutMatches->execute([$tournamentId]);
                $knockoutMatches = $knockoutMatches->fetchAll();
                
                if (empty($knockoutMatches) || $tournament['format'] == 'round_robin'):
                ?>
                <div class="empty-state">
                    <div class="empty-icon">🎯</div>
                    <div class="empty-text">
                        <?php if ($tournament['format'] == 'round_robin'): ?>
                        Giải đấu này chỉ thi đấu theo thể thức vòng tròn
                        <?php else: ?>
                        Chưa tạo sơ đồ đấu loại
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bracket-container">
                    <?php 
                    $matchesByRound = [];
                    foreach ($knockoutMatches as $match) {
                        $round = $match['round'] ?: 'Chưa xác định';
                        $matchesByRound[$round][] = $match;
                    }
                    
                    $roundOrder = [];
                    foreach ($matchesByRound as $roundName => $roundMatches) {
                        $roundOrder[$roundName] = count($roundMatches);
                    }
                    
                    asort($roundOrder);
                    
                    $roundCount = count($roundOrder);
                    $currentRound = 0;
                    
                    foreach ($roundOrder as $roundName => $matchCount):
                        $currentRound++;
                        $roundDisplayName = $roundName;
                        if ($matchCount == 1) $roundDisplayName = "CHUNG KẾT";
                        elseif ($matchCount == 2) $roundDisplayName = "BÁN KẾT";
                        elseif ($matchCount == 4) $roundDisplayName = "TỨ KẾT";
                    ?>
                    <div class="bracket-stage">
                        <div class="stage-title"><?php echo $roundDisplayName; ?></div>
                        <?php 
                        $roundMatches = $matchesByRound[$roundName];
                        foreach($roundMatches as $match): 
                            $team1Winner = ($match['score1'] !== null && $match['score2'] !== null && $match['score1'] > $match['score2']);
                            $team2Winner = ($match['score1'] !== null && $match['score2'] !== null && $match['score2'] > $match['score1']);
                        ?>
                        <div class="bracket-match">
                            <div class="bracket-team <?php echo $team1Winner ? 'winner' : ''; ?>">
                                <span><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                <?php if ($match['score1'] !== null): ?>
                                <span class="bracket-score"><?php echo $match['score1']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="bracket-team <?php echo $team2Winner ? 'winner' : ''; ?>">
                                <span><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                <?php if ($match['score2'] !== null): ?>
                                <span class="bracket-score"><?php echo $match['score2']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    
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
                        $winnerName = ($finalMatch['score1'] > $finalMatch['score2']) 
                            ? $finalMatch['team1_name'] 
                            : $finalMatch['team2_name'];
                    ?>
                    <div class="bracket-stage">
                        <div class="stage-title">NHÀ VÔ ĐỊCH</div>
                        <div class="bracket-match">
                            <div class="bracket-team winner" style="height: 60px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; color: var(--winner-gold);">
                                🏆 <?php echo htmlspecialchars($winnerName); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Buttons -->
    <div class="fab-container">
        <button class="fab" onclick="shareTournament()">📤</button>
        <button class="fab" onclick="refreshData()">🔄</button>
    </div>

    <!-- Match Detail Modal -->
    <div id="modalMatchDetail" class="overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            <span class="close-x" onclick="closeModal('modalMatchDetail')">&times;</span>
            
            <div class="match-detail-header">
                <div class="match-round-badge" id="detailRound">Chung kết</div>
                <div class="match-date" id="detailDate">Hôm nay</div>
            </div>
            
            <div class="match-teams-detail">
                <div class="team-detail">
                    <div class="team-avatar-lg" id="detailTeam1Avatar">T1</div>
                    <div class="team-name-lg" id="detailTeam1Name">Team 1</div>
                    <div class="team-players-lg" id="detailTeam1Players">Player1 & Player2</div>
                    <div class="score-display-lg" id="detailTeam1Score">2</div>
                </div>
                
                <div class="vs-text-lg">VS</div>
                
                <div class="team-detail">
                    <div class="team-avatar-lg" id="detailTeam2Avatar">T2</div>
                    <div class="team-name-lg" id="detailTeam2Name">Team 2</div>
                    <div class="team-players-lg" id="detailTeam2Players">Player1 & Player2</div>
                    <div class="score-display-lg" id="detailTeam2Score">1</div>
                </div>
            </div>

            <div style="text-align: center; margin: 20px 0;">
                <div id="detailStatus" style="display: inline-block; padding: 6px 15px; border-radius: 15px; background: rgba(74, 222, 128, 0.2); color: var(--primary); font-size: 13px; font-weight: 600;">
                    Hoàn thành • 45 phút trước
                </div>
            </div>

            <div class="score-breakdown">
                <div class="score-row">
                    <span class="map-name">Ván 1</span>
                    <span class="map-score winner" id="detailMap1Score">21-15</span>
                </div>
                <div class="score-row">
                    <span class="map-name">Ván 2</span>
                    <span class="map-score" id="detailMap2Score">18-21</span>
                </div>
                <div class="score-row">
                    <span class="map-name">Ván 3</span>
                    <span class="map-score winner" id="detailMap3Score">21-19</span>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <button class="fab" onclick="closeModal('modalMatchDetail')" style="width: 100%; height: 50px; border-radius: 12px; font-size: 16px; background: var(--secondary);">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="modalShare" class="overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            <span class="close-x" onclick="closeModal('modalShare')">&times;</span>
            
            <h3 style="text-align: center; margin-bottom: 25px;">Chia sẻ giải đấu</h3>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0;">
                <button style="background: #1877F2; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 24px;">
                    f
                </button>
                <button style="background: #1DA1F2; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 24px;">
                    🐦
                </button>
                <button style="background: #25D366; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 24px;">
                    📱
                </button>
            </div>

            <div style="background: var(--input-bg); padding: 15px; border-radius: 12px; margin: 20px 0;">
                <div style="font-size: 12px; color: var(--text-grey); margin-bottom: 8px;">Liên kết giải đấu</div>
                <div style="display: flex; gap: 10px;">
                    <input type="text" value="<?php echo "https://yourdomain.com/tournament_view.php?id=" . $tournamentId; ?>" readonly 
                           style="flex: 1; background: transparent; border: none; color: white; padding: 8px; font-size: 14px;">
                    <button onclick="copyLink()" style="background: var(--primary); color: black; border: none; padding: 8px 15px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                        Sao chép
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab switching
    function switchTab(tabName) {
        // Hide all tabs
        const tabs = ['overview', 'teams', 'groups', 'matches', 'standings', 'bracket'];
        tabs.forEach(tab => {
            document.getElementById(tab + 'Tab').style.display = 'none';
        });
        
        // Show selected tab
        document.getElementById(tabName + 'Tab').style.display = 'block';
        
        // Update active tab
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
        
        // Scroll to top
        document.querySelector('.content').scrollTop = 0;
        
        // Save active tab to sessionStorage
        sessionStorage.setItem('activeTab', tabName);
    }

    // Restore active tab on page load
    document.addEventListener('DOMContentLoaded', function() {
        const savedTab = sessionStorage.getItem('activeTab') || 'overview';
        const tabElement = document.querySelector(`.tab[onclick*="${savedTab}"]`);
        if (tabElement) {
            tabElement.click();
        }
        
        // Auto-scroll bracket if needed
        const bracketTab = document.getElementById('bracketTab');
        if (bracketTab.style.display !== 'none') {
            const bracketContainer = bracketTab.querySelector('.bracket-container');
            if (bracketContainer) {
                setTimeout(() => {
                    bracketContainer.scrollLeft = 150;
                }, 100);
            }
        }
    });

    // Modal control
    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking overlay
    document.querySelectorAll('.overlay').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });

    // Share tournament
    function shareTournament() {
        openModal('modalShare');
    }

    function copyLink() {
        const linkInput = document.querySelector('#modalShare input[type="text"]');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            navigator.clipboard.writeText(linkInput.value);
            alert('Đã sao chép liên kết!');
        } catch (err) {
            // Fallback for older browsers
            document.execCommand('copy');
            alert('Đã sao chép liên kết vào clipboard!');
        }
    }

    // Show match detail (simulated)
    function showMatchDetail(matchId) {
        // In a real app, you would fetch match details via AJAX
        // For now, we'll use sample data
        const matchData = {
            round: "Chung kết",
            date: "Hôm nay",
            team1: {
                name: "Team Phoenix",
                avatar: "TP",
                players: "Nguyễn Văn A & Trần Thị B",
                score: 2
            },
            team2: {
                name: "Team Thunder",
                avatar: "TT",
                players: "Lê Văn C & Phạm Thị D",
                score: 1
            },
            status: "Hoàn thành • 45 phút trước",
            maps: [
                { name: "Ván 1", score1: 21, score2: 15 },
                { name: "Ván 2", score1: 18, score2: 21 },
                { name: "Ván 3", score1: 21, score2: 19 }
            ]
        };
        
        document.getElementById('detailRound').textContent = matchData.round;
        document.getElementById('detailDate').textContent = matchData.date;
        
        document.getElementById('detailTeam1Avatar').textContent = matchData.team1.avatar;
        document.getElementById('detailTeam1Name').textContent = matchData.team1.name;
        document.getElementById('detailTeam1Players').textContent = matchData.team1.players;
        document.getElementById('detailTeam1Score').textContent = matchData.team1.score;
        
        document.getElementById('detailTeam2Avatar').textContent = matchData.team2.avatar;
        document.getElementById('detailTeam2Name').textContent = matchData.team2.name;
        document.getElementById('detailTeam2Players').textContent = matchData.team2.players;
        document.getElementById('detailTeam2Score').textContent = matchData.team2.score;
        
        document.getElementById('detailStatus').textContent = matchData.status;
        
        document.getElementById('detailMap1Score').textContent = `${matchData.maps[0].score1}-${matchData.maps[0].score2}`;
        document.getElementById('detailMap2Score').textContent = `${matchData.maps[1].score1}-${matchData.maps[1].score2}`;
        document.getElementById('detailMap3Score').textContent = `${matchData.maps[2].score1}-${matchData.maps[2].score2}`;
        
        // Add winner class to map scores
        const mapScores = document.querySelectorAll('.map-score');
        mapScores.forEach((score, index) => {
            score.classList.remove('winner');
            if (matchData.maps[index].score1 > matchData.maps[index].score2) {
                if (index === 0 || index === 2) {
                    score.classList.add('winner');
                }
            } else if (matchData.maps[index].score1 < matchData.maps[index].score2) {
                if (index === 1) {
                    score.classList.add('winner');
                }
            }
        });
        
        openModal('modalMatchDetail');
    }

    // Show team detail
    function showTeamDetail(teamId) {
        // In a real app, fetch team details via AJAX
        alert(`Xem chi tiết đội ID: ${teamId}\n(Tính năng đang phát triển)`);
    }

    // Refresh data
    function refreshData() {
        const content = document.querySelector('.content');
        const originalContent = content.innerHTML;
        
        content.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                <div>Đang cập nhật dữ liệu...</div>
            </div>
        `;
        
        // Simulate API call
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
</script>

</body>
</html>

<?php
// Hàm tính bảng xếp hạng cho giải đấu
function calculateTournamentStandings($tournamentId) {
    global $pdo;
    
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