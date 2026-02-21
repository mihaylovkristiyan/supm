<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$success_message = '';
$error_message = '';

// Get all animal objects
try {
    $stmt = $pdo->query("SELECT * FROM animal_objects ORDER BY star_jo");
    $animal_objects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на данните: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактиране на Животновъден Обект</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Редактиране на животновъден обект</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <table id="animalObjectsTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Стар ЖО</th>
                            <th>Нов ЖО</th>
                            <th>Производител</th>
                            <th>Област</th>
                            <th>Община</th>
                            <th>Населени място</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($animal_objects as $object): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($object['star_jo']); ?></td>
                                <td><?php echo htmlspecialchars($object['nov_jo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($object['proizvoditel']); ?></td>
                                <td><?php echo htmlspecialchars($object['oblast']); ?></td>
                                <td><?php echo htmlspecialchars($object['obshtina']); ?></td>
                                <td><?php echo htmlspecialchars($object['naseleno_miasto']); ?></td>
                                <td>
                                    <a href="edit_form.php?id=<?php echo $object['id']; ?>" 
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
            $('#animalObjectsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Bulgarian.json"
                },
                "pageLength": 25,
                "order": [[0, "asc"]]
            });
        });
    </script>
</body>
</html> 