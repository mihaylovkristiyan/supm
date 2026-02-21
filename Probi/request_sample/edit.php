<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: list.php');
    exit;
}

$id = $_GET['id'];

// Get the request details
$stmt = $pdo->prepare("SELECT * FROM request_sample WHERE id = ?");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['error_message'] = 'Заявката не беше намерена.';
    header('Location: list.php');
    exit;
}

// Get all animal objects and takers for the dropdowns
$animal_objects = $pdo->query("SELECT star_jo, proizvoditel FROM animal_objects ORDER BY proizvoditel")->fetchAll(PDO::FETCH_ASSOC);
$takers = $pdo->query("SELECT id, ime FROM takers ORDER BY ime")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактиране на заявка</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Редактиране на проба</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <form action="update.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" value="<?php echo $request['id']; ?>">
                
                <div class="mb-3">
                    <label for="star_jo" class="form-label">Животновъден обект</label>
                    <select class="form-select select2" id="star_jo" name="star_jo" required>
                        <option value="">Изберете животновъден обект</option>
                        <?php foreach ($animal_objects as $object): ?>
                            <option value="<?php echo htmlspecialchars($object['star_jo']); ?>" 
                                    <?php echo $object['star_jo'] == $request['star_jo'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($object['proizvoditel'] . ' (' . $object['star_jo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="date" class="form-label">Дата</label>
                    <input type="text" class="form-control datepicker" id="date" name="date" 
                           value="<?php echo date('d.m.Y', strtotime($request['date'])); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="taker_id" class="form-label">Пробовземач</label>
                    <select class="form-select" id="taker_id" name="taker_id" required>
                        <option value="">Изберете пробовземач</option>
                        <?php foreach ($takers as $taker): ?>
                            <option value="<?php echo htmlspecialchars($taker['id']); ?>"
                                    <?php echo $taker['id'] == $request['taker_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($taker['ime']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Запази промените</button>
                    <a href="list.php" class="btn btn-secondary">Назад</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        flatpickr(".datepicker", {
            locale: "bg",
            dateFormat: "d.m.Y",
            allowInput: true
        });

        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                language: 'bg',
                placeholder: 'Изберете животновъден обект',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>
</html> 