<?php
/**
 * Database Configuration
 * MySQL connection via PDO
 */

// Helper function to read from .env if present
function getEnvVal(string $key, string $default): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $env[trim($name)] = trim($value);
            }
        }
    }
    return $env[$key] ?? $default;
}

define('DB_HOST',    getEnvVal('DB_HOST',    'localhost'));
define('DB_NAME',    getEnvVal('DB_NAME',    'mdm_system'));
define('DB_USER',    getEnvVal('DB_USER',    'root'));
define('DB_PASS',    getEnvVal('DB_PASS',    ''));
define('DB_CHARSET', 'utf8mb4');


/**
 * Get PDO database connection
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
    }
    
    return $pdo;
}
