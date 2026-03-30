<?php
// tournament_bracket.php - Sơ đồ bảng đấu và tiến độ giải đấu
require_once 'db.php';
require_once 'functions.php';
require_once 'components/template.php';

$tournamentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tournamentId) {
    header("Location: tournament_list.php");
    exit;
}

$tournament = getTournamentById($tournamentId);
if (!$tournament) {
    header("Location: tournament_list.php");
    exit;
}

$groups = getGroupsByTournament($tournamentId);
$matches = getMatchesByTournament($tournamentId);

$stageLabels = [
    'planning' => 'Lập kế hoạch',
    'registration' => 'Đăng ký',
    'setup' => 'Chuẩn bị',
    'group_stage' => 'Vòng bảng',
    'knockout_stage' => 'Vòng loại trực tiếp',
    'completed' => 'Hoàn thành'
];

function getGroupStandingsFromMatches($groupId, $matches) {
    $groupMatches = array_filter($matches, fn($m) => $m['group_id'] == $groupId && ($m['status'] === 'completed' || $m['status'] === 'live'));
    
    $standings = [];
    
    foreach ($groupMatches as $match) {
        $t1Id = $match['team1_id'];
        $t2Id = $match['team2_id'];
        
        if (!isset($standings[$t1Id])) {
            $standings[$t1Id] = [
                'team_id' => $t1Id,
                'team_name' => $match['team1_name'],
                'player1' => $match['player1'] ?? '',
                'player2' => $match['player2'] ?? '',
                'played' => 0, 'won' => 0, 'lost' => 0, 'points_for' => 0, 'points_against' => 0
            ];
        }
        if (!isset($standings[$t2Id])) {
            $standings[$t2Id] = [
                'team_id' => $t2Id,
                'team_name' => $match['team2_name'],
                'player1' => $match['player1'] ?? '',
                'player2' => $match['player2'] ?? '',
                'played' => 0, 'won' => 0, 'lost' => 0, 'points_for' => 0, 'points_against' => 0
            ];
        }
        
        if ($match['status'] === 'completed' && $match['score1'] !== null) {
            $standings[$t1Id]['played']++;
            $standings[$t2Id]['played']++;
            $standings[$t1Id]['points_for'] += $match['score1'];
            $standings[$t1Id]['points_against'] += $match['score2'];
            $standings[$t2Id]['points_for'] += $match['score2'];
            $standings[$t2Id]['points_against'] += $match['score1'];
            
            if ($match['score1'] > $match['score2']) {
                $standings[$t1Id]['won']++;
                $standings[$t2Id]['lost']++;
            } else {
                $standings[$t2Id]['won']++;
                $standings[$t1Id]['lost']++;
            }
        }
    }
    
    usort($standings, function($a, $b) {
        if ($b['won'] !== $a['won']) return $b['won'] - $a['won'];
        return ($b['points_for'] - $b['points_against']) - ($a['points_for'] - $a['points_against']);
    });
    
    return $standings;
}

function getTeamInfoById($teamId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Teams WHERE id = ?");
    $stmt->execute([$teamId]);
    return $stmt->fetch();
}

renderHeader('Sơ Đồ Bảng Đấu - ' . htmlspecialchars($tournament['name']), '');
?>

