<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/request_handler.php';
handleRequest();
requireLogin();

// Handle form submission for updating invoices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoices'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['invoice'] as $sample_id => $invoice_number) {
            if (!empty($invoice_number)) {
                $stmt = $pdo->prepare("UPDATE samples SET faktura = :faktura WHERE id = :id");
                $stmt->execute([
                    ':faktura' => $invoice_number,
                    ':id' => $sample_id
                ]);
            }
        }
        
        $pdo->commit();
        $success_message = "Фактурите бяха успешно обновени.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Грешка при обновяване на фактурите: " . $e->getMessage();
    }
}

// Get date range from GET parameters or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch samples within date range
$sql = "SELECT 
    s.id,
    s.data as date,
    s.faktura,
    ao.proizvoditel,
    ao.bulstat,
    s.barkod,
    s.vid_mliako,
    ao.star_jo,
    t.ime as probovzemach
FROM samples s
JOIN animal_objects ao ON s.star_jo = ao.star_jo
JOIN takers t ON s.probovzemach_id = t.id
WHERE s.data BETWEEN :start_date AND :end_date
ORDER BY s.data DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на пробите: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактиране на фактури</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>Редактиране на фактура</h2>
            <a href="../index.php" class="btn btn-secondary">Назад</a>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Date Filter Form -->
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <label>От дата:</label>
                    <input type="text" class="form-control datepicker" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                <div class="col-md-3">
                    <label>До дата:</label>
                    <input type="text" class="form-control datepicker" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Филтриране</button>
                </div>
            </div>
        </form>

        <!-- Invoices Edit Form -->
        <form method="POST">
            <div class="table-responsive">
                <table id="invoicesTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Производител</th>
                            <th>Булстат</th>
                            <th>Стар ЖО</th>
                            <th>Баркод</th></th>
                            <th>Пробовземач</th>
                            <th>Вид мляко</th>
                            <th>Фактура</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($samples as $sample): ?>
                        <tr>
                            <td><?= htmlspecialchars($sample['date']) ?></td>
                            <td><?= htmlspecialchars($sample['proizvoditel']) ?></td>
                            <td><?= htmlspecialchars($sample['bulstat']) ?></td>
                            <td><?= htmlspecialchars($sample['star_jo']) ?></td>
                            <td><?= htmlspecialchars($sample['barkod']) ?></td>
                            <td><?= htmlspecialchars($sample['probovzemach']) ?></td>
                            <td><?= htmlspecialchars($sample['vid_mliako']) ?></td>
                            <td>
                                <input type="text" class="form-control" 
                                       name="invoice[<?= $sample['id'] ?>]" 
                                       value="<?= htmlspecialchars($sample['faktura'] ?? '') ?>"
                                       placeholder="Въведете номер">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" name="update_invoices" class="btn btn-success">Запазване на промените</button>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/locales/bootstrap-datepicker.bg.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#invoicesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json'
                },
                pageLength: 25,
                order: [[0, "desc"]]
            });

            // Initialize datepicker
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                language: 'bg'
            });
        });
    </script>
</body>
</html> 