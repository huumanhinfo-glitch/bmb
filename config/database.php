<?php
// config/database.php

// Database configuration - LOCAL
define('DB_HOST', 'localhost');
define('DB_NAME', 'bmb_tournaments');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'TRỌNG TÀI SỐ');
define('APP_VERSION', '2.0.0');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Tournament settings
define('DEFAULT_GROUPS', 4);
define('MAX_GROUPS', 8);
define('MIN_TEAMS_PER_GROUP', 2);
define('MAX_TEAMS_PER_GROUP', 8);
?>
