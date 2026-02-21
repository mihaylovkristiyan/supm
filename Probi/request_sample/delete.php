<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "Липсващо ID на заявката.";
    header("Location: list.php");
    exit;
}

$id = $_GET['id'];
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Delete the request
    $stmt = $pdo->prepare("DELETE FROM request_sample WHERE id = ?");
    $stmt->execute([$id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = "Заявката беше изтрита успешно.";
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error_message'] = "Грешка при изтриване на заявката: " . $e->getMessage();
}

// Redirect back to list with the date filter preserved
$redirect_url = "list.php";
if (!empty($date_filter)) {
    $redirect_url .= "?date=" . urlencode($date_filter);
}
header("Location: " . $redirect_url);
exit; 