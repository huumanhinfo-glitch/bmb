<?php
// draw_view.php

function renderDrawPage($data) {
    extract($data);
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quản lý Bốc thăm - TRỌNG TÀI SỐ</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <style>
            <?php include 'draw.css'; ?>
        </style>
    </head>
    <body class="draw-page">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
            <div class="container">
                <a class="navbar-brand fw-bold" href="index.php">
                    <i class="fas fa-basketball-ball me-2"></i>TRỌNG TÀI SỐ
                </a>
                <div class="navbar-nav">
                    <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Trang chủ</a>
                    <a class="nav-link" href="tournament_list.php"><i class="fas fa-trophy"></i> Giải đấu</a>
                    <a class="nav-link" href="matches.php"><i class="fas fa-basketball-ball"></i> Trận đấu</a>
                    <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                </div>
            </div>
        </nav>

        <!-- Header -->
        <header class="page-header">
            <div class="container">
                <h1 class="page-title">QUẢN LÝ BỐC THÂM & IMPORT</h1>
                <p class="lead">Import CSV, chia bảng đấu tự động, quản lý đội thi pickleball</p>
            </div>
        </header>

        <div class="container-fluid py-4">
            <?php echo $message; ?>
            
            <!-- Dashboard Stats -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card bg-primary">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-content">
                            <h3><?php echo $stats['totalTeams']; ?></h3>
                            <p>Đội thi đấu</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card bg-success">
                        <div class="card-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="card-content">
                            <h3><?php echo $stats['totalTournaments']; ?></h3>
                            <p>Giải đấu</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card bg-warning">
                        <div class="card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="card-content">
                            <h3><?php echo $stats['totalGroups']; ?></h3>
                            <p>Bảng đấu</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card bg-info">
                        <div class="card-icon">
                            <i class="fas fa-basketball-ball"></i>
                        </div>
                        <div class="card-content">
                            <h3><?php echo $stats['totalMatches']; ?></h3>
                            <p>Trận đấu</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Tabs -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="drawTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'import' ? 'active' : ''; ?>" 
                                    data-bs-toggle="tab" data-bs-target="#importTab">
                                <i class="fas fa-file-import me-2"></i>Import CSV
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'draw' ? 'active' : ''; ?>" 
                                    data-bs-toggle="tab" data-bs-target="#drawTab">
                                <i class="fas fa-random me-2"></i>Bốc thăm
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'teams' ? 'active' : ''; ?>" 
                                    data-bs-toggle="tab" data-bs-target="#teamsTab">
                                <i class="fas fa-users me-2"></i>Đội thi
                                <span class="badge bg-primary ms-1"><?php echo $stats['totalTeams']; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'groups' ? 'active' : ''; ?>" 
                                    data-bs-toggle="tab" data-bs-target="#groupsTab">
                                <i class="fas fa-layer-group me-2"></i>Bảng đấu
                                <span class="badge bg-primary ms-1"><?php echo $stats['totalGroups']; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#toolsTab">
                                <i class="fas fa-tools me-2"></i>Công cụ
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- Tab 1: Import CSV -->
                        <div class="tab-pane fade <?php echo $activeTab === 'import' ? 'show active' : ''; ?>" 
                             id="importTab">
                            <?php renderImportTab($excelData, $previewMode); ?>
                        </div>
                        
                        <!-- Tab 2: Bốc thăm -->
                        <div class="tab-pane fade <?php echo $activeTab === 'draw' ? 'show active' : ''; ?>" 
                             id="drawTab">
                            <?php renderDrawTab($tournaments); ?>
                        </div>
                        
                        <!-- Tab 3: Teams -->
                        <div class="tab-pane fade <?php echo $activeTab === 'teams' ? 'show active' : ''; ?>" 
                             id="teamsTab">
                            <?php renderTeamsTab($teams); ?>
                        </div>
                        
                        <!-- Tab 4: Groups -->
                        <div class="tab-pane fade <?php echo $activeTab === 'groups' ? 'show active' : ''; ?>" 
                             id="groupsTab">
                            <?php renderGroupsTab($groups); ?>
                        </div>
                        
                        <!-- Tab 5: Tools -->
                        <div class="tab-pane fade" id="toolsTab">
                            <?php renderToolsTab(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-dark text-white py-4 mt-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5>TRỌNG TÀI SỐ</h5>
                        <p class="mb-0">Hệ thống quản lý giải đấu pickleball chuyên nghiệp</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="index.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Quay về trang chủ
                        </a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            <?php include 'draw.js'; ?>
        </script>
    </body>
    </html>
    <?php
}

// Import Tab
function renderImportTab($excelData, $previewMode) {
    if ($previewMode && !empty($excelData)) {
        renderImportPreview($excelData);
    } else {
        renderImportForm();
    }
}

function renderImportForm() {
    ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-upload text-primary me-2"></i>Upload File CSV
                    </h4>
                    
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-zone mb-4" id="dropZone">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h5>Kéo thả file CSV vào đây</h5>
                            <p class="text-muted">hoặc click để chọn file</p>
                            <input type="file" class="d-none" name="excel" id="csvFile" accept=".csv" required>
                            <button type="button" class="btn btn-primary mt-3" onclick="document.getElementById('csvFile').click()">
                                <i class="fas fa-folder-open me-2"></i>Chọn File
                            </button>
                        </div>
                        
                        <div id="fileInfo" class="mb-4"></div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg py-3">
                                <i class="fas fa-upload me-2"></i>Upload & Preview
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-info me-2"></i>Hướng dẫn định dạng CSV
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tên đội</th>
                                    <th>VĐV 1</th>
                                    <th>VĐV 2</th>
                                    <th>Giải đấu</th>
                                    <th>Trình độ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Team01</td>
                                    <td>Nguyễn Văn A</td>
                                    <td>Trần Thị B</td>
                                    <td>BMB Super Cup</td>
                                    <td>3.0</td>
                                </tr>
                                <tr>
                                    <td>Team02</td>
                                    <td>Lê Văn C</td>
                                    <td>Phạm Thị D</td>
                                    <td>Giải Mixed</td>
                                    <td>3.5</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Mẹo sử dụng
                    </h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>File CSV chuẩn:</strong> Dùng dấu phẩy phân cách
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Encoding:</strong> UTF-8 để hiển thị tiếng Việt
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Giải đấu mới:</strong> Hệ thống tự tạo nếu chưa có
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Trình độ:</strong> 2.5, 3.0, 3.5, 4.0, 4.5, 5.0
                        </li>
                        <li>
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Preview:</strong> Kiểm tra trước khi import
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-download text-success me-2"></i>Template mẫu
                    </h5>
                    <p>Tải về file CSV mẫu để làm theo định dạng:</p>
                    <a href="template.csv" class="btn btn-outline-success w-100" download>
                        <i class="fas fa-file-download me-2"></i>Tải Template CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderImportPreview($excelData) {
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">
                    <i class="fas fa-eye text-primary me-2"></i>Preview Dữ Liệu
                </h4>
                <span class="badge bg-primary fs-6"><?php echo count($excelData); ?> đội</span>
            </div>
            
            <div class="table-responsive mb-4">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tên đội</th>
                            <th>VĐV 1</th>
                            <th>VĐV 2</th>
                            <th>Giải đấu</th>
                            <th>Trình độ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($excelData as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['team_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['player1']); ?></td>
                            <td><?php echo htmlspecialchars($item['player2']); ?></td>
                            <td>
                                <?php if(!empty($item['tournament_name'])): ?>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($item['tournament_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($item['skill_level'])): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($item['skill_level']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <form method="post" class="h-100">
                        <input type="hidden" name="teams_data" value='<?php echo json_encode($excelData, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                        <button type="submit" name="confirm_import" class="btn btn-success btn-lg w-100 h-100 py-3">
                            <i class="fas fa-check me-2"></i>Xác nhận Import
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <a href="draw.php" class="btn btn-outline-secondary btn-lg w-100 h-100 py-3">
                        <i class="fas fa-times me-2"></i>Hủy & Quay lại
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Draw Tab
function renderDrawTab($tournaments) {
    ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-random text-warning me-2"></i>Bốc thăm chia bảng
                    </h4>
                    
                    <form method="post" id="drawForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-trophy me-2"></i>Chọn giải đấu
                                </label>
                                <select class="form-select form-select-lg" name="tournament_filter" required>
                                    <option value="">-- Chọn giải đấu --</option>
                                    <?php foreach($tournaments as $tournament): ?>
                                    <option value="<?php echo $tournament['id']; ?>">
                                        <?php echo htmlspecialchars($tournament['name']); ?>
                                        <?php if($tournament['status'] == 'upcoming'): ?>
                                        <span class="badge bg-info float-end">Sắp diễn ra</span>
                                        <?php elseif($tournament['status'] == 'ongoing'): ?>
                                        <span class="badge bg-success float-end">Đang diễn ra</span>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-layer-group me-2"></i>Số lượng bảng
                                </label>
                                <input type="number" class="form-control form-control-lg" 
                                       name="num_groups" value="4" min="2" max="8" required>
                                <small class="text-muted">Từ 2 đến 8 bảng</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-cogs me-2"></i>Phương thức bốc thăm
                            </label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="draw_method" value="random" id="randomDraw" checked>
                                        <label class="form-check-label" for="randomDraw">
                                            <i class="fas fa-random me-1"></i>Ngẫu nhiên
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="draw_method" value="seeded" id="seededDraw">
                                        <label class="form-check-label" for="seededDraw">
                                            <i class="fas fa-seedling me-1"></i>Hạt giống
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="draw_method" value="balanced" id="balancedDraw">
                                        <label class="form-check-label" for="balancedDraw">
                                            <i class="fas fa-balance-scale me-1"></i>Cân bằng
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Lưu ý:</strong> Việc bốc thăm sẽ xóa toàn bộ bảng đấu cũ và tạo bảng mới cho giải đã chọn.
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" name="action" value="draw" 
                                    class="btn btn-warning btn-lg py-3">
                                <i class="fas fa-random me-2"></i>Tiến hành bốc thăm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-calculator text-primary me-2"></i>Tính toán số trận
                    </h5>
                    <div class="mb-3">
                        <label>Số đội trong bảng:</label>
                        <input type="range" class="form-range" min="3" max="8" value="4" 
                               id="teamCountSlider">
                        <div class="text-center">
                            <span id="teamCount" class="fw-bold fs-4">4</span> đội/bảng
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6>Số trận đấu:</h6>
                        <p class="mb-1">Vòng tròn: <span id="roundRobinMatches">6</span> trận</p>
                        <p class="mb-1">Playoff: <span id="playoffMatches">2</span> trận</p>
                        <p>Tổng: <strong><span id="totalMatches">8</span> trận/bảng</strong></p>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-history text-success me-2"></i>Lịch sử bốc thăm
                    </h5>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar me-2"></i>
                            BMB Super Cup - 4 bảng (12/12/2024)
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar me-2"></i>
                            Mixed Doubles - 3 bảng (05/12/2024)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Teams Tab
function renderTeamsTab($teams) {
    global $pdo;
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">
                    <i class="fas fa-users text-success me-2"></i>Danh sách đội thi
                </h4>
                <div>
                    <button class="btn btn-primary" onclick="exportData('teams', 'csv')">
                        <i class="fas fa-download me-2"></i>Xuất CSV
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                        <i class="fas fa-plus me-2"></i>Thêm đội
                    </button>
                </div>
            </div>
            
            <?php if (empty($teams)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h4>Chưa có đội nào trong hệ thống</h4>
                <p>Hãy import từ CSV hoặc tạo dữ liệu mẫu!</p>
                <div class="mt-3">
                    <a href="#importTab" class="btn btn-primary me-2" onclick="switchTab('importTab')">
                        <i class="fas fa-file-import me-2"></i>Import CSV
                    </a>
                    <form method="post" class="d-inline">
                        <button type="submit" name="action" value="create_sample" 
                                class="btn btn-outline-primary">
                            <i class="fas fa-magic me-2"></i>Tạo dữ liệu mẫu
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Tên đội</th>
                            <th>Vận động viên</th>
                            <th>Giải đấu</th>
                            <th>Trình độ</th>
                            <th>Bảng</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $controller = new DrawController($pdo);
                        foreach($teams as $index => $team): 
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                            </td>
                            <td>
                                <div class="small">
                                    <div><?php echo htmlspecialchars($team['player1']); ?></div>
                                    <div><?php echo htmlspecialchars($team['player2']); ?></div>
                                </div>
                            </td>
                            <td>
                                <?php if($team['tournament_name']): ?>
                                <span class="badge <?php echo $controller->getTournamentBadgeClass($team['tournament_name']); ?>">
                                    <?php echo htmlspecialchars($team['tournament_name']); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($team['skill_level']): ?>
                                <span class="badge bg-info"><?php echo $team['skill_level']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($team['group_name']): ?>
                                <span class="badge bg-warning">Bảng <?php echo $team['group_name']; ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Chưa xếp</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editTeam(<?php echo $team['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteTeam(<?php echo $team['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <span class="text-muted">
                        Hiển thị <?php echo count($teams); ?> đội
                    </span>
                </div>
                <div>
                    <button class="btn btn-outline-primary" onclick="printTeams()">
                        <i class="fas fa-print me-2"></i>In danh sách
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Groups Tab
function renderGroupsTab($groups) {
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">
                    <i class="fas fa-layer-group text-warning me-2"></i>Các bảng đấu
                </h4>
                <div>
                    <button class="btn btn-primary" onclick="exportData('groups', 'csv')">
                        <i class="fas fa-download me-2"></i>Xuất bảng
                    </button>
                    <button class="btn btn-success" onclick="window.location.href='matches.php'">
                        <i class="fas fa-calendar-alt me-2"></i>Xem lịch đấu
                    </button>
                </div>
            </div>
            
            <?php if (empty($groups)): ?>
            <div class="text-center py-5">
                <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                <h4>Chưa có bảng đấu nào</h4>
                <p>Hãy bốc thăm để tạo bảng đấu tự động!</p>
                <a href="#drawTab" class="btn btn-primary mt-3" onclick="switchTab('drawTab')">
                    <i class="fas fa-random me-2"></i>Bốc thăm ngay
                </a>
            </div>
            <?php else: ?>
            <div class="accordion" id="groupsAccordion">
                <?php 
                $tournaments = [];
                foreach ($groups as $group) {
                    $tournamentName = $group['tournament_name'] ?? 'Không xác định';
                    if (!isset($tournaments[$tournamentName])) {
                        $tournaments[$tournamentName] = [];
                    }
                    $tournaments[$tournamentName][] = $group;
                }
                
                $accordionIndex = 0;
                foreach ($tournaments as $tournamentName => $tournamentGroups): 
                ?>
                <div class="accordion-item mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?php echo $accordionIndex > 0 ? 'collapsed' : ''; ?>" 
                                type="button" data-bs-toggle="collapse" 
                                data-bs-target="#collapse<?php echo $accordionIndex; ?>">
                            <i class="fas fa-trophy me-2 text-primary"></i>
                            <?php echo htmlspecialchars($tournamentName); ?>
                            <span class="badge bg-primary ms-2"><?php echo count($tournamentGroups); ?> bảng</span>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $accordionIndex; ?>" 
                         class="accordion-collapse collapse <?php echo $accordionIndex === 0 ? 'show' : ''; ?>" 
                         data-bs-parent="#groupsAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <?php foreach($tournamentGroups as $group): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card group-card">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0">
                                                <i class="fas fa-chess-board me-2"></i>
                                                Bảng <?php echo $group['group_name']; ?>
                                                <span class="badge bg-primary float-end">
                                                    <?php echo count($group['teams']); ?> đội
                                                </span>
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="team-list">
                                                <?php foreach($group['teams'] as $index => $team): ?>
                                                <div class="team-item">
                                                    <div class="team-rank">#<?php echo $index + 1; ?></div>
                                                    <div class="flex-grow-1">
                                                        <div class="team-name">
                                                            <?php echo htmlspecialchars($team['team_name']); ?>
                                                        </div>
                                                        <div class="team-players">
                                                            <?php echo htmlspecialchars($team['player1']); ?>
                                                            <?php if ($team['player2']): ?>
                                                             & <?php echo htmlspecialchars($team['player2']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <?php if ($team['skill_level']): ?>
                                                        <span class="skill-badge"><?php echo $team['skill_level']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <a href="matches.php?group=<?php echo $group['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary w-100">
                                                    <i class="fas fa-eye me-1"></i>Xem trận đấu
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                $accordionIndex++;
                endforeach; 
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Tools Tab
function renderToolsTab() {
    ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-download text-success me-2"></i>Xuất dữ liệu
                    </h5>
                    <p class="text-muted">Xuất dữ liệu ra file để sao lưu hoặc phân tích</p>
                    
                    <div class="d-grid gap-3 mt-4">
                        <button class="btn btn-outline-success py-3" onclick="exportData('teams', 'csv')">
                            <i class="fas fa-users me-2"></i>Xuất danh sách đội
                        </button>
                        <button class="btn btn-outline-info py-3" onclick="exportData('groups', 'csv')">
                            <i class="fas fa-layer-group me-2"></i>Xuất danh sách bảng
                        </button>
                        <button class="btn btn-outline-warning py-3" onclick="exportData('matches', 'csv')">
                            <i class="fas fa-calendar-alt me-2"></i>Xuất lịch thi đấu
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-trash text-danger me-2"></i>Xóa dữ liệu
                    </h5>
                    <p class="text-muted">Cảnh báo: Hành động này không thể hoàn tác!</p>
                    
                    <div class="d-grid gap-3 mt-4">
                        <button class="btn btn-outline-danger py-3" onclick="clearData('teams')">
                            <i class="fas fa-users-slash me-2"></i>Xóa tất cả đội
                        </button>
                        <button class="btn btn-outline-danger py-3" onclick="clearData('groups')">
                            <i class="fas fa-layer-group me-2"></i>Xóa tất cả bảng
                        </button>
                        <button class="btn btn-outline-danger py-3" onclick="clearData('matches')">
                            <i class="fas fa-calendar-times me-2"></i>Xóa tất cả trận đấu
                        </button>
                        <button class="btn btn-outline-danger py-3" onclick="clearData('all')">
                            <i class="fas fa-bomb me-2"></i>Xóa tất cả dữ liệu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-magic text-primary me-2"></i>Hành động nhanh
                    </h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="action-card" onclick="switchTab('importTab')">
                                <div class="action-icon">
                                    <i class="fas fa-file-import"></i>
                                </div>
                                <div class="action-title">Import CSV</div>
                                <div class="action-desc">Thêm đội từ file</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="action-card" onclick="switchTab('drawTab')">
                                <div class="action-icon">
                                    <i class="fas fa-random"></i>
                                </div>
                                <div class="action-title">Bốc thăm</div>
                                <div class="action-desc">Chia bảng tự động</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <form method="post" class="h-100">
                                <input type="hidden" name="action" value="create_sample">
                                <button type="submit" class="action-card w-100 h-100 border-0 bg-transparent">
                                    <div class="action-icon">
                                        <i class="fas fa-magic"></i>
                                    </div>
                                    <div class="action-title">Dữ liệu mẫu</div>
                                    <div class="action-desc">Tạo dữ liệu demo</div>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderErrorPage($error) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lỗi hệ thống</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle"></i> Đã xảy ra lỗi</h4>
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="draw.php" class="btn btn-primary">Quay lại trang chính</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>