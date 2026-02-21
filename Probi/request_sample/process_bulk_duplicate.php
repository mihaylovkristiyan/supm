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
    header('Location: list.php');
    exit;
}

// Check if date is present
if (!isset($_POST['date'])) {
    $_SESSION['error_message'] = 'Датата е задължителна.';
    header('Location: list.php');
    exit;
}

// Sanitize input
$date = htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8');
$filter_date = isset($_POST['filter_date']) ? htmlspecialchars($_POST['filter_date'], ENT_QUOTES, 'UTF-8') : '';

// Convert dates from d.m.Y to Y-m-d format
$dateObj = DateTime::createFromFormat('d.m.Y', $date);
if (!$dateObj) {
    $_SESSION['error_message'] = 'Невалиден формат на новата дата.';
    header('Location: list.php');
    exit;
}
$mysqlDate = $dateObj->format('Y-m-d');

try {
    // Build the query to get requests to duplicate
    $query = "SELECT star_jo, taker_id FROM request_sample";
    if ($filter_date) {
        $filterDateObj = DateTime::createFromFormat('d.m.Y', $filter_date);
        if ($filterDateObj) {
            $mysqlFilterDate = $filterDateObj->format('Y-m-d');
            $query .= " WHERE DATE(date) = :filter_date";
        }
    }

    $stmt = $pdo->prepare($query);
    if ($filter_date && isset($mysqlFilterDate)) {
        $stmt->bindParam(':filter_date', $mysqlFilterDate);
    }
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($requests)) {
        $_SESSION['error_message'] = 'Няма заявки за дублиране.';
        header('Location: list.php');
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert duplicates for all requests
    $insertStmt = $pdo->prepare("INSERT INTO request_sample (star_jo, date, taker_id) VALUES (?, ?, ?)");
    $duplicateCount = 0;

    foreach ($requests as $request) {
        $insertStmt->execute([$request['star_jo'], $mysqlDate, $request['taker_id']]);
        $duplicateCount++;
    }

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = "Успешно бяха дублирани $duplicateCount заявки за дата $date.";
    header('Location: list.php');
    exit;
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Грешка при дублиране на заявките: ' . $e->getMessage();
    header('Location: list.php');
    exit;
}

// Clean the output buffer
ob_end_clean(); 