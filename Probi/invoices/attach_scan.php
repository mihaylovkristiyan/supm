<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get filter parameters from URL first
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Get DataTables state parameters
$page = $_GET['page'] ?? '1';
$length = $_GET['length'] ?? '25';
$order_col = $_GET['order_col'] ?? '1';
$order_dir = $_GET['order_dir'] ?? 'desc';
$search_val = $_GET['search_val'] ?? '';

// Function to redirect back with parameters
function redirectBack($message = null) {
    global $search, $start_date, $end_date, $page, $length, $order_col, $order_dir, $search_val;
    if ($message) {
        $_SESSION['error_message'] = $message;
    }
    $params = http_build_query([
        'search' => $search,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'dt_page' => $page,
        'dt_length' => $length,
        'dt_order_col' => $order_col,
        'dt_order_dir' => $order_dir,
        'dt_search' => $search_val
    ]);
    header('Location: list.php?' . $params);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get all parameters from POST
    $search = $_POST['search'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $page = $_POST['page'] ?? '1';
    $length = $_POST['length'] ?? '25';
    $order_col = $_POST['order_col'] ?? '1';
    $order_dir = $_POST['order_dir'] ?? 'desc';
    $search_val = $_POST['search_val'] ?? '';

    $faktura = trim((string)($_POST['faktura'] ?? ''));
    $uploadDir = __DIR__ . '/files/';
    
    // Validate invoice number
    if (empty($faktura)) {
        redirectBack('Невалиден номер на фактура.');
    }

    // Verify invoice exists in database
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM samples WHERE faktura = ?");
    $stmt->execute([$faktura]);
    if (!$stmt->fetchColumn()) {
        redirectBack('Фактурата не съществува в базата данни.');
    }

    if (!isset($_FILES['scan_file']) || $_FILES['scan_file']['error'] !== UPLOAD_ERR_OK) {
        redirectBack('Моля, изберете файл за качване.');
    }

    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadFile = $uploadDir . basename($_FILES['scan_file']['name']);

    if (move_uploaded_file($_FILES['scan_file']['tmp_name'], $uploadFile)) {
        // Update the database with the file path
        $stmt = $pdo->prepare("UPDATE samples SET faktura_snimka = :file_path WHERE faktura = :faktura");
        $stmt->execute([':file_path' => $uploadFile, ':faktura' => $faktura]);

        $_SESSION['success_message'] = 'Файлът е успешно качен и записан.';
    } else {
        $_SESSION['error_message'] = 'Грешка при качване на файла.';
    }

    redirectBack();
}

// Validate invoice number from GET parameters
$faktura = trim((string)($_GET['faktura'] ?? ''));
if (empty($faktura)) {
    redirectBack('Невалиден номер на фактура.');
}

// Verify invoice exists in database
$stmt = $pdo->prepare("SELECT COUNT(*) FROM samples WHERE faktura = ?");
$stmt->execute([$faktura]);
if (!$stmt->fetchColumn()) {
    redirectBack('Фактурата не съществува в базата данни.');
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прикачване на сканиран файл</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <h2>Прикачване на сканиран файл</h2>
        <form action="attach_scan.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="faktura" class="form-label">Фактура №</label>
                <input type="text" class="form-control" id="faktura" name="faktura" value="<?php echo htmlspecialchars($faktura); ?>" readonly>
            </div>
            <div class="mb-3">
                <label for="scan_file" class="form-label">Изберете файл за качване</label>
                <input type="file" class="form-control" id="scan_file" name="scan_file" required accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <!-- Hidden inputs for filter parameters -->
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <!-- Hidden inputs for DataTables state -->
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
            <input type="hidden" name="length" value="<?php echo htmlspecialchars($length); ?>">
            <input type="hidden" name="order_col" value="<?php echo htmlspecialchars($order_col); ?>">
            <input type="hidden" name="order_dir" value="<?php echo htmlspecialchars($order_dir); ?>">
            <input type="hidden" name="search_val" value="<?php echo htmlspecialchars($search_val); ?>">
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Качи файл</button>
                <?php
                $backUrl = 'list.php?' . http_build_query([
                    'search' => $search,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'dt_page' => $page,
                    'dt_length' => $length,
                    'dt_order_col' => $order_col,
                    'dt_order_dir' => $order_dir,
                    'dt_search' => $search_val
                ]);
                ?>
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-secondary">Назад</a>
            </div>
        </form>
    </div>
</body>
</html> 