<?php
// tournament_list.php - Phiên bản mới với giao diện hiện đại
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

try {
    // Lấy danh sách giải đấu với thống kê
    $query = "
        SELECT t.*, 
               COUNT(DISTINCT tm.id) as team_count,
               COUNT(DISTINCT m.id) as match_count,
               COUNT(DISTINCT g.id) as group_count
        FROM Tournaments t
        LEFT JOIN Teams tm ON t.id = tm.tournament_id
        LEFT JOIN Matches m ON t.id = m.tournament_id
        LEFT JOIN Groups g ON t.id = g.tournament_id
        GROUP BY t.id
        ORDER BY 
            CASE t.status 
                WHEN 'ongoing' THEN 1
                WHEN 'upcoming' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'cancelled' THEN 4
                ELSE 5
            END,
            t.start_date DESC
    ";
    
    $tournaments = $pdo->query($query)->fetchAll();
    
    // Tính thống kê
    $totalTournaments = count($tournaments);
    $upcomingTournaments = 0;
    $ongoingTournaments = 0;
    $completedTournaments = 0;
    $cancelledTournaments = 0;
    
    foreach ($tournaments as $tournament) {
        switch ($tournament['status']) {
            case 'upcoming':
                $upcomingTournaments++;
                break;
            case 'ongoing':
                $ongoingTournaments++;
                break;
            case 'completed':
                $completedTournaments++;
                break;
            case 'cancelled':
                $cancelledTournaments++;
                break;
        }
    }
    
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Giải đấu - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2ecc71;
            --accent: #ff6b00;
            --text-dark: #1e293b;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Open Sans', sans-serif;
        }
        
        
        .btn-glow {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            background: #111;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            z-index: 0;
        }
        .btn-glow:before {
            content: '';
            background: linear-gradient(45deg, #ff0000, #ff7300, #fffb00, #48ff00, #00ffd5, #002bff, #7a00ff, #ff00c8, #ff0000);
            position: absolute;
            top: -2px; left: -2px;
            background-size: 400%;
            z-index: -1;
            filter: blur(5px);
            width: calc(100% + 2px);
            height: calc(100% + 2px);
            animation: glowing 20s linear infinite;
            opacity: 0;
            transition: opacity .3s ease-in-out;
            border-radius: 8px;
        }
        .btn-glow:hover:before { opacity: 1; }
        .btn-glow:after {
            z-index: -1; content: '';
            position: absolute; width: 100%; height: 100%;
            background: #111; left: 0; top: 0; border-radius: 8px;
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            color: var(--accent) !important;
        }
        
        .nav-link {
            font-weight: 600;
            color: var(--text-dark) !important;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--accent) !important;
        }
        
        .page-header {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://images.unsplash.com/photo-1737476997205-b3336182f215?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        
        .page-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 15px;
            min-width: 80px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            font-weight: 300;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .nav-tabs-custom {
            background: white;
            border-radius: 15px;
            padding: 0 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-dark);
            font-weight: 600;
            padding: 20px 25px;
            border-radius: 0;
            margin: 0;
            position: relative;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--accent);
            background: transparent;
        }
        
        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--accent);
        }
        
        .tournament-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 25px;
            border: 0.5px solid #C0C0C0;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-dark);
            display: block;
            box-shadow: 0 10px 10px rgba(0,0,0,0.1);
            min-height: 255px;
        }
        
        .tournament-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            color: var(--text-dark);
        }
        
        .tournament-card-header {
            padding: 30px 25px 15px;
            position: relative;
        }
        
        .tournament-status-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.6rem;
            text-transform: uppercase;
        }
        
        .status-upcoming { 
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        .status-ongoing { 
            background: linear-gradient(135deg, #ff4343, #810707);
            color: white;
        }
        .status-completed { 
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        .status-cancelled { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .tournament-name {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .tournament-format-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .format-round_robin { 
            background: linear-gradient(135deg, #e8f4fc, #d6eaf8);
            color: #3498db;
            border: 1px solid #aed6f1;
        }
        .format-knockout { 
            background: linear-gradient(135deg, #fde8e8, #fadbd8);
            color: #e74c3c;
            border: 1px solid #f1948a;
        }
        .format-combined { 
            background: linear-gradient(135deg, #f0e8fd, #e8daef);
            color: #9b59b6;
            border: 1px solid #bb8fce;
        }
        .format-double_elimination { 
            background: linear-gradient(135deg, #e8f8f5, #d1f2eb);
            color: #1abc9c;
            border: 1px solid #76d7c4;
        }
        
        .tournament-info {
            padding: 0 25px 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #64748b;
        }
        
        .info-item i {
            width: 25px;
            color: var(--accent);
            font-size: 1.1rem;
        }
        
        .tournament-stats {
            display: flex;
            justify-content: space-around;
            background: #f8fafc;
            padding: 12px 10px;
            border-top: 1px solid #e2e8f0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .tournament-stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 5px;
        }
        
        .tournament-stat-label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tournament-actions {
            padding: 20px 25px;
            border-top: 1px solid #e2e8f0;
            background: white;
            border-radius: 0 0 15px 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .btn-primary-custom {
            background: var(--accent);
            border: none;
            color: white;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid var(--accent);
            color: var(--accent);
            background: transparent;
            padding: 10px 20px;
            font-weight: 400;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-3px);
        }
        
        .search-container {
            position: relative;
            margin-bottom: 7px;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.25);
            outline: none;
        }
        
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .filter-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .create-tournament-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 2px dashed var(--accent);
            border-radius: 15px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 25px;
            cursor: pointer;
            transition: all 0.3s;
            height: 96%;
        }
        
        .create-tournament-card:hover {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-color: var(--accent);
            transform: translateY(-3px);
        }
        
        .create-tournament-card i {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .page-stats {
                gap: 15px;
            }
            
            .stat-box {
                padding: 15px;
                min-width: 120px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 15px 10px;
                font-size: 0.9rem;
            }
            
            .tournament-name {
                font-size: 1.5rem;
            }
            
            .tournament-stats {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .stat-item {
                flex: 1;
                min-width: 80px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
<div class="container">
            <a class="navbar-brand" href="index.php">TRỌNG TÀI SỐ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
                <a class="nav-link active" href="tournament_list.php"><i class="fas fa-trophy"></i> Giải đấu</a>
                <a class="nav-link" href="matches.php"><i class="fas fa-basketball-ball"></i> Trận đấu</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="page-title">DANH SÁCH GIẢI ĐẤU</h1>
                    <p class="lead" style="font-size: 1.2rem; opacity: 0.9;">
                        Nơi hội tụ những giải đấu pickleball chuyên nghiệp nhất
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-trophy me-1"></i>Giải đấu
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar-alt me-1"></i>Lịch thi đấu
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-users me-1"></i>Thành viên
                        </span>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-chart-line me-1"></i>Thống kê
                        </span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="page-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $totalTournaments; ?></div>
                            <div class="stat-label">Tổng giải</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $upcomingTournaments; ?></div>
                            <div class="stat-label">Sắp diễn ra</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $ongoingTournaments; ?></div>
                            <div class="stat-label">Đang diễn ra</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $completedTournaments; ?></div>
                            <div class="stat-label">Đã kết thúc</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Search and Filter -->
        <div class="filter-container">
            <div class="row align-items-center">

                <div class="col-md-6 mb-3 mb-md-0col-md-6">
                    <div class="d-flex gap-2 justify-content-md">
                        <a href="create_tournament.php" class="btn btn-accent-custom">
                            <i class="fas fa-plus-circle me-2"></i>TẠO GIẢI MỚI
                        </a>
                        <button class="btn btn-outline-custom" onclick="refreshTournaments()">
                            <i class="fas fa-sync-alt me-2"></i>LÀM MỚI
                        </button>
                    </div>
                </div>
                                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="searchTournaments" 
                               placeholder="Tìm kiếm giải đấu theo tên, địa điểm...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs border-0" id="tournamentsTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#all-tournaments">
                        <i class="fas fa-list-ol me-2"></i>TẤT CẢ GIẢI
                        <span class="badge bg-accent ms-2"><?php echo $totalTournaments; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#upcoming-tournaments">
                        <i class="fas fa-clock me-2"></i>SẮP DIỄN RA
                        <span class="badge bg-info ms-2"><?php echo $upcomingTournaments; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#ongoing-tournaments">
                        <i class="fas fa-play-circle me-2"></i>ĐANG DIỄN RA
                        <span class="badge bg-success ms-2"><?php echo $ongoingTournaments; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#completed-tournaments">
                        <i class="fas fa-check-circle me-2"></i>ĐÃ KẾT THÚC
                        <span class="badge bg-secondary ms-2"><?php echo $completedTournaments; ?></span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- Tab 1: All Tournaments -->
            <div class="tab-pane fade show active" id="all-tournaments">
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">
                            TẤT CẢ GIẢI ĐẤU
                        </h3>
                        <span class="badge bg-accent fs-6">Tổng: <?php echo $totalTournaments; ?> giải</span>
                    </div>
                    
                    <?php if (empty($tournaments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h4>Chưa có giải đấu nào</h4>
                        <p>Hãy tạo giải đấu đầu tiên để bắt đầu!</p>
                        <a href="create_tournament.php" class="btn btn-accent-custom mt-3">
                            <i class="fas fa-plus me-2"></i>Tạo giải đấu mới
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="create-tournament-card" onclick="window.location.href='create_tournament.php'">
                                <i class="fas fa-plus-circle"></i>
                                <h5>Tạo giải đấu mới</h5>
                                <p class="text-muted mb-0">Thêm giải đấu mới vào hệ thống</p>
                            </div>
                        </div>
                        
                        <?php foreach($tournaments as $tournament): ?>
                        <div class="col-lg-4 col-md-6 mb-4 tournament-item" 
                             data-status="<?php echo $tournament['status']; ?>"
                             data-name="<?php echo strtolower(htmlspecialchars($tournament['name'])); ?>"
                             data-location="<?php echo strtolower(htmlspecialchars($tournament['location'])); ?>">
                            <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="tournament-card">
                                <div class="tournament-card-header">
                                    <span class="tournament-status-badge status-<?php echo $tournament['status']; ?>">
                                        <?php 
                                        $statusText = [
                                            'upcoming' => 'Sắp diễn ra',
                                            'ongoing' => 'Đang diễn ra',
                                            'completed' => 'Đã kết thúc',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        echo $statusText[$tournament['status']] ?? $tournament['status'];
                                        ?>
                                    </span>
                                    <h3 class="tournament-name"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                    <span class="tournament-format-badge format-<?php echo $tournament['format']; ?>">
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Hỗn hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? $tournament['format'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>
                                            <?php 
                                            if ($tournament['start_date']) {
                                                echo date('d/m/Y', strtotime($tournament['start_date']));
                                                if ($tournament['end_date']) {
                                                    echo ' - ' . date('d/m/Y', strtotime($tournament['end_date']));
                                                }
                                            } else {
                                                echo 'Chưa xác định ngày';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Chưa xác định địa điểm'; ?></span>
                                    </div>
                                    <?php if ($tournament['description']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span><?php echo htmlspecialchars(substr($tournament['description'], 0, 50)); ?>...</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="tournament-stats">
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['team_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Đội</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['group_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Bảng</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['match_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Trận</div>
                                    </div>
                                </div>
                                
                                <div class="tournament-actions">
                                    <div class="d-grid">
                                        <button class="btn btn-glow">
                                            <i class="fas fa-eye me-2"></i>XEM CHI TIẾT GIẢI
                                        </button>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 2: Upcoming Tournaments -->
            <div class="tab-pane fade" id="upcoming-tournaments">
                <div class="section-card">
                    <h3 class="section-title">CÁC GIẢI SẮP DIỄN RA</h3>
                    
                    <?php 
                    $upcomingTournamentsList = array_filter($tournaments, function($tournament) {
                        return $tournament['status'] == 'upcoming';
                    });
                    
                    if (empty($upcomingTournamentsList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <h4>Không có giải đấu sắp diễn ra</h4>
                        <p>Hãy tạo giải đấu mới và đặt trạng thái "Sắp diễn ra"!</p>
                        <a href="create_tournament.php" class="btn btn-accent-custom mt-3">
                            <i class="fas fa-plus me-2"></i>Tạo giải đấu mới
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($upcomingTournamentsList as $tournament): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="tournament-card">
                                <div class="tournament-card-header">
                                    <span class="tournament-status-badge status-upcoming">
                                        Sắp diễn ra
                                    </span>
                                    <h3 class="tournament-name"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                    <span class="tournament-format-badge format-<?php echo $tournament['format']; ?>">
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Hỗn hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? $tournament['format'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>
                                            <?php 
                                            if ($tournament['start_date']) {
                                                echo date('d/m/Y', strtotime($tournament['start_date']));
                                            } else {
                                                echo 'Chưa xác định ngày';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Chưa xác định địa điểm'; ?></span>
                                    </div>
                                </div>
                                
                                <div class="tournament-stats">
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['team_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Đội đã đăng ký</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 3: Ongoing Tournaments -->
            <div class="tab-pane fade" id="ongoing-tournaments">
                <div class="section-card">
                    <h3 class="section-title">CÁC GIẢI ĐANG DIỄN RA</h3>
                    
                    <?php 
                    $ongoingTournamentsList = array_filter($tournaments, function($tournament) {
                        return $tournament['status'] == 'ongoing';
                    });
                    
                    if (empty($ongoingTournamentsList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-play-circle"></i>
                        <h4>Không có giải đấu đang diễn ra</h4>
                        <p>Hãy bắt đầu giải đấu bằng cách thay đổi trạng thái!</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($ongoingTournamentsList as $tournament): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="tournament-card">
                                <div class="tournament-card-header">
                                    <span class="tournament-status-badge status-ongoing">
                                        Đang diễn ra
                                    </span>
                                    <h3 class="tournament-name"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                    <span class="tournament-format-badge format-<?php echo $tournament['format']; ?>">
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Hỗn hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? $tournament['format'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>Đang diễn ra</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo $tournament['location'] ? htmlspecialchars($tournament['location']) : 'Chưa xác định địa điểm'; ?></span>
                                    </div>
                                </div>
                                
                                <div class="tournament-stats">
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['match_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Trận đã đấu</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 4: Completed Tournaments -->
            <div class="tab-pane fade" id="completed-tournaments">
                <div class="section-card">
                    <h3 class="section-title">CÁC GIẢI ĐÃ KẾT THÚC</h3>
                    
                    <?php 
                    $completedTournamentsList = array_filter($tournaments, function($tournament) {
                        return $tournament['status'] == 'completed';
                    });
                    
                    if (empty($completedTournamentsList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>Không có giải đấu đã kết thúc</h4>
                        <p>Các giải đấu đã hoàn thành sẽ xuất hiện ở đây!</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($completedTournamentsList as $tournament): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="tournament_view.php?id=<?php echo $tournament['id']; ?>" class="tournament-card">
                                <div class="tournament-card-header">
                                    <span class="tournament-status-badge status-completed">
                                        Đã kết thúc
                                    </span>
                                    <h3 class="tournament-name"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                    <span class="tournament-format-badge format-<?php echo $tournament['format']; ?>">
                                        <?php 
                                        $formatText = [
                                            'round_robin' => 'Vòng tròn',
                                            'knockout' => 'Loại trực tiếp',
                                            'combined' => 'Hỗn hợp',
                                            'double_elimination' => 'Loại kép'
                                        ];
                                        echo $formatText[$tournament['format']] ?? $tournament['format'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>Đã kết thúc</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-trophy"></i>
                                        <span>
                                            <?php
                                            // Hiển thị thông tin đơn giản
                                            echo 'Hoàn thành với ' . ($tournament['team_count'] ?: 0) . ' đội';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="tournament-stats">
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['team_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Đội tham gia</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="tournament-stat-number"><?php echo $tournament['match_count'] ?: 0; ?></div>
                                        <div class="tournament-stat-label">Tổng trận</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-top mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h4 style="color: var(--accent); font-family: 'Montserrat', sans-serif;">TRỌNG TÀI SỐ</h4>
                    <p class="text-muted">Nơi hội tụ đam mê - Sân chơi chuyên nghiệp</p>
                    <p class="text-muted small">&copy; 2026 TRỌNG TÀI SỐ. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchTournaments').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const tournamentItems = document.querySelectorAll('.tournament-item');
            
            tournamentItems.forEach(item => {
                const name = item.getAttribute('data-name');
                const location = item.getAttribute('data-location');
                
                if (name.includes(searchTerm) || location.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Refresh tournaments
        function refreshTournaments() {
            window.location.reload();
        }
        
        // Auto-activate tabs from URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash) {
                const tabTrigger = document.querySelector(`a[href="${hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
            
            // Save active tab to URL
            const tabEls = document.querySelectorAll('a[data-bs-toggle="tab"]');
            tabEls.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    history.pushState(null, null, e.target.hash);
                });
            });
            
            // Auto-refresh page every 60 seconds
            setTimeout(() => {
                if (window.location.pathname.includes('tournament_list.php')) {
                    window.location.reload();
                }
            }, 60000);
        });
    </script>
</body>
</html>