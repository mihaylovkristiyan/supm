<?php
require_once __DIR__ . '/../config/init.php';



// Require login for this page

requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Probi/user/');
    exit;
}



$success_message = '';

$error_message = '';

$samples = [];

$selected_date = $_GET['date'] ?? date('Y-m-d');



// Get samples for the selected date that don't have a protocol number

try {

    $stmt = $pdo->prepare("

        SELECT s.*, ao.proizvoditel, t.ime as probovzemach_ime 

        FROM samples s

        LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo

        LEFT JOIN takers t ON s.probovzemach_id = t.id

        WHERE s.data = ? AND (s.protokol_nomer IS NULL OR TRIM(s.protokol_nomer) = '')

        ORDER BY s.barkod

    ");

    $stmt->execute([$selected_date]);

    $samples = $stmt->fetchAll();

} catch (PDOException $e) {

    $error_message = "Грешка при зареждане на пробите: " . $e->getMessage();

}



// Handle form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['samples'])) {

    try {

        // Validate CSRF token

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {

            throw new Exception('Невалиден CSRF токен.');

        }



        $pdo->beginTransaction();

        

        $stmt = $pdo->prepare("UPDATE samples SET protokol_nomer = ? WHERE id = ?");

        $updated_count = 0;

        

        foreach ($_POST['samples'] as $sample_id => $protocol_number) {

            // Handle both empty string and null cases

            if (trim($protocol_number) === '') {

                $stmt = $pdo->prepare("UPDATE samples SET protokol_nomer = NULL WHERE id = ?");

                $stmt->execute([$sample_id]);

                $updated_count++;

            } else if (!empty($protocol_number)) {

                $stmt = $pdo->prepare("UPDATE samples SET protokol_nomer = ? WHERE id = ?");

                $stmt->execute([$protocol_number, $sample_id]);

                $updated_count++;

            }

        }

        

        $pdo->commit();

        

        if ($updated_count > 0) {

            $success_message = "Успешно обновени $updated_count проби!";

        }

        

        // Always redirect back to the same page with the same date

        header("Location: add_protocol.php?date=" . urlencode($selected_date));

        exit;

        

    } catch (PDOException $e) {

        $pdo->rollBack();

        $error_message = "Грешка при обновяване на протоколните номера: " . $e->getMessage();

    } catch (Exception $e) {

        $error_message = $e->getMessage();

    }

}

?>

<!DOCTYPE html>

<html lang="bg">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Въвеждане на протокол</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <link rel="icon" href="../icon.png">

    <style>

        .protocol-input {

            width: 150px !important;

        }

    </style>

</head>

<body>

    <div class="container mt-5">

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">

                <h2>Въвеждане на протокол</h2>

                <a href="../index.php" class="btn btn-secondary">Назад</a>

            </div>

            <div class="card-body">

                <?php if ($success_message): ?>

                    <div class="alert alert-success"><?php echo $success_message; ?></div>

                <?php endif; ?>

                

                <?php if ($error_message): ?>

                    <div class="alert alert-danger"><?php echo $error_message; ?></div>

                <?php endif; ?>



                <!-- Date Filter Form -->

                <form method="GET" class="mb-4">

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row align-items-end">

                        <div class="col-md-4">

                            <label for="date" class="form-label">Изберете дата</label>

                            <input type="date" class="form-control" id="date" name="date" 

                                   value="<?php echo $selected_date; ?>">

                        </div>

                        <div class="col-md-2">

                            <button type="submit" class="btn btn-primary">Филтрирай</button>

                        </div>

                    </div>

                </form>



                <?php if (empty($samples)): ?>

                    <div class="alert alert-info">

                        Няма намерени проби без протоколен номер за избраната дата.

                    </div>

                <?php else: ?>

                    <form method="POST">

                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <table id="samplesTable" class="table table-striped table-bordered">

                            <thead>

                                <tr>

                                    <th>№</th>

                                    <th>Баркод</th>

                                    <th>Производител</th>

                                    <th>Стар ЖО</th>

                                    <th>Нов ЖО</th>

                                    <th>Вид мляко</th>

                                    <th>Пробовземач</th>

                                    <th>Протокол №</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php 

                                $row_number = 1;

                                foreach ($samples as $sample): ?>

                                    <tr>

                                        <td><?= $row_number++ ?></td>

                                        <td><?= htmlspecialchars($sample['barkod']) ?></td>

                                        <td><?= htmlspecialchars($sample['proizvoditel'] ?? '') ?></td>

                                        <td><?= htmlspecialchars($sample['star_jo'] ?? '') ?></td>

                                        <td><?= htmlspecialchars($sample['nov_jo'] ?? '') ?></td>

                                        <td><?= htmlspecialchars($sample['vid_mliako']) ?></td>

                                        <td><?= htmlspecialchars($sample['probovzemach_ime']) ?></td>

                                        <td>

                                            <input type="text" class="form-control protocol-input" 

                                                   name="samples[<?= $sample['id'] ?>]" 

                                                   placeholder="Въведете номер">

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                        <div class="d-grid gap-2">

                            <button type="submit" class="btn btn-success btn-lg">Запази протоколни номера</button>

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

    <script>

        $(document).ready(function() {

            $('.samplesTable').DataTable({

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

                order: [[0, "desc"]], // Sort by date descending

                pageLength: 10

            });



            // Date picker initialization

            $('#start_date, #end_date').datepicker({

                format: 'yyyy-mm-dd',

                autoclose: true,

                language: 'bg'

            });



            // Set default dates

            var today = new Date();

            var lastMonth = new Date();

            lastMonth.setMonth(lastMonth.getMonth() - 1);

            

            $('#start_date').datepicker('setDate', lastMonth);

            $('#end_date').datepicker('setDate', today);

        });

    </script>

</body>

</html> 