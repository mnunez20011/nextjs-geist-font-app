<?php
/**
 * Database Configuration File
 * PHP Version 7.4
 */

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Change in production
define('DB_PASS', '');         // Change in production
define('DB_NAME', 'cheque_management');

// Application settings
define('APP_NAME', 'Sistema de Gestión de Cheques');
define('APP_VERSION', '1.0.0');
define('SITE_URL', 'http://localhost:8000/backend/'); // Update based on your server configuration

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Error: No se pudo conectar a la base de datos.");
}

// Time zone setting
date_default_timezone_set('America/Guayaquil'); // Adjust to your timezone