<style>
    .bracket-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
    }
    .bracket-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary);
    }
    .standings-table {
        width: 100%;
        border-collapse: collapse;
    }
    .standings-table th {
        background: #f8fafc;
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 0.85rem;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
    }
    .standings-table th:first-child { text-align: left; width: 40px; }
    .standings-table td {
        padding: 10px 8px;
        text-align: center;
        border-bottom: 1px solid #f1f5f9;
    }
    .standings-table td:first-child { 
        text-align: center; 
        font-weight: 700;
        color: #64748b;
    }
    .standings-table td.team-cell { 
        text-align: left; 
        font-weight: 600;
    }
    .standings-table tr:nth-child(1) td { background: #fef3c7; }
    .standings-table tr:nth-child(2) td { background: #f3f4f6; }
    .standings-table tr:hover td { background: #f8fafc; }
    .win { color: #16a34a; font-weight: 700; }
    .loss { color: #dc2626; font-weight: 700; }
    .match-result {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 8px;
        border-left: 4px solid #e2e8f0;
    }
    .match-result.winner { border-left-color: #16a34a; background: #f0fdf4; }
    .match-result.live { border-left-color: #dc2626; background: #fef2f2; animation: pulse-border 2s infinite; }
    @keyframes pulse-border {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
        50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0); }
    }
    .team-score {
        font-size: 1.2rem;
        font-weight: 700;
        min-width: 40px;
        text-align: center;
    }
    .team-score.winner { color: #16a34a; }
    .team-score.loser { color: #94a3b8; }
    .progress-bar-custom {
        height: 8px;
        border-radius: 4px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 5px;
    }
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
    }
    .group-header {
        background: linear-gradient(135deg, #1e3a5f, #2ecc71);
        color: white;
        padding: 15px 20px;
        border-radius: 10px 10px 0 0;
        margin: -20px -20px 15px -20px;
    }
    .rank-medal { font-size: 1.2rem; }
    .knockout-round {
        text-align: center;
        padding: 20px;
    }
    .knockout-match {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px;
        margin: 10px 0;
        position: relative;
    }
    .knockout-match::before {
        content: '';
        position: absolute;
        right: -20px;
        top: 50%;
        width: 20px;
        height: 2px;
        background: #e2e8f0;
    }
    .round-title {
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 15px;
    }
    .no-matches {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }
    .bracket-tree {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        padding: 20px 0;
    }
    .bracket-round {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        min-width: 200px;
        margin-right: 40px;
    }
    .bracket-round-title {
        text-align: center;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    .bracket-match {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        margin: 8px 0;
        position: relative;
        transition: all 0.2s;
    }
    .bracket-match:hover {
        border-color: #2ecc71;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .bracket-match.winner-left { border-color: #16a34a; background: #f0fdf4; }
    .bracket-match.winner-right { border-color: #16a34a; background: #f0fdf4; }
    .bracket-team {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.85rem;
    }
    .bracket-team.winner {
        font-weight: 700;
        color: #16a34a;
    }
    .bracket-team.loser {
        color: #94a3b8;
    }
    .bracket-score {
        font-weight: 700;
        min-width: 30px;
        text-align: center;
    }
    .bracket-team.winner .bracket-score { color: #16a34a; }
    .bracket-team.loser .bracket-score { color: #94a3b8; }
    .bracket-connector {
        position: absolute;
        right: -20px;
        top: 50%;
        width: 20px;
        height: 2px;
        background: #e2e8f0;
    }
    .bracket-match-pair {
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .bracket-match-pair .bracket-connector-vertical {
        position: absolute;
        right: -20px;
        width: 2px;
        background: #e2e8f0;
    }
    .bracket-final {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 2px solid #f59e0b;
    }
    .champion-box {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        margin-top: 20px;
    }
    .champion-box .trophy {
        font-size: 3rem;
    }
    .champion-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin-top: 10px;
    }
    .bracket-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .bracket-nav .btn {
        flex: 1;
    }
</style>

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="page-title"><i class="fas fa-chart-bar me-2"></i>SƠ ĐỒ BẢNG ĐẤU</h1>
                <p class="mb-0 mt-2"><?php echo htmlspecialchars($tournament['name']); ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-<?php echo $tournament['stage'] === 'group_stage' ? 'primary' : ($tournament['stage'] === 'knockout_stage' ? 'danger' : 'secondary'); ?> fs-6">
                    <?php echo $stageLabels[$tournament['stage']] ?? 'Không xác định'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <?php if (empty($groups)): ?>
        <div class="bracket-container">
            <div class="no-matches">
                <i class="fas fa-folder-open fa-3x mb-3"></i>
                <h4>Chưa có bảng đấu</h4>
                <p class="mb-0">Giải đấu chưa được bốc thăm chia bảng</p>
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                    <a href="draw.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-random me-1"></i>Bốc thăm ngay
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $index => $group): ?>
            <?php 
                $standings = getGroupStandingsFromMatches($group['id'], $matches);
                $groupMatches = array_filter($matches, fn($m) => $m['group_id'] == $group['id']);
                $groupMatches = array_filter($groupMatches, fn($m) => $m['status'] === 'completed' || $m['status'] === 'live');
                usort($groupMatches, fn($a, $b) => strcmp($b['id'], $a['id']));
                $totalMatches = count($matches);
                $completedMatches = count(array_filter($matches, fn($m) => $m['group_id'] == $group['id'] && $m['status'] === 'completed'));
            ?>
            <div class="bracket-container">
                <div class="group-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($group['group_name']); ?></h4>
                        <span class="badge bg-white text-dark">
                            <i class="fas fa-bullhorn me-1"></i><?php echo $completedMatches; ?>/<?php echo count(array_filter($matches, fn($m) => $m['group_id'] == $group['id'])); ?> trận
                        </span>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Bảng xếp hạng -->
                    <div class="col-lg-7">
                        <table class="standings-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>VĐV / Đội</th>
                                    <th>Trận</th>
                                    <th>Thắng</th>
                                    <th>Thua</th>
                                    <th>Hiệu số</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($standings)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">Chưa có trận đấu</td></tr>
                                <?php else: ?>
                                    <?php foreach ($standings as $rank => $team): ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank === 0): ?><span class="rank-medal">🥇</span>
                                                <?php elseif ($rank === 1): ?><span class="rank-medal">🥈</span>
                                                <?php elseif ($rank === 2): ?><span class="rank-medal">🥉</span>
                                                <?php echo $rank + 1; endif; ?>
                                            </td>
                                            <td class="team-cell">
                                                <div><?php echo renderTeamPlayers(getTeamInfoById($team['team_id']), '', 'font-size: 0.9rem;'); ?></div>
                                            </td>
                                            <td><?php echo $team['played']; ?></td>
                                            <td class="win"><?php echo $team['won']; ?></td>
                                            <td class="loss"><?php echo $team['lost']; ?></td>
                                            <td><?php echo $team['points_for'] - $team['points_against']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Kết quả trận đấu -->
                    <div class="col-lg-5">
                        <h6 class="mb-3"><i class="fas fa-history me-1"></i>Kết quả gần nhất</h6>
                        <?php if (empty($groupMatches)): ?>
                            <p class="text-muted text-center py-3">Chưa có trận đấu nào</p>
                        <?php else: ?>
                            <?php foreach (array_slice($groupMatches, 0, 4) as $match): ?>
                                <?php 
                                    $team1 = getTeamInfoById($match['team1_id']);
                                    $team2 = getTeamInfoById($match['team2_id']);
                                    $isLive = $match['status'] === 'live';
                                    $winner = ($match['score1'] > $match['score2']) ? 1 : ($match['score2'] > $match['score1'] ? 2 : 0);
                                ?>
                                <div class="match-result <?php echo $isLive ? 'live' : ''; ?> <?php echo (!$isLive && $winner > 0) ? 'winner' : ''; ?>">
                                    <div class="text-start">
                                        <div class="team-score <?php echo $winner === 1 ? 'winner' : 'loser'; ?>"><?php echo $match['score1'] ?? 0; ?></div>
                                        <div class="small text-muted" style="max-width: 80px; font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($team1['player1'] ?? $match['team1_name']); ?>
                                            <?php if (!empty($team1['player2'])): ?><br><?php echo htmlspecialchars($team1['player2']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <?php if ($isLive): ?>
                                            <span class="badge bg-danger"><i class="fas fa-circle fa-xs me-1"></i>LIVE</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo $match['round'] ?? 'Vòng ' . $match['round_number']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="team-score <?php echo $winner === 2 ? 'winner' : 'loser'; ?>"><?php echo $match['score2'] ?? 0; ?></div>
                                        <div class="small text-muted" style="max-width: 80px; font-size: 0.75rem; text-align: right;">
                                            <?php echo htmlspecialchars($team2['player1'] ?? $match['team2_name']); ?>
                                            <?php if (!empty($team2['player2'])): ?><br><?php echo htmlspecialchars($team2['player2']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($groupMatches) > 4): ?>
                                <a href="matches.php?tournament_id=<?php echo $tournamentId; ?>&group=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    Xem tất cả <?php echo count($groupMatches); ?> trận
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Vòng loại trực tiếp -->
        <?php 
            $knockoutMatches = array_filter($matches, fn($m) => empty($m['group_id']) && ($m['status'] === 'completed' || $m['status'] === 'live' || $m['status'] === 'pending'));
        ?>
        <?php if (!empty($knockoutMatches) && $tournament['stage'] === 'knockout_stage'): ?>
            <div class="bracket-container">
                <div class="bracket-title">
                    <i class="fas fa-sitemap me-2"></i>CÂY SƠ ĐỒ BẢNG ĐẤU
                </div>
                
                <!-- Bracket Navigation -->
                <div class="bracket-nav">
                    <button class="btn btn-outline-primary active" onclick="showBracketView('tree')">
                        <i class="fas fa-sitemap me-1"></i>Cây sơ đồ
                    </button>
                    <button class="btn btn-outline-secondary" onclick="showBracketView('list')">
                        <i class="fas fa-list me-1"></i>Danh sách
                    </button>
                </div>

                <!-- Tree View -->
                <div id="bracket-tree-view">
                    <?php 
                    $rounds = [];
                    $roundOrder = ['Tứ kết', 'Bán kết', 'Chung kết', 'Tranh hạng 3', 'Final'];
                    $roundPriority = ['Tứ kết' => 1, 'Bán kết' => 2, 'Chung kết' => 3, 'Tranh hạng 3' => 4, 'Final' => 5];
                    
                    foreach ($knockoutMatches as $m) {
                        $round = $m['round'] ?? 'Vòng 1';
                        if (!isset($rounds[$round])) $rounds[$round] = [];
                        $rounds[$round][] = $m;
                    }
                    
                    uksort($rounds, function($a, $b) use ($roundPriority) {
                        $pa = $roundPriority[$a] ?? 99;
                        $pb = $roundPriority[$b] ?? 99;
                        return $pa - $pb;
                    });
                    
                    $champion = null;
                    $finalMatch = null;
                    foreach ($rounds as $roundName => $roundMatches) {
                        if (stripos($roundName, 'chung') !== false || stripos($roundName, 'final') !== false) {
                            foreach ($roundMatches as $m) {
                                if ($m['status'] === 'completed' && ($m['score1'] > 0 || $m['score2'] > 0)) {
                                    $winnerId = $m['score1'] > $m['score2'] ? $m['team1_id'] : $m['team2_id'];
                                    if ($winnerId) {
                                        $champion = getTeamInfoById($winnerId);
                                        $finalMatch = $m;
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    
                    <div class="bracket-tree">
                        <?php foreach ($rounds as $roundName => $roundMatches): ?>
                            <div class="bracket-round">
                                <div class="bracket-round-title">
                                    <?php echo htmlspecialchars($roundName); ?>
                                </div>
                                <?php 
                                $isFinal = stripos($roundName, 'chung') !== false || stripos($roundName, 'final') !== false;
                                $isThird = stripos($roundName, 'hạng 3') !== false || stripos($roundName, '3') !== false;
                                ?>
                                <?php foreach ($roundMatches as $match): ?>
                                    <?php 
                                        $team1 = getTeamInfoById($match['team1_id']);
                                        $team2 = getTeamInfoById($match['team2_id']);
                                        $isLive = $match['status'] === 'live';
                                        $winner = ($match['score1'] > $match['score2']) ? 1 : ($match['score2'] > $match['score1'] ? 2 : 0);
                                        $matchClass = '';
                                        if ($isFinal) $matchClass = 'bracket-final';
                                        elseif ($winner === 1) $matchClass = 'winner-left';
                                        elseif ($winner === 2) $matchClass = 'winner-right';
                                    ?>
                                    <div class="bracket-match <?php echo $matchClass; ?> <?php echo $isLive ? 'border-danger' : ''; ?>">
                                        <div class="bracket-team <?php echo $winner === 1 ? 'winner' : ($winner > 0 ? 'loser' : ''); ?>">
                                            <span><?php echo htmlspecialchars($team1['player1'] ?? $match['team1_name'] ?? 'TBD'); ?></span>
                                            <span class="bracket-score"><?php echo $match['score1'] ?? '-'; ?></span>
                                        </div>
                                        <div class="bracket-team <?php echo $winner === 2 ? 'winner' : ($winner > 0 ? 'loser' : ''); ?>">
                                            <span><?php echo htmlspecialchars($team2['player1'] ?? $match['team2_name'] ?? 'TBD'); ?></span>
                                            <span class="bracket-score"><?php echo $match['score2'] ?? '-'; ?></span>
                                        </div>
                                        <?php if ($isLive): ?>
                                            <div class="text-center mt-1">
                                                <span class="badge bg-danger badge-sm">LIVE</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($champion): ?>
                        <div class="champion-box">
                            <div class="trophy">🏆</div>
                            <div class="champion-name">
                                <i class="fas fa-crown me-2"></i>
                                <?php echo htmlspecialchars($champion['player1'] ?? $champion['team_name']); ?>
                                <?php if (!empty($champion['player2'])): ?> / <?php echo htmlspecialchars($champion['player2']); ?><?php endif; ?>
                            </div>
                            <div class="mt-2">Nhà vô địch <?php echo htmlspecialchars($tournament['name']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- List View -->
                <div id="bracket-list-view" style="display: none;">
                    <?php foreach ($rounds as $roundName => $roundMatches): ?>
                        <div class="knockout-round">
                            <div class="round-title"><?php echo $roundName; ?></div>
                            <div class="row justify-content-center">
                                <?php foreach ($roundMatches as $match): ?>
                                    <?php 
                                        $team1 = getTeamInfoById($match['team1_id']);
                                        $team2 = getTeamInfoById($match['team2_id']);
                                        $isLive = $match['status'] === 'live';
                                        $winner = ($match['score1'] > $match['score2']) ? 1 : ($match['score2'] > $match['score1'] ? 2 : 0);
                                    ?>
                                    <div class="col-md-4">
                                        <div class="knockout-match <?php echo $isLive ? 'border-danger' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="<?php echo $winner === 1 ? 'fw-bold text-success' : ''; ?>">
                                                    <?php echo htmlspecialchars($team1['player1'] ?? $match['team1_name'] ?? 'TBD'); ?>
                                                </span>
                                                <span class="team-score <?php echo $winner === 1 ? 'winner' : ''; ?>"><?php echo $match['score1'] ?? '-'; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="<?php echo $winner === 2 ? 'fw-bold text-success' : ''; ?>">
                                                    <?php echo htmlspecialchars($team2['player1'] ?? $match['team2_name'] ?? 'TBD'); ?>
                                                </span>
                                                <span class="team-score <?php echo $winner === 2 ? 'winner' : ''; ?>"><?php echo $match['score2'] ?? '-'; ?></span>
                                            </div>
                                            <?php if ($isLive): ?>
                                                <div class="text-center mt-2">
                                                    <span class="badge bg-danger"><i class="fas fa-circle fa-xs me-1"></i>ĐANG ĐẤU</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
            function showBracketView(view) {
                document.getElementById('bracket-tree-view').style.display = view === 'tree' ? 'block' : 'none';
                document.getElementById('bracket-list-view').style.display = view === 'list' ? 'block' : 'none';
                var buttons = document.querySelectorAll('.bracket-nav .btn');
                buttons.forEach(function(btn) {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-primary', 'btn-outline-secondary');
                });
                event.target.classList.add('active');
                event.target.classList.remove('btn-outline-primary', 'btn-outline-secondary');
            }
            </script>
        <?php elseif (!empty($knockoutMatches)): ?>
            <div class="bracket-container">
                <div class="bracket-title">
                    <i class="fas fa-trophy me-2"></i>VÒNG LOẠI TRỰC TIẾP
                </div>
                <?php 
                    $rounds = [];
                    foreach ($knockoutMatches as $m) {
                        $round = $m['round'] ?? 'Vòng 1';
                        if (!isset($rounds[$round])) $rounds[$round] = [];
                        $rounds[$round][] = $m;
                    }
                ?>
                <?php foreach ($rounds as $roundName => $roundMatches): ?>
                    <div class="knockout-round">
                        <div class="round-title"><?php echo $roundName; ?></div>
                        <div class="row justify-content-center">
                            <?php foreach ($roundMatches as $match): ?>
                                <?php 
                                    $team1 = getTeamInfoById($match['team1_id']);
                                    $team2 = getTeamInfoById($match['team2_id']);
                                    $isLive = $match['status'] === 'live';
                                    $winner = ($match['score1'] > $match['score2']) ? 1 : ($match['score2'] > $match['score1'] ? 2 : 0);
                                ?>
                                <div class="col-md-4">
                                    <div class="knockout-match <?php echo $isLive ? 'border-danger' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="<?php echo $winner === 1 ? 'fw-bold text-success' : ''; ?>">
                                                <?php echo htmlspecialchars($team1['player1'] ?? $match['team1_name'] ?? 'TBD'); ?>
                                            </span>
                                            <span class="team-score <?php echo $winner === 1 ? 'winner' : ''; ?>"><?php echo $match['score1'] ?? '-'; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="<?php echo $winner === 2 ? 'fw-bold text-success' : ''; ?>">
                                                <?php echo htmlspecialchars($team2['player1'] ?? $match['team2_name'] ?? 'TBD'); ?>
                                            </span>
                                            <span class="team-score <?php echo $winner === 2 ? 'winner' : ''; ?>"><?php echo $match['score2'] ?? '-'; ?></span>
                                        </div>
                                        <?php if ($isLive): ?>
                                            <div class="text-center mt-2">
                                                <span class="badge bg-danger"><i class="fas fa-circle fa-xs me-1"></i>ĐANG ĐẤU</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="mt-4">
        <a href="tournament_view.php?id=<?php echo $tournamentId; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Quay lại
        </a>
        <a href="matches.php?tournament_id=<?php echo $tournamentId; ?>" class="btn btn-outline-primary">
            <i class="fas fa-bullhorn me-1"></i>Xem tất cả trận đấu
        </a>
    </div>
</div>

<?php renderFooter(); ?>
