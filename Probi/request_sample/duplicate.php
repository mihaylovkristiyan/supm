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

// Get animal object and taker information
$stmt = $pdo->prepare("
    SELECT ao.proizvoditel, t.ime as taker_name 
    FROM request_sample rs
    LEFT JOIN animal_objects ao ON rs.star_jo = ao.star_jo
    LEFT JOIN takers t ON rs.taker_id = t.id
    WHERE rs.id = ?
");
$stmt->execute([$id]);
$details = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дублиране на заявка</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="mb-4">Дублиране на заявка</h2>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Оригинална заявка</h5>
                    <p class="mb-1"><strong>Животновъден обект:</strong> <?php echo htmlspecialchars($details['proizvoditel'] . ' (' . $request['star_jo'] . ')'); ?></p>
                    <p class="mb-1"><strong>Дата:</strong> <?php echo date('d.m.Y', strtotime($request['date'])); ?></p>
                    <p class="mb-0"><strong>Пробовземач:</strong> <?php echo htmlspecialchars($details['taker_name']); ?></p>
                </div>
            </div>

            <form action="process_duplicate.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="original_id" value="<?php echo $request['id']; ?>">
                <input type="hidden" name="star_jo" value="<?php echo htmlspecialchars($request['star_jo']); ?>">
                <input type="hidden" name="taker_id" value="<?php echo htmlspecialchars($request['taker_id']); ?>">
                
                <div class="mb-3">
                    <label for="date" class="form-label">Нова дата</label>
                    <input type="text" class="form-control datepicker" id="date" name="date" required>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Създай дубликат</button>
                    <a href="list.php" class="btn btn-secondary">Назад</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
    <script>
        flatpickr(".datepicker", {
            locale: "bg",
            dateFormat: "d.m.Y",
            allowInput: true,
            defaultDate: "today"
        });
    </script>
</body>
</html> 