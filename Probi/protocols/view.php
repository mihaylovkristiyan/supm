<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

$protokolNomer = $_GET['protokol_nomer'] ?? null;
$dateFilter = $_GET['date'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$filePath = null;
$fileName = null;

if ($protokolNomer) {
    $stmt = $pdo->prepare("SELECT protokol_snimka FROM samples WHERE protokol_nomer = :protokol_nomer");
    $stmt->execute([':protokol_nomer' => $protokolNomer]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $filePath = $result['protokol_snimka'] ?? null;
    if ($filePath) {
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    }
}

?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Преглед на Протокол</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <h2>Преглед на Протокол № <?php echo htmlspecialchars($protokolNomer); ?></h2>
        <?php if ($filePath): ?>
            <div class="mt-4">
                <h4>Име на файла: <?php echo htmlspecialchars($fileName); ?></h4>
                <a href="download.php?file=<?php echo urlencode($filePath); ?>" class="btn btn-primary mt-3">Изтегли PDF</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-4">Няма прикачен протокол!</div>
        <?php endif; ?>
        <a href="list.php?date=<?php echo urlencode($dateFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" class="btn btn-secondary mt-3">Назад към списъка</a>
    </div>
</body>
</html> 