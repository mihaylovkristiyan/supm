<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();


$error_message = '';

$samples = [];



try {

    // Fetch all samples with related data

    $stmt = $pdo->query("

        SELECT s.*, ao.proizvoditel, t.ime as taker_name

        FROM samples s

        LEFT JOIN animal_objects ao ON (s.nov_jo = ao.nov_jo OR s.star_jo = ao.star_jo)

        LEFT JOIN takers t ON s.probovzemach_id = t.id

        ORDER BY s.data DESC

    ");

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

    <title>Списък с проби</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <link rel="icon" href="../icon.png">

</head>

<body>

    <div class="container-fluid mt-5">

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">

                <h2>Списък с проби</h2>

                <div>

                    <a href="new_sample.php" class="btn btn-success me-2">Нова проба</a>

                    <a href="../index.php" class="btn btn-secondary">Назад</a>

                </div>

            </div>

            <div class="card-body">

                <?php if ($error_message): ?>

                    <div class="alert alert-danger"><?php echo $error_message; ?></div>

                <?php endif; ?>



                <div class="table-responsive">

                    <table id="samplesTable" class="table table-striped table-bordered">

                        <thead>

                            <tr>

                                <th>Дата</th>

                                <th>Протокол №</th>

                                <th>Пробовземач</th>

                                <th>Баркод</th>

                                <th>Вид мляко</th>

                                <th>Производител</th>

                                <th>Нов ЖО</th>

                                <th>Стар ЖО</th>

                                <th>Фактура</th>

                                <th>Плащане</th>

                                <th>Действия</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($samples as $sample): ?>

                                <tr>

                                    <td><?php echo htmlspecialchars($sample['data']); ?></td>

                                    <td><?php echo htmlspecialchars($sample['protokol_nomer'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['taker_name'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['barkod']); ?></td>

                                    <td><?php echo htmlspecialchars($sample['vid_mliako']); ?></td>

                                    <td><?php echo htmlspecialchars($sample['proizvoditel'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['nov_jo'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['star_jo'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['faktura'] ?? ''); ?></td>

                                    <td><?php echo htmlspecialchars($sample['plashtane'] ?? ''); ?></td>

                                    <td>

                                        <a href="edit_sample.php?id=<?php echo $sample['id']; ?>" 

                                           class="btn btn-primary btn-sm">

                                            Редактирай

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>



    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>

        $(document).ready(function() {

            $('#samplesTable').DataTable({

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

                pageLength: 25

            });

        });

    </script>

</body>

</html> 