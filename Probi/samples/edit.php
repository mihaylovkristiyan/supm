<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$error_message = '';

// Get date range from form or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Get all samples within date range
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.data,
            s.protokol_nomer,
            s.barkod,
            s.vid_mliako,
            s.valid,
            ao.proizvoditel,
            ao.star_jo,
            t.ime as probovzemach
        FROM samples s
        LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
        LEFT JOIN takers t ON s.probovzemach_id = t.id
        WHERE s.data BETWEEN ? AND ?
        ORDER BY s.data DESC
    ");
    $stmt->execute([$start_date, $end_date]);
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
    <title>Редактиране на Проба</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Редактиране на проба</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Пробата е обновена успешно!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Date Range Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Филтър по дата</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">От дата:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">До дата:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Филтрирай</button>
                                <a href="edit.php" class="btn btn-secondary">Изчисти</a>
                            </div>
                        </form>
                        
                        <!-- Quick Date Range Buttons -->
                        <div class="mt-3">
                            <small class="text-muted">Бързи филтри:</small>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('today')">Днес</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('yesterday')">Вчера</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('this_week')">Тази седмица</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('last_week')">Миналата седмица</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('this_month')">Този месец</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('last_month')">Миналия месец</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="alert alert-info">
                    <strong>Намерени проби:</strong> <?php echo count($samples); ?> 
                    (от <?php echo date('d.m.Y', strtotime($start_date)); ?> до <?php echo date('d.m.Y', strtotime($end_date)); ?>)
                </div>

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
                            <th>Валидност</th>
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
                                    <span class="badge <?php echo $sample['valid'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $sample['valid'] ? 'Валидна' : 'Невалидна'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_sample.php?id=<?php echo $sample['id']; ?><?php 
                                        $params = [];
                                        if (isset($_GET['start_date'])) $params[] = 'start_date=' . urlencode($_GET['start_date']);
                                        if (isset($_GET['end_date'])) $params[] = 'end_date=' . urlencode($_GET['end_date']);
                                        echo $params ? '&' . implode('&', $params) : '';
                                    ?>" 
                                       class="btn btn-warning btn-sm">
                                        Редактиране
                                    </a>
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

            // Date validation
            $('#start_date, #end_date').on('change', function() {
                validateDateRange();
            });

            function validateDateRange() {
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                
                if (startDate && endDate && startDate > endDate) {
                    alert('Началната дата не може да бъде по-късна от крайната дата!');
                    $('#end_date').val(startDate);
                }
            }
        });

        // Quick date range functions
        function setDateRange(range) {
            const today = new Date();
            let startDate, endDate;
            
            switch(range) {
                case 'today':
                    startDate = endDate = today.toISOString().split('T')[0];
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(today.getDate() - 1);
                    startDate = endDate = yesterday.toISOString().split('T')[0];
                    break;
                case 'this_week':
                    const startOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay() + 1);
                    const endOfWeek = new Date(today);
                    endOfWeek.setDate(today.getDate() - today.getDay() + 7);
                    startDate = startOfWeek.toISOString().split('T')[0];
                    endDate = endOfWeek.toISOString().split('T')[0];
                    break;
                case 'last_week':
                    const startOfLastWeek = new Date(today);
                    startOfLastWeek.setDate(today.getDate() - today.getDay() - 6);
                    const endOfLastWeek = new Date(today);
                    endOfLastWeek.setDate(today.getDate() - today.getDay());
                    startDate = startOfLastWeek.toISOString().split('T')[0];
                    endDate = endOfLastWeek.toISOString().split('T')[0];
                    break;
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                    break;
                case 'last_month':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1).toISOString().split('T')[0];
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0];
                    break;
            }
            
            $('#start_date').val(startDate);
            $('#end_date').val(endDate);
            
            // Submit the form
            $('form').submit();
        }
    </script>
</body>
</html> 