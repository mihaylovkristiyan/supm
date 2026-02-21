<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$success_message = '';
$error_message = '';
$samples = [];

// Get date range from GET parameters or set defaults
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get samples with invoice numbers
try {
    $sql = "SELECT 
        s.id,
        s.data as date,
        s.protokol_nomer,
        s.barkod,
        ao.proizvoditel,
        ao.bulstat,
        ao.star_jo,
        t.ime as probovzemach,
        s.vid_mliako,
        s.faktura,
        s.plashtane
    FROM samples s
    LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
    LEFT JOIN takers t ON s.probovzemach_id = t.id
    WHERE s.data BETWEEN :start_date AND :end_date
    AND s.faktura IS NOT NULL
    ORDER BY s.data DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $samples = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на фактурите: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['samples'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE samples SET plashtane = ? WHERE id = ?");
        $updated_count = 0;
        
        foreach ($_POST['samples'] as $sample_id => $payment_info) {
            if (!empty($payment_info)) {
                $stmt->execute([$payment_info, $sample_id]);
                $updated_count++;
            }
        }
        
        $pdo->commit();
        
        if ($updated_count > 0) {
            $success_message = "Успешно въведени плащания за $updated_count проби!";
            // Refresh the page to show updated list
            header("Location: payment.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date));
            exit;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Грешка при обновяване на плащанията: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Плащане на фактура</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
    <link rel="icon" href="../icon.png">
    <style>
        .payment-input {
            width: 150px !important;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Плащане на фактура</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Date Range Filter Form -->
                <div class="filter-section">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">От дата</label>
                            <input type="text" class="form-control datepicker" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">До дата</label>
                            <input type="text" class="form-control datepicker" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">Филтрирай</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($samples)): ?>
                    <div class="alert alert-info">
                        Няма намерени фактури за избрания период.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="table-responsive">
                            <table id="samplesTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Протокол №</th>
                                        <th>Баркод</th>
                                        <th>Производител</th>
                                        <th>Булстат</th>
                                        <th>Стар ЖО</th>
                                        <th>Вид мляко</th>
                                        <th>Пробовземач</th>
                                        <th>Фактура №</th>
                                        <th>Плащане</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($samples as $sample): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y', strtotime($sample['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($sample['protokol_nomer']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['barkod'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($sample['proizvoditel']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['bulstat']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['star_jo']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['vid_mliako']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['probovzemach']); ?></td>
                                            <td><?php echo htmlspecialchars($sample['faktura']); ?></td>
                                            <td>
                                                <input type="text" class="form-control payment-input" 
                                                       name="samples[<?php echo $sample['id']; ?>]" 
                                                       value="<?php echo htmlspecialchars($sample['plashtane'] ?? ''); ?>"
                                                       placeholder="Въведете плащане">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">Запази плащания</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/locales/bootstrap-datepicker.bg.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#samplesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json',
                    search: "Търсене по фактура/име:"
                },
                pageLength: 25,
                order: [[0, "desc"]],
                columnDefs: [
                    {
                        "targets": [0,1,2,4,5,6,7], // Columns to disable search
                        "searchable": false
                    },
                    {
                        "targets": [3,8], // Producer name and invoice number columns
                        "searchable": true
                    },
                    {
                        "targets": [9], // Payment column
                        "searchable": false,
                        "orderable": false
                    }
                ]
            });

            // Initialize datepicker
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                language: 'bg',
                todayBtn: "linked",
                clearBtn: true,
                todayHighlight: true
            });
        });
    </script>
</body>
</html> 