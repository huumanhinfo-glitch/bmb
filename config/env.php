<?php
/**
 * Environment Configuration Loader
 * Load environment variables from .env file
 */

class Env {
    private static $loaded = false;
    private static $values = [];

    /**
     * Load .env file
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }

        $envFile = dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            self::$loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                    $value = $matches[1];
                }

                self::$values[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        $value = getenv($key);
        
        if ($value === false) {
            $value = $_ENV[$key] ?? ($_SERVER[$key] ?? null);
        }

        return $value !== null && $value !== '' ? $value : $default;
    }

    /**
     * Get database configuration
     */
    public static function getDB() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::get('DB_PORT', '3306'),
            'name' => self::get('DB_NAME', 'bmb_tournaments'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
            'charset' => 'utf8mb4'
        ];
    }

    /**
     * Get app configuration
     */
    public static function getApp() {
        return [
            'name' => self::get('APP_NAME', 'TRỌNG TÀI SỐ'),
            'version' => self::get('APP_VERSION', '2.0.0'),
            'env' => self::get('APP_ENV', 'production'),
            'debug' => self::get('APP_DEBUG', 'false') === 'true'
        ];
    }
}

// Auto-load environment
Env::load();
