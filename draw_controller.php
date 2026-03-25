<?php
// draw_controller.php
require_once 'functions.php';

class DrawController {
    private $pdo;
    private $message = '';
    private $excelData = [];
    private $previewMode = false;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest() {
        session_start();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
        
        // Lấy tab active từ session
        $activeTab = $_SESSION['activeDrawTab'] ?? 'import';
        
        return [
            'message' => $this->message,
            'excelData' => $this->excelData,
            'previewMode' => $this->previewMode,
            'teams' => $this->fetchAllTeams(),
            'tournaments' => $this->getAllTournaments(),
            'groups' => $this->getAllGroupsWithTeams(),
            'stats' => $this->getStatistics(),
            'activeTab' => $activeTab
        ];
    }

    private function handlePostRequest() {
        // Import CSV
        if (isset($_FILES['excel']) && $_FILES['excel']['error'] === UPLOAD_ERR_OK) {
            $_SESSION['activeDrawTab'] = 'import';
            $this->handleFileUpload();
        } 
        // Confirm import
        elseif (isset($_POST['confirm_import']) && isset($_POST['teams_data'])) {
            $_SESSION['activeDrawTab'] = 'teams';
            $this->handleImport();
        } 
        // Bốc thăm
        elseif (isset($_POST['action']) && $_POST['action'] === 'draw') {
            $_SESSION['activeDrawTab'] = 'groups';
            $this->handleDraw();
        } 
        // Tạo dữ liệu mẫu
        elseif (isset($_POST['action']) && $_POST['action'] === 'create_sample') {
            $this->handleCreateSample();
        } 
        // Xóa dữ liệu
        elseif (isset($_POST['action']) && $_POST['action'] === 'clear_data') {
            $this->handleClearData($_POST['data_type']);
        }
        // Xuất dữ liệu
        elseif (isset($_GET['export'])) {
            $this->handleExport($_GET['export'], $_GET['format'] ?? 'csv');
        }
    }

    private function handleFileUpload() {
        $tmp = $_FILES['excel']['tmp_name'];
        $fileType = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
        
        if ($fileType !== 'csv') {
            $this->message = $this->createAlert('danger', 'Chỉ hỗ trợ file CSV!', 'exclamation-circle');
            return;
        }
        
        $this->excelData = $this->parseCSVFile($tmp);
        
        if (empty($this->excelData)) {
            $this->message = $this->createAlert('warning', 'File không có dữ liệu hợp lệ!', 'exclamation-triangle');
        } else {
            $this->previewMode = true;
            $this->message = $this->createAlert('info', 
                'Đã load ' . count($this->excelData) . ' đội từ file. Kiểm tra và xác nhận import.', 
                'info-circle'
            );
        }
    }

