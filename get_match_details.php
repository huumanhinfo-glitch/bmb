<?php
// get_match_details.php - AJAX endpoint for match details
require_once 'functions.php';

$matchId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($matchId <= 0) {
    echo '<div class="alert alert-danger">ID trận đấu không hợp lệ!</div>';
    exit;
}

$match = getMatchById($matchId);

if (!$match) {
    echo '<div class="alert alert-danger">Không tìm thấy thông tin trận đấu!</div>';
    exit;
}

// Get team details
$team1 = getTeamInfo($match['team1_id']);
$team2 = getTeamInfo($match['team2_id']);
?>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <h5 class="card-title text-primary">ĐỘI 1 - <?php echo htmlspecialchars($match['team1_name']); ?></h6>
                <div class="mb-2">
                    <strong>VĐV 1:</strong> <?php echo htmlspecialchars($team1['player1']); ?>
                </div>
                <div class="mb-2">
                    <strong>VĐV 2:</strong> <?php echo htmlspecialchars($team1['player2']); ?>
                </div>
                <div class="mb-2">
                    <strong>Trình độ:</strong> 
                    <span class="badge bg-info"><?php echo $team1['skill_level'] ?: 'N/A'; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <h5 class="card-title text-primary">ĐỘI 2 - <?php echo htmlspecialchars($match['team2_name']); ?></h5>
                <div class="mb-2">
                    <strong>VĐV 1:</strong> <?php echo htmlspecialchars($team2['player1']); ?>
                </div>
                <div class="mb-2">
                    <strong>VĐV 2:</strong> <?php echo htmlspecialchars($team2['player2']); ?>
                </div>
                <div class="mb-3">
                    <strong>Trình độ:</strong> 
                    <span class="badge bg-info"><?php echo $team2['skill_level'] ?: 'N/A'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>THÔNG TIN TRẬN ĐẤU
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Vòng/Bảng:</strong> <?php echo $match['round'] ?: 'Chưa xác định'; ?></p>
                        <?php if ($match['group_name']): ?>
                        <p><strong>Bảng:</strong> <?php echo $match['group_name']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($match['match_date']): ?>
                        <p><strong>Ngày giờ:</strong> <?php echo date('d/m/Y H:i', strtotime($match['match_date'])); ?></p>
                        <?php endif; ?>
                        <?php if ($match['court']): ?>
                        <p><strong>Sân:</strong> <?php echo $match['court']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <form method="post" action="matches.php">
            <input type="hidden" name="match_id" value="<?php echo $matchId; ?>">
            
            <div class="card border-0">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-edit me-2"></i>CẬP NHẬT KẾT QUẢ
                    </h5>
                    
                    <div class="row align-items-center">
                        <div class="col-md-5 text-center">
                            <h6><?php echo htmlspecialchars($match['team1_name']); ?></h6>
                        </div>
                        
                        <div class="col-md-2 text-center">
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <div class="form-group">
                                    <input type="number" name="score1" class="form-control form-control-lg score-input" 
                                           value="<?php echo $match['score1'] ?? 0; ?>" min="0" required>
                                </div>
                                <span class="fs-3">-</span>
                                <div class="form-group">
                                    <input type="number" name="score2" class="form-control form-control-lg score-input" 
                                           value="<?php echo $match['score2'] ?? 0; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5 text-center">
                            <h6><?php echo htmlspecialchars($match['team2_name']); ?></h6>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="update_score" class="btn btn-primary-custom btn-lg">
                            <i class="fas fa-save me-2"></i>LƯU KẾT QUẢ
                        </button>
                        <button type="button" class="btn btn-outline-custom btn-lg ms-2" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>ĐÓNG
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="text-center mt-3">
    <small class="text-muted">
        <i class="fas fa-info-circle me-1"></i>
        Cập nhật kết quả sẽ tự động tính lại bảng xếp hạng
    </small>
</div>