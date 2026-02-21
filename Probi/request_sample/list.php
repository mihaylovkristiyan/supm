<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get date filter if set
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Get search query if set
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query with optional date filter
$query = "SELECT 
            rs.id,
            rs.date,
            rs.star_jo,
            ao.proizvoditel,
            t.ime as taker_name
          FROM request_sample rs
          LEFT JOIN animal_objects ao ON rs.star_jo = ao.star_jo
          LEFT JOIN takers t ON rs.taker_id = t.id";

$params = [];  // Initialize the params array

if ($date_filter) {
    $date_obj = DateTime::createFromFormat('d.m.Y', $date_filter);
    if ($date_obj) {
        $mysql_date = $date_obj->format('Y-m-d');
        $query .= " AND DATE(rs.date) = :date";
        $params[':date'] = $mysql_date;
    }
}

if ($search_query) {
    $query .= " AND (ao.proizvoditel LIKE :search OR rs.star_jo LIKE :search)";
    $params[':search'] = "%$search_query%";
}

$query .= " ORDER BY rs.date DESC";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки за проби</title>
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
        .bulk-actions {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Заявки за проби</h2>
                <div>
                    <a href="../index.php" class="btn btn-secondary me-2">Назад</a>
                    <button type="button" class="btn btn-info me-2" onclick="bulkDuplicate()">Дублирай всички</button>
                    <a href="new_request.php" class="btn btn-primary">Нова заявка</a>
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

            <?php if ($date_filter): ?>
            <div class="bulk-actions">
                <button id="selectAllBtn" class="btn btn-secondary me-2">Избери всички</button>
                <button id="deleteSelectedBtn" class="btn btn-danger" disabled>Изтрий избраните</button>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table id="requestsTable" class="table table-striped">
                    <thead>
                        <tr>
                            <?php if ($date_filter): ?>
                            <th><input type="checkbox" id="checkAll"></th>
                            <?php endif; ?>
                            <th>№</th>
                            <th>Дата</th>
                            <th>Животновъден обект</th>
                            <th>Пробовземач</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $index => $request): ?>
                            <tr>
                                <?php if ($date_filter): ?>
                                <td><input type="checkbox" class="request-checkbox" value="<?php echo $request['id']; ?>"></td>
                                <?php endif; ?>
                                <td></td>
                                <td><?php echo date('d.m.Y', strtotime($request['date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['proizvoditel'] . ' (' . $request['star_jo'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($request['taker_name'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?php echo $request['id']; ?>" class="btn btn-warning btn-sm">Редактирай</a>
                                        <button type="button" class="btn btn-info btn-sm" 
                                                onclick="duplicateRequest(<?php echo $request['id']; ?>)">Дублирай</button>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="confirmDelete(<?php echo $request['id']; ?>)">Изтрий</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bulk Duplicate Modal -->
    <div class="modal fade" id="bulkDuplicateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Дублиране на заявки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bulkDuplicateForm" action="process_bulk_duplicate.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        <div class="mb-3">
                            <label for="new_date" class="form-label">Нова дата</label>
                            <input type="text" class="form-control datepicker" id="new_date" name="date" required>
                        </div>
                        <div class="alert alert-info">
                            <?php if ($date_filter): ?>
                                Ще бъдат дублирани всички заявки за дата <?php echo htmlspecialchars($date_filter); ?>
                            <?php else: ?>
                                Ще бъдат дублирани всички заявки
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="button" class="btn btn-primary" onclick="submitBulkDuplicate()">Дублирай</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Selected Modal -->
    <div class="modal fade" id="deleteSelectedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Изтриване на избраните заявки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="deleteSelectedForm" action="delete_selected.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="selected_ids" id="selected_ids">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        <div class="alert alert-danger">
                            Сигурни ли сте, че искате да изтриете избраните заявки?
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="button" class="btn btn-danger" onclick="submitDeleteSelected()">Изтрий</button>
                </div>
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
            if (confirm('Сигурни ли сте, че искате да изтриете тази заявка?')) {
                const dateFilter = document.getElementById('date_filter').value;
                const searchElement = document.getElementById('search');
                const searchQuery = searchElement ? searchElement.value : '';
                window.location.href = 'delete.php?id=' + id + '&date=' + encodeURIComponent(dateFilter) + '&search=' + encodeURIComponent(searchQuery);
            }
        }

        function duplicateRequest(id) {
            const dateFilter = document.getElementById('date_filter').value;
            const searchQuery = document.getElementById('search').value;
            window.location.href = 'duplicate.php?id=' + id + '&date=' + encodeURIComponent(dateFilter) + '&search=' + encodeURIComponent(searchQuery);
        }

        function bulkDuplicate() {
            const modal = new bootstrap.Modal(document.getElementById('bulkDuplicateModal'));
            modal.show();
        }

        function submitBulkDuplicate() {
            document.getElementById('bulkDuplicateForm').submit();
        }

        function deleteSelected() {
            const selectedIds = [];
            document.querySelectorAll('.request-checkbox:checked').forEach(checkbox => {
                selectedIds.push(checkbox.value);
            });
            
            if (selectedIds.length === 0) {
                alert('Моля, изберете поне една заявка за изтриване.');
                return;
            }
            
            document.getElementById('selected_ids').value = JSON.stringify(selectedIds);
            const modal = new bootstrap.Modal(document.getElementById('deleteSelectedModal'));
            modal.show();
        }

        function submitDeleteSelected() {
            document.getElementById('deleteSelectedForm').submit();
        }

        let searchTimeout;

        function submitForm() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                document.getElementById('searchForm').submit();
            }, 500);
        }

        flatpickr(".datepicker", {
            locale: "bg",
            dateFormat: "d.m.Y",
            allowInput: true
        });

        let dataTable;

        $(document).ready(function() {
            <?php if ($date_filter): ?>
            // Handle select all checkbox
            $('#checkAll').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.request-checkbox').prop('checked', isChecked);
                updateDeleteButtonState();
            });

            // Handle individual checkboxes
            $(document).on('change', '.request-checkbox', function() {
                updateDeleteButtonState();
                
                // Update "select all" checkbox state
                const allChecked = $('.request-checkbox:checked').length === $('.request-checkbox').length;
                $('#checkAll').prop('checked', allChecked);
            });

            // Select all button functionality
            $('#selectAllBtn').on('click', function() {
                $('.request-checkbox').prop('checked', true);
                $('#checkAll').prop('checked', true);
                updateDeleteButtonState();
            });

            // Delete selected button
            $('#deleteSelectedBtn').on('click', function() {
                deleteSelected();
            });

            function updateDeleteButtonState() {
                const hasChecked = $('.request-checkbox:checked').length > 0;
                $('#deleteSelectedBtn').prop('disabled', !hasChecked);
            }
            <?php endif; ?>

            dataTable = $('#requestsTable').DataTable({
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
                order: [[<?php echo $date_filter ? '2' : '1'; ?>, "desc"]],
                pageLength: 25
            });

            // Add custom filter for date
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const dateFilter = $('#date_filter').val();
                    if (!dateFilter) return true;
                    const rowDate = data[<?php echo $date_filter ? '2' : '1'; ?>]; // Date column index
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
                dataTable.column(<?php echo $date_filter ? '1' : '0'; ?>, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function(cell, i) {
                    cell.innerHTML = i + 1;
                });
            });
        });
    </script>
</body>
</html> 