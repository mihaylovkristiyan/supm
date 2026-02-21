<?php

require_once 'db_connect.php';

require_once 'security_headers.php';



// Function to check if user is logged in

function isLoggedIn() {

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

}



// Function to check if user is admin

function isAdmin() {

    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

}



// Function to require login

function requireLogin() {

    if (!isLoggedIn()) {

        header('Location: /login.php?system=Probi');

        exit;

    }

}



// Function to require admin role

function requireAdmin() {

    requireLogin();

    if (!isAdmin()) {

        header('Location: /unauthorized.php');

        exit;

    }

}



// Function to verify login credentials

function verifyLogin($username, $password) {

    global $pdo;

    

    try {

        $stmt = $pdo->prepare("

            SELECT id, username, password_hash, full_name, role 

            FROM users 

            WHERE username = ? AND is_active = TRUE

        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch();



        if ($user && password_verify($password, $user['password_hash'])) {

            // Update last login time

            $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");

            $update_stmt->execute([$user['id']]);



            // Set session variables

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['username'] = $user['username'];

            $_SESSION['full_name'] = $user['full_name'];

            $_SESSION['user_role'] = $user['role'];

            

            // Regenerate session ID for security

            session_regenerate_id(true);

            

            return true;

        }

        

        return false;

    } catch (PDOException $e) {

        error_log("Login error: " . $e->getMessage());

        return false;

    }

}



// Function to log out user

function logout() {

    // Unset all session variables

    $_SESSION = array();



    // Destroy the session cookie

    if (isset($_COOKIE[session_name()])) {

        setcookie(session_name(), '', time() - 3600, '/');

    }



    // Destroy the session

    session_destroy();

}



// Function to change password

function changePassword($user_id, $current_password, $new_password) {

    global $pdo;

    

    try {

        // Verify current password

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");

        $stmt->execute([$user_id]);

        $user = $stmt->fetch();



        if (!$user || !password_verify($current_password, $user['password_hash'])) {

            return false;

        }



        // Update to new password

        $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

        $stmt->execute([$new_hash, $user_id]);



        return true;

    } catch (PDOException $e) {

        error_log("Password change error: " . $e->getMessage());

        return false;

    }

}



// CSRF Protection Functions

function generateCSRFToken() {

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    }

    return $_SESSION['csrf_token'];

}



function validateCSRFToken($token) {

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {

        header('HTTP/1.1 403 Forbidden');

        die('Invalid CSRF token');

    }

    return true;

}



// Add CSRF token to all forms automatically

function outputCSRFToken() {

    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';

}



// Validate POST requests

function validatePOSTRequest() {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!isset($_POST['csrf_token'])) {

            header('HTTP/1.1 403 Forbidden');

            die('CSRF token missing');

        }

        validateCSRFToken($_POST['csrf_token']);

    }

}



// Add permission checking

function hasPermission($permission) {

    // Default permissions for all authenticated users

    $default_permissions = [

        'view_reports',

        'manage_samples',

        'manage_protocols',

        'manage_invoices',

        'manage_animal_objects'

    ];

    

    return in_array($permission, $default_permissions);

}



// Input validation function

function validateInput($data) {

    if (is_array($data)) {

        return array_map('validateInput', $data);

    }

    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');

}



// Function to verify CSRF token

function verifyCSRFToken($token) {

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

} 