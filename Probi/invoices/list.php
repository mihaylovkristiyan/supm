<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get date filter if set
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Get search query if set
$search_query = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Add two date inputs for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build the query with optional date filter
$query = "SELECT 
            s.id,
            s.data AS date,
            s.faktura,
            s.faktura_snimka,
            s.star_jo,
            ao.proizvoditel AS customer_name
          FROM samples s
          LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo";

$params = [];  // Initialize the params array
$invoices = []; // Initialize empty invoices array

// Check if we're returning from attach_scan.php
$returning_from_attach = isset($_GET['dt_page']) || isset($_GET['dt_length']) || isset($_GET['dt_order_col']);

// Only load data if filters are applied or returning from attach_scan
if ($search_query || ($start_date && $end_date) || $returning_from_attach) {
    $conditions = [];

    if ($search_query) {
        $conditions[] = "(s.faktura LIKE :search)";
        $params[':search'] = "%$search_query%";
    }

    if ($start_date && $end_date) {
        $start_date_obj = DateTime::createFromFormat('d.m.Y', $start_date);
        $end_date_obj = DateTime::createFromFormat('d.m.Y', $end_date);
        if ($start_date_obj && $end_date_obj) {
            $mysql_start_date = $start_date_obj->format('Y-m-d');
            $mysql_end_date = $end_date_obj->format('Y-m-d');
            $conditions[] = "s.data BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $mysql_start_date;
            $params[':end_date'] = $mysql_end_date;
        }
    }

    // If no conditions but returning from attach, load all data
    if (empty($conditions) && $returning_from_attach) {
        // Optional: You can add a default date range if you want to limit the data
        $default_start_date = date('Y-m-d', strtotime('-1 month'));
        $default_end_date = date('Y-m-d');
        $conditions[] = "s.data BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $default_start_date;
        $params[':end_date'] = $default_end_date;
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
        $query .= " ORDER BY s.data DESC";

        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Remove the info message about using filters if we're returning from attach_scan.php
$show_filter_message = !$returning_from_attach && empty($invoices);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Фактури</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" href="../icon.png">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
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
                <h2>Фактури</h2>
                <div>
                    <a href="../index.php" class="btn btn-secondary me-2">Назад</a>
                    <a href="add_invoice.php" class="btn btn-primary">Нова фактура</a>
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
                        <label for="start_date" class="form-label">От дата</label>
                        <input type="text" class="form-control datepicker" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>" placeholder="Изберете начална дата">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">До дата</label>
                        <input type="text" class="form-control datepicker" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>" placeholder="Изберете крайна дата">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Филтрирай</button>
                    </div>
                    <?php if ($search_query || ($start_date && $end_date)): ?>
                    <div class="col-md-2">
                        <a href="list.php" class="btn btn-secondary w-100">Изчисти филтри</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <?php if ($show_filter_message): ?>
                    <div class="alert alert-info">
                        Моля, използвайте филтрите, за да видите фактурите.
                    </div>
                <?php else: ?>
                <table id="invoicesTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Дата</th>
                            <th>Номер на фактура</th>
                            <th>Стар ЖО</th>
                            <th>Клиент</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $index => $invoice): ?>
                            <tr>
                                <td></td>
                                <td><?php echo date('d.m.Y', strtotime($invoice['date'])); ?></td>
                                <td><?php echo htmlspecialchars($invoice['faktura'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($invoice['star_jo']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['customer_name'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php
                                        // Ensure invoice number is not empty and properly formatted
                                        $invoice_number = $invoice['faktura'] ?? '';
                                        if (!empty($invoice_number)) {
                                            $attachUrl = 'attach_scan.php?' . http_build_query([
                                                'faktura' => $invoice_number,
                                                'search' => $search_query,
                                                'start_date' => $start_date,
                                                'end_date' => $end_date,
                                                'page' => '__PAGE__',
                                                'length' => '__LENGTH__',
                                                'order_col' => '__ORDER_COL__',
                                                'order_dir' => '__ORDER_DIR__',
                                                'search_val' => '__SEARCH_VAL__'
                                            ]);
                                        ?>
                                            <a href="#" onclick="navigateToAttach('<?php echo htmlspecialchars($attachUrl); ?>'); return false;" class="btn btn-info btn-sm">Прикачи/Сканирай</a>
                                        <?php } ?>
                                        <a href="view.php?faktura=<?php echo urlencode(($invoice['faktura'] ?? '')); ?>&search=<?php echo urlencode(($search_query ?? '')); ?>" 
                                           class="btn <?php echo $invoice['faktura_snimka'] ? 'btn-success' : 'btn-secondary'; ?> btn-sm">Виж</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize flatpickr with Bulgarian locale
        flatpickr.localize(flatpickr.l10ns.bg);

        // Wait for DOM to be ready
        $(document).ready(function() {
            // Get URL parameters for DataTables state
            const urlParams = new URLSearchParams(window.location.search);
            const dtPage = parseInt(urlParams.get('dt_page')) || 1;
            const dtLength = parseInt(urlParams.get('dt_length')) || 25;
            const dtOrderCol = parseInt(urlParams.get('dt_order_col')) || 1;
            const dtOrderDir = urlParams.get('dt_order_dir') || 'desc';
            const dtSearch = urlParams.get('dt_search') || '';

            // Calculate start position
            const dtStart = (dtPage - 1) * dtLength;

            // Initialize DataTable first
            let dataTable = $('#invoicesTable').DataTable({
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
                order: [[dtOrderCol, dtOrderDir]],
                pageLength: dtLength,
                displayStart: dtStart,
                stateSave: true,
                search: {
                    search: dtSearch
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

            // Initialize regular datepickers
            $('.datepicker').each(function() {
                const element = this;
                setTimeout(function() {
                    new flatpickr(element, {
                        dateFormat: "d.m.Y",
                        allowInput: true,
                        disableMobile: true
                    });
                }, 0);
            });

            // Initialize month pickers
            $('.monthpicker').each(function() {
                const element = this;
                setTimeout(function() {
                    new flatpickr(element, {
                        plugins: [
                            new monthSelectPlugin({
                                shorthand: true,
                                dateFormat: "m.Y",
                                altFormat: "F Y",
                                theme: "light"
                            })
                        ],
                        dateFormat: "m.Y",
                        disableMobile: true
                    });
                }, 0);
            });

            // Add custom filter for date
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const dateFilter = $('#month_filter').val();
                    if (!dateFilter) return true;
                    const rowDate = data[1]; // Date is in the second column
                    return rowDate === dateFilter;
                }
            );

            // Apply date filter when date changes
            $('#month_filter').on('change', function() {
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