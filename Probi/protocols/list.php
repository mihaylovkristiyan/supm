<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get date filter if set
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Get search query if set
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get DataTables state parameters
$page = $_GET['dt_page'] ?? '1';
$length = $_GET['dt_length'] ?? '25';
$order_col = $_GET['dt_order_col'] ?? '1';
$order_dir = $_GET['dt_order_dir'] ?? 'desc';
$search_val = $_GET['dt_search'] ?? '';

// Check if we're returning from attach_scan.php
$returning_from_attach = isset($_GET['dt_page']) || isset($_GET['dt_length']) || isset($_GET['dt_order_col']);

// Build the query with optional date filter
$query = "SELECT 
            s.id,
            s.data AS date,
            s.protokol_nomer,
            s.star_jo,
            s.barkod,
            s.protokol_snimka,
            ao.proizvoditel,
            t.ime as probovzemach_ime
          FROM samples s
          LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
          LEFT JOIN takers t ON s.probovzemach_id = t.id";

$params = [];  // Initialize the params array

// Load data only if a date is selected, search is performed, or returning from attach
if ($date_filter || $search_query || $returning_from_attach) {
    $conditions = [];

    if ($date_filter) {
        $date_obj = DateTime::createFromFormat('d.m.Y', $date_filter);
        if ($date_obj) {
            $mysql_date = $date_obj->format('Y-m-d');
            $conditions[] = "DATE(s.data) = :date";
            $params[':date'] = $mysql_date;
        }
    }

    if ($search_query) {
        $conditions[] = "(s.protokol_nomer LIKE :search OR s.star_jo LIKE :search OR s.barkod LIKE :search)";
        $params[':search'] = "%$search_query%";
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }

    $query .= " AND s.protokol_nomer IS NOT NULL ORDER BY s.data DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $protocols = [];
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Протоколи</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" href="../icon.png">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .table-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .filter-container {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_filter {
            float: none;
            text-align: left;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Протоколи</h2>
                <div>
                    <a href="../index.php" class="btn btn-secondary me-2">Назад</a>
                    <a href="add_protocol.php" class="btn btn-primary">Нов протокол</a>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="filter-container">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="date_filter" class="form-label">Филтър по дата</label>
                        <input type="text" class="form-control datepicker" id="date_filter" name="date" 
                               value="<?php echo htmlspecialchars($date_filter); ?>" placeholder="Изберете дата">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Филтрирай</button>
                    </div>
                    <?php if ($date_filter || $search_query): ?>
                    <div class="col-md-2">
                        <a href="list.php" class="btn btn-secondary w-100">Изчисти филтри</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table id="protocolsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Дата</th>
                            <th>Протокол №</th>
                            <th>Стар ЖО</th>
                            <th>Баркод</th>
                            <th>Производител</th>
                            <th>Пробовземач</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($protocols as $index => $protocol): ?>
                            <tr>
                                <td></td>
                                <td><?php echo date('d.m.Y', strtotime($protocol['date'])); ?></td>
                                <td><?php echo htmlspecialchars($protocol['protokol_nomer']); ?></td>
                                <td><?php echo htmlspecialchars($protocol['star_jo']); ?></td>
                                <td><?php echo htmlspecialchars($protocol['barkod']); ?></td>
                                <td><?php echo htmlspecialchars($protocol['proizvoditel'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($protocol['probovzemach_ime'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php
                                        $attachUrl = 'attach_scan.php?' . http_build_query([
                                            'protokol_nomer' => $protocol['protokol_nomer'],
                                            'date' => $date_filter,
                                            'search' => $search_query,
                                            'page' => '__PAGE__',
                                            'length' => '__LENGTH__',
                                            'order_col' => '__ORDER_COL__',
                                            'order_dir' => '__ORDER_DIR__',
                                            'search_val' => '__SEARCH_VAL__'
                                        ]);
                                        ?>
                                        <a href="#" onclick="navigateToAttach('<?php echo htmlspecialchars($attachUrl); ?>'); return false;" class="btn btn-info btn-sm">Прикачи/Сканирай</a>
                                        <a href="view.php?protokol_nomer=<?php echo urlencode($protocol['protokol_nomer']); ?>&date=<?php echo urlencode($date_filter); ?>&search=<?php echo urlencode($search_query); ?>" class="btn <?php echo $protocol['protokol_snimka'] ? 'btn-success' : 'btn-secondary'; ?> btn-sm">Виж</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        function confirmDelete(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете този протокол?')) {
                const dateFilter = document.getElementById('date_filter').value;
                window.location.href = 'delete.php?id=' + id + '&date=' + encodeURIComponent(dateFilter);
            }
        }

        flatpickr(".datepicker", {
            locale: "bg",
            dateFormat: "d.m.Y",
            allowInput: true
        });

        let dataTable;

        $(document).ready(function() {
            dataTable = $('#protocolsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json',
                    processing: "Обработка на резултатите...",
                    search: "Търсене:",
                    lengthMenu: "Показване на _MENU_ резултата",
                    info: "Показване на резултати от _START_ до _END_ от общо _TOTAL_",
                    infoEmpty: "Показване на резултати от 0 до 0 от общо 0",
                    infoFiltered: "(филтрирани от общо _MAX_ резултата)",
                    paginate: {
                        first: "Първа",
                        previous: "Предишна",
                        next: "Следваща",
                        last: "Последна"
                    }
                },
                order: [[parseInt('<?php echo $order_col; ?>'), '<?php echo $order_dir; ?>']],
                pageLength: parseInt('<?php echo $length; ?>'),
                displayStart: (parseInt('<?php echo $page; ?>') - 1) * parseInt('<?php echo $length; ?>'),
                stateSave: true,
                search: {
                    search: '<?php echo $search_val; ?>'
                }
            });

            // Function to navigate to attach_scan.php with current DataTables state
            window.navigateToAttach = function(url) {
                const state = dataTable.state();
                const page = Math.ceil(state.start / state.length) + 1;
                const orderCol = state.order[0][0];
                const orderDir = state.order[0][1];
                const searchVal = state.search.search;
                
                url = url.replace('__PAGE__', page);
                url = url.replace('__LENGTH__', state.length);
                url = url.replace('__ORDER_COL__', orderCol);
                url = url.replace('__ORDER_DIR__', orderDir);
                url = url.replace('__SEARCH_VAL__', searchVal);
                
                window.location.href = url;
            };

            // Add custom filter for date
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const dateFilter = $('#date_filter').val();
                    if (!dateFilter) return true;
                    const rowDate = data[1]; // Date is in the second column
                    return rowDate === dateFilter;
                }
            );

            // Apply date filter when date changes
            $('#date_filter').on('change', function() {
                dataTable.draw();
            });

            $('#dateFilterForm').on('submit', function(e) {
                e.preventDefault();
                dataTable.draw();
            });

            // Renumber the first column after every draw
            dataTable.on('order.dt search.dt draw.dt', function() {
                dataTable.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function(cell, i) {
                    cell.innerHTML = i + 1;
                });
            });
        });
    </script>
</body>
</html> 