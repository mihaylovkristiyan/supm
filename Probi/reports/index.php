<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Get all unique star_jo and proizvoditel combinations for the dropdown
$stmt = $pdo->query("SELECT DISTINCT star_jo, proizvoditel FROM animal_objects ORDER BY star_jo");
$star_jo_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique probovzemach for the dropdown
$stmt = $pdo->query("SELECT DISTINCT id, ime FROM takers ORDER BY ime");
$takers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справки</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="icon" href="../icon.png">
    <style>
        .dt-buttons {
            margin-bottom: 15px;
        }
        .dt-button {
            margin-right: 10px;
        }
        .select2-container {
            width: 100% !important;
        }
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .table th, .table td {
                padding: 0.3rem !important;
                font-size: 11px !important;
            }
            .container-fluid {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Справки</h2> 
        <a href="../index.php" class="btn btn-secondary">Назад</a><br><br>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle active" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">Неплатени фактури</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item active" id="unpaid-tab" data-bs-toggle="tab" href="#unpaid" role="tab" aria-controls="unpaid" aria-selected="true">Всички неплатени</a></li>
                    <li><a class="dropdown-item" id="star-jo-tab" data-bs-toggle="tab" href="#star-jo" role="tab" aria-controls="star-jo" aria-selected="false">По стар ЖО</a></li>
                    <li><a class="dropdown-item" id="taker-tab" data-bs-toggle="tab" href="#taker" role="tab" aria-controls="taker" aria-selected="false">По пробовземач</a></li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="belejka-tab" data-bs-toggle="tab" href="#belejka" role="tab" aria-controls="belejka" aria-selected="false">По бележка</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="samples-tab" data-bs-toggle="tab" href="#samples" role="tab" aria-controls="samples" aria-selected="false">Справка за взетите проби</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="animal-objects-tab" data-bs-toggle="tab" href="#animal-objects" role="tab" aria-controls="animal-objects" aria-selected="false">Справка за ферми</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="request-sample-tab" data-bs-toggle="tab" href="#request-sample" role="tab" aria-controls="request-sample" aria-selected="false">Справка за заявки за проби</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="unpaid-by-jo-tab" data-bs-toggle="tab" href="#unpaid-by-jo" role="tab" aria-controls="unpaid-by-jo" aria-selected="false">
                    Справка за неплатени фактури по заявка
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="mandra-report-tab" data-bs-toggle="tab" href="#mandra-report" role="tab" aria-controls="mandra-report" aria-selected="false">
                    Справка по мандри
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="plateni-fakturi-tab" data-bs-toggle="tab" href="#plateni-fakturi" role="tab" aria-controls="plateni-fakturi" aria-selected="false">
                    Платени фактури
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3" id="reportTabContent">
            <!-- Unpaid Invoices Tab -->
            <div class="tab-pane fade show active" id="unpaid" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="unpaid_start_date">От дата:</label>
                            <input type="text" class="form-control datepicker" id="unpaid_start_date" name="start_date" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="unpaid_end_date">До дата:</label>
                            <input type="text" class="form-control datepicker" id="unpaid_end_date" name="end_date" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" onclick="generateReport('unpaid')">Генерирай справка</button>
                    </div>
                </div>
            </div>

            <!-- Star JO Tab -->
            <div class="tab-pane fade" id="star-jo" role="tabpanel">
                <form id="starJoForm" class="report-form" method="GET">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="report_type" value="star_jo_unpaid">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>От дата:</label>
                            <input type="text" class="form-control datepicker" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label>До дата:</label>
                            <input type="text" class="form-control datepicker" name="end_date" required>
                        </div>
                        <div class="col-md-4">
                            <label>Стар ЖО:</label>
                            <select class="form-control select2" name="star_jo">
                                <option value="">Всички стари ЖО</option>
                                <?php foreach ($star_jo_list as $jo): ?>
                                    <option value="<?= htmlspecialchars($jo['star_jo']) ?>"><?= htmlspecialchars($jo['star_jo'] . ' - ' . $jo['proizvoditel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Генериране</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Taker Tab -->
            <div class="tab-pane fade" id="taker" role="tabpanel">
                <form id="takerForm" class="report-form" method="GET">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="report_type" value="taker_unpaid">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>От дата:</label>
                            <input type="text" class="form-control datepicker" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label>До дата:</label>
                            <input type="text" class="form-control datepicker" name="end_date" required>
                        </div>
                        <div class="col-md-4">
                            <label>Пробовземач:</label>
                            <select class="form-control" name="taker_id" required>
                                <option value="">Изберете пробовземач</option>
                                <?php foreach ($takers as $taker): ?>
                                    <option value="<?= htmlspecialchars($taker['id']) ?>"><?= htmlspecialchars($taker['ime']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Генериране</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Belejka Tab -->
            <div class="tab-pane fade" id="belejka" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="belejka_start_date">От дата:</label>
                            <input type="text" class="form-control datepicker" id="belejka_start_date" name="start_date" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="belejka_end_date">До дата:</label>
                            <input type="text" class="form-control datepicker" id="belejka_end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="belejka">Бележка:</label>
                            <input type="text" class="form-control" id="belejka" name="belejka" placeholder="Въведете бележка">
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" onclick="generateReport('belejka')">Генерирай справка</button>
                    </div>
                </div>
            </div>

            <!-- Samples Report Tab -->
            <div class="tab-pane fade" id="samples" role="tabpanel">
                <form id="samplesForm" class="report-form" method="GET">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="report_type" value="samples">
                    
                    <div class="row mb-3">
                        <div class="col-md-12 mb-3">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="samples_view" id="normal" value="normal" checked>
                                <label class="btn btn-outline-primary" for="normal">Нормален изглед</label>

                                <input type="radio" class="btn-check" name="samples_view" id="multiple" value="multiple">
                                <label class="btn btn-outline-primary" for="multiple">Две проби</label>

                                <input type="radio" class="btn-check" name="samples_view" id="unlimited" value="unlimited">
                                <label class="btn btn-outline-primary" for="unlimited">Всички проби</label>

                                <input type="radio" class="btn-check" name="samples_view" id="paid_invoices" value="paid_invoices">
                                <label class="btn btn-outline-primary" for="paid_invoices">Платени фактури</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>От дата:</label>
                            <input type="text" class="form-control datepicker" name="start_date" required>
                        </div>
                        <div class="col-md-3">
                            <label>До дата:</label>
                            <input type="text" class="form-control datepicker" name="end_date" required>
                        </div>
                        <div class="col-md-3">
                            <label>Пробовземач:</label>
                            <select class="form-control select2" name="taker_id">
                                <option value="">Всички пробовземачи</option>
                                <?php foreach ($takers as $taker): ?>
                                    <option value="<?= htmlspecialchars($taker['id']) ?>"><?= htmlspecialchars($taker['ime']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>Област:</label>
                            <select class="form-control select2" name="oblast">
                                <option value="">Всички области</option>
                                <?php 
                                $oblast_query = "SELECT DISTINCT oblast FROM animal_objects WHERE oblast IS NOT NULL ORDER BY oblast";
                                $oblast_stmt = $pdo->query($oblast_query);
                                while ($oblast = $oblast_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($oblast['oblast']) . '">' . htmlspecialchars($oblast['oblast']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Населено място:</label>
                            <select class="form-control select2" name="naseleno_miasto">
                                <option value="">Всички населени места</option>
                                <?php
                                $city_query = "SELECT DISTINCT naseleno_miasto FROM animal_objects WHERE naseleno_miasto IS NOT NULL ORDER BY naseleno_miasto";
                                $city_stmt = $pdo->query($city_query);
                                while ($city = $city_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($city['naseleno_miasto']) . '">' . htmlspecialchars($city['naseleno_miasto']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Генериране</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Animal Objects Report Tab -->
            <div class="tab-pane fade" id="animal-objects" role="tabpanel">
                <form id="animalObjectsForm" class="report-form" method="GET">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="report_type" value="animal_objects">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Област:</label>
                            <select class="form-control select2" name="oblast">
                                <option value="">Всички области</option>
                                <?php 
                                $oblast_query = "SELECT DISTINCT oblast FROM animal_objects WHERE oblast IS NOT NULL ORDER BY oblast";
                                $oblast_stmt = $pdo->query($oblast_query);
                                while ($oblast = $oblast_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($oblast['oblast']) . '">' . htmlspecialchars($oblast['oblast']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Населено място:</label>
                            <select class="form-control select2" name="naseleno_miasto">
                                <option value="">Всички населени места</option>
                                <?php
                                $city_query = "SELECT DISTINCT naseleno_miasto FROM animal_objects WHERE naseleno_miasto IS NOT NULL ORDER BY naseleno_miasto";
                                $city_stmt = $pdo->query($city_query);
                                while ($city = $city_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($city['naseleno_miasto']) . '">' . htmlspecialchars($city['naseleno_miasto']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Стар ЖО:</label>
                            <select class="form-control select2" name="star_jo">
                                <option value="">Всички стари ЖО</option>
                                <?php foreach ($star_jo_list as $jo): ?>
                                    <option value="<?= htmlspecialchars($jo['star_jo']) ?>"><?= htmlspecialchars($jo['star_jo'] . ' - ' . $jo['proizvoditel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Генериране</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Request Sample Report Tab -->
            <div class="tab-pane fade" id="request-sample" role="tabpanel" aria-labelledby="request-sample-tab">
                <form id="requestSampleForm" class="report-form" method="GET">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="report_type" value="request_sample">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>От дата:</label>
                            <input type="text" class="form-control datepicker" name="start_date" required 
                                value="<?php echo date('d.m.Y'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label>До дата:</label>
                            <input type="text" class="form-control datepicker" name="end_date" required 
                                value="<?php echo date('d.m.Y'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Пробовземач:</label>
                            <select class="form-control select2" name="taker_id">
                                <option value="">Всички пробовземачи</option>
                                <?php foreach ($takers as $taker): ?>
                                    <option value="<?= htmlspecialchars($taker['id']) ?>"><?= htmlspecialchars($taker['ime']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Генериране</button>
                        </div>
                    </div>
                </form>
                <div id="requestSampleReport" class="mt-3"></div>
            </div>

            <!-- Unpaid Invoices by Request Tab -->
            <div class="tab-pane fade" id="unpaid-by-jo" role="tabpanel" aria-labelledby="unpaid-by-jo-tab">
                <div class="card mt-3">
                    <div class="card-body">
                        <form id="unpaidByJoForm" class="report-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="report_type" value="unpaid_by_jo">
                            
                            <div class="form-group mb-3">
                                <label for="specific_date">Дата:</label>
                                <input type="text" class="form-control datepicker" id="specific_date" name="specific_date" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Генерирай справка</button>
                        </form>
                        <div id="unpaidByJoReport" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <!-- Mandra Report Tab -->
            <div class="tab-pane fade" id="mandra-report" role="tabpanel" aria-labelledby="mandra-report-tab">
                <div class="card mt-3">
                    <div class="card-body">
                        <form id="mandraReportForm" class="report-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="report_type" value="mandra_report">
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mandra_start_date">От дата:</label>
                                        <input type="text" class="form-control datepicker" id="mandra_start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mandra_end_date">До дата:</label>
                                        <input type="text" class="form-control datepicker" id="mandra_end_date" name="end_date" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mandra_taker_id">Пробовземач:</label>
                                        <select class="form-control" id="mandra_taker_id" name="taker_id">
                                            <option value="">Всички</option>
                                            <?php foreach ($takers as $taker): ?>
                                                <option value="<?php echo $taker['id']; ?>"><?php echo htmlspecialchars($taker['ime']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mandra_oblast">Област:</label>
                                        <select class="form-control" id="mandra_oblast" name="oblast">
                                            <option value="">Всички</option>
                                            <?php foreach ($oblasti as $oblast): ?>
                                                <option value="<?php echo htmlspecialchars($oblast); ?>"><?php echo htmlspecialchars($oblast); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mandra_naseleno_miasto">Населено място:</label>
                                        <select class="form-control" id="mandra_naseleno_miasto" name="naseleno_miasto">
                                            <option value="">Всички</option>
                                            <?php foreach ($naseleni_mesta as $mesto): ?>
                                                <option value="<?php echo htmlspecialchars($mesto); ?>"><?php echo htmlspecialchars($mesto); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Генерирай справка по мандри</button>
                        </form>
                        <div id="mandraReport" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <!-- Paid Invoices Tab -->
            <div class="tab-pane fade" id="plateni-fakturi" role="tabpanel" aria-labelledby="plateni-fakturi-tab">
                <div class="card mt-3">
                    <div class="card-body">
                        <form id="plateniFakturiForm" class="report-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="report_type" value="plateni_fakturi">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="plateni_plashtane_text">Текст в плащане:</label>
                                        <input type="text" class="form-control" id="plateni_plashtane_text" name="plashtane_text" placeholder="Въведете текст за търсене в полето плащане">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">Генерирай справка за платени фактури</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div id="plateniFakturiReport" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="reportResult" class="mt-4">
            <!-- Results will be loaded here -->
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/bg.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Flatpickr for all date inputs
            flatpickr.localize(flatpickr.l10ns.bg);
            $('.datepicker').each(function() {
                flatpickr(this, {
                    dateFormat: "d.m.Y",
                    allowInput: true,
                    defaultDate: "today"
                });
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                language: 'bg'
            });

            // Function to initialize DataTable
            function initializeDataTable(tableId = '#reportTable') {
                if ($(tableId).length) {
                    if ($.fn.DataTable.isDataTable(tableId)) {
                        $(tableId).DataTable().destroy();
                    }
                    $(tableId).DataTable({
                        language: {
                            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Bulgarian.json"
                        },
                        pageLength: 25,
                        order: [[0, "asc"]],
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                    });
                }
            }

            // Handle all form submissions
            $('.report-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var tabPane = form.closest('.tab-pane');
                var resultContainer = tabPane.find('.mt-3');
                
                // If no specific result container found, use the general one
                if (resultContainer.length === 0) {
                    resultContainer = $('#reportResult');
                }
                
                // Clear previous results
                resultContainer.empty();
                
                // Show loading indicator
                resultContainer.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Зареждане...</span></div></div>');

                // Get the CSRF token
                var csrfToken = form.find('input[name="csrf_token"]').val();

                // Make AJAX request
                $.ajax({
                    url: 'generate_report.php',
                    type: 'GET',
                    data: form.serialize(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    },
                    success: function(response) {
                        resultContainer.html(response);
                        initializeDataTable();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        var errorMessage = 'Възникна грешка при генериране на справката.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        resultContainer.html('<div class="alert alert-danger">' + errorMessage + '</div>');
                    }
                });
            });

            // Function to generate reports
            window.generateReport = function(type) {
                var formData = {
                    csrf_token: $('input[name="csrf_token"]').val(),
                    report_type: type,
                    start_date: $(`#${type}_start_date`).val(),
                    end_date: $(`#${type}_end_date`).val()
                };

                if (type === 'belejka') {
                    formData.belezhka = $('#belejka').val();
                }

                var resultDiv = $('#reportResult');
                // Clear previous results
                resultDiv.empty();
                
                resultDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Зареждане...</span></div></div>');

                $.ajax({
                    url: 'generate_report.php',
                    type: 'GET',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': formData.csrf_token
                    },
                    success: function(response) {
                        resultDiv.html(response);
                        initializeDataTable();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        var errorMessage = 'Възникна грешка при генериране на справката.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        resultDiv.html('<div class="alert alert-danger">' + errorMessage + '</div>');
                    }
                });
            };

            // Initialize Bootstrap tabs
            var triggerTabList = [].slice.call(document.querySelectorAll('#reportTabs a'));
            triggerTabList.forEach(function(triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', function(event) {
                    event.preventDefault();
                    tabTrigger.show();
                    // Clear the report result when switching tabs
                    $('#reportResult').empty();
                });
            });

            // Add submit handlers for specific forms
            $('#samplesForm, #animalObjectsForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var resultDiv = $('#reportResult');
                
                // Clear previous results
                resultDiv.empty();
                
                // Show loading indicator
                resultDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Зареждане...</span></div></div>');

                $.ajax({
                    url: 'generate_report.php',
                    type: 'GET',
                    data: form.serialize(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': form.find('input[name="csrf_token"]').val()
                    },
                    success: function(response) {
                        resultDiv.html(response);
                        initializeDataTable();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        var errorMessage = 'Възникна грешка при генериране на справката.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        resultDiv.html('<div class="alert alert-danger">' + errorMessage + '</div>');
                    }
                });
            });
        });
    </script>
</body>
</html> 