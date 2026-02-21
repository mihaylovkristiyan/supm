<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Невалиден метод на заявка.';
    header('Location: list.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Невалиден токен за сигурност.';
    header('Location: list.php');
    exit;
}

// Get selected IDs
if (!isset($_POST['selected_ids']) || empty($_POST['selected_ids'])) {
    $_SESSION['error_message'] = 'Не са избрани заявки за изтриване.';
    header('Location: list.php');
    exit;
}

try {
    // Decode the JSON string to get the array of IDs
    $selectedIds = json_decode($_POST['selected_ids'], true);
    
    if (!is_array($selectedIds) || empty($selectedIds)) {
        throw new Exception('Невалидни данни за избраните заявки.');
    }
    
    // Start a transaction
    $pdo->beginTransaction();
    
    // Delete each selected request
    $deletedCount = 0;
    foreach ($selectedIds as $id) {
        // Validate ID is numeric
        if (!is_numeric($id)) {
            continue;
        }
        
        // Delete the request - using the same logic as in delete.php
        $stmt = $pdo->prepare("DELETE FROM request_sample WHERE id = ?");
        $stmt->execute([$id]);
        
        $deletedCount++;
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Set success message
    $_SESSION['success_message'] = "Успешно изтрити $deletedCount заявки.";
    
} catch (Exception $e) {
    // Rollback the transaction in case of error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = 'Грешка при изтриване на заявките: ' . $e->getMessage();
}

// Redirect back to the list page with the same filters
$redirectUrl = 'list.php';
if (isset($_POST['date']) && !empty($_POST['date'])) {
    $redirectUrl .= '?date=' . urlencode($_POST['date']);
}

header("Location: $redirectUrl");
exit; 