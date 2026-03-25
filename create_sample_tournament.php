<?php
require_once 'db.php';

$tournamentName = "Giải Pickleball Mở Rộng 2026";
$teams = [
    ["Lê Minh Đức", "Nguyễn Hoàng Long"],
    ["Trần Văn A", "Phạm Quốc B"],
    ["Vũ Minh Cường", "Đỗ Xuân D"],
    ["Bùi Thị E", "Hoàng Văn F"],
    ["Ngô Thị G", "Lý Văn H"],
    ["Đặng Văn I", "Vương Thị J"],
    ["Trịnh Văn K", "Lê Thị L"],
    ["Phan Văn M", "Nguyễn Thị N"],
    ["Hoàng Văn O", "Trần Thị P"],
    ["Lê Văn Q", "Phạm Văn R"],
    ["Vũ Văn S", "Đỗ Văn T"],
    ["Nguyễn Văn U", "Bùi Văn V"],
    ["Hoàng Thị X", "Ngô Văn Y"],
    ["Trần Văn Z", "Lê Thị AA"],
    ["Phạm Văn AB", "Vũ Văn AC"],
    ["Đỗ Thị AD", "Nguyễn Văn AE"],
    ["Lý Văn AF", "Trần Văn AG"],
    ["Vương Văn AH", "Phạm Văn AI"],
    ["Trịnh Thị AJ", "Hoàng Văn AK"],
    ["Nguyễn Thị AL", "Lê Văn AM"],
    ["Bùi Văn AN", "Vũ Thị AO"],
    ["Đặng Văn AP", "Ngô Văn AQ"],
    ["Phan Văn AR", "Trịnh Văn AS"],
    ["Hoàng Thị AT", "Lý Văn AU"],
    ["Nguyễn Văn AV", "Phạm Thị AW"],
    ["Trần Văn AX", "Vũ Văn AY"],
    ["Lê Văn AZ", "Đỗ Văn BA"],
    ["Vũ Thị BB", "Nguyễn Văn BC"],
    ["Ngô Văn BD", "Hoàng Văn BE"],
    ["Trần Thị BF", "Phạm Văn BG"],
    ["Đặng Văn BH", "Bùi Thị BI"],
];

$stmt = $pdo->prepare("INSERT INTO Tournaments (name, format, status, start_date, location, description) VALUES (?, 'combined', 'upcoming', '2026-04-15', 'Sân Pickleball TP.HCM', 'Giải đấu pickleball 2v2 dành cho tất cả các vận động viên')");
$stmt->execute([$tournamentName]);
$tournamentId = $pdo->lastInsertId();

echo "Đã tạo giải đấu: $tournamentName (ID: $tournamentId)<br>";

$count = 0;
foreach ($teams as $team) {
    $stmt = $pdo->prepare("INSERT INTO Teams (tournament_id, team_name, player1, player2, skill_level) VALUES (?, ?, ?, ?, '3.5')");
    $stmt->execute([$tournamentId, "Đội " . ($count + 1), $team[0], $team[1]]);
    $count++;
}

echo "Đã thêm $count đội (62 vận động viên)";
?>
