<?php

function handleRequest() {
    // Get CSRF token from either GET or POST
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    
    // Validate CSRF token if it exists
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $token !== null) {
        if (!verifyCSRFToken($token)) {
            die('Invalid CSRF token');
        }
    }
    
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php?system=Probi');
        exit;
    }
    
    // Sanitize input
    $_GET = validateInput($_GET);
    $_POST = validateInput($_POST);
    
    // Set default response headers
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}