<?php
// register.php - Trang đăng ký giải đấu cho VĐV
require_once 'db.php';
require_once 'components/template.php';

$tournamentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lấy thông tin giải đấu
$stmt = $pdo->prepare("SELECT * FROM Tournaments WHERE id = ?");
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    die("Giải đấu không tồn tại!");
}

// Lấy các nội dung thi đấu
$categories = $pdo->prepare("SELECT * FROM TournamentCategories WHERE tournament_id = ? AND status = 'open'");
$categories->execute([$tournamentId]);
$categories = $categories->fetchAll();

// Nếu chưa có categories, tạo mặc định
if (empty($categories)) {
    $defaultCategories = [
        ['Nam Doubles', 'male', 3.5],
        ['Nữ Doubles', 'female', 3.5],
        ['Mixed Doubles', 'mixed', 3.5]
    ];
    
    foreach ($defaultCategories as $cat) {
        $stmt = $pdo->prepare("INSERT INTO TournamentCategories (tournament_id, name, gender, skill_level, max_teams) VALUES (?, ?, ?, ?, 16)");
        $stmt->execute([$tournamentId, $cat[0], $cat[1], $cat[2]]);
    }
    
    $categories = $pdo->prepare("SELECT * FROM TournamentCategories WHERE tournament_id = ? AND status = 'open'");
    $categories->execute([$tournamentId]);
    $categories = $categories->fetchAll();
}

$message = '';
$success = false;

