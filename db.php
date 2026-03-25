<?php
// db.php - Cập nhật đầy đủ các bảng
$host = 'localhost';
$db   = 'bmb_tournaments';  // Tên database của bạn
$user = 'root'; // User XAMPP mặc định
$pass = '';     // Password XAMPP mặc định (để trống)
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Tự động tạo bảng nếu chưa tồn tại
createTablesIfNotExist();

session_start();

function createTablesIfNotExist() {
    global $pdo;
    
    // 1. Tạo bảng Users (phải tạo đầu tiên vì các bảng khác tham chiếu đến)
    $pdo->exec("CREATE TABLE IF NOT EXISTS Users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        display_name VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        role ENUM('admin', 'manager', 'referee', 'user') DEFAULT 'user',
        tournament_permissions TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 2. Tạo bảng Tournaments
    $pdo->exec("CREATE TABLE IF NOT EXISTS Tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        format ENUM('round_robin', 'knockout', 'combined', 'double_elimination') DEFAULT 'combined',
        start_date DATE,
        end_date DATE,
        location VARCHAR(200),
        total_teams INT DEFAULT 0,
        status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
        owner_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE SET NULL
    )");
    
    // 3. Tạo bảng Teams
    $pdo->exec("CREATE TABLE IF NOT EXISTS Teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT,
        team_name VARCHAR(100) NOT NULL,
        player1 VARCHAR(100),
        player2 VARCHAR(100),
        skill_level VARCHAR(10),
        group_name VARCHAR(1),
        seed INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_team_tournament (team_name, tournament_id),
        FOREIGN KEY (tournament_id) REFERENCES Tournaments(id) ON DELETE CASCADE
    )");
    
    // 4. Tạo bảng Groups
    $pdo->exec("CREATE TABLE IF NOT EXISTS Groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT,
        group_name VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tournament_id) REFERENCES Tournaments(id) ON DELETE CASCADE
    )");
    
    // 5. Tạo bảng Arena
    $pdo->exec("CREATE TABLE IF NOT EXISTS Arena (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(200),
        capacity INT,
        status ENUM('available', 'maintenance', 'unavailable') DEFAULT 'available',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 6. Tạo bảng TournamentManagers
    $pdo->exec("CREATE TABLE IF NOT EXISTS TournamentManagers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('full', 'limited') DEFAULT 'full',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_manager_tournament (tournament_id, user_id),
        FOREIGN KEY (tournament_id) REFERENCES Tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )");
    
    // 7. Tạo bảng Matches
    $pdo->exec("CREATE TABLE IF NOT EXISTS Matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT,
        team1_id INT,
        team2_id INT,
        group_id INT,
        round VARCHAR(50),
        match_type ENUM('group', 'knockout', 'quarter', 'semi', 'final', 'live') DEFAULT 'group',
        score1 INT DEFAULT NULL,
        score2 INT DEFAULT NULL,
        winner_id INT DEFAULT NULL,
        match_date DATETIME,
        court VARCHAR(50),
        arena_id INT,
        winning_score INT DEFAULT 11,
        first_server INT DEFAULT 1,
        server_team INT DEFAULT 1,
        server_hand INT DEFAULT 1,
        status ENUM('pending', 'live', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tournament_id) REFERENCES Tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (team1_id) REFERENCES Teams(id) ON DELETE CASCADE,
        FOREIGN KEY (team2_id) REFERENCES Teams(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES Groups(id) ON DELETE CASCADE,
        FOREIGN KEY (winner_id) REFERENCES Teams(id) ON DELETE SET NULL,
        FOREIGN KEY (arena_id) REFERENCES Arena(id) ON DELETE SET NULL
    )");
    
    // Add columns if not exist (for live matches)
    try {
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS winning_score INT DEFAULT 11");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS first_server INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS server_team INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS server_hand INT DEFAULT 1");
        $pdo->exec("ALTER TABLE Matches ADD COLUMN IF NOT EXISTS status ENUM('pending', 'live', 'completed') DEFAULT 'pending'");
        $pdo->exec("ALTER TABLE Matches MODIFY COLUMN match_type ENUM('group', 'knockout', 'quarter', 'semi', 'final', 'live') DEFAULT 'group'");
    } catch (Exception $e) {
        // Columns may already exist
    }
    
    // 8. Tạo bảng RefereeAssignments
    $pdo->exec("CREATE TABLE IF NOT EXISTS RefereeAssignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        match_id INT NOT NULL,
        referee_id INT NOT NULL,
        assignment_type ENUM('main', 'assistant') DEFAULT 'main',
        status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME,
        UNIQUE KEY unique_match_referee (match_id, referee_id),
        FOREIGN KEY (match_id) REFERENCES Matches(id) ON DELETE CASCADE,
        FOREIGN KEY (referee_id) REFERENCES Users(id) ON DELETE CASCADE
    )");
    
    // 9. Tạo user admin mặc định nếu chưa có
    try {
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = 'admin'");
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO Users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $passwordHash, 'Quản Trị Viên', 'admin']);
        }
    } catch (Exception $e) {
        // Bảng Users có thể chưa tồn tại
    }
    
    // 10. Thêm các cột thiếu vào bảng Matches nếu cần
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM Matches")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('arena_id', $columns)) {
            $pdo->exec("ALTER TABLE Matches ADD COLUMN arena_id INT DEFAULT NULL AFTER court");
            $pdo->exec("ALTER TABLE Matches ADD FOREIGN KEY (arena_id) REFERENCES Arena(id) ON DELETE SET NULL");
        }
    } catch (Exception $e) {
        // Bảng có thể chưa tồn tại
    }
    
    
    // Kiểm tra và tạo bảng RefereeAssignments nếu chưa có
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS RefereeAssignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        match_id INT NOT NULL,
        referee_id INT NOT NULL,
        assignment_type ENUM('main', 'assistant') DEFAULT 'main',
        status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME,
        UNIQUE KEY unique_match_referee (match_id, referee_id),
        FOREIGN KEY (match_id) REFERENCES Matches(id) ON DELETE CASCADE,
        FOREIGN KEY (referee_id) REFERENCES Users(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    // Bảng đã tồn tại
}
    
    
    
}
?>