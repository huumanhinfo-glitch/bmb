<?php
// components/template.php - Template chung cho tất cả các trang

function renderNavbar($activePage = '') {
    $userRole = $_SESSION['role'] ?? '';
    $userName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    $isLoggedIn = isset($_SESSION['user_id']);
    $isAdmin = in_array($userRole, ['admin', 'manager']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-trophy me-2"></i>TRỌNG TÀI SỐ</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <a class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-home me-1"></i>Trang chủ
                </a>
                <a class="nav-link <?php echo $activePage === 'tournaments' ? 'active' : ''; ?>" href="tournament_list.php">
                    <i class="fas fa-trophy me-1"></i>Giải đấu
                </a>
                <a class="nav-link <?php echo $activePage === 'matches' ? 'active' : ''; ?>" href="matches.php">
                    <i class="fas fa-bullhorn me-1"></i>Trận đấu
                </a>
                <a class="nav-link <?php echo $activePage === 'control' ? 'active' : ''; ?>" href="match-control.php">
                    <i class="fas fa-whistle me-1"></i>Điều hành
                </a>
                <?php if ($isAdmin): ?>
                <a class="nav-link <?php echo $activePage === 'admin' ? 'active' : ''; ?>" href="admin.php">
                    <i class="fas fa-cog me-1"></i>Quản trị
                </a>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($userName); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-1"></i>Hồ sơ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Đăng xuất</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                </a>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php
}

function renderHeader($title = 'TRỌNG TÀI SỐ', $activePage = '') {
    $userRole = $_SESSION['role'] ?? '';
    $userName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    $isLoggedIn = isset($_SESSION['user_id']);
    $isAdmin = in_array($userRole, ['admin', 'manager']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 30px 0; }
        .page-title { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; border-bottom: 3px solid var(--primary); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; }
        .card-custom { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        .card-custom:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: #27ae60; }
        .nav-tab { padding: 12px 20px; border: none; background: transparent; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; }
        .nav-tab:hover, .nav-tab.active { color: var(--accent); border-bottom-color: var(--accent); text-decoration: none; }
        .badge-status { font-size: 0.75rem; padding: 5px 10px; }
        .stage-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 20px; background: #e9ecef; }
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-trophy me-2"></i>TRỌNG TÀI SỐ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <a class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home me-1"></i>Trang chủ
                    </a>
                    <a class="nav-link <?php echo $activePage === 'tournaments' ? 'active' : ''; ?>" href="tournament_list.php">
                        <i class="fas fa-trophy me-1"></i>Giải đấu
                    </a>
                    <a class="nav-link <?php echo $activePage === 'matches' ? 'active' : ''; ?>" href="matches.php">
                        <i class="fas fa-bullhorn me-1"></i>Trận đấu
                    </a>
                    <a class="nav-link <?php echo $activePage === 'control' ? 'active' : ''; ?>" href="match-control.php">
                        <i class="fas fa-whistle me-1"></i>Điều hành
                    </a>
                    <?php if ($isAdmin): ?>
                    <a class="nav-link <?php echo $activePage === 'admin' ? 'active' : ''; ?>" href="admin.php">
                        <i class="fas fa-cog me-1"></i>Quản trị
                    </a>
                    <?php endif; ?>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-1"></i>Hồ sơ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                    </a>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
<?php
}

function renderFooter() {
?>
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 TRỌNG TÀI SỐ - Quản lý giải đấu Pickleball</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function renderPageHeader($title, $subtitle = '') {
?>
    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><i class="fas fa-trophy me-2"></i><?php echo $title; ?></h1>
            <?php if ($subtitle): ?>
                <p class="mb-0 mt-2"><?php echo $subtitle; ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php
}

function renderStatsRow($stats) {
?>
    <div class="row mb-4">
        <?php foreach ($stats as $stat): ?>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stat['value']; ?></div>
                <div class="stat-label"><?php echo $stat['label']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php
}

function renderFilterBar($filters) {
?>
    <div class="card-custom mb-4">
        <form method="get" class="row g-3">
            <?php foreach ($filters as $filter): ?>
            <div class="col-md-<?php echo $filter['col'] ?? '3'; ?>">
                <label class="form-label"><?php echo $filter['label']; ?></label>
                <?php if ($filter['type'] === 'select'): ?>
                    <select name="<?php echo $filter['name']; ?>" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($filter['options'] as $opt): ?>
                            <option value="<?php echo $opt['value']; ?>" <?php echo ($filter['value'] ?? '') == $opt['value'] ? 'selected' : ''; ?>>
                                <?php echo $opt['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($filter['type'] === 'text'): ?>
                    <input type="text" name="<?php echo $filter['name']; ?>" class="form-control" value="<?php echo $filter['value'] ?? ''; ?>" placeholder="<?php echo $filter['placeholder'] ?? ''; ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="col-md-auto d-flex align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Tìm</button>
                <?php if (!empty($filters[0]['value'])): ?>
                    <a href="?" class="btn btn-outline-secondary ms-2"><i class="fas fa-redo me-1"></i>Xóa</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php
}

function renderTabs($tabs, $activeTab) {
?>
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($tabs as $tab): ?>
        <li class="nav-item">
            <a class="nav-tab <?php echo $activeTab === $tab['id'] ? 'active' : ''; ?>" href="<?php echo $tab['url']; ?>">
                <?php if (!empty($tab['icon'])): ?><i class="fas fa-<?php echo $tab['icon']; ?> me-1"></i><?php endif; ?>
                <?php echo $tab['label']; ?>
                <?php if (isset($tab['badge'])): ?><span class="badge bg-secondary ms-1"><?php echo $tab['badge']; ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
<?php
}
