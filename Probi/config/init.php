<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Database connection
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=supmonli_db;charset=utf8mb4",
        "supmonli_admin",
        "fckTT7e}UG)A",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    error_log("Connection details: host=localhost, dbname=supmonli_db, user=supmonli_admin");
    header('HTTP/1.1 500 Internal Server Error');
    die('Database connection failed: ' . $e->getMessage());
}

// Include auth functions first
require_once __DIR__ . '/auth.php';

// Load request handler
require_once __DIR__ . '/request_handler.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}