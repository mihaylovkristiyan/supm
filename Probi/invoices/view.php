<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

$invoiceNumber = $_GET['faktura'] ?? null;
$dateFilter = $_GET['date'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$filePath = null;
$fileName = null;

if ($invoiceNumber) {
    $stmt = $pdo->prepare("SELECT faktura_snimka FROM samples WHERE faktura = :invoice_number");
    $stmt->execute([':invoice_number' => $invoiceNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $filePath = $result['faktura_snimka'] ?? null;
    if ($filePath) {
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    }
}

// Debugging output
error_log("Invoice Number: " . $invoiceNumber);
error_log("File Path: " . $filePath);
error_log("File Name: " . $fileName);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Преглед на Фактура</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <h2>Преглед на Фактура № <?php echo htmlspecialchars($invoiceNumber ?? ''); ?></h2>
        <?php if ($filePath): ?>
            <div class="mt-4">
                <h4>Име на файла: <?php echo htmlspecialchars($fileName ?? ''); ?></h4>
                <a href="download.php?file=<?php echo urlencode($filePath); ?>" class="btn btn-primary mt-3">Изтегли PDF</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-4">Няма прикачена фактура!</div>
        <?php endif; ?>
        <a href="list.php?date=<?php echo urlencode($dateFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>" class="btn btn-secondary mt-3">Назад към списъка</a>
    </div>
</body>
</html> 