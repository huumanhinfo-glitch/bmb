<?php
// export.php
require_once 'functions.php';

$tournament = $_GET['tournament'] ?? null;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="teams_' . ($tournament ? str_replace(' ', '_', $tournament) : 'all') . '_' . date('Y-m-d') . '.csv"');

echo exportTeamsToCSV($tournament);
exit;
?>