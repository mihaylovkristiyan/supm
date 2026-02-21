<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$success_message = '';
$error_message = '';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM samples WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $success_message = "Пробата беше изтрита успешно!";
    } catch (PDOException $e) {
        $error_message = "Грешка при изтриване на пробата: " . $e->getMessage();
    }
}

// Get all samples
try {
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.data,
            s.protokol_nomer,
            s.barkod,
            s.vid_mliako,
            ao.proizvoditel,
            ao.star_jo,
            t.ime as probovzemach
        FROM samples s
        LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
        LEFT JOIN takers t ON s.probovzemach_id = t.id
        ORDER BY s.data DESC
    ");
    $samples = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на данните: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Изтриване на Проба</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Изтриване на проба</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <table id="samplesTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Протокол №</th>
                            <th>Пробовземач</th>
                            <th>Баркод</th>
                            <th>Производител</th>
                            <th>Стар ЖО</th>
                            <th>Вид мляко</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($samples as $sample): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($sample['data'])); ?></td>
                                <td><?php echo htmlspecialchars($sample['protokol_nomer'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sample['probovzemach'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sample['barkod'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sample['proizvoditel'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sample['star_jo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sample['vid_mliako'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Сигурни ли сте, че искате да изтриете тази проба?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $sample['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Изтрии</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#samplesTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Bulgarian.json"
                },
                "pageLength": 25,
                "order": [[0, "desc"]]
            });
        });
    </script>
</body>
</html> 