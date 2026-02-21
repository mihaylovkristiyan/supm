<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Невалидна сесия. Моля, опитайте отново.';
    header('Location: list.php');
    exit;
}

// Check if all required fields are present
if (!isset($_POST['id']) || !isset($_POST['star_jo']) || !isset($_POST['date']) || !isset($_POST['taker_id'])) {
    $_SESSION['error_message'] = 'Всички полета са задължителни.';
    header('Location: list.php');
    exit;
}

// Sanitize input
$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
$star_jo = htmlspecialchars(trim($_POST['star_jo']), ENT_QUOTES, 'UTF-8');
$date = htmlspecialchars(trim($_POST['date']), ENT_QUOTES, 'UTF-8');
$taker_id = filter_var($_POST['taker_id'], FILTER_SANITIZE_NUMBER_INT);

// Convert date from d.m.Y to Y-m-d format
$dateObj = DateTime::createFromFormat('d.m.Y', $date);
if (!$dateObj) {
    $_SESSION['error_message'] = 'Невалиден формат на датата.';
    header('Location: list.php');
    exit;
}
$mysqlDate = $dateObj->format('Y-m-d');

try {
    // Update the request
    $stmt = $pdo->prepare("UPDATE request_sample SET star_jo = ?, date = ?, taker_id = ? WHERE id = ?");
    $stmt->execute([$star_jo, $mysqlDate, $taker_id, $id]);

    $_SESSION['success_message'] = 'Заявката беше успешно обновена.';
    header('Location: list.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Грешка при обновяване на заявката: ' . $e->getMessage();
    header('Location: list.php');
    exit;
} 