<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Ensure we have a valid database connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection error. Please contact the administrator.");
}

// Start output buffering to prevent headers already sent error
ob_start();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Невалидна сесия. Моля, опитайте отново.';
    header('Location: new_request.php');
    exit;
}

// Check if all required fields are present
if (!isset($_POST['star_jo']) || !isset($_POST['date']) || !isset($_POST['taker_id'])) {
    $_SESSION['error_message'] = 'Всички полета са задължителни.';
    header('Location: new_request.php');
    exit;
}

// Sanitize input using htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
$star_jo = htmlspecialchars($_POST['star_jo'], ENT_QUOTES, 'UTF-8');
$date = htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8');
$taker_id = filter_var($_POST['taker_id'], FILTER_SANITIZE_NUMBER_INT);

// Convert date from d.m.Y to Y-m-d format
$dateObj = DateTime::createFromFormat('d.m.Y', $date);
if (!$dateObj) {
    $_SESSION['error_message'] = 'Невалиден формат на датата.';
    header('Location: new_request.php');
    exit;
}
$mysqlDate = $dateObj->format('Y-m-d');

try {
    // Insert the new request
    $stmt = $pdo->prepare("INSERT INTO request_sample (star_jo, date, taker_id) VALUES (?, ?, ?)");
    $stmt->execute([$star_jo, $mysqlDate, $taker_id]);

    $_SESSION['success_message'] = 'Заявката беше успешно създадена.';
    header('Location: list.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Грешка при създаване на заявката: ' . $e->getMessage();
    header('Location: new_request.php');
    exit;
}

// Clean the output buffer
ob_end_clean(); 