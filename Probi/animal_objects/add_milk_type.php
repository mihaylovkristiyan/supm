<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

$success_message = '';
$error_message = '';

// Get all animal objects for dropdown
try {
    $stmt = $pdo->query("SELECT star_jo, proizvoditel FROM animal_objects ORDER BY star_jo");
    $animal_objects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на животновъдните обекти: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['star_jo']) || empty($_POST['milk_type'])) {
            $error_message = "Всички полета са задължителни!";
        } else {
            $sql = "INSERT INTO animal_milk_types (star_jo, milk_type) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['star_jo'], $_POST['milk_type']]);
            
            $success_message = "Видът мляко е добавен успешно!";
            // Clear form data after successful submission
            $_POST = array();
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            $error_message = "Този вид мляко вече е добавен за този животновъден обект!";
        } else {
            $error_message = "Грешка при добавяне на вида мляко: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавяне на вид мляко</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="icon" href="../icon.png">
    <style>
        .required::after {
            content: " *";
            color: red;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Добавяне на вид мляко</h2>
                <a href="../index.php" class="btn btn-secondary">Назад</a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="star_jo" class="form-label required">Животновъден обект</label>
                        <select class="form-select" id="star_jo" name="star_jo" required>
                            <option value="">Изберете животновъден обект</option>
                            <?php foreach ($animal_objects as $object): ?>
                                <option value="<?php echo htmlspecialchars($object['star_jo']); ?>" 
                                        <?php echo (isset($_POST['star_jo']) && $_POST['star_jo'] == $object['star_jo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($object['star_jo'] . ' - ' . $object['proizvoditel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="milk_type" class="form-label required">Вид мляко</label>
                        <select class="form-select" id="milk_type" name="milk_type" required>
                            <option value="">Изберете вид мляко</option>
                            <option value="овче" <?php echo (isset($_POST['milk_type']) && $_POST['milk_type'] == 'овче') ? 'selected' : ''; ?>>Овче</option>
                            <option value="козе" <?php echo (isset($_POST['milk_type']) && $_POST['milk_type'] == 'козе') ? 'selected' : ''; ?>>Козе</option>
                            <option value="краве" <?php echo (isset($_POST['milk_type']) && $_POST['milk_type'] == 'краве') ? 'selected' : ''; ?>>Краве</option>
                            <option value="биволско" <?php echo (isset($_POST['milk_type']) && $_POST['milk_type'] == 'биволско') ? 'selected' : ''; ?>>Биволско</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">Добави вид мляко</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for better dropdown experience
            $('#star_jo').select2({
                placeholder: "Изберете животновъден обект",
                allowClear: true
            });
        });

        // Custom form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html> 