    private function parseCSVFile($filePath) {
        $data = [];
        $row = 0;
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $isFirstRow = true;
            
            while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                
                // Skip empty rows
                if (empty(trim(implode('', $rowData)))) {
                    continue;
                }
                
                // Check if first row is header
                if ($isFirstRow) {
                    $firstCell = strtolower(trim($rowData[0]));
                    if (in_array($firstCell, ['team_name', 'tên đội', 'team', 'đội'])) {
                        $isFirstRow = false;
                        continue;
                    }
                }
                
                // Ensure we have at least team name
                if (!empty(trim($rowData[0]))) {
                    $teamName = trim($rowData[0]);
                    $player1 = trim($rowData[1] ?? '');
                    $player2 = trim($rowData[2] ?? '');
                    $tournamentName = trim($rowData[3] ?? '');
                    $skillLevel = trim($rowData[4] ?? '');
                    
                    // Tìm tournament_id từ tên giải đấu
                    $tournamentId = null;
                    if (!empty($tournamentName)) {
                        $stmt = $this->pdo->prepare("SELECT id FROM Tournaments WHERE name LIKE ?");
                        $stmt->execute(["%$tournamentName%"]);
                        $tournament = $stmt->fetch();
                        $tournamentId = $tournament['id'] ?? null;
                        
                        // Nếu không tìm thấy, tạo giải đấu mới
                        if (!$tournamentId) {
                            $stmt = $this->pdo->prepare("INSERT INTO Tournaments (name, format, status) VALUES (?, 'combined', 'upcoming')");
                            $stmt->execute([$tournamentName]);
                            $tournamentId = $this->pdo->lastInsertId();
                        }
                    }
                    
                    $data[] = [
                        'row_num' => $row,
                        'team_name' => $teamName,
                        'player1' => $player1,
                        'player2' => $player2,
                        'tournament_name' => $tournamentName,
                        'tournament_id' => $tournamentId,
                        'skill_level' => $skillLevel,
                    ];
                }
            }
            fclose($handle);
        }
        
        return $data;
    }

    private function handleImport() {
        $teamsData = json_decode($_POST['teams_data'], true);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($teamsData as $index => $team) {
            // Kiểm tra trùng tên đội trong cùng giải đấu
            $checkStmt = $this->pdo->prepare("SELECT id FROM Teams WHERE team_name = ? AND tournament_id = ?");
            $checkStmt->execute([$team['team_name'], $team['tournament_id'] ?? null]);
            
            if ($checkStmt->fetch()) {
                $skipped++;
                $errors[] = "Dòng {$index}: Đội '{$team['team_name']}' đã tồn tại";
                continue;
            }
            
            try {
                // Insert đội mới
                $stmt = $this->pdo->prepare("INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) VALUES (?,?,?,?,?)");
                $stmt->execute([
                    $team['team_name'],
                    $team['player1'],
                    $team['player2'],
                    $team['tournament_id'] ?? null,
                    $team['skill_level'] ?? null
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Dòng {$index}: " . $e->getMessage();
                $skipped++;
            }
        }
        
        $message = "Import thành công! $imported đội đã được thêm, $skipped đội bị bỏ qua.";
        
        if (!empty($errors)) {
            $message .= " Lỗi: " . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= "... (và " . (count($errors) - 3) . " lỗi khác)";
            }
        }
        
        $this->message = $this->createAlert('success', $message, 'check-circle');
    }

    private function handleDraw() {
        $numGroups = intval($_POST['num_groups'] ?? 4);
        $tournamentId = intval($_POST['tournament_filter'] ?? 0);
        $drawMethod = $_POST['draw_method'] ?? 'random'; // random, seeded, manual
        
        if ($numGroups < 1) $numGroups = 4;
        
        if ($tournamentId <= 0) {
            $this->message = $this->createAlert('warning', 'Vui lòng chọn giải đấu!', 'exclamation-triangle');
            return;
        }
        
        // Kiểm tra giải đấu có tồn tại không
        $checkTournament = $this->pdo->prepare("SELECT id, name FROM Tournaments WHERE id = ?");
        $checkTournament->execute([$tournamentId]);
        $tournament = $checkTournament->fetch();
        
        if (!$tournament) {
            $this->message = $this->createAlert('danger', 'Giải đấu không tồn tại!', 'times-circle');
            return;
        }
        
        // Lấy danh sách đội của giải đấu
        $teamsStmt = $this->pdo->prepare("SELECT * FROM Teams WHERE tournament_id = ?");
        $teamsStmt->execute([$tournamentId]);
        $teams = $teamsStmt->fetchAll();
        
        if (count($teams) < $numGroups) {
            $this->message = $this->createAlert('warning', 
                "Số đội ({$teams}) ít hơn số bảng ({$numGroups}). Mỗi bảng cần ít nhất 1 đội.", 
                'exclamation-triangle'
            );
            return;
        }
        
        try {
            // Xóa bảng cũ của giải đấu này
            $this->pdo->beginTransaction();
            
            // Xóa matches trước
            $deleteMatches = $this->pdo->prepare("
                DELETE m FROM Matches m 
                JOIN Groups g ON m.group_id = g.id 
                WHERE g.tournament_id = ?
            ");
            $deleteMatches->execute([$tournamentId]);
            
            // Xóa groups
            $deleteGroups = $this->pdo->prepare("DELETE FROM Groups WHERE tournament_id = ?");
            $deleteGroups->execute([$tournamentId]);
            
            // Chia đội vào các bảng
            $groups = $this->distributeTeamsToGroups($teams, $numGroups, $drawMethod);
            
            // Tạo bảng và matches
            $groupNames = range('A', 'Z');
            foreach ($groups as $index => $groupTeams) {
                $groupName = $groupNames[$index] ?? ('Bảng ' . ($index + 1));
                
                // Tạo bảng
                $stmt = $this->pdo->prepare("INSERT INTO Groups (group_name, tournament_id) VALUES (?, ?)");
                $stmt->execute([$groupName, $tournamentId]);
                $groupId = $this->pdo->lastInsertId();
                
                // Tạo các trận đấu vòng tròn
                $this->createRoundRobinMatches($groupTeams, $groupId, $tournamentId);
            }
            
            $this->pdo->commit();
            
            $this->message = $this->createAlert('success', 
                "Đã bốc thăm thành công! Tạo {$numGroups} bảng đấu cho giải '{$tournament['name']}' với {$drawMethod} draw.", 
                'check-circle'
            );
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->message = $this->createAlert('danger', 'Lỗi bốc thăm: ' . $e->getMessage(), 'times-circle');
        }
    }

    private function distributeTeamsToGroups($teams, $numGroups, $method = 'random') {
        // Xáo trộn đội
        shuffle($teams);
        
        // Chia đều vào các bảng
        $groups = array_fill(0, $numGroups, []);
        $groupIndex = 0;
        
        foreach ($teams as $team) {
            $groups[$groupIndex][] = $team;
            $groupIndex = ($groupIndex + 1) % $numGroups;
        }
        
        return $groups;
    }

    private function createRoundRobinMatches($teams, $groupId, $tournamentId) {
        $count = count($teams);
        
        if ($count < 2) return;
        
        // Tạo tất cả các cặp đấu
        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO Matches (tournament_id, team1_id, team2_id, group_id, round, status) 
                    VALUES (?, ?, ?, ?, 1, 'scheduled')
                ");
                $stmt->execute([$tournamentId, $teams[$i]['id'], $teams[$j]['id'], $groupId]);
            }
        }
    }

    private function handleCreateSample() {
        try {
            // Tạo 3 giải đấu mẫu
            $tournaments = [
                ['BMB Super Cup 2024', 'combined', 'upcoming'],
                ['Giải Mixed Doubles', 'mixed', 'upcoming'],
                ['Weekend Giao Lưu', 'social', 'ongoing']
            ];
            
            $tournamentIds = [];
            foreach ($tournaments as $tournament) {
                $stmt = $this->pdo->prepare("INSERT INTO Tournaments (name, format, status) VALUES (?, ?, ?)");
                $stmt->execute($tournament);
                $tournamentIds[] = $this->pdo->lastInsertId();
            }
            
            // Tạo 24 đội mẫu
            $sampleTeams = [];
            $players = [
                'Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C', 'Phạm Thị D',
                'Hoàng Văn E', 'Vũ Thị F', 'Đặng Văn G', 'Bùi Thị H',
                'Mai Văn I', 'Lý Thị J', 'Trịnh Văn K', 'Đỗ Thị L'
            ];
            
            $skillLevels = ['2.5', '3.0', '3.5', '4.0', '4.5', '5.0'];
            
            for ($i = 1; $i <= 24; $i++) {
                $tournamentId = $tournamentIds[array_rand($tournamentIds)];
                $player1 = $players[array_rand($players)];
                $player2 = $players[array_rand($players)];
                $skill = $skillLevels[array_rand($skillLevels)];
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO Teams (team_name, player1, player2, tournament_id, skill_level) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $teamName = "Team" . str_pad($i, 2, '0', STR_PAD_LEFT);
                $stmt->execute([$teamName, $player1, $player2, $tournamentId, $skill]);
            }
            
            $this->message = $this->createAlert('success', 
                'Đã tạo dữ liệu mẫu: 3 giải đấu và 24 đội thi!', 
                'check-circle'
            );
            
        } catch (Exception $e) {
            $this->message = $this->createAlert('danger', 
                'Lỗi tạo dữ liệu mẫu: ' . $e->getMessage(), 
                'times-circle'
            );
        }
    }

    private function handleClearData($dataType) {
        try {
            $this->pdo->beginTransaction();
            
            switch ($dataType) {
                case 'teams':
                    $this->pdo->exec("DELETE FROM Teams");
                    $message = "Đã xóa tất cả đội";
                    break;
                    
                case 'groups':
                    $this->pdo->exec("DELETE FROM Matches");
                    $this->pdo->exec("DELETE FROM Groups");
                    $message = "Đã xóa tất cả bảng và trận đấu";
                    break;
                    
                case 'matches':
                    $this->pdo->exec("DELETE FROM Matches");
                    $message = "Đã xóa tất cả trận đấu";
                    break;
                    
                case 'all':
                    $this->pdo->exec("DELETE FROM Matches");
                    $this->pdo->exec("DELETE FROM Groups");
                    $this->pdo->exec("DELETE FROM Teams");
                    $this->pdo->exec("DELETE FROM Tournaments");
                    $message = "Đã xóa tất cả dữ liệu";
                    break;
                    
                default:
                    throw new Exception("Loại dữ liệu không hợp lệ");
            }
            
            $this->pdo->commit();
            $this->message = $this->createAlert('success', $message, 'check-circle');
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->message = $this->createAlert('danger', 'Lỗi xóa dữ liệu: ' . $e->getMessage(), 'times-circle');
        }
    }

    private function handleExport($type, $format = 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $type . '_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        switch ($type) {
            case 'teams':
                fputcsv($output, ['Tên đội', 'VĐV 1', 'VĐV 2', 'Giải đấu', 'Trình độ', 'Bảng']);
                $teams = $this->fetchAllTeams();
                foreach ($teams as $team) {
                    fputcsv($output, [
                        $team['team_name'],
                        $team['player1'],
                        $team['player2'],
                        $team['tournament_name'] ?? '',
                        $team['skill_level'] ?? '',
                        $team['group_name'] ?? ''
                    ]);
                }
                break;
                
            case 'groups':
                fputcsv($output, ['Giải đấu', 'Tên bảng', 'Số đội', 'Đội 1', 'Đội 2', 'Đội 3', 'Đội 4']);
                $groups = $this->getAllGroupsWithTeams();
                foreach ($groups as $group) {
                    $teams = array_column($group['teams'], 'team_name');
                    fputcsv($output, array_merge(
                        [$group['tournament_name'], $group['group_name'], count($teams)],
                        $teams
                    ));
                }
                break;
                
            case 'matches':
                fputcsv($output, ['Giải đấu', 'Bảng', 'Đội 1', 'Đội 2', 'Tỉ số', 'Trạng thái', 'Thời gian']);
                $matches = $this->pdo->query("
                    SELECT t1.team_name as team1, t2.team_name as team2, 
                           g.group_name, tn.name as tournament_name,
                           m.score1, m.score2, m.status, m.match_date
                    FROM Matches m
                    JOIN Teams t1 ON m.team1_id = t1.id
                    JOIN Teams t2 ON m.team2_id = t2.id
                    JOIN Groups g ON m.group_id = g.id
                    JOIN Tournaments tn ON g.tournament_id = tn.id
                    ORDER BY tn.name, g.group_name
                ")->fetchAll();
                
                foreach ($matches as $match) {
                    fputcsv($output, [
                        $match['tournament_name'],
                        $match['group_name'],
                        $match['team1'],
                        $match['team2'],
                        $match['score1'] . '-' . $match['score2'],
                        $match['status'],
                        $match['match_date']
                    ]);
                }
                break;
        }
        
        fclose($output);
        exit;
    }

    private function fetchAllTeams() {
        return $this->pdo->query("
            SELECT t.*, tn.name as tournament_name, g.group_name 
            FROM Teams t 
            LEFT JOIN Tournaments tn ON t.tournament_id = tn.id 
            LEFT JOIN (
                SELECT DISTINCT m.group_id, t.id as team_id, g.group_name
                FROM Matches m
                JOIN Teams t ON (t.id = m.team1_id OR t.id = m.team2_id)
                JOIN Groups g ON m.group_id = g.id
            ) g ON t.id = g.team_id
            ORDER BY tn.name, t.team_name
        ")->fetchAll();
    }

    private function getAllTournaments() {
        return $this->pdo->query("SELECT * FROM Tournaments ORDER BY status, name")->fetchAll();
    }

    private function getAllGroupsWithTeams() {
        $groups = $this->pdo->query("
            SELECT g.*, t.name as tournament_name, 
                   COUNT(DISTINCT CASE WHEN m.team1_id IS NOT NULL THEN m.team1_id END) + 
                   COUNT(DISTINCT CASE WHEN m.team2_id IS NOT NULL THEN m.team2_id END) as team_count
            FROM Groups g
            LEFT JOIN Tournaments t ON g.tournament_id = t.id
            LEFT JOIN Matches m ON g.id = m.group_id
            GROUP BY g.id
            ORDER BY t.name, g.group_name
        ")->fetchAll();
        
        foreach ($groups as &$group) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT t.* 
                FROM Teams t
                JOIN Matches m ON (t.id = m.team1_id OR t.id = m.team2_id)
                WHERE m.group_id = ?
                ORDER BY t.team_name
            ");
            $stmt->execute([$group['id']]);
            $group['teams'] = $stmt->fetchAll();
        }
        
        return $groups;
    }

    private function getStatistics() {
        return [
            'totalTeams' => $this->pdo->query("SELECT COUNT(*) FROM Teams")->fetchColumn(),
            'totalTournaments' => $this->pdo->query("SELECT COUNT(*) FROM Tournaments")->fetchColumn(),
            'totalGroups' => $this->pdo->query("SELECT COUNT(*) FROM Groups")->fetchColumn(),
            'totalMatches' => $this->pdo->query("SELECT COUNT(*) FROM Matches")->fetchColumn()
        ];
    }

    private function createAlert($type, $message, $icon = 'info-circle') {
        return '
        <div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
            <i class="fas fa-' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }

    public function getTournamentBadgeClass($tournamentName) {
        if (!$tournamentName) return 'bg-secondary';
        
        $tournamentName = strtolower($tournamentName);
        if (strpos($tournamentName, 'super cup') !== false) return 'bg-primary';
        if (strpos($tournamentName, 'mixed') !== false) return 'bg-success';
        if (strpos($tournamentName, 'giao lưu') !== false || strpos($tournamentName, 'weekend') !== false) return 'bg-warning';
        
        return 'bg-info';
    }
}
?>