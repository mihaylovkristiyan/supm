<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get filter parameters from URL first
$date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Get DataTables state parameters
$page = $_GET['page'] ?? '1';
$length = $_GET['length'] ?? '25';
$order_col = $_GET['order_col'] ?? '1';
$order_dir = $_GET['order_dir'] ?? 'desc';
$search_val = $_GET['search_val'] ?? '';

// Function to redirect back with parameters
function redirectBack($message = null) {
    global $date, $search, $page, $length, $order_col, $order_dir, $search_val;
    if ($message) {
        $_SESSION['error_message'] = $message;
    }
    $params = http_build_query([
        'date' => $date,
        'search' => $search,
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
    $date = $_POST['date'] ?? '';
    $search = $_POST['search'] ?? '';
    $page = $_POST['page'] ?? '1';
    $length = $_POST['length'] ?? '25';
    $order_col = $_POST['order_col'] ?? '1';
    $order_dir = $_POST['order_dir'] ?? 'desc';
    $search_val = $_POST['search_val'] ?? '';

    $protokolNomer = $_POST['protokol_nomer'] ?? null;
    $uploadDir = __DIR__ . '/files/';
    $uploadFile = $uploadDir . basename($_FILES['scan_file']['name']);

    if ($protokolNomer && isset($_FILES['scan_file'])) {
        if (move_uploaded_file($_FILES['scan_file']['tmp_name'], $uploadFile)) {
            // Update the database with the file path
            $stmt = $pdo->prepare("UPDATE samples SET protokol_snimka = :file_path WHERE protokol_nomer = :protokol_nomer");
            $stmt->execute([':file_path' => $uploadFile, ':protokol_nomer' => $protokolNomer]);

            $_SESSION['success_message'] = 'Файлът е успешно качен и записан.';
        } else {
            $_SESSION['error_message'] = 'Грешка при качване на файла.';
        }
    } else {
        $_SESSION['error_message'] = 'Невалидни данни за протокол или файл.';
    }

    redirectBack();
}

// Validate protocol number from GET parameters
$protokolNomer = $_GET['protokol_nomer'] ?? null;
if (empty($protokolNomer)) {
    redirectBack('Невалиден номер на протокол.');
}

// Verify protocol exists in database
$stmt = $pdo->prepare("SELECT COUNT(*) FROM samples WHERE protokol_nomer = ?");
$stmt->execute([$protokolNomer]);
if (!$stmt->fetchColumn()) {
    redirectBack('Протоколът не съществува в базата данни.');
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
                <label for="protokol_nomer" class="form-label">Протокол №</label>
                <input type="text" class="form-control" id="protokol_nomer" name="protokol_nomer" value="<?php echo htmlspecialchars($protokolNomer); ?>" readonly>
            </div>
            <div class="mb-3">
                <label for="scan_file" class="form-label">Изберете файл за качване</label>
                <input type="file" class="form-control" id="scan_file" name="scan_file" required accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <!-- Hidden inputs for filter parameters -->
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
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
                    'date' => $date,
                    'search' => $search,
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