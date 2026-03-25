<?php
// admin.php - Trang quản trị hệ thống
require_once 'functions.php';

// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// Kiểm tra quyền: chỉ admin và manager được phép truy cập admin
if ($user_role !== 'admin' && $user_role !== 'manager') {
    header("Location: index.php");
    exit;
}

// Lấy thông tin user hiện tại
$current_user = getUserInfo($user_id);

// Xử lý các action từ URL
$action = $_GET['action'] ?? 'dashboard';
$message = '';
$success = '';

// Xử lý các POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ========== CREATE TOURNAMENT ==========
    if (isset($_POST['create_tournament'])) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $format = $_POST['format'] ?? 'combined';
        $location = $_POST['location'] ?? '';
        $status = $_POST['status'] ?? 'upcoming';
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        
        if ($name) {
            // Manager chỉ có thể tạo giải đấu cho chính mình
            $owner_id = ($user_role === 'admin') ? ($_POST['owner_id'] ?? $user_id) : $user_id;
            
            $stmt = $pdo->prepare("
                INSERT INTO Tournaments (name, description, format, location, status, start_date, end_date, owner_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $description, $format, $location, $status, $startDate, $endDate, $owner_id])) {
                $tournament_id = $pdo->lastInsertId();
                
                // Nếu là manager, tự động thêm vào bảng quản lý
                if ($user_role === 'manager') {
                    $stmt = $pdo->prepare("
                        INSERT INTO TournamentManagers (tournament_id, user_id, permission_level) 
                        VALUES (?, ?, 'full')
                    ");
                    $stmt->execute([$tournament_id, $user_id]);
                }
                
                $success = "Tạo giải đấu '$name' thành công!";
            } else {
                $message = "Lỗi khi tạo giải đấu!";
            }
        }
    }
    
    // ========== UPDATE TOURNAMENT ==========
    elseif (isset($_POST['update_tournament'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra quyền truy cập
        if (!canManageTournament($user_id, $id, $user_role)) {
            $message = "Bạn không có quyền chỉnh sửa giải đấu này!";
        } else {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $format = $_POST['format'] ?? 'combined';
            $location = $_POST['location'] ?? '';
            $status = $_POST['status'] ?? 'upcoming';
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            $owner_id = ($user_role === 'admin') ? ($_POST['owner_id'] ?? null) : null;
            
            $sql = "UPDATE Tournaments SET name = ?, description = ?, format = ?, location = ?, status = ?, start_date = ?, end_date = ?";
            $params = [$name, $description, $format, $location, $status, $startDate, $endDate];
            
            if ($owner_id !== null) {
                $sql .= ", owner_id = ?";
                $params[] = $owner_id;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = "Cập nhật giải đấu thành công!";
            } else {
                $message = "Lỗi khi cập nhật giải đấu!";
            }
        }
    }
    
    // ========== DELETE TOURNAMENT ==========
    elseif (isset($_POST['delete_tournament'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra quyền truy cập
        if (!canManageTournament($user_id, $id, $user_role)) {
            $message = "Bạn không có quyền xóa giải đấu này!";
        } else {
            // Kiểm tra xem giải đấu có đội không
            $check = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id = ?");
            $check->execute([$id]);
            $hasTeams = $check->fetchColumn();
            
            if ($hasTeams > 0) {
                $message = "Không thể xóa giải đấu vì đã có đội tham gia!";
            } else {
                // Xóa các bản ghi liên quan
                $pdo->prepare("DELETE FROM TournamentManagers WHERE tournament_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM Groups WHERE tournament_id = ?")->execute([$id]);
                
                $stmt = $pdo->prepare("DELETE FROM Tournaments WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = "Xóa giải đấu thành công!";
                } else {
                    $message = "Lỗi khi xóa giải đấu!";
                }
            }
        }
    }
    
    // ========== CREATE USER ==========
    elseif (isset($_POST['create_user'])) {
        // Chỉ admin mới được tạo user mới
        if ($user_role !== 'admin') {
            $message = "Chỉ admin mới được tạo user mới!";
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $displayName = $_POST['display_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $role = $_POST['role'] ?? 'user';
            
            if ($username && $password) {
                $result = registerUser($username, $password, $displayName, $email, $phone, $role);
                if ($result) {
                    $success = "Tạo người dùng '$username' thành công!";
                } else {
                    $message = "Lỗi: Tên đăng nhập đã tồn tại!";
                }
            }
        }
    }
    
    // ========== UPDATE USER ==========
    elseif (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        
        // Chỉ admin hoặc chính user đó mới được sửa thông tin
        if ($user_role !== 'admin' && $id != $user_id) {
            $message = "Bạn không có quyền chỉnh sửa user này!";
        } else {
            $displayName = $_POST['display_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Chỉ admin mới được thay đổi role
            $role = ($user_role === 'admin') ? ($_POST['role'] ?? 'user') : null;
            
            if ($password) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($role !== null) {
                    $stmt = $pdo->prepare("UPDATE Users SET display_name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$displayName, $email, $phone, $role, $passwordHash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE Users SET display_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                    $stmt->execute([$displayName, $email, $phone, $passwordHash, $id]);
                }
            } else {
                if ($role !== null) {
                    $stmt = $pdo->prepare("UPDATE Users SET display_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
                    $stmt->execute([$displayName, $email, $phone, $role, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE Users SET display_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$displayName, $email, $phone, $id]);
                }
            }
            $success = "Cập nhật người dùng thành công!";
        }
    }
    
    // ========== DELETE USER ==========
    elseif (isset($_POST['delete_user'])) {
        // Chỉ admin mới được xóa user
        if ($user_role !== 'admin') {
            $message = "Chỉ admin mới được xóa user!";
        } else {
            $id = intval($_POST['id']);
            
            // Không cho xóa admin chính
            if ($id == $user_id) {
                $message = "Không thể xóa chính tài khoản admin của bạn!";
            } else {
                // Xóa các bản ghi liên quan
                $pdo->prepare("DELETE FROM TournamentManagers WHERE user_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM RefereeAssignments WHERE referee_id = ?")->execute([$id]);
                
                $stmt = $pdo->prepare("DELETE FROM Users WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = "Xóa người dùng thành công!";
                } else {
                    $message = "Lỗi khi xóa người dùng!";
                }
            }
        }
    }
    
    // ========== CREATE ARENA ==========
    elseif (isset($_POST['create_arena'])) {
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $capacity = $_POST['capacity'] ?? null;
        $status = $_POST['status'] ?? 'available';
        $description = $_POST['description'] ?? '';
        
        if ($name) {
            $stmt = $pdo->prepare("
                INSERT INTO Arena (name, location, capacity, status, description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $location, $capacity, $status, $description])) {
                $success = "Tạo sân thi đấu '$name' thành công!";
            } else {
                $message = "Lỗi khi tạo sân thi đấu!";
            }
        }
    }
    
    // ========== UPDATE ARENA ==========
    elseif (isset($_POST['update_arena'])) {
        $id = intval($_POST['id']);
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $capacity = $_POST['capacity'] ?? null;
        $status = $_POST['status'] ?? 'available';
        $description = $_POST['description'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE Arena 
            SET name = ?, location = ?, capacity = ?, status = ?, description = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$name, $location, $capacity, $status, $description, $id])) {
            $success = "Cập nhật sân thi đấu thành công!";
        } else {
            $message = "Lỗi khi cập nhật sân thi đấu!";
        }
    }
    
    // ========== DELETE ARENA ==========
    elseif (isset($_POST['delete_arena'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra xem sân có đang được sử dụng không
        $check = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE arena_id = ?");
        $check->execute([$id]);
        $isUsed = $check->fetchColumn();
        
        if ($isUsed > 0) {
            $message = "Không thể xóa sân vì đã có trận đấu sử dụng sân này!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Arena WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Xóa sân thi đấu thành công!";
            } else {
                $message = "Lỗi khi xóa sân thi đấu!";
            }
        }
    }
    
    // ========== ADD TOURNAMENT MANAGER ==========
    elseif (isset($_POST['add_tournament_manager'])) {
        $tournament_id = intval($_POST['tournament_id']);
        $user_id_to_add = intval($_POST['user_id']);
        $permission_level = $_POST['permission_level'] ?? 'limited';
        
        // Kiểm tra quyền
        if (!canManageTournament($user_id, $tournament_id, $user_role)) {
            $message = "Bạn không có quyền thêm manager cho giải đấu này!";
        } else {
            // Kiểm tra xem user có tồn tại không
            $check = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
            $check->execute([$user_id_to_add]);
            $user = $check->fetch();
            
            if (!$user) {
                $message = "User không tồn tại!";
            } elseif ($user['role'] !== 'manager') {
                $message = "Chỉ có thể thêm user có role 'manager' vào quản lý giải đấu!";
            } else {
                // Kiểm tra xem đã có trong bảng chưa
                $check = $pdo->prepare("SELECT COUNT(*) FROM TournamentManagers WHERE tournament_id = ? AND user_id = ?");
                $check->execute([$tournament_id, $user_id_to_add]);
                $exists = $check->fetchColumn();
                
                if ($exists > 0) {
                    $message = "User đã là manager của giải đấu này!";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO TournamentManagers (tournament_id, user_id, permission_level) 
                        VALUES (?, ?, ?)
                    ");
                    if ($stmt->execute([$tournament_id, $user_id_to_add, $permission_level])) {
                        $success = "Thêm manager thành công!";
                    } else {
                        $message = "Lỗi khi thêm manager!";
                    }
                }
            }
        }
    }
    
    // ========== REMOVE TOURNAMENT MANAGER ==========
    elseif (isset($_POST['remove_tournament_manager'])) {
        $id = intval($_POST['id']);
        
        // Lấy tournament_id từ manager record
        $stmt = $pdo->prepare("SELECT tournament_id FROM TournamentManagers WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        
        if ($record && canManageTournament($user_id, $record['tournament_id'], $user_role)) {
            $stmt = $pdo->prepare("DELETE FROM TournamentManagers WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Xóa manager thành công!";
            } else {
                $message = "Lỗi khi xóa manager!";
            }
        } else {
            $message = "Bạn không có quyền xóa manager này!";
        }
    }
    
    // ========== ASSIGN REFEREE ==========
    elseif (isset($_POST['assign_referee'])) {
        $match_id = intval($_POST['match_id']);
        $referee_id = intval($_POST['referee_id']);
        $assignment_type = $_POST['assignment_type'] ?? 'main';
        
        // Kiểm tra quyền
        if ($user_role !== 'admin' && $user_role !== 'manager') {
            $message = "Bạn không có quyền phân công trọng tài!";
        } else {
            // Kiểm tra xem trọng tài có tồn tại và có role referee không
            $check = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
            $check->execute([$referee_id]);
            $user = $check->fetch();
            
            if (!$user) {
                $message = "Trọng tài không tồn tại!";
            } elseif ($user['role'] !== 'referee') {
                $message = "Chỉ có thể phân công user có role 'referee' làm trọng tài!";
            } else {
                // Kiểm tra xem đã được phân công chưa
                $check = $pdo->prepare("SELECT COUNT(*) FROM RefereeAssignments WHERE match_id = ? AND referee_id = ?");
                $check->execute([$match_id, $referee_id]);
                $exists = $check->fetchColumn();
                
                if ($exists > 0) {
                    $message = "Trọng tài đã được phân công cho trận đấu này!";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO RefereeAssignments (match_id, referee_id, assignment_type) 
                        VALUES (?, ?, ?)
                    ");
                    if ($stmt->execute([$match_id, $referee_id, $assignment_type])) {
                        $success = "Phân công trọng tài thành công!";
                    } else {
                        $message = "Lỗi khi phân công trọng tài!";
                    }
                }
            }
        }
    }
    
    // ========== UPDATE REFEREE ASSIGNMENT ==========
    elseif (isset($_POST['update_referee_assignment'])) {
        $id = intval($_POST['id']);
        $assignment_type = $_POST['assignment_type'] ?? 'main';
        $status = $_POST['status'] ?? 'assigned';
        
        $stmt = $pdo->prepare("
            UPDATE RefereeAssignments 
            SET assignment_type = ?, status = ?, completed_at = ?
            WHERE id = ?
        ");
        
        $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        
        if ($stmt->execute([$assignment_type, $status, $completed_at, $id])) {
            $success = "Cập nhật phân công trọng tài thành công!";
        } else {
            $message = "Lỗi khi cập nhật phân công trọng tài!";
        }
    }
    
    // ========== DELETE REFEREE ASSIGNMENT ==========
    elseif (isset($_POST['delete_referee_assignment'])) {
        $id = intval($_POST['id']);
        
        $stmt = $pdo->prepare("DELETE FROM RefereeAssignments WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Xóa phân công trọng tài thành công!";
        } else {
            $message = "Lỗi khi xóa phân công trọng tài!";
        }
    }
    
    // ========== DELETE TEAM ==========
    elseif (isset($_POST['delete_team'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra quyền
        $stmt = $pdo->prepare("SELECT tournament_id FROM Teams WHERE id = ?");
        $stmt->execute([$id]);
        $team = $stmt->fetch();
        
        if ($team && canManageTournament($user_id, $team['tournament_id'], $user_role)) {
            // Xóa các trận đấu liên quan trước
            $stmt = $pdo->prepare("DELETE FROM Matches WHERE team1_id = ? OR team2_id = ?");
            $stmt->execute([$id, $id]);
            
            // Xóa các phân công trọng tài liên quan
            $stmt = $pdo->prepare("DELETE FROM RefereeAssignments WHERE match_id IN (SELECT id FROM Matches WHERE team1_id = ? OR team2_id = ?)");
            $stmt->execute([$id, $id]);
            
            // Xóa đội
            $stmt = $pdo->prepare("DELETE FROM Teams WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Xóa đội thành công!";
            } else {
                $message = "Lỗi khi xóa đội!";
            }
        } else {
            $message = "Bạn không có quyền xóa đội này!";
        }
    }
    
    // ========== DELETE GROUP ==========
    elseif (isset($_POST['delete_group'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra quyền
        $stmt = $pdo->prepare("SELECT tournament_id FROM Groups WHERE id = ?");
        $stmt->execute([$id]);
        $group = $stmt->fetch();
        
        if ($group && canManageTournament($user_id, $group['tournament_id'], $user_role)) {
            // Xóa các trận đấu trong bảng
            $stmt = $pdo->prepare("DELETE FROM Matches WHERE group_id = ?");
            $stmt->execute([$id]);
            
            // Xóa các phân công trọng tài liên quan
            $stmt = $pdo->prepare("DELETE FROM RefereeAssignments WHERE match_id IN (SELECT id FROM Matches WHERE group_id = ?)");
            $stmt->execute([$id]);
            
            // Xóa bảng
            $stmt = $pdo->prepare("DELETE FROM Groups WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Xóa bảng đấu thành công!";
            } else {
                $message = "Lỗi khi xóa bảng đấu!";
            }
        } else {
            $message = "Bạn không có quyền xóa bảng đấu này!";
        }
    }
    
    // ========== DELETE MATCH ==========
    elseif (isset($_POST['delete_match'])) {
        $id = intval($_POST['id']);
        
        // Kiểm tra quyền
        $stmt = $pdo->prepare("SELECT tournament_id FROM Matches WHERE id = ?");
        $stmt->execute([$id]);
        $match = $stmt->fetch();
        
        if ($match && canManageTournament($user_id, $match['tournament_id'], $user_role)) {
            // Xóa phân công trọng tài trước
            $stmt = $pdo->prepare("DELETE FROM RefereeAssignments WHERE match_id = ?");
            $stmt->execute([$id]);
            
            // Xóa trận đấu
            $stmt = $pdo->prepare("DELETE FROM Matches WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Xóa trận đấu thành công!";
            } else {
                $message = "Lỗi khi xóa trận đấu!";
            }
        } else {
            $message = "Bạn không có quyền xóa trận đấu này!";
        }
    }
    
    // ========== CLEAR ALL DATA ==========
    elseif (isset($_POST['clear_all_data'])) {
        // Chỉ admin mới được xóa toàn bộ dữ liệu
        if ($user_role !== 'admin') {
            $message = "Chỉ admin mới được xóa toàn bộ dữ liệu!";
        } else {
            $confirm = $_POST['confirm'] ?? '';
            if ($confirm === 'DELETE_ALL') {
                // Xóa tất cả dữ liệu (trừ admin user)
                $pdo->exec("DELETE FROM RefereeAssignments");
                $pdo->exec("DELETE FROM TournamentManagers");
                $pdo->exec("DELETE FROM Matches");
                $pdo->exec("DELETE FROM Groups");
                $pdo->exec("DELETE FROM Teams");
                $pdo->exec("DELETE FROM Tournaments WHERE id > 0");
                $pdo->exec("DELETE FROM Arena WHERE id > 0");
                $pdo->exec("DELETE FROM Users WHERE role != 'admin'");
                
                $success = "Đã xóa toàn bộ dữ liệu thành công!";
            } else {
                $message = "Xác nhận không đúng!";
            }
        }
    }
}

// ========== LẤY DỮ LIỆU ==========

// Lấy dữ liệu cho dashboard
$totalUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
$totalTournaments = $pdo->query("SELECT COUNT(*) FROM Tournaments")->fetchColumn();
$totalTeams = $pdo->query("SELECT COUNT(*) FROM Teams")->fetchColumn();
$totalMatches = $pdo->query("SELECT COUNT(*) FROM Matches")->fetchColumn();
$totalGroups = $pdo->query("SELECT COUNT(*) FROM Groups")->fetchColumn();
$totalArenas = $pdo->query("SELECT COUNT(*) FROM Arena")->fetchColumn();
$totalRefereeAssignments = $pdo->query("SELECT COUNT(*) FROM RefereeAssignments")->fetchColumn();

// Lấy dữ liệu cho các trang
$users = $pdo->query("SELECT * FROM Users ORDER BY role DESC, username")->fetchAll();

// Lấy giải đấu với điều kiện quyền
if ($user_role === 'admin') {
    $tournaments = $pdo->query("SELECT t.*, u.username as owner_name FROM Tournaments t LEFT JOIN Users u ON t.owner_id = u.id ORDER BY t.created_at DESC")->fetchAll();
} else {
    // Manager chỉ xem được giải đấu mình sở hữu hoặc được quản lý
    $tournaments = $pdo->query("
        SELECT DISTINCT t.*, u.username as owner_name 
        FROM Tournaments t 
        LEFT JOIN Users u ON t.owner_id = u.id
        LEFT JOIN TournamentManagers tm ON t.id = tm.tournament_id
        WHERE t.owner_id = ? OR tm.user_id = ?
        ORDER BY t.created_at DESC
    ", [$user_id, $user_id])->fetchAll();
}

$teams = $pdo->query("SELECT t.*, tr.name as tournament_name FROM Teams t LEFT JOIN Tournaments tr ON t.tournament_id = tr.id ORDER BY t.created_at DESC")->fetchAll();
$matches = $pdo->query("SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name, g.group_name, a.name as arena_name FROM Matches m LEFT JOIN Teams t1 ON m.team1_id = t1.id LEFT JOIN Teams t2 ON m.team2_id = t2.id LEFT JOIN Groups g ON m.group_id = g.id LEFT JOIN Arena a ON m.arena_id = a.id ORDER BY m.created_at DESC")->fetchAll();
$groups = $pdo->query("SELECT g.*, t.name as tournament_name FROM Groups g LEFT JOIN Tournaments t ON g.tournament_id = t.id ORDER BY g.created_at DESC")->fetchAll();
$arenas = $pdo->query("SELECT * FROM Arena ORDER BY status, name")->fetchAll();

// Lấy danh sách manager cho từng giải đấu
$tournament_managers = [];
foreach ($tournaments as $tournament) {
    $stmt = $pdo->prepare("
        SELECT tm.*, u.username, u.display_name 
        FROM TournamentManagers tm 
        JOIN Users u ON tm.user_id = u.id 
        WHERE tm.tournament_id = ?
    ");
    $stmt->execute([$tournament['id']]);
    $tournament_managers[$tournament['id']] = $stmt->fetchAll();
}

// Lấy danh sách phân công trọng tài
$referee_assignments = $pdo->query("
    SELECT ra.*, m.id as match_id, 
           t1.team_name as team1_name, t2.team_name as team2_name,
           u.username as referee_username, u.display_name as referee_name
    FROM RefereeAssignments ra
    JOIN Matches m ON ra.match_id = m.id
    LEFT JOIN Teams t1 ON m.team1_id = t1.id
    LEFT JOIN Teams t2 ON m.team2_id = t2.id
    JOIN Users u ON ra.referee_id = u.id
    ORDER BY ra.assigned_at DESC
")->fetchAll();

// Lấy bản ghi cụ thể cho edit
$editData = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    if ($action === 'users') {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    } elseif ($action === 'tournaments') {
        $stmt = $pdo->prepare("SELECT * FROM Tournaments WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    } elseif ($action === 'arenas') {
        $stmt = $pdo->prepare("SELECT * FROM Arena WHERE id = ?");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch();
    }
}

// ========== HELPER FUNCTIONS ==========
function canManageTournament($user_id, $tournament_id, $user_role) {
    global $pdo;
    
    if ($user_role === 'admin') {
        return true;
    }
    
    if ($user_role === 'manager') {
        // Kiểm tra xem user có phải là owner hoặc được phân quyền quản lý không
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM Tournaments t 
            LEFT JOIN TournamentManagers tm ON t.id = tm.tournament_id
            WHERE t.id = ? AND (t.owner_id = ? OR tm.user_id = ?)
        ");
        $stmt->execute([$tournament_id, $user_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    return false;
}

function getUserInfo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function registerUser($username, $password, $displayName = '', $email = '', $phone = '', $role = 'user') {
    global $pdo;
    
    // Kiểm tra username đã tồn tại chưa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        return false;
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO Users (username, password, display_name, email, phone, role) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$username, $passwordHash, $displayName, $email, $phone, $role]);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2ecc71;
            --accent: #ff6b00;
            --text-dark: #1e293b;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --manager: #9b59b6;
            --referee: #3498db;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            z-index: 1000;
            padding-top: 20px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .user-info {
            padding: 0 25px 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 10px;
        }
        
        .user-name {
            font-weight: 600;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .user-role {
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .nav-link-admin {
            display: block;
            padding: 15px 25px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .nav-link-admin:hover, .nav-link-admin.active {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .nav-link-admin i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .admin-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-bottom: 4px solid var(--primary);
        }
        
        .admin-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .admin-subtitle {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-top: 4px solid var(--primary);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stats-card.users {
            border-top-color: var(--primary);
        }
        
        .stats-card.tournaments {
            border-top-color: var(--accent);
        }
        
        .stats-card.teams {
            border-top-color: var(--info);
        }
        
        .stats-card.matches {
            border-top-color: var(--warning);
        }
        
        .stats-card.arenas {
            border-top-color: var(--manager);
        }
        
        .stats-card.referees {
            border-top-color: var(--referee);
        }
        
        .stats-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 1rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .stats-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn-admin {
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-admin-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-admin-primary:hover {
            background: #27ae60;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-admin-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-admin-danger:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-admin-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-admin-warning:hover {
            background: #e0a800;
            color: #212529;
            transform: translateY(-2px);
        }
        
        .btn-admin-manager {
            background: var(--manager);
            color: white;
        }
        
        .btn-admin-manager:hover {
            background: #8e44ad;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-admin-referee {
            background: var(--referee);
            color: white;
        }
        
        .btn-admin-referee:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .form-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary);
        }
        
        .alert-admin {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--primary);
            color: #155724;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger);
            color: #721c24;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .badge-admin {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-admin-primary {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
        }
        
        .badge-admin-success {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
        }
        
        .badge-admin-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .badge-admin-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        .badge-admin-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .badge-admin-manager {
            background: rgba(155, 89, 182, 0.1);
            color: var(--manager);
        }
        
        .badge-admin-referee {
            background: rgba(52, 152, 219, 0.1);
            color: var(--referee);
        }
        
        .permission-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .logo span {
                display: none;
            }
            
            .user-info {
                display: none;
            }
            
            .nav-link-admin span {
                display: none;
            }
            
            .nav-link-admin i {
                margin-right: 0;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .nav-link-admin {
                display: inline-block;
                padding: 10px 15px;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .nav-link-admin:hover, .nav-link-admin.active {
                border-left: none;
                border-bottom-color: var(--primary);
            }
            
            .stats-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-user-shield"></i>
            <span>TRỌNG TÀI SỐ ADMIN</span>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($current_user['display_name'] ?? $current_user['username']); ?></div>
            <div class="user-role">
                <span class="badge-admin 
                    <?php 
                    if ($user_role === 'admin') echo 'badge-admin-danger';
                    elseif ($user_role === 'manager') echo 'badge-admin-manager';
                    elseif ($user_role === 'referee') echo 'badge-admin-referee';
                    else echo 'badge-admin-info';
                    ?>
                ">
                    <?php echo ucfirst($user_role); ?>
                </span>
            </div>
        </div>
        
        <nav class="nav flex-column">
            <a href="?action=dashboard" class="nav-link-admin <?php echo $action === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <?php if ($user_role === 'admin'): ?>
            <a href="?action=users" class="nav-link-admin <?php echo $action === 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Quản lý User</span>
            </a>
            <?php endif; ?>
            
            <a href="?action=tournaments" class="nav-link-admin <?php echo $action === 'tournaments' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                <span>Quản lý Giải đấu</span>
            </a>
            
            <a href="?action=teams" class="nav-link-admin <?php echo $action === 'teams' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Quản lý Đội</span>
            </a>
            
            <a href="?action=groups" class="nav-link-admin <?php echo $action === 'groups' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i>
                <span>Quản lý Bảng đấu</span>
            </a>
            
            <a href="?action=matches" class="nav-link-admin <?php echo $action === 'matches' ? 'active' : ''; ?>">
                <i class="fas fa-basketball-ball"></i>
                <span>Quản lý Trận đấu</span>
            </a>
            
            <a href="?action=arenas" class="nav-link-admin <?php echo $action === 'arenas' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i>
                <span>Quản lý Sân đấu</span>
            </a>
            
            <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <a href="?action=referee_assignments" class="nav-link-admin <?php echo $action === 'referee_assignments' ? 'active' : ''; ?>">
                <i class="fas fa-whistle"></i>
                <span>Phân công Trọng tài</span>
            </a>
            <?php endif; ?>
            
            <?php if ($user_role === 'admin'): ?>
            <a href="?action=settings" class="nav-link-admin <?php echo $action === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Cài đặt hệ thống</span>
            </a>
            <?php endif; ?>
            
            <?php if ($user_role === 'referee'): ?>
            <a href="referee_dashboard.php" class="nav-link-admin">
                <i class="fas fa-whistle"></i>
                <span>Trang Trọng tài</span>
            </a>
            <?php endif; ?>
            
            <a href="index.php" class="nav-link-admin">
                <i class="fas fa-home"></i>
                <span>Về trang chủ</span>
            </a>
            
            <a href="logout.php" class="nav-link-admin">
                <i class="fas fa-sign-out-alt"></i>
                <span>Đăng xuất</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="admin-header">
            <h1 class="admin-title">
                <i class="fas fa-user-shield me-2"></i>
                <?php 
                $titles = [
                    'dashboard' => 'Dashboard - Tổng quan hệ thống',
                    'users' => 'Quản lý Người dùng',
                    'tournaments' => 'Quản lý Giải đấu',
                    'teams' => 'Quản lý Đội',
                    'groups' => 'Quản lý Bảng đấu',
                    'matches' => 'Quản lý Trận đấu',
                    'arenas' => 'Quản lý Sân thi đấu',
                    'referee_assignments' => 'Phân công Trọng tài',
                    'settings' => 'Cài đặt hệ thống'
                ];
                echo $titles[$action] ?? 'Quản trị hệ thống';
                ?>
            </h1>
            <p class="admin-subtitle">
                <i class="fas fa-user me-1"></i>
                Đăng nhập với quyền: 
                <strong>
                    <?php 
                    if ($user_role === 'admin') echo 'Administrator';
                    elseif ($user_role === 'manager') echo 'Quản lý giải đấu';
                    elseif ($user_role === 'referee') echo 'Trọng tài';
                    else echo 'Người dùng';
                    ?>
                </strong>
            </p>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert-admin alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="alert-admin alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Dashboard -->
        <?php if ($action === 'dashboard'): ?>
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card users">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalUsers; ?></div>
                    <div class="stats-label">Người dùng</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card tournaments">
                    <div class="stats-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalTournaments; ?></div>
                    <div class="stats-label">Giải đấu</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card teams">
                    <div class="stats-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalTeams; ?></div>
                    <div class="stats-label">Đội tham gia</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card matches">
                    <div class="stats-icon">
                        <i class="fas fa-basketball-ball"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalMatches; ?></div>
                    <div class="stats-label">Trận đấu</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card arenas">
                    <div class="stats-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalArenas; ?></div>
                    <div class="stats-label">Sân thi đấu</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card referees">
                    <div class="stats-icon">
                        <i class="fas fa-whistle"></i>
                    </div>
                    <div class="stats-number"><?php echo $totalRefereeAssignments; ?></div>
                    <div class="stats-label">Phân công trọng tài</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-bolt me-2"></i>Hành động nhanh
            </h3>
            <div class="row">
                <?php if ($user_role === 'admin'): ?>
                <div class="col-md-3 mb-3">
                    <a href="?action=users" class="btn btn-admin btn-admin-primary w-100 py-3">
                        <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 mb-3">
                    <a href="?action=tournaments" class="btn btn-admin btn-admin-primary w-100 py-3">
                        <i class="fas fa-plus-circle me-2"></i>Tạo giải đấu mới
                    </a>
                </div>
                
                <div class="col-md-3 mb-3">
                    <a href="?action=arenas" class="btn btn-admin btn-admin-manager w-100 py-3">
                        <i class="fas fa-map-marker-alt me-2"></i>Thêm sân thi đấu
                    </a>
                </div>
                
                <div class="col-md-3 mb-3">
                    <a href="draw.php" class="btn btn-admin btn-admin-primary w-100 py-3">
                        <i class="fas fa-random me-2"></i>Bốc thăm chia bảng
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-6">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-history me-2"></i>Giải đấu của bạn (<?php echo count($tournaments); ?>)
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tên giải</th>
                                    <th>Trạng thái</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($tournaments, 0, 5) as $tournament): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tournament['name']); ?></td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'upcoming' => 'badge-admin badge-admin-info',
                                            'ongoing' => 'badge-admin badge-admin-primary',
                                            'completed' => 'badge-admin badge-admin-success',
                                            'cancelled' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'upcoming' => 'Sắp diễn ra',
                                            'ongoing' => 'Đang diễn ra',
                                            'completed' => 'Đã kết thúc',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$tournament['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$tournament['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($tournament['owner_name'] ?? 'N/A'); ?>
                                        <?php if ($tournament['owner_id'] == $user_id): ?>
                                        <span class="badge-admin badge-admin-primary permission-badge">Bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($tournament['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-user-friends me-2"></i>Người dùng gần đây
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Tên hiển thị</th>
                                    <th>Vai trò</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($users, 0, 5) as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                    <td>
                                        <?php 
                                        $roleBadges = [
                                            'admin' => 'badge-admin-danger',
                                            'manager' => 'badge-admin-manager',
                                            'referee' => 'badge-admin-referee',
                                            'user' => 'badge-admin-info'
                                        ];
                                        $roleText = [
                                            'admin' => 'Admin',
                                            'manager' => 'Quản lý',
                                            'referee' => 'Trọng tài',
                                            'user' => 'User'
                                        ];
                                        ?>
                                        <span class="badge-admin <?php echo $roleBadges[$user['role']] ?? 'badge-admin-info'; ?>">
                                            <?php echo $roleText[$user['role']] ?? 'User'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Users Management (Admin only) -->
        <?php if ($action === 'users' && $user_role === 'admin'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-user-plus me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa User' : 'Tạo User mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['username']) : ''; ?>" 
                                   <?php echo $editData ? 'readonly' : 'required'; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo $editData ? 'Mật khẩu mới (để trống nếu không đổi)' : 'Mật khẩu *'; ?></label>
                            <input type="password" class="form-control" name="password" <?php echo !$editData ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên hiển thị</label>
                            <input type="text" class="form-control" name="display_name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['display_name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['phone']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role">
                                <option value="user" <?php echo ($editData && $editData['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                <option value="manager" <?php echo ($editData && $editData['role'] === 'manager') ? 'selected' : ''; ?>>Quản lý</option>
                                <option value="referee" <?php echo ($editData && $editData['role'] === 'referee') ? 'selected' : ''; ?>>Trọng tài</option>
                                <option value="admin" <?php echo ($editData && $editData['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_user" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-save me-2"></i>Cập nhật User
                            </button>
                            <a href="?action=users" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_user" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Tạo User
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách User (<?php echo count($users); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Tên hiển thị</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                        $roleBadges = [
                                            'admin' => 'badge-admin-danger',
                                            'manager' => 'badge-admin-manager',
                                            'referee' => 'badge-admin-referee',
                                            'user' => 'badge-admin-info'
                                        ];
                                        $roleText = [
                                            'admin' => 'Admin',
                                            'manager' => 'Quản lý',
                                            'referee' => 'Trọng tài',
                                            'user' => 'User'
                                        ];
                                        ?>
                                        <span class="badge-admin <?php echo $roleBadges[$user['role']] ?? 'badge-admin-info'; ?>">
                                            <?php echo $roleText[$user['role']] ?? 'User'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="?action=users&edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Xóa user này?');">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-admin btn-admin-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tournaments Management -->
        <?php if ($action === 'tournaments'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-trophy me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa Giải đấu' : 'Tạo Giải đấu mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên giải đấu *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thể thức</label>
                                <select class="form-select" name="format" required>
                                    <option value="round_robin" <?php echo ($editData && $editData['format'] === 'round_robin') ? 'selected' : ''; ?>>Vòng tròn</option>
                                    <option value="knockout" <?php echo ($editData && $editData['format'] === 'knockout') ? 'selected' : ''; ?>>Loại trực tiếp</option>
                                    <option value="combined" <?php echo ($editData && $editData['format'] === 'combined') ? 'selected' : ''; ?>>Kết hợp</option>
                                    <option value="double_elimination" <?php echo ($editData && $editData['format'] === 'double_elimination') ? 'selected' : ''; ?>>Loại kép</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status" required>
                                    <option value="upcoming" <?php echo ($editData && $editData['status'] === 'upcoming') ? 'selected' : ''; ?>>Sắp diễn ra</option>
                                    <option value="ongoing" <?php echo ($editData && $editData['status'] === 'ongoing') ? 'selected' : ''; ?>>Đang diễn ra</option>
                                    <option value="completed" <?php echo ($editData && $editData['status'] === 'completed') ? 'selected' : ''; ?>>Đã kết thúc</option>
                                    <option value="cancelled" <?php echo ($editData && $editData['status'] === 'cancelled') ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày bắt đầu</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo $editData ? $editData['start_date'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày kết thúc</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo $editData ? $editData['end_date'] : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa điểm</label>
                            <input type="text" class="form-control" name="location" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['location']) : ''; ?>">
                        </div>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <div class="mb-3">
                            <label class="form-label">Chủ sở hữu (Owner)</label>
                            <select class="form-select" name="owner_id">
                                <option value="">-- Chọn chủ sở hữu --</option>
                                <?php 
                                $owners = $pdo->query("SELECT id, username, display_name FROM Users WHERE role IN ('admin', 'manager') ORDER BY username")->fetchAll();
                                foreach ($owners as $owner): 
                                ?>
                                <option value="<?php echo $owner['id']; ?>" <?php echo ($editData && $editData['owner_id'] == $owner['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($owner['display_name'] ? $owner['display_name'] . ' (' . $owner['username'] . ')' : $owner['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_tournament" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-save me-2"></i>Cập nhật giải
                            </button>
                            <a href="?action=tournaments" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_tournament" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Tạo giải đấu
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Form thêm manager cho giải đấu -->
                <?php if ($editData && canManageTournament($user_id, $editData['id'], $user_role)): ?>
                <div class="form-container mt-4">
                    <h3 class="form-title">
                        <i class="fas fa-user-plus me-2"></i>Thêm Quản lý cho giải
                    </h3>
                    
                    <form method="post">
                        <input type="hidden" name="tournament_id" value="<?php echo $editData['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Chọn Manager</label>
                            <select class="form-select" name="user_id" required>
                                <option value="">-- Chọn manager --</option>
                                <?php 
                                $managers = $pdo->query("SELECT id, username, display_name FROM Users WHERE role = 'manager' AND id != ? ORDER BY username", [$editData['owner_id'] ?? 0])->fetchAll();
                                foreach ($managers as $manager): 
                                ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['display_name'] ? $manager['display_name'] . ' (' . $manager['username'] . ')' : $manager['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quyền hạn</label>
                            <select class="form-select" name="permission_level" required>
                                <option value="limited">Hạn chế</option>
                                <option value="full">Đầy đủ</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_tournament_manager" class="btn btn-admin btn-admin-manager w-100">
                            <i class="fas fa-plus me-2"></i>Thêm Manager
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách Giải đấu (<?php echo count($tournaments); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên giải</th>
                                    <th>Thể thức</th>
                                    <th>Trạng thái</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Số đội</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tournaments as $tournament): 
                                    $teamCount = $pdo->prepare("SELECT COUNT(*) FROM Teams WHERE tournament_id = ?");
                                    $teamCount->execute([$tournament['id']]);
                                    $teamCount = $teamCount->fetchColumn();
                                    
                                    // Kiểm tra quyền quản lý
                                    $can_manage = canManageTournament($user_id, $tournament['id'], $user_role);
                                ?>
                                <tr>
                                    <td><?php echo $tournament['id']; ?></td>
                                    <td>
                                        <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                        </a>
                                        <?php if ($tournament_managers[$tournament['id']] ?? false): ?>
                                        <span class="badge-admin badge-admin-manager permission-badge" title="<?php echo count($tournament_managers[$tournament['id']]); ?> manager(s)">
                                            <i class="fas fa-users"></i> <?php echo count($tournament_managers[$tournament['id']]); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Kết hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'upcoming' => 'badge-admin badge-admin-info',
                                            'ongoing' => 'badge-admin badge-admin-primary',
                                            'completed' => 'badge-admin badge-admin-success',
                                            'cancelled' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'upcoming' => 'Sắp diễn ra',
                                            'ongoing' => 'Đang diễn ra',
                                            'completed' => 'Đã kết thúc',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$tournament['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$tournament['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($tournament['owner_name'] ?? 'N/A'); ?>
                                        <?php if ($tournament['owner_id'] == $user_id): ?>
                                        <span class="badge-admin badge-admin-primary permission-badge">Bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $teamCount; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($tournament['created_at'])); ?></td>
                                    <td>
                                        <?php if ($can_manage): ?>
                                        <a href="?action=tournaments&edit_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Xóa giải đấu này?');">
                                            <input type="hidden" name="id" value="<?php echo $tournament['id']; ?>">
                                            <button type="submit" name="delete_tournament" class="btn btn-sm btn-admin btn-admin-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted">Không có quyền</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Danh sách manager cho giải đấu đang chỉnh sửa -->
                <?php if ($editData && canManageTournament($user_id, $editData['id'], $user_role) && isset($tournament_managers[$editData['id']])): ?>
                <div class="table-container mt-4">
                    <h3 class="table-title">
                        <i class="fas fa-users me-2"></i>Danh sách Quản lý cho giải "<?php echo htmlspecialchars($editData['name']); ?>"
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Tên hiển thị</th>
                                    <th>Quyền hạn</th>
                                    <th>Ngày thêm</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tournament_managers[$editData['id']] as $manager): ?>
                                <tr>
                                    <td><?php echo $manager['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($manager['username']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['display_name']); ?></td>
                                    <td>
                                        <span class="badge-admin <?php echo $manager['permission_level'] === 'full' ? 'badge-admin-primary' : 'badge-admin-warning'; ?>">
                                            <?php echo $manager['permission_level'] === 'full' ? 'Đầy đủ' : 'Hạn chế'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($manager['created_at'])); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Xóa manager này khỏi giải đấu?');">
                                            <input type="hidden" name="id" value="<?php echo $manager['id']; ?>">
                                            <button type="submit" name="remove_tournament_manager" class="btn btn-sm btn-admin btn-admin-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teams Management -->
        <?php if ($action === 'teams'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-users-cog me-2"></i>Quản lý Đội (<?php echo count($teams); ?>)
            </h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đội</th>
                            <th>VĐV 1</th>
                            <th>VĐV 2</th>
                            <th>Giải đấu</th>
                            <th>Trình độ</th>
                            <th>Bảng</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teams as $team): 
                            // Kiểm tra quyền
                            $can_manage = $team['tournament_id'] ? canManageTournament($user_id, $team['tournament_id'], $user_role) : false;
                        ?>
                        <tr>
                            <td><?php echo $team['id']; ?></td>
                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td><?php echo htmlspecialchars($team['player1']); ?></td>
                            <td><?php echo htmlspecialchars($team['player2']); ?></td>
                            <td><?php echo htmlspecialchars($team['tournament_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if($team['skill_level']): ?>
                                <span class="badge-admin badge-admin-info"><?php echo $team['skill_level']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($team['group_name']): ?>
                                <span class="badge-admin badge-admin-warning"><?php echo $team['group_name']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($team['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="?action=edit_team&id=<?php echo $team['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Xóa đội này?');">
                                    <input type="hidden" name="id" value="<?php echo $team['id']; ?>">
                                    <button type="submit" name="delete_team" class="btn btn-sm btn-admin btn-admin-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Groups Management -->
        <?php if ($action === 'groups'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-layer-group me-2"></i>Quản lý Bảng đấu (<?php echo count($groups); ?>)
            </h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên bảng</th>
                            <th>Giải đấu</th>
                            <th>Số đội</th>
                            <th>Số trận</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($groups as $group): 
                            // Kiểm tra quyền
                            $can_manage = $group['tournament_id'] ? canManageTournament($user_id, $group['tournament_id'], $user_role) : false;
                            
                            // Đếm số đội trong bảng
                            $teamCount = $pdo->prepare("SELECT COUNT(DISTINCT t.id) FROM Teams t INNER JOIN Matches m ON (t.id = m.team1_id OR t.id = m.team2_id) WHERE m.group_id = ?");
                            $teamCount->execute([$group['id']]);
                            $teamCount = $teamCount->fetchColumn();
                            
                            // Đếm số trận trong bảng
                            $matchCount = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE group_id = ?");
                            $matchCount->execute([$group['id']]);
                            $matchCount = $matchCount->fetchColumn();
                        ?>
                        <tr>
                            <td><?php echo $group['id']; ?></td>
                            <td><strong>Bảng <?php echo $group['group_name']; ?></strong></td>
                            <td><?php echo htmlspecialchars($group['tournament_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $teamCount; ?></td>
                            <td><?php echo $matchCount; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($group['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="matches.php?group=<?php echo $group['id']; ?>" class="btn btn-sm btn-admin btn-admin-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Xóa bảng đấu này? Tất cả trận đấu trong bảng sẽ bị xóa!');">
                                    <input type="hidden" name="id" value="<?php echo $group['id']; ?>">
                                    <button type="submit" name="delete_group" class="btn btn-sm btn-admin btn-admin-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Matches Management -->
        <?php if ($action === 'matches'): ?>
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-basketball-ball me-2"></i>Quản lý Trận đấu (<?php echo count($matches); ?>)
            </h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Trận đấu</th>
                            <th>Kết quả</th>
                            <th>Bảng/Vòng</th>
                            <th>Sân đấu</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($matches as $match): 
                            // Kiểm tra quyền
                            $can_manage = $match['tournament_id'] ? canManageTournament($user_id, $match['tournament_id'], $user_role) : false;
                        ?>
                        <tr>
                            <td><?php echo $match['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($match['team1_name']); ?> 
                                <strong>vs</strong> 
                                <?php echo htmlspecialchars($match['team2_name']); ?>
                            </td>
                            <td>
                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                <span class="badge-admin badge-admin-primary">
                                    <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                                </span>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Chưa có kết quả</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($match['group_name']): ?>
                                <span class="badge-admin badge-admin-info">Bảng <?php echo $match['group_name']; ?></span>
                                <br>
                                <small><?php echo $match['round']; ?></small>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Loại trực tiếp</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($match['arena_name']): ?>
                                <span class="badge-admin badge-admin-manager"><?php echo $match['arena_name']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($match['score1'] !== null && $match['score2'] !== null): ?>
                                <span class="badge-admin badge-admin-success">Đã hoàn thành</span>
                                <?php else: ?>
                                <span class="badge-admin badge-admin-warning">Chưa diễn ra</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($match['created_at'] ?? 'now')); ?></td>
                            <td>
                                <?php if ($can_manage): ?>
                                <a href="matches.php?match=<?php echo $match['id']; ?>" class="btn btn-sm btn-admin btn-admin-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Xóa trận đấu này?');">
                                    <input type="hidden" name="id" value="<?php echo $match['id']; ?>">
                                    <button type="submit" name="delete_match" class="btn btn-sm btn-admin btn-admin-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted">Không có quyền</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Arena Management -->
        <?php if ($action === 'arenas'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo $editData ? 'Chỉnh sửa Sân đấu' : 'Thêm Sân đấu mới'; ?>
                    </h3>
                    
                    <form method="post">
                        <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tên sân *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa điểm</label>
                            <input type="text" class="form-control" name="location" 
                                   value="<?php echo $editData ? htmlspecialchars($editData['location']) : ''; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sức chứa</label>
                                <input type="number" class="form-control" name="capacity" 
                                       value="<?php echo $editData ? $editData['capacity'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status" required>
                                    <option value="available" <?php echo ($editData && $editData['status'] === 'available') ? 'selected' : ''; ?>>Có sẵn</option>
                                    <option value="maintenance" <?php echo ($editData && $editData['status'] === 'maintenance') ? 'selected' : ''; ?>>Bảo trì</option>
                                    <option value="unavailable" <?php echo ($editData && $editData['status'] === 'unavailable') ? 'selected' : ''; ?>>Không có sẵn</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editData): ?>
                            <button type="submit" name="update_arena" class="btn btn-admin btn-admin-manager">
                                <i class="fas fa-save me-2"></i>Cập nhật sân
                            </button>
                            <a href="?action=arenas" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                            <?php else: ?>
                            <button type="submit" name="create_arena" class="btn btn-admin btn-admin-manager">
                                <i class="fas fa-plus me-2"></i>Thêm sân đấu
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách Sân thi đấu (<?php echo count($arenas); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên sân</th>
                                    <th>Địa điểm</th>
                                    <th>Sức chứa</th>
                                    <th>Trạng thái</th>
                                    <th>Số trận đấu</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($arenas as $arena): 
                                    // Đếm số trận đấu sử dụng sân này
                                    $matchCount = $pdo->prepare("SELECT COUNT(*) FROM Matches WHERE arena_id = ?");
                                    $matchCount->execute([$arena['id']]);
                                    $matchCount = $matchCount->fetchColumn();
                                ?>
                                <tr>
                                    <td><?php echo $arena['id']; ?></td>
                                    <td><?php echo htmlspecialchars($arena['name']); ?></td>
                                    <td><?php echo htmlspecialchars($arena['location']); ?></td>
                                    <td><?php echo $arena['capacity'] ?: 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'available' => 'badge-admin badge-admin-primary',
                                            'maintenance' => 'badge-admin badge-admin-warning',
                                            'unavailable' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'available' => 'Có sẵn',
                                            'maintenance' => 'Bảo trì',
                                            'unavailable' => 'Không có sẵn'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$arena['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$arena['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $matchCount; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($arena['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="?action=arenas&edit_id=<?php echo $arena['id']; ?>" class="btn btn-sm btn-admin btn-admin-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Xóa sân này?');">
                                            <input type="hidden" name="id" value="<?php echo $arena['id']; ?>">
                                            <button type="submit" name="delete_arena" class="btn btn-sm btn-admin btn-admin-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Referee Assignments Management -->
        <?php if ($action === 'referee_assignments' && ($user_role === 'admin' || $user_role === 'manager')): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-whistle me-2"></i>Phân công Trọng tài
                    </h3>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Chọn Trận đấu *</label>
                            <select class="form-select" name="match_id" required>
                                <option value="">-- Chọn trận đấu --</option>
                                <?php 
                                $matches_list = $pdo->query("
                                    SELECT m.id, t1.team_name as team1, t2.team_name as team2, t.name as tournament_name
                                    FROM Matches m
                                    LEFT JOIN Teams t1 ON m.team1_id = t1.id
                                    LEFT JOIN Teams t2 ON m.team2_id = t2.id
                                    LEFT JOIN Tournaments t ON m.tournament_id = t.id
                                    WHERE m.match_date >= CURDATE() OR m.match_date IS NULL
                                    ORDER BY m.match_date DESC
                                ")->fetchAll();
                                foreach ($matches_list as $match): 
                                ?>
                                <option value="<?php echo $match['id']; ?>">
                                    <?php echo htmlspecialchars($match['tournament_name'] . ': ' . $match['team1'] . ' vs ' . $match['team2']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Chọn Trọng tài *</label>
                            <select class="form-select" name="referee_id" required>
                                <option value="">-- Chọn trọng tài --</option>
                                <?php 
                                $referees = $pdo->query("SELECT id, username, display_name FROM Users WHERE role = 'referee' ORDER BY username")->fetchAll();
                                foreach ($referees as $referee): 
                                ?>
                                <option value="<?php echo $referee['id']; ?>">
                                    <?php echo htmlspecialchars($referee['display_name'] ? $referee['display_name'] . ' (' . $referee['username'] . ')' : $referee['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="assignment_type" required>
                                <option value="main">Trọng tài chính</option>
                                <option value="assistant">Trọng tài phụ</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="assign_referee" class="btn btn-admin btn-admin-referee w-100">
                            <i class="fas fa-user-plus me-2"></i>Phân công Trọng tài
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="table-container">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>Danh sách Phân công Trọng tài (<?php echo count($referee_assignments); ?>)
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Trận đấu</th>
                                    <th>Trọng tài</th>
                                    <th>Vai trò</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày phân công</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($referee_assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo $assignment['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($assignment['team1_name']); ?> 
                                        <strong>vs</strong> 
                                        <?php echo htmlspecialchars($assignment['team2_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($assignment['referee_name']); ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($assignment['referee_username']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-admin <?php echo $assignment['assignment_type'] === 'main' ? 'badge-admin-primary' : 'badge-admin-info'; ?>">
                                            <?php echo $assignment['assignment_type'] === 'main' ? 'Chính' : 'Phụ'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusBadges = [
                                            'assigned' => 'badge-admin badge-admin-warning',
                                            'completed' => 'badge-admin badge-admin-success',
                                            'cancelled' => 'badge-admin badge-admin-danger'
                                        ];
                                        $statusText = [
                                            'assigned' => 'Đã phân công',
                                            'completed' => 'Đã hoàn thành',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        ?>
                                        <span class="<?php echo $statusBadges[$assignment['status']] ?? 'badge-admin badge-admin-info'; ?>">
                                            <?php echo $statusText[$assignment['status']] ?? 'Unknown'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-admin btn-admin-warning" data-bs-toggle="modal" data-bs-target="#editAssignmentModal<?php echo $assignment['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Xóa phân công này?');">
                                            <input type="hidden" name="id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" name="delete_referee_assignment" class="btn btn-sm btn-admin btn-admin-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <!-- Modal chỉnh sửa phân công -->
                                <div class="modal fade" id="editAssignmentModal<?php echo $assignment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Chỉnh sửa Phân công Trọng tài</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $assignment['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Vai trò</label>
                                                        <select class="form-select" name="assignment_type" required>
                                                            <option value="main" <?php echo $assignment['assignment_type'] === 'main' ? 'selected' : ''; ?>>Trọng tài chính</option>
                                                            <option value="assistant" <?php echo $assignment['assignment_type'] === 'assistant' ? 'selected' : ''; ?>>Trọng tài phụ</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Trạng thái</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="assigned" <?php echo $assignment['status'] === 'assigned' ? 'selected' : ''; ?>>Đã phân công</option>
                                                            <option value="completed" <?php echo $assignment['status'] === 'completed' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                                                            <option value="cancelled" <?php echo $assignment['status'] === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                    <button type="submit" name="update_referee_assignment" class="btn btn-admin btn-admin-primary">Cập nhật</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Settings (Admin only) -->
        <?php if ($action === 'settings' && $user_role === 'admin'): ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-database me-2"></i>Quản lý dữ liệu
                    </h3>
                    
                    <div class="alert-admin alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Các thao tác này sẽ xóa dữ liệu vĩnh viễn!
                    </div>
                    
                    <div class="mb-4">
                        <h5><i class="fas fa-trash me-2 text-danger"></i>Xóa dữ liệu theo loại</h5>
                        <div class="d-grid gap-2 mb-3">
                            <a href="clear_data.php?type=teams" class="btn btn-admin btn-admin-danger" onclick="return confirm('Xóa tất cả đội?');">
                                <i class="fas fa-users-slash me-2"></i>Xóa tất cả đội
                            </a>
                            <a href="clear_data.php?type=matches" class="btn btn-admin btn-admin-danger" onclick="return confirm('Xóa tất cả trận đấu?');">
                                <i class="fas fa-basketball-ball me-2"></i>Xóa tất cả trận đấu
                            </a>
                            <a href="clear_data.php?type=groups" class="btn btn-admin btn-admin-danger" onclick="return confirm('Xóa tất cả bảng đấu?');">
                                <i class="fas fa-layer-group me-2"></i>Xóa tất cả bảng đấu
                            </a>
                            <a href="clear_data.php?type=arenas" class="btn btn-admin btn-admin-danger" onclick="return confirm('Xóa tất cả sân đấu?');">
                                <i class="fas fa-map-marker-alt me-2"></i>Xóa tất cả sân đấu
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <h5><i class="fas fa-bomb me-2 text-danger"></i>Xóa toàn bộ dữ liệu</h5>
                        <form method="post" onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa TOÀN BỘ dữ liệu? Hành động này không thể hoàn tác!');">
                            <div class="mb-3">
                                <label class="form-label">Nhập "DELETE_ALL" để xác nhận</label>
                                <input type="text" class="form-control" name="confirm" required>
                                <div class="form-text">Tất cả dữ liệu sẽ bị xóa, chỉ giữ lại tài khoản admin.</div>
                            </div>
                            <button type="submit" name="clear_all_data" class="btn btn-admin btn-admin-danger w-100">
                                <i class="fas fa-skull-crossbones me-2"></i>XÓA TOÀN BỘ DỮ LIỆU
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="form-container">
                    <h3 class="form-title">
                        <i class="fas fa-cogs me-2"></i>Cài đặt hệ thống
                    </h3>
                    
                    <div class="mb-4">
                        <h5><i class="fas fa-download me-2"></i>Xuất dữ liệu</h5>
                        <div class="d-grid gap-2">
                            <a href="export.php?type=users" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-file-csv me-2"></i>Xuất danh sách User
                            </a>
                            <a href="export.php?type=tournaments" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Giải đấu
                            </a>
                            <a href="export.php?type=teams" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Đội
                            </a>
                            <a href="export.php?type=arenas" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-file-excel me-2"></i>Xuất danh sách Sân đấu
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <h5><i class="fas fa-tools me-2"></i>Công cụ hệ thống</h5>
                        <div class="d-grid gap-2">
                            <a href="create_sample_data.php" class="btn btn-admin btn-admin-primary" onclick="return confirm('Tạo dữ liệu mẫu?');">
                                <i class="fas fa-magic me-2"></i>Tạo dữ liệu mẫu
                            </a>
                            <a href="rebuild_indexes.php" class="btn btn-admin btn-admin-warning">
                                <i class="fas fa-sync me-2"></i>Xây lại chỉ mục
                            </a>
                            <a href="backup_database.php" class="btn btn-admin btn-admin-primary">
                                <i class="fas fa-database me-2"></i>Backup cơ sở dữ liệu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h3 class="table-title">
                <i class="fas fa-info-circle me-2"></i>Thông tin hệ thống
            </h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Database:</strong> MySQL
                    </div>
                    <div class="mb-3">
                        <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong>Memory Usage:</strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB
                    </div>
                    <div class="mb-3">
                        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds
                    </div>
                    <div class="mb-3">
                        <strong>Upload Max Filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Xác nhận xóa
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForms = document.querySelectorAll('form[onsubmit]');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Bạn có chắc chắn muốn xóa?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-admin');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            // Tab active state
            const currentAction = '<?php echo $action; ?>';
            const navLinks = document.querySelectorAll('.nav-link-admin');
            navLinks.forEach(link => {
                if (link.href.includes(`action=${currentAction}`)) {
                    link.classList.add('active');
                }
            });
            
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Export functions
        function exportData(type) {
            window.location.href = `export.php?type=${type}`;
        }
    </script>
</body>
</html>