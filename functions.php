<?php
// functions.php - Phiên bản đã sửa lỗi
require_once 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Lấy danh sách tất cả đội với thông tin giải đấu
 */
function fetchAllTeams() {
    global $pdo;
    return $pdo->query("
        SELECT t.*, tr.name as tournament_name, tr.id as tournament_id
        FROM `Teams` t
        LEFT JOIN Tournaments tr ON t.tournament_id = tr.id
        ORDER BY tr.name, t.skill_level DESC, t.team_name
    ")->fetchAll();
}

/**
 * Lấy danh sách đội theo giải đấu
 */
function fetchTeamsByTournament($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, tr.name as tournament_name 
        FROM `Teams` t
        LEFT JOIN Tournaments tr ON t.tournament_id = tr.id
        WHERE t.tournament_id = ? 
        ORDER BY t.skill_level DESC, t.team_name
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

/**
 * Thêm đội mới
 */
function insertTeam($teamName, $p1, $p2, $tournamentId = null, $skillLevel = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?,?,?,?,?)");
    $stmt->execute([$teamName, $p1, $p2, $tournamentId, $skillLevel]);
    return $pdo->lastInsertId();
}

/**
 * Xóa tất cả đội
 */
function deleteAllTeams() {
    global $pdo;
    return $pdo->exec("DELETE FROM `Teams`");
}

/**
 * Xóa đội theo ID
 */
function deleteTeamById($teamId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM `Teams` WHERE id = ?");
    return $stmt->execute([$teamId]);
}

/**
 * Xóa bảng và trận đấu của một giải đấu
 */
function clearGroupsAndMatches($tournamentId = null) {
    global $pdo;
    
    if ($tournamentId) {
        // Xóa matches của giải đấu
        $pdo->prepare("DELETE FROM `Matches` WHERE tournament_id = ?")->execute([$tournamentId]);
        // Xóa groups của giải đấu
        $pdo->prepare("DELETE FROM `Groups` WHERE tournament_id = ?")->execute([$tournamentId]);
    } else {
        $pdo->exec("DELETE FROM `Matches`");
        $pdo->exec("DELETE FROM `Groups`");
    }
    $pdo->exec("UPDATE Teams SET group_name = NULL");
}

/**
 * Tạo bảng mới
 */
function createGroup($name, $tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO Groups (group_name, tournament_id) VALUES (?, ?)");
    $stmt->execute([$name, $tournamentId]);
    return $pdo->lastInsertId();
}

/**
 * Lấy danh sách tất cả bảng
 */
function fetchAllGroups() {
    global $pdo;
    return $pdo->query("SELECT * FROM `Groups` ORDER BY group_name")->fetchAll();
}

/**
 * Lấy thông tin bảng theo ID
 */
function getGroupById($groupId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM `Groups` WHERE id = ?");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

/**
 * Lấy danh sách đội trong bảng
 */
function getTeamsInGroup($groupId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.* 
        FROM `Teams` t 
        INNER JOIN Matches m ON (t.id = m.team1_id OR t.id = m.team2_id) 
        WHERE m.group_id = ?
        ORDER BY t.team_name
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * Lưu trận đấu mới
 */
function saveMatch($team1_id, $team2_id, $group_id = null, $round = '', $tournament_id = null) {
    global $pdo;
    
    // Lấy tournament_id từ group nếu không được cung cấp
    if ($tournament_id === null && $group_id !== null) {
        $stmt = $pdo->prepare("SELECT tournament_id FROM `Groups` WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        $tournament_id = $group['tournament_id'] ?? null;
    }
    
    $stmt = $pdo->prepare("INSERT INTO Matches (tournament_id, team1_id, team2_id, group_id, round) VALUES (?,?,?,?,?)");
    $stmt->execute([$tournament_id, $team1_id, $team2_id, $group_id, $round]);
    return $pdo->lastInsertId();
}

/**
 * Cập nhật kết quả trận đấu
 */
function updateMatchScore($matchId, $score1, $score2) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Matches SET score1 = ?, score2 = ? WHERE id = ?");
    return $stmt->execute([$score1, $score2, $matchId]);
}

/**
 * Lấy thông tin trận đấu theo ID
 */
function getMatchById($matchId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name, g.group_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN `Groups` g ON m.group_id = g.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch();
}

/**
 * Lấy tất cả trận đấu
 */
function getAllMatches($groupFilter = null, $tournamentFilter = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               t1.team_name as team1_name, t1.tournament_id as tournament1_id,
               t1.skill_level as skill_level1,
               t1.player1 as team1_player1, t1.player2 as team1_player2,
               t2.team_name as team2_name, t2.tournament_id as tournament2_id,
               t2.skill_level as skill_level2,
               t2.player1 as team2_player1, t2.player2 as team2_player2,
               g.group_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN `Groups` g ON m.group_id = g.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($groupFilter) {
        $sql .= " AND m.group_id = ?";
        $params[] = $groupFilter;
    }
    
    if ($tournamentFilter) {
        $sql .= " AND (t1.tournament_id = ? OR t2.tournament_id = ?)";
        $params[] = $tournamentFilter;
        $params[] = $tournamentFilter;
    }
    
    $sql .= " ORDER BY g.group_name, m.round, m.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Thuật toán Round Robin (vòng tròn) để tạo cặp đấu
 */
function roundRobinPairs($teamIds) {
    $teams = $teamIds;
    $n = count($teams);
    
    // Nếu số đội lẻ, thêm đội BYE
    if ($n % 2 != 0) {
        $teams[] = null; // null đại diện cho BYE
        $n++;
    }
    
    $rounds = $n - 1;
    $matchesPerRound = $n / 2;
    $allMatches = [];
    
    // Tạo vòng đấu đầu tiên
    $fixed = array_shift($teams);
    for ($round = 1; $round <= $rounds; $round++) {
        $roundMatches = [];
        
        // Thêm đội cố định
        array_unshift($teams, $fixed);
        
        // Tạo cặp đấu
        for ($i = 0; $i < $matchesPerRound; $i++) {
            $team1 = $teams[$i];
            $team2 = $teams[$n - 1 - $i];
            
            // Bỏ qua nếu có BYE
            if ($team1 !== null && $team2 !== null) {
                $roundMatches[] = [
                    'round' => $round,
                    'team1' => $team1,
                    'team2' => $team2
                ];
            }
        }
        
        // Xoay vòng tròn
        $last = array_pop($teams);
        array_splice($teams, 1, 0, $last);
        
        $allMatches = array_merge($allMatches, $roundMatches);
    }
    
    return $allMatches;
}

/**
 * Tạo bảng và lịch thi đấu
 */
function createGroupsAndMatches($numGroups = 4, $teamIds = null, $tournamentId = null) {
    global $pdo;
    
    if (!$tournamentId) {
        return false;
    }
    
    // Lấy danh sách đội
    if ($teamIds === null) {
        $teams = fetchTeamsByTournament($tournamentId);
        $teamIds = array_column($teams, 'id');
    }
    
    if (empty($teamIds)) {
        return false;
    }
    
    // Xáo trộn đội nếu cần
    shuffle($teamIds);
    
    // Xóa bảng cũ và trận đấu cũ của giải đấu này
    clearGroupsAndMatches($tournamentId);
    
    // Chia đội vào các bảng
    $teamsPerGroup = ceil(count($teamIds) / $numGroups);
    $groupTeams = array_chunk($teamIds, $teamsPerGroup);
    
    // Tạo bảng và lịch thi đấu cho từng bảng
    foreach ($groupTeams as $index => $teamsInGroup) {
        $groupName = chr(65 + $index); // A, B, C, ...
        $groupId = createGroup($groupName, $tournamentId);
        
        // Tạo lịch thi đấu vòng tròn
        $matches = roundRobinPairs($teamsInGroup);
        
        foreach ($matches as $match) {
            saveMatch($match['team1'], $match['team2'], $groupId, 'Vòng bảng ' . $match['round'], $tournamentId);
        }
    }
    
    return true;
}

/**
 * Tạo vòng loại trực tiếp (Knockout)
 */
function createKnockoutMatches($tournamentId, $numTeams = 4) {
    global $pdo;
    
    // Lấy danh sách đội
    $teams = fetchTeamsByTournament($tournamentId);
    if (count($teams) < 2) return false;
    
    // Xóa dữ liệu cũ
    clearGroupsAndMatches($tournamentId);
    
    // Xáo trộn đội
    shuffle($teams);
    $teams = array_slice($teams, 0, $numTeams);
    
    // Tạo trận loại trực tiếp
    $rounds = [];
    $numRounds = log($numTeams, 2);
    
    $round = 1;
    $matchesInRound = $numTeams / 2;
    
    for ($i = 0; $i < $matchesInRound; $i++) {
        $team1 = $teams[$i * 2] ?? null;
        $team2 = $teams[$i * 2 + 1] ?? null;
        
        if ($team1 && $team2) {
            $stmt = $pdo->prepare("
                INSERT INTO Matches (tournament_id, team1_id, team2_id, round, match_type) 
                VALUES (?, ?, ?, 'Vòng 1', 'knockout')
            ");
            $stmt->execute([$tournamentId, $team1['id'], $team2['id']]);
        }
    }
    
    return true;
}

/**
 * Tạo chia bảng + đánh loại (Group + Knockout)
 */
function createGroupAndKnockout($numGroups, $tournamentId, $knockoutTeams = 4) {
    global $pdo;
    
    // Lấy danh sách đội
    $teams = fetchTeamsByTournament($tournamentId);
    if (count($teams) < 4) return false;
    
    // Xóa dữ liệu cũ
    clearGroupsAndMatches($tournamentId);
    
    // Xáo trộn đội
    shuffle($teams);
    
    // Chia đội vào các bảng
    $teamsPerGroup = ceil(count($teams) / $numGroups);
    $groupTeams = array_chunk($teams, $teamsPerGroup);
    
    // Tạo bảng và trận vòng tròn
    foreach ($groupTeams as $index => $groupTeamList) {
        $groupName = chr(65 + $index);
        $groupId = createGroup($groupName, $tournamentId);
        
        // Tạo trận vòng tròn
        $count = count($groupTeamList);
        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $stmt = $pdo->prepare("
                    INSERT INTO Matches (tournament_id, team1_id, team2_id, group_id, round, match_type) 
                    VALUES (?, ?, ?, ?, 'Vòng bảng', 'group')
                ");
                $stmt->execute([$tournamentId, $groupTeamList[$i]['id'], $groupTeamList[$j]['id'], $groupId]);
            }
        }
    }
    
    return true;
}

/**
 * Tính bảng xếp hạng
 */
function calculateStandings($tournamentFilter = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, g.group_name, 
               t1.tournament_id as t1_tournament_id,
               t2.tournament_id as t2_tournament_id
        FROM `Matches` m
        LEFT JOIN `Groups` g ON m.group_id = g.id
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        WHERE m.group_id IS NOT NULL
    ";
    
    $params = [];
    
    if ($tournamentFilter) {
        $sql .= " AND (t1.tournament_id = ? OR t2.tournament_id = ?)";
        $params[] = $tournamentFilter;
        $params[] = $tournamentFilter;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
    
    $standings = [];
    $teamDetails = [];
    
    // Khởi tạo thống kê cho từng đội trong từng bảng
    foreach ($matches as $m) {
        $g = $m['group_name'];
        $t1 = $m['team1_id'];
        $t2 = $m['team2_id'];
        
        // Khởi tạo bảng nếu chưa có
        if (!isset($standings[$g])) {
            $standings[$g] = [];
        }
        
        // Lấy thông tin đội nếu chưa có
        if (!isset($teamDetails[$t1])) {
            $teamDetails[$t1] = getTeamInfo($t1);
        }
        if (!isset($teamDetails[$t2])) {
            $teamDetails[$t2] = getTeamInfo($t2);
        }
        
        // Khởi tạo thống kê cho đội nếu chưa có
        if (!isset($standings[$g][$t1])) {
            $standings[$g][$t1] = initTeamStats($teamDetails[$t1]);
        }
        if (!isset($standings[$g][$t2])) {
            $standings[$g][$t2] = initTeamStats($teamDetails[$t2]);
        }
        
        // Cập nhật kết quả nếu có
        if ($m['score1'] !== null && $m['score2'] !== null) {
            updateTeamStats($standings[$g][$t1], $m['score1'], $m['score2'], $m['team2_id']);
            updateTeamStats($standings[$g][$t2], $m['score2'], $m['score1'], $m['team1_id']);
        }
    }
    
    // Sắp xếp bảng xếp hạng
    foreach ($standings as $group => &$teams) {
        uasort($teams, 'compareTeams');
    }
    
    return $standings;
}

/**
 * Lấy thông tin đội
 */
function getTeamInfo($teamId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, tr.name as tournament_name 
        FROM `Teams` t
        LEFT JOIN Tournaments tr ON t.tournament_id = tr.id
        WHERE t.id = ?
    ");
    $stmt->execute([$teamId]);
    return $stmt->fetch();
}

/**
 * Khởi tạo thống kê đội
 */
function initTeamStats($team) {
    return [
        'team_id' => $team['id'],
        'team_name' => $team['team_name'],
        'player1' => $team['player1'],
        'player2' => $team['player2'],
        'tournament_id' => $team['tournament_id'],
        'tournament_name' => $team['tournament_name'] ?? null,
        'skill_level' => $team['skill_level'],
        'matches' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'points' => 0,
        'scored' => 0,
        'conceded' => 0,
        'goal_diff' => 0,
        'opponents' => [] // Lưu đối thủ đã đấu
    ];
}

/**
 * Cập nhật thống kê đội
 */
function updateTeamStats(&$teamStats, $scored, $conceded, $opponentId) {
    $teamStats['matches']++;
    $teamStats['scored'] += $scored;
    $teamStats['conceded'] += $conceded;
    $teamStats['goal_diff'] = $teamStats['scored'] - $teamStats['conceded'];
    $teamStats['opponents'][] = $opponentId;
    
    if ($scored > $conceded) {
        $teamStats['wins']++;
        $teamStats['points'] += 3;
    } elseif ($scored < $conceded) {
        $teamStats['losses']++;
    } else {
        $teamStats['draws']++;
        $teamStats['points']++;
    }
}

/**
 * So sánh 2 đội để sắp xếp
 */
function compareTeams($a, $b) {
    // So điểm
    if ($a['points'] != $b['points']) {
        return $b['points'] <=> $a['points'];
    }
    
    // So hiệu số
    if ($a['goal_diff'] != $b['goal_diff']) {
        return $b['goal_diff'] <=> $a['goal_diff'];
    }
    
    // So số bàn thắng
    if ($a['scored'] != $b['scored']) {
        return $b['scored'] <=> $a['scored'];
    }
    
    // So đối đầu trực tiếp (nếu có)
    if (in_array($b['team_id'], $a['opponents'])) {
        $directMatch = getDirectMatchResult($a['team_id'], $b['team_id']);
        if ($directMatch) {
            if ($directMatch['team1_id'] == $a['team_id']) {
                if ($directMatch['score1'] > $directMatch['score2']) return -1;
                if ($directMatch['score1'] < $directMatch['score2']) return 1;
            } else {
                if ($directMatch['score2'] > $directMatch['score1']) return -1;
                if ($directMatch['score2'] < $directMatch['score1']) return 1;
            }
        }
    }
    
    return strcmp($a['team_name'], $b['team_name']);
}

/**
 * Lấy kết quả đối đầu trực tiếp
 */
function getDirectMatchResult($team1Id, $team2Id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM `Matches` 
        WHERE (team1_id = ? AND team2_id = ?) 
           OR (team1_id = ? AND team2_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$team1Id, $team2Id, $team2Id, $team1Id]);
    return $stmt->fetch();
}

/**
 * Lấy top 2 mỗi bảng
 */
function getQualifiedTop2($tournamentFilter = null) {
    $standings = calculateStandings($tournamentFilter);
    $qualified = [];
    
    foreach ($standings as $group => $teams) {
        $teamIds = array_keys($teams);
        if (count($teamIds) >= 1) $qualified[] = $teamIds[0];
        if (count($teamIds) >= 2) $qualified[] = $teamIds[1];
    }
    
    return $qualified;
}

/**
 * Tạo vòng loại trực tiếp
 */
function createKnockoutStage($qualifiedTeams = null) {
    global $pdo;
    
    if ($qualifiedTeams === null) {
        $qualifiedTeams = getQualifiedTop2();
    }
    
    if (empty($qualifiedTeams)) {
        return false;
    }
    
    // Xóa các trận đấu vòng loại cũ
    $pdo->exec("DELETE FROM `Matches` WHERE group_id IS NULL");
    
    // Xáo trộn đội để tạo cặp đấu
    shuffle($qualifiedTeams);
    
    // Tạo cặp đấu
    $matches = [];
    for ($i = 0; $i < count($qualifiedTeams); $i += 2) {
        if (isset($qualifiedTeams[$i + 1])) {
            $matchId = saveMatch($qualifiedTeams[$i], $qualifiedTeams[$i + 1], null, 'Vòng 1/8');
            $matches[] = $matchId;
        }
    }
    
    return $matches;
}

/**
 * Lấy tất cả giải đấu
 */
function getAllTournaments() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM `Tournaments` ORDER BY created_at DESC, name");
    return $stmt->fetchAll();
}

/**
 * Lấy số liệu thống kê giải đấu
 */
function getTournamentStats($tournamentId = null) {
    global $pdo;
    
    $stats = [
        'total_teams' => 0,
        'total_matches' => 0,
        'completed_matches' => 0,
        'groups' => 0,
        'skill_levels' => []
    ];
    
    // Thống kê đội
    $sql = "SELECT COUNT(*) as count, skill_level FROM `Teams`";
    if ($tournamentId) {
        $sql .= " WHERE tournament_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tournamentId]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $teamStats = $stmt->fetchAll();
    $stats['total_teams'] = array_sum(array_column($teamStats, 'count'));
    
    // Thống kê trình độ
    foreach ($teamStats as $row) {
        if ($row['skill_level']) {
            $stats['skill_levels'][$row['skill_level']] = $row['count'];
        }
    }
    
    // Thống kê trận đấu
    $sql = "
        SELECT COUNT(*) as total,
               SUM(CASE WHEN score1 IS NOT NULL AND score2 IS NOT NULL THEN 1 ELSE 0 END) as completed
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
    ";
    
    if ($tournamentId) {
        $sql .= " WHERE t1.tournament_id = ? OR t2.tournament_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tournamentId, $tournamentId]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $matchStats = $stmt->fetch();
    $stats['total_matches'] = $matchStats['total'] ?? 0;
    $stats['completed_matches'] = $matchStats['completed'] ?? 0;
    
    // Thống kê số bảng
    $sql = "SELECT COUNT(DISTINCT group_id) as groups FROM `Matches` WHERE group_id IS NOT NULL";
    if ($tournamentId) {
        $sql = "
            SELECT COUNT(DISTINCT m.group_id) as groups
            FROM `Matches` m
            LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
            LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
            WHERE (t1.tournament_id = ? OR t2.tournament_id = ?) AND m.group_id IS NOT NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tournamentId, $tournamentId]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $groupStats = $stmt->fetch();
    $stats['groups'] = $groupStats['groups'] ?? 0;
    
    return $stats;
}

/**
 * Tạo dữ liệu mẫu
 */
if (!function_exists('createSampleData')) {
    function createSampleData($tournamentId = null) {
        global $pdo;
        
        try {
            // Bắt đầu transaction
            $pdo->beginTransaction();
            
            if ($tournamentId) {
                // Xóa dữ liệu cũ của giải đấu
                $pdo->prepare("DELETE FROM `Matches` WHERE tournament_id = ?")->execute([$tournamentId]);
                $pdo->prepare("DELETE FROM `Teams` WHERE tournament_id = ?")->execute([$tournamentId]);
                $pdo->prepare("DELETE FROM `Groups` WHERE tournament_id = ?")->execute([$tournamentId]);
                
                // Lấy thông tin giải đấu
                $stmt = $pdo->prepare("SELECT * FROM `Tournaments` WHERE id = ?");
                $stmt->execute([$tournamentId]);
                $tournament = $stmt->fetch();
                
                if (!$tournament) {
                    throw new Exception("Không tìm thấy giải đấu");
                }
                
                // Tạo đội mẫu
                $sampleTeams = [
                    ['Đội A', 'player1,player2', 'Advanced'],
                    ['Đội B', 'player3,player4', 'Intermediate'],
                    ['Đội C', 'player5,player6', 'Beginner'],
                    ['Đội D', 'player7,player8', 'Advanced'],
                    ['Đội E', 'player9,player10', 'Intermediate'],
                    ['Đội F', 'player11,player12', 'Beginner'],
                ];
                
                $teamIds = [];
                foreach ($sampleTeams as $team) {
                    $stmt = $pdo->prepare("INSERT INTO Teams (tournament_id, name, players, skill_level) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tournamentId, $team[0], $team[1], $team[2]]);
                    $teamIds[] = $pdo->lastInsertId();
                }
                
                // Tạo bảng đấu mẫu
                $groupNames = ['A', 'B'];
                $groupIds = [];
                
                foreach ($groupNames as $groupName) {
                    $stmt = $pdo->prepare("INSERT INTO Groups (tournament_id, group_name) VALUES (?, ?)");
                    $stmt->execute([$tournamentId, $groupName]);
                    $groupIds[] = $pdo->lastInsertId();
                }
                
                // Phân chia đội vào các bảng
                $teamsPerGroup = floor(count($teamIds) / count($groupIds));
                $teamGroups = [];
                
                for ($i = 0; $i < count($teamIds); $i++) {
                    $groupId = $groupIds[$i % count($groupIds)];
                    $teamGroups[$groupId][] = $teamIds[$i];
                }
                
                // Tạo trận đấu mẫu
                foreach ($teamGroups as $groupId => $groupTeams) {
                    $groupTeams = array_slice($groupTeams, 0, 3); // Giới hạn 3 đội mỗi bảng
                    
                    for ($i = 0; $i < count($groupTeams); $i++) {
                        for ($j = $i + 1; $j < count($groupTeams); $j++) {
                            $stmt = $pdo->prepare("
                                INSERT INTO Matches (tournament_id, team1_id, team2_id, group_id, round, match_date, court)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $matchDate = date('Y-m-d H:i:s', strtotime('+' . (($i * 3 + $j) * 2) . ' hours'));
                            $round = "Vòng bảng";
                            $court = "Sân " . (($i + $j) % 3 + 1);
                            
                            $stmt->execute([
                                $tournamentId,
                                $groupTeams[$i],
                                $groupTeams[$j],
                                $groupId,
                                $round,
                                $matchDate,
                                $court
                            ]);
                        }
                    }
                }
                
                // Tạo trận đấu loại trực tiếp mẫu
                $knockoutRounds = ['Tứ kết', 'Bán kết', 'Chung kết'];
                
                foreach ($knockoutRounds as $index => $round) {
                    $stmt = $pdo->prepare("
                        INSERT INTO Matches (tournament_id, team1_id, team2_id, round, match_type, match_date, court)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $matchDate = date('Y-m-d H:i:s', strtotime('+' . (($index + 6) * 2) . ' hours'));
                    $court = "Sân chính";
                    
                    // Tạo trận đấu với team mẫu
                    $stmt->execute([
                        $tournamentId,
                        $teamIds[0] ?? null,
                        $teamIds[1] ?? null,
                        $round,
                        'knockout',
                        $matchDate,
                        $court
                    ]);
                }
                
                $pdo->commit();
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Lỗi tạo dữ liệu mẫu: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Tạo user admin
 */
function createAdminUser() {
    global $pdo;
    
    // Kiểm tra xem admin đã tồn tại chưa
    $stmt = $pdo->prepare("SELECT id FROM `Users` WHERE username = 'admin'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $passwordHash, 'Quản Trị Viên', 'admin']);
    }
}

/**
 * Xác thực user
 */
function authenticateUser($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM `Users` WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Lấy thông tin user theo ID
 */
function getUserById($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM `Users` WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}



/**
 * Kiểm tra quyền admin
 */
function isAdmin($userId) {
    $user = getUserById($userId);
    return $user && $user['role'] === 'admin';
}

/**
 * Format số cho hiển thị
 */
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

/**
 * Lấy icon cho trình độ
 */
function getSkillLevelIcon($skillLevel) {
    $icons = [
        '2.5' => 'fas fa-star-half-alt',
        '3.0' => 'fas fa-star',
        '3.5' => 'fas fa-star',
        '4.0' => 'fas fa-star',
        '4.5' => 'fas fa-star',
        '5.0' => 'fas fa-crown',
        'Open' => 'fas fa-trophy'
    ];
    
    return $icons[$skillLevel] ?? 'fas fa-user';
}

/**
 * Lấy thông tin giải đấu theo ID
 */
function getTournamentById($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM `Tournaments` WHERE id = ?");
    $stmt->execute([$tournamentId]);
    return $stmt->fetch();
}

/**
 * Lấy bảng đấu theo giải đấu
 */
function getGroupsByTournament($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT g.*, t.name as tournament_name 
        FROM `Groups` g
        LEFT JOIN Tournaments t ON g.tournament_id = t.id
        WHERE g.tournament_id = ? 
        ORDER BY g.group_name
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

/**
 * Lấy trận đấu theo giải đấu
 */
function getMatchesByTournament($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
               t1.team_name as team1_name, 
               t2.team_name as team2_name,
               g.group_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN `Groups` g ON m.group_id = g.id
        WHERE t1.tournament_id = ? OR t2.tournament_id = ?
        ORDER BY m.round, m.id
    ");
    $stmt->execute([$tournamentId, $tournamentId]);
    return $stmt->fetchAll();
}

/**
 * Kiểm tra user có quyền quản lý giải đấu không
 */
function canManageTournament($user_id, $tournament_id, $user_role) {
    global $pdo;
    
    if ($user_role === 'admin') {
        return true;
    }
    
    if ($user_role === 'manager') {
        // Kiểm tra xem user có phải là owner hoặc được phân quyền quản lý không
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM `Tournaments` t 
            LEFT JOIN TournamentManagers tm ON t.id = tm.tournament_id
            WHERE t.id = ? AND (t.owner_id = ? OR tm.user_id = ?)
        ");
        $stmt->execute([$tournament_id, $user_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }
     
    return false;
}

/**
 * Lấy thông tin user
 */
function getUserInfo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM `Users` WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Đăng ký user mới (phiên bản mở rộng)
 */
function registerUser($username, $password, $displayName = '', $email = '', $phone = '', $role = 'user') {
    global $pdo;
    
    // Kiểm tra username đã tồn tại chưa
    $stmt = $pdo->prepare("SELECT id FROM `Users` WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        return false; // Username đã tồn tại
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO Users (username, password, display_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([$username, $passwordHash, $displayName, $email, $phone, $role]);
}

/**
 * Lấy danh sách Arena
 */
function getAllArenas() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM Arena ORDER BY status, name");
    return $stmt->fetchAll();
}

/**
 * Lấy Arena theo ID
 */
function getArenaById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Arena WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Lấy danh sách trọng tài (referee)
 */
function getAllReferees() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, username, display_name FROM `Users` WHERE role = 'referee' ORDER BY username");
    return $stmt->fetchAll();
}

/**
 * Lấy danh sách manager
 */
function getAllManagers() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, username, display_name FROM `Users` WHERE role = 'manager' ORDER BY username");
    return $stmt->fetchAll();
}

/**
 * Lấy danh sách phân công trọng tài
 */
function getAllRefereeAssignments() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT ra.*, m.id as match_id, 
               t1.team_name as team1_name, t2.team_name as team2_name,
               u.username as referee_username, u.display_name as referee_name
        FROM RefereeAssignments ra
        JOIN Matches m ON ra.match_id = m.id
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        JOIN Users u ON ra.referee_id = u.id
        ORDER BY ra.assigned_at DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Lấy danh sách Tournament Managers
 */
function getTournamentManagers($tournament_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT tm.*, u.username, u.display_name 
        FROM TournamentManagers tm 
        JOIN Users u ON tm.user_id = u.id 
        WHERE tm.tournament_id = ?
    ");
    $stmt->execute([$tournament_id]);
    return $stmt->fetchAll();
}

/**
 * Lấy các trận đấu loại trực tiếp
 */
function getKnockoutMatches($tournamentId = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               t1.team_name as team1_name, 
               t2.team_name as team2_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        WHERE m.group_id IS NULL
    ";
    
    if ($tournamentId) {
        $sql .= " AND (t1.tournament_id = ? OR t2.tournament_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tournamentId, $tournamentId]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    return $stmt->fetchAll();
}
/**
 * Lấy phân công trọng tài theo ID
 */
function getRefereeAssignmentById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM RefereeAssignments WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Thêm phân công trọng tài
 */
function addRefereeAssignment($match_id, $referee_id, $assignment_type = 'main', $status = 'assigned') {
    global $pdo;
    
    // Kiểm tra đã phân công chưa
    $check = $pdo->prepare("SELECT id FROM RefereeAssignments WHERE match_id = ? AND referee_id = ?");
    $check->execute([$match_id, $referee_id]);
    
    if ($check->fetch()) {
        return false; // Đã phân công rồi
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO RefereeAssignments (match_id, referee_id, assignment_type, status) 
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$match_id, $referee_id, $assignment_type, $status]);
}

/**
 * Cập nhật phân công trọng tài
 */
function updateRefereeAssignment($id, $assignment_type, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE RefereeAssignments 
        SET assignment_type = ?, status = ? 
        WHERE id = ?
    ");
    
    return $stmt->execute([$assignment_type, $status, $id]);
}

/**
 * Kiểm tra xem user có phải là trọng tài không
 */
function isReferee($user_id) {
    $user = getUserById($user_id);
    return $user && $user['role'] === 'referee';
}

/**
 * Kiểm tra xem user có phải là manager không
 */
function isManager($user_id) {
    $user = getUserById($user_id);
    return $user && $user['role'] === 'manager';
}

/**
 * Lấy tất cả matches để phân công trọng tài
 */
function getMatchesForRefereeAssignment($tournament_id = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               t1.team_name as team1_name, 
               t2.team_name as team2_name,
               tr.name as tournament_name,
               tr.id as tournament_id
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($tournament_id) {
        $sql .= " AND m.tournament_id = ?";
        $params[] = $tournament_id;
    }
    
    $sql .= " ORDER BY m.match_date DESC, m.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Kiểm tra trọng tài đã được phân công cho trận đấu chưa
 */
function isRefereeAssignedToMatch($match_id, $referee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id FROM RefereeAssignments 
        WHERE match_id = ? AND referee_id = ?
    ");
    $stmt->execute([$match_id, $referee_id]);
    return $stmt->fetch() !== false;
}

/**
 * Lấy số lượng phân công của trọng tài
 */
function getRefereeAssignmentCount($referee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM RefereeAssignments 
        WHERE referee_id = ?
    ");
    $stmt->execute([$referee_id]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

/**
 * Cập nhật trạng thái completed_at khi hoàn thành phân công
 */
function completeRefereeAssignment($assignment_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE RefereeAssignments 
        SET status = 'completed', completed_at = NOW() 
        WHERE id = ?
    ");
    return $stmt->execute([$assignment_id]);
}

/**
 * Lấy tất cả trận đấu chưa có trọng tài chính
 */
function getMatchesWithoutMainReferee($tournament_id = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        WHERE m.id NOT IN (
            SELECT match_id FROM RefereeAssignments 
            WHERE assignment_type = 'main' AND status != 'cancelled'
        )
    ";
    
    $params = [];
    
    if ($tournament_id) {
        $sql .= " AND m.tournament_id = ?";
        $params[] = $tournament_id;
    }
    
    $sql .= " ORDER BY m.match_date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Lấy thông tin trận đấu chi tiết cho trọng tài
 */
function getMatchDetailsForReferee($match_id, $referee_id = null) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               t1.team_name as team1_name, t1.player1 as team1_player1, t1.player2 as team1_player2,
               t2.team_name as team2_name, t2.player1 as team2_player1, t2.player2 as team2_player2,
               tr.name as tournament_name, tr.location as tournament_location,
               a.name as arena_name, a.location as arena_location
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        LEFT JOIN Arena a ON m.arena_id = a.id
        WHERE m.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();
    
    if ($match && $referee_id) {
        // Lấy thông tin phân công của trọng tài này
        $sql = "
            SELECT ra.*, u.display_name as referee_name
            FROM RefereeAssignments ra
            LEFT JOIN Users u ON ra.referee_id = u.id
            WHERE ra.match_id = ? AND ra.referee_id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$match_id, $referee_id]);
        $assignment = $stmt->fetch();
        
        if ($assignment) {
            $match['referee_assignment'] = $assignment;
        }
    }
    
    return $match;
}

/**
 * Lấy lịch sử phân công của trọng tài
 */
function getRefereeAssignmentHistory($referee_id, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ra.*, m.id as match_id, 
               t1.team_name as team1_name, t2.team_name as team2_name,
               tr.name as tournament_name,
               m.score1, m.score2, m.match_date, m.court
        FROM RefereeAssignments ra
        JOIN Matches m ON ra.match_id = m.id
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        WHERE ra.referee_id = ?
        ORDER BY ra.assigned_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$referee_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Kiểm tra xem trọng tài có quyền cập nhật kết quả trận đấu không
 */
function canUpdateMatchScore($match_id, $referee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ra.id 
        FROM RefereeAssignments ra
        JOIN Matches m ON ra.match_id = m.id
        WHERE ra.match_id = ? 
          AND ra.referee_id = ? 
          AND ra.status = 'assigned'
          AND (m.score1 IS NULL OR m.score2 IS NULL)
    ");
    
    $stmt->execute([$match_id, $referee_id]);
    return $stmt->fetch() !== false;
}

/**
 * Lấy thống kê trọng tài
 */
function getRefereeStatistics($referee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN assignment_type = 'main' THEN 1 ELSE 0 END) as main_referee,
            SUM(CASE WHEN assignment_type = 'assistant' THEN 1 ELSE 0 END) as assistant_referee
        FROM RefereeAssignments 
        WHERE referee_id = ?
    ");
    
    $stmt->execute([$referee_id]);
    return $stmt->fetch();
}

/**
 * Gửi thông báo cho trọng tài về phân công mới
 */
function notifyRefereeNewAssignment($referee_id, $match_id) {
    // Lấy thông tin trọng tài
    $referee = getUserById($referee_id);
    $match = getMatchById($match_id);
    
    if (!$referee || !$match) {
        return false;
    }
    
    // Gửi email thông báo (nếu có email)
    if (!empty($referee['email'])) {
        $to = $referee['email'];
        $subject = "Thông báo phân công trọng tài mới - TRỌNG TÀI SỐ";
        $message = "
        Xin chào " . ($referee['display_name'] ?: $referee['username']) . ",
        
        Bạn vừa được phân công làm trọng tài cho trận đấu:
        
        Trận đấu: {$match['team1_name']} vs {$match['team2_name']}
        Vòng: {$match['round']}
        
        Vui lòng kiểm tra lịch thi đấu và có mặt đúng giờ.
        
        Trân trọng,
        Ban tổ chức TRỌNG TÀI SỐ
        ";
        
        // Gửi email (cần cấu hình mail server)
        // mail($to, $subject, $message);
    }
    
    // Có thể thêm gửi SMS hoặc thông báo trong hệ thống
    
    return true;
}

/**
 * Cập nhật điểm và xác định đội thắng
 */
function updateMatchScoreAndWinner($match_id, $score1, $score2, $referee_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Cập nhật điểm
        $stmt = $pdo->prepare("UPDATE Matches SET score1 = ?, score2 = ? WHERE id = ?");
        $stmt->execute([$score1, $score2, $match_id]);
        
        // Xác định đội thắng
        $match = getMatchById($match_id);
        $winner_id = null;
        
        if ($score1 > $score2) {
            $winner_id = $match['team1_id'];
        } elseif ($score2 > $score1) {
            $winner_id = $match['team2_id'];
        }
        
        if ($winner_id) {
            $stmt = $pdo->prepare("UPDATE Matches SET winner_id = ? WHERE id = ?");
            $stmt->execute([$winner_id, $match_id]);
        }
        
        // Nếu có referee_id, cập nhật trạng thái phân công
        if ($referee_id) {
            $stmt = $pdo->prepare("
                UPDATE RefereeAssignments 
                SET status = 'completed', completed_at = NOW() 
                WHERE match_id = ? AND referee_id = ?
            ");
            $stmt->execute([$match_id, $referee_id]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Lỗi cập nhật điểm: " . $e->getMessage());
        return false;
    }
}

/**
 * Xóa phân công trọng tài
 */
function deleteRefereeAssignment($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM RefereeAssignments WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Lấy phân công trọng tài theo trận đấu
 */
function getRefereeAssignmentsByMatch($match_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ra.*, u.username, u.display_name 
        FROM RefereeAssignments ra
        JOIN Users u ON ra.referee_id = u.id
        WHERE ra.match_id = ?
        ORDER BY ra.assignment_type
    ");
    $stmt->execute([$match_id]);
    return $stmt->fetchAll();
}

/**
 * Lấy phân công trọng tài theo trọng tài
 */
function getRefereeAssignmentsByReferee($referee_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ra.*, m.id as match_id, 
               t1.team_name as team1_name, t2.team_name as team2_name,
               tr.name as tournament_name
        FROM RefereeAssignments ra
        JOIN Matches m ON ra.match_id = m.id
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN Tournaments tr ON m.tournament_id = tr.id
        WHERE ra.referee_id = ?
        ORDER BY ra.assigned_at DESC
    ");
    $stmt->execute([$referee_id]);
    return $stmt->fetchAll();
}

/**
 * Cập nhật giai đoạn giải đấu
 */
function updateTournamentStage($tournamentId, $stage) {
    global $pdo;
    $validStages = ['planning', 'registration', 'setup', 'group_stage', 'knockout_stage', 'completed'];
    if (!in_array($stage, $validStages)) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE Tournaments SET stage = ? WHERE id = ?");
    return $stmt->execute([$stage, $tournamentId]);
}

/**
 * Lấy danh sách hạng mục của giải đấu
 */
function getTournamentCategories($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM TournamentCategories WHERE tournament_id = ? ORDER BY name");
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

/**
 * Tạo hạng mục mới cho giải đấu
 */
function createTournamentCategory($tournamentId, $name, $gender = 'all', $skillMin = 0, $skillMax = 5.5, $maxTeams = 16) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO TournamentCategories (tournament_id, name, gender, skill_min, skill_max, max_teams) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$tournamentId, $name, $gender, $skillMin, $skillMax, $maxTeams]);
}

/**
 * Lấy thông tin hạng mục
 */
function getCategoryById($categoryId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM TournamentCategories WHERE id = ?");
    $stmt->execute([$categoryId]);
    return $stmt->fetch();
}

/**
 * Lấy lịch thi đấu theo ngày
 */
function getTournamentSchedule($tournamentId, $date = null) {
    global $pdo;
    if ($date) {
        $stmt = $pdo->prepare("
            SELECT s.*, m.team1_id, m.team2_id, m.score1, m.score2, m.status as match_status,
                   t1.team_name as team1_name, t2.team_name as team2_name,
                   a.name as court_name
            FROM TournamentSchedule s
            LEFT JOIN Matches m ON s.match_id = m.id
            LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
            LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
            LEFT JOIN Arena a ON s.court_id = a.id
            WHERE s.tournament_id = ? AND s.scheduled_date = ?
            ORDER BY s.scheduled_time
        ");
        $stmt->execute([$tournamentId, $date]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, m.team1_id, m.team2_id, m.score1, m.score2, m.status as match_status,
                   t1.team_name as team1_name, t2.team_name as team2_name,
                   a.name as court_name
            FROM TournamentSchedule s
            LEFT JOIN Matches m ON s.match_id = m.id
            LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
            LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
            LEFT JOIN Arena a ON s.court_id = a.id
            WHERE s.tournament_id = ?
            ORDER BY s.scheduled_date, s.scheduled_time
        ");
        $stmt->execute([$tournamentId]);
    }
    return $stmt->fetchAll();
}

/**
 * Thêm lịch thi đấu
 */
function addTournamentSchedule($tournamentId, $matchId, $courtId, $scheduledTime, $scheduledDate) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO TournamentSchedule (tournament_id, match_id, court_id, scheduled_time, scheduled_date) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$tournamentId, $matchId, $courtId, $scheduledTime, $scheduledDate]);
}

/**
 * Lấy bảng xếp hạng vòng bảng
 */
if (!function_exists('getGroupStandings')) {
function getGroupStandings($groupId) {
    global $pdo;
    
    $teams = $pdo->prepare("SELECT * FROM `Teams` WHERE group_name = ? ORDER BY seed");
    $teams->execute([$groupId]);
    $teams = $teams->fetchAll();
    
    $standings = [];
    foreach ($teams as $team) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as matches,
                SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN team1_id = ? THEN score1 ELSE score2 END) as points_for,
                SUM(CASE WHEN team1_id = ? THEN score2 ELSE score1 END) as points_against
            FROM `Matches` 
            WHERE (team1_id = ? OR team2_id = ?) AND status = 'completed'
        ");
        $stmt->execute([$team['id'], $team['id'], $team['id'], $team['id'], $team['id'], $team['id']]);
        $stats = $stmt->fetch();
        
        $standings[] = [
            'team' => $team,
            'matches' => $stats['matches'] ?? 0,
            'wins' => $stats['wins'] ?? 0,
            'losses' => $stats['losses'] ?? 0,
            'points_for' => $stats['points_for'] ?? 0,
            'points_against' => $stats['points_against'] ?? 0,
            'points' => (($stats['wins'] ?? 0) * 3) + ($stats['losses'] ?? 0)
        ];
    }
    
    usort($standings, function($a, $b) {
        if ($b['points'] != $a['points']) return $b['points'] - $a['points'];
        return ($b['points_for'] - $b['points_against']) - ($a['points_for'] - $a['points_against']);
    });
    
    return $standings;
}
}

/**
 * Lấy trận đấu tiếp theo theo lịch
 */
function getNextScheduledMatch($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.*, m.team1_id, m.team2_id,
               t1.team_name as team1_name, t2.team_name as team2_name,
               a.name as court_name
        FROM TournamentSchedule s
        JOIN Matches m ON s.match_id = m.id
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN Arena a ON s.court_id = a.id
        WHERE s.tournament_id = ? AND s.status = 'scheduled'
        ORDER BY s.scheduled_date, s.scheduled_time
        LIMIT 1
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetch();
}

/**
 * Chuyển giai đoạn giải đấu
 */
function advanceTournamentStage($tournamentId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT stage FROM `Tournaments` WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    
    $stageOrder = [
        'planning' => 'registration',
        'registration' => 'setup',
        'setup' => 'group_stage',
        'group_stage' => 'knockout_stage',
        'knockout_stage' => 'completed'
    ];
    
    $currentStage = $tournament['stage'] ?? 'planning';
    $nextStage = $stageOrder[$currentStage] ?? $currentStage;
    
    $update = $pdo->prepare("UPDATE Tournaments SET stage = ? WHERE id = ?");
    $update->execute([$nextStage, $tournamentId]);
    
    return $nextStage;
}

/**
 * Lấy tất cả các trận đấu đang chờ của giải đấu
 */
function getPendingMatches($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name,
               g.group_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN `Groups` g ON m.group_id = g.id
        WHERE m.tournament_id = ? AND m.status = 'pending'
        ORDER BY m.round, m.match_date
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

/**
 * Hiển thị tên đội với format VĐV1 / VĐV2
 */
function displayTeamPlayers($team) {
    $p1 = $team['player1'] ?? '';
    $p2 = $team['player2'] ?? '';
    
    if (empty($p1) && empty($p2)) {
        return $team['team_name'] ?? 'Chưa có VĐV';
    }
    
    return nl2br(htmlspecialchars($p1 . "\nVÀ\n" . $p2));
}

/**
 * Hiển thị tên VĐV đậm, to, rõ ràng
 */
function renderTeamPlayers($team, $class = '', $style = '') {
    $p1 = $team['player1'] ?? '';
    $p2 = $team['player2'] ?? '';
    
    $style = $style ?: 'font-weight: 700; font-size: 1.1rem; line-height: 1.3;';
    
    if (empty($p1) && empty($p2)) {
        return '<div class="' . $class . '" style="' . $style . '">' . htmlspecialchars($team['team_name'] ?? 'Chưa có VĐV') . '</div>';
    }
    
    return '<div class="' . $class . '" style="' . $style . '">
        <strong>' . htmlspecialchars($p1) . '</strong><br>
        <span style="font-weight: 400; font-size: 0.85rem; color: #64748b;">VÀ</span><br>
        <strong>' . htmlspecialchars($p2) . '</strong>
    </div>';
}
function getCompletedMatches($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name,
               g.group_name
        FROM `Matches` m
        LEFT JOIN `Teams` t1 ON m.team1_id = t1.id
        LEFT JOIN `Teams` t2 ON m.team2_id = t2.id
        LEFT JOIN `Groups` g ON m.group_id = g.id
        WHERE m.tournament_id = ? AND m.status = 'completed'
        ORDER BY m.completed_at DESC
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}
?>