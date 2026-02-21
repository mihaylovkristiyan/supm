<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$success_message = '';
$error_message = '';
$sample = null;

// Get sample ID from URL
$sample_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$sample_id) {
    header('Location: ../index.php');
    exit;
}

// Fetch sample data
try {
    $stmt = $pdo->prepare("
        SELECT s.*, ao.proizvoditel, ao.nov_jo, ao.star_jo, t.ime as taker_name
        FROM samples s
        LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
        LEFT JOIN takers t ON s.probovzemach_id = t.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sample_id]);
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sample) {
        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на данните: " . $e->getMessage();
}

// Fetch all takers for dropdown
try {
    $takers_stmt = $pdo->query("SELECT id, ime FROM takers ORDER BY ime");
    $takers = $takers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на пробовземачите: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Невалиден CSRF токен.');
        }

        // Validate required fields
        $required_fields = ['data', 'probovzemach_id', 'barkod', 'vid_mliako'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Полето " . $field . " е задължително!";
            }
        }

        if (empty($errors)) {
            $sql = "UPDATE samples SET 
                    data = ?,
                    probovzemach_id = ?,
                    barkod = ?,
                    vid_mliako = ?,
                    protokol_nomer = ?,
                    star_jo = ?,
                    valid = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['data'],
                $_POST['probovzemach_id'],
                $_POST['barkod'],
                $_POST['vid_mliako'],
                $_POST['protokol_nomer'] ?? null,
                $_POST['star_jo'] ?? null,
                isset($_POST['valid']) ? 1 : 0,
                $sample_id
            ]);
            
            // Redirect back to edit.php with preserved filters
            $redirect_url = "edit.php";
            $params = [];
            if (isset($_GET['start_date'])) $params[] = 'start_date=' . urlencode($_GET['start_date']);
            if (isset($_GET['end_date'])) $params[] = 'end_date=' . urlencode($_GET['end_date']);
            
            // Add success parameter
            $params[] = 'success=1';
            
            if ($params) $redirect_url .= '?' . implode('&', $params);
            
            header("Location: " . $redirect_url);
            exit;
        } else {
            $error_message = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $error_message = "Грешка при обновяване на пробата: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактиране на проба</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Редактиране на проба</h2>
                <a href="edit.php<?php 
                    $params = [];
                    if (isset($_GET['start_date'])) $params[] = 'start_date=' . urlencode($_GET['start_date']);
                    if (isset($_GET['end_date'])) $params[] = 'end_date=' . urlencode($_GET['end_date']);
                    echo $params ? '?' . implode('&', $params) : '';
                ?>" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data" class="form-label required">Дата</label>
                            <input type="date" class="form-control" id="data" name="data" 
                                   value="<?php echo htmlspecialchars($sample['data']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="protokol_nomer" class="form-label">Протокол №</label>
                            <input type="text" class="form-control" id="protokol_nomer" name="protokol_nomer" 
                                   value="<?php echo htmlspecialchars($sample['protokol_nomer'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="probovzemach_id" class="form-label">Пробовземач*</label>
                            <select class="form-select" id="probovzemach_id" name="probovzemach_id" required>
                                <option value="">Изберете пробовземач</option>
                                <?php foreach ($takers as $taker): ?>
                                    <option value="<?php echo $taker['id']; ?>" 
                                            <?php echo ($sample['probovzemach_id'] == $taker['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($taker['ime']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Моля, изберете пробовземач.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="barkod" class="form-label">Баркод*</label>
                            <input type="text" class="form-control" id="barkod" name="barkod" required
                                   value="<?php echo htmlspecialchars($sample['barkod'] ?? ''); ?>">
                            <div class="invalid-feedback">Моля, въведете баркод.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="vid_mliako" class="form-label">Вид мляко*</label>
                            <select class="form-select" id="vid_mliako" name="vid_mliako" required>
                                <option value="">Изберете вид мляко</option>
                                <option value="овче" <?php echo ($sample['vid_mliako'] == 'овче') ? 'selected' : ''; ?>>Овче</option>
                                <option value="козе" <?php echo ($sample['vid_mliako'] == 'козе') ? 'selected' : ''; ?>>Козе</option>
                                <option value="краве" <?php echo ($sample['vid_mliako'] == 'краве') ? 'selected' : ''; ?>>Краве</option>
                                <option value="биволско" <?php echo ($sample['vid_mliako'] == 'биволско') ? 'selected' : ''; ?>>Биволско</option>
                            </select>
                            <div class="invalid-feedback">Моля, изберете вид мляко.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="nov_jo" class="form-label">Нов ЖО</label>
                            <input type="text" class="form-control" id="nov_jo" name="nov_jo"
                                   value="<?php echo htmlspecialchars($sample['nov_jo'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="star_jo" class="form-label">Стар ЖО</label>
                            <input type="text" class="form-control" id="star_jo" name="star_jo"
                                   value="<?php echo htmlspecialchars($sample['star_jo'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="valid" name="valid" 
                                       <?php echo ($sample['valid'] ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="valid">
                                    Валидна проба
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Ако НЕ е отметната, пробата ще бъде маркирана като НЕВАЛИДНА
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Запази промените</button>
                            <a href="edit.php<?php 
                                $params = [];
                                if (isset($_GET['start_date'])) $params[] = 'start_date=' . urlencode($_GET['start_date']);
                                if (isset($_GET['end_date'])) $params[] = 'end_date=' . urlencode($_GET['end_date']);
                                echo $params ? '?' . implode('&', $params) : '';
                            ?>" class="btn btn-secondary ms-2">Отказ</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Initialize date picker
        flatpickr("#data", {
            dateFormat: "Y-m-d",
            locale: "bg"
        });
    </script>
</body>
</html> 