// Xử lý đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $team_name = trim($_POST['team_name'] ?? '');
    $skill_level = trim($_POST['skill_level'] ?? '');
    
    // Thông tin VĐV 1
    $player1_name = trim($_POST['player1_name'] ?? '');
    $player1_phone = trim($_POST['player1_phone'] ?? '');
    $player1_dob = $_POST['player1_dob'] ?? null;
    
    // Thông tin VĐV 2
    $player2_name = trim($_POST['player2_name'] ?? '');
    $player2_phone = trim($_POST['player2_phone'] ?? '');
    $player2_dob = $_POST['player2_dob'] ?? null;
    
    // Validation
    $errors = [];
    
    if (!$category_id) {
        $errors[] = "Vui lòng chọn nội dung thi đấu";
    }
    if (empty($team_name)) {
        $errors[] = "Vui lòng nhập tên đội";
    }
    if (empty($player1_name) || empty($player1_phone)) {
        $errors[] = "Vui lòng nhập đầy đủ thông tin VĐV 1";
    }
    if (empty($player2_name) || empty($player2_phone)) {
        $errors[] = "Vui lòng nhập đầy đủ thông tin VĐV 2";
    }
    
    // Kiểm tra SĐT đã đăng ký nội dung này chưa
    if ($category_id && ($player1_phone || $player2_phone)) {
        $checkPhone = $pdo->prepare("
            SELECT id FROM TournamentRegistrations 
            WHERE tournament_id = ? AND category_id = ? 
            AND (player1_phone = ? OR player2_phone = ? OR player1_phone = ? OR player2_phone = ?)
            AND status != 'cancelled'
        ");
        $checkPhone->execute([$tournamentId, $category_id, $player1_phone, $player1_phone, $player2_phone, $player2_phone]);
        
        if ($checkPhone->fetch()) {
            $errors[] = "Số điện thoại đã được đăng ký cho nội dung này!";
        }
    }
    
    // Kiểm tra số lượng đăng ký tối đa 3 nội dung
    if ($player1_phone) {
        $checkCount = $pdo->prepare("
            SELECT COUNT(DISTINCT category_id) as cnt FROM TournamentRegistrations 
            WHERE tournament_id = ? 
            AND (player1_phone = ? OR player2_phone = ?)
            AND status != 'cancelled'
        ");
        $checkCount->execute([$tournamentId, $player1_phone, $player1_phone]);
        $registeredCount = $checkCount->fetch()['cnt'] ?? 0;
        
        if ($registeredCount >= 3) {
            $errors[] = "Bạn đã đăng ký tối đa 3 nội dung trong giải đấu này!";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO TournamentRegistrations 
                (tournament_id, category_id, team_name, skill_level, player1_name, player1_phone, player1_dob, player2_name, player2_phone, player2_dob, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$tournamentId, $category_id, $team_name, $skill_level, $player1_name, $player1_phone, $player1_dob, $player2_name, $player2_phone, $player2_dob]);
            
            // Cập nhật số lượng đội trong category
            $pdo->prepare("UPDATE TournamentCategories SET current_teams = current_teams + 1 WHERE id = ?")->execute([$category_id]);
            
            $success = true;
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Đăng ký thành công! Chúng tôi sẽ liên hệ qua SĐT để xác nhận.</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Lỗi: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>' . implode('<br>', $errors) . '</div>';
    }
}

// Lấy danh sách đăng ký của SĐT (nếu có)
$myRegistrations = [];
if (isset($_POST['check_phone']) && !empty($_POST['check_phone'])) {
    $phone = trim($_POST['check_phone']);
    $myRegistrations = $pdo->prepare("
        SELECT r.*, c.name as category_name
        FROM TournamentRegistrations r
        LEFT JOIN TournamentCategories c ON r.category_id = c.id
        WHERE r.tournament_id = ? 
        AND (r.player1_phone = ? OR r.player2_phone = ?)
        AND r.status != 'cancelled'
        ORDER BY r.registered_at DESC
    ")->fetchAll();
}

$genderLabels = [
    'male' => 'Nam Doubles',
    'female' => 'Nữ Doubles',
    'mixed' => 'Mixed Doubles',
    'all' => 'Tất cả'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký <?php echo htmlspecialchars($tournament['name']); ?> - TRỌNG TÀI SỐ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2ecc71; --accent: #ff6b00; --text-dark: #1e293b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; color: var(--accent) !important; }
        .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2ecc71 100%); color: white; padding: 40px 0; }
        .page-title { font-size: 2rem; font-weight: 700; margin: 0; }
        .reg-card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; }
        .form-label { font-weight: 600; }
        .category-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .category-card:hover { border-color: var(--primary); }
        .category-card.selected { border-color: var(--accent); background: #fff8f0; }
        .category-card input { display: none; }
    </style>
</head>
<body>
    <?php renderNavbar('tournaments'); ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><i class="fas fa-edit me-2"></i>ĐĂNG KÝ GIẢI ĐẤU</h1>
            <p class="mt-2"><?php echo htmlspecialchars($tournament['name']); ?></p>
            <p class="mb-0">
                <i class="fas fa-calendar me-1"></i><?php echo $tournament['start_date']; ?> - <?php echo $tournament['end_date']; ?>
                <span class="ms-3"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($tournament['location'] ?? 'Chưa cập nhật'); ?></span>
            </p>
        </div>
    </div>

    <div class="container py-4">
        <?php echo $message; ?>
        
        <?php if ($success): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                <h3 class="mt-3">Đăng ký thành công!</h3>
                <p class="text-muted">Chúng tôi sẽ liên hệ qua SĐT để xác nhận và thu phí.</p>
                <a href="register.php?id=<?php echo $tournamentId; ?>" class="btn btn-primary">Đăng ký thêm nội dung khác</a>
                <a href="tournament_list.php" class="btn btn-outline-secondary">Về danh sách giải</a>
            </div>
        <?php else: ?>
        
        <!-- Quy định -->
        <div class="reg-card mb-4">
            <h5><i class="fas fa-info-circle me-2 text-info"></i>Quy định đăng ký</h5>
            <ul class="mb-0">
                <li>Mỗi SĐT chỉ được đăng ký <strong>1 lần</strong> cho mỗi nội dung thi đấu</li>
                <li>Mỗi SĐT được đăng ký tối đa <strong>3 nội dung</strong> trong 1 giải đấu</li>
                <li>Vui lòng điền đầy đủ thông tin chính xác để BTC liên hệ xác nhận</li>
            </ul>
        </div>

        <!-- Kiểm tra đăng ký -->
        <div class="reg-card mb-4">
            <h5><i class="fas fa-search me-2"></i>Kiểm tra đăng ký</h5>
            <form method="post" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="check_phone" class="form-control" placeholder="Nhập SĐT đã đăng ký" value="<?php echo htmlspecialchars($_POST['check_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary w-100">Tra cứu</button>
                </div>
            </form>
            
            <?php if (!empty($myRegistrations)): ?>
                <div class="mt-3">
                    <h6>Đăng ký của bạn:</h6>
                    <?php foreach ($myRegistrations as $reg): ?>
                        <div class="alert alert-info mb-2">
                            <strong><?php echo htmlspecialchars($reg['team_name']); ?></strong> - 
                            <?php echo htmlspecialchars($reg['category_name']); ?>
                            <span class="badge bg-<?php echo $reg['status'] === 'confirmed' ? 'success' : 'warning'; ?> ms-2">
                                <?php echo $reg['status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form đăng ký -->
        <div class="reg-card">
            <h5 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Thông tin đăng ký</h5>
            
            <form method="post">
                <input type="hidden" name="register" value="1">
                
                <!-- Chọn nội dung -->
                <div class="mb-4">
                    <label class="form-label">Chọn nội dung thi đấu <span class="text-danger">*</span></label>
                    <div class="row">
                        <?php foreach ($categories as $cat): ?>
                        <div class="col-md-4">
                            <label class="category-card w-100" onclick="selectCategory(<?php echo $cat['id']; ?>)">
                                <input type="radio" name="category_id" value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'checked' : ''; ?>>
                                <div class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?php echo $cat['current_teams']; ?>/<?php echo $cat['max_teams']; ?> đội
                                </small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Thông tin đội -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tên đội <span class="text-danger">*</span></label>
                        <input type="text" name="team_name" class="form-control" placeholder="Nhập tên đội" value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trình độ (skill level)</label>
                        <select name="skill_level" class="form-select">
                            <option value="">Chọn trình độ</option>
                            <option value="2.0" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '2.0') ? 'selected' : ''; ?>>2.0</option>
                            <option value="2.5" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '2.5') ? 'selected' : ''; ?>>2.5</option>
                            <option value="3.0" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '3.0') ? 'selected' : ''; ?>>3.0</option>
                            <option value="3.5" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '3.5') ? 'selected' : ''; ?>>3.5</option>
                            <option value="4.0" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '4.0') ? 'selected' : ''; ?>>4.0</option>
                            <option value="4.5" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '4.5') ? 'selected' : ''; ?>>4.5</option>
                            <option value="5.0" <?php echo (isset($_POST['skill_level']) && $_POST['skill_level'] == '5.0') ? 'selected' : ''; ?>>5.0</option>
                        </select>
                    </div>
                </div>

                <!-- VĐV 1 -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3"><i class="fas fa-user me-1"></i>Thông tin VĐV 1 <span class="text-danger">*</span></h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" name="player1_name" class="form-control" placeholder="Nhập họ tên" value="<?php echo htmlspecialchars($_POST['player1_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="player1_phone" class="form-control" placeholder="Nhập SĐT (dùng để xác nhận)" value="<?php echo htmlspecialchars($_POST['player1_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày sinh</label>
                            <input type="date" name="player1_dob" class="form-control" value="<?php echo htmlspecialchars($_POST['player1_dob'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- VĐV 2 -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3"><i class="fas fa-user me-1"></i>Thông tin VĐV 2 <span class="text-danger">*</span></h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" name="player2_name" class="form-control" placeholder="Nhập họ tên" value="<?php echo htmlspecialchars($_POST['player2_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="player2_phone" class="form-control" placeholder="Nhập SĐT" value="<?php echo htmlspecialchars($_POST['player2_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày sinh</label>
                            <input type="date" name="player2_dob" class="form-control" value="<?php echo htmlspecialchars($_POST['player2_dob'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane me-2"></i>Gửi đăng ký
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function selectCategory(id) {
        document.querySelectorAll('.category-card').forEach(card => {
            card.classList.remove('selected');
            card.querySelector('input').checked = false;
        });
        document.querySelector('.category-card:has(input[value="' + id + '"])').classList.add('selected');
        document.querySelector('input[value="' + id + '"]').checked = true;
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
