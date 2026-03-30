<?php
// config/database.php
require_once __DIR__ . '/env.php';

// Database configuration - từ Environment Variables
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_PORT', Env::get('DB_PORT', '3306'));
define('DB_NAME', Env::get('DB_NAME', 'bmb_tournaments'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));

// Application settings
define('APP_NAME', Env::get('APP_NAME', 'TRỌNG TÀI SỐ'));
define('APP_VERSION', Env::get('APP_VERSION', '2.0.0'));
define('UPLOAD_PATH', Env::get('UPLOAD_PATH', 'uploads/'));
define('MAX_FILE_SIZE', (int)Env::get('MAX_FILE_SIZE', 5 * 1024 * 1024));

// Tournament settings
define('DEFAULT_GROUPS', (int)Env::get('DEFAULT_GROUPS', 4));
define('MAX_GROUPS', (int)Env::get('MAX_GROUPS', 8));
define('MIN_TEAMS_PER_GROUP', (int)Env::get('MIN_TEAMS_PER_GROUP', 2));
define('MAX_TEAMS_PER_GROUP', (int)Env::get('MAX_TEAMS_PER_GROUP', 8));
?>
