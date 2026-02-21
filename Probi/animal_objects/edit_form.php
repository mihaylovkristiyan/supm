<?php
require_once __DIR__ . '/../config/init.php';

$success_message = '';
$error_message = '';
$animal_object = null;

if (!isset($_GET['id'])) {
    header('Location: edit.php');
    exit;
}

try {
    // Get current data
    $stmt = $pdo->prepare("SELECT * FROM animal_objects WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $animal_object = $stmt->fetch();

    if (!$animal_object) {
        header('Location: edit.php');
        exit;
    }

} catch (PDOException $e) {
    $error_message = "Грешка при обработка на данните: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактиране на Животновъден Обект</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
    <style>
        .required::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Редактиране на животновъден обект</h2>
                <div>
                    <a href="edit.php" class="btn btn-secondary me-2">Назад</a>
                    <a href="../index.php" class="btn btn-primary">Начало</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <form method="POST" action="process_update.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($animal_object['id']); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulstat" class="form-label required">Булстат</label>
                            <input type="text" class="form-control" id="bulstat" name="bulstat" 
                                   value="<?php echo htmlspecialchars($animal_object['bulstat']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="eik_egn" class="form-label required">ЕИК/ЕГН</label>
                            <input type="text" class="form-control" id="eik_egn" name="eik_egn" 
                                   value="<?php echo htmlspecialchars($animal_object['eik_egn']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="proizvoditel" class="form-label required">Производител</label>
                        <input type="text" class="form-control" id="proizvoditel" name="proizvoditel" 
                               value="<?php echo htmlspecialchars($animal_object['proizvoditel']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nov_jo" class="form-label required">Нов ЖО</label>
                            <input type="text" class="form-control" id="nov_jo" name="nov_jo" 
                                   value="<?php echo htmlspecialchars($animal_object['nov_jo']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="star_jo" class="form-label required">Стар ЖО</label>
                            <input type="text" class="form-control" id="star_jo" name="star_jo" 
                                   value="<?php echo htmlspecialchars($animal_object['star_jo']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="oblast" class="form-label required">Област</label>
                            <input type="text" class="form-control" id="oblast" name="oblast" 
                                   value="<?php echo htmlspecialchars($animal_object['oblast']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="obshtina" class="form-label required">Община</label>
                            <input type="text" class="form-control" id="obshtina" name="obshtina" 
                                   value="<?php echo htmlspecialchars($animal_object['obshtina']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="naseleno_miasto" class="form-label required">Населено място</label>
                            <input type="text" class="form-control" id="naseleno_miasto" name="naseleno_miasto" 
                                   value="<?php echo htmlspecialchars($animal_object['naseleno_miasto']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefon" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="telefon" name="telefon" 
                                   value="<?php echo htmlspecialchars($animal_object['telefon'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($animal_object['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email_mandra" class="form-label">Имейл на Мандра</label>
                            <input type="email" class="form-control" id="email_mandra" name="email_mandra" 
                                   value="<?php echo htmlspecialchars($animal_object['email_mandra'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="belezhka" class="form-label">Бележка</label>
                        <textarea class="form-control" id="belezhka" name="belezhka" rows="3"><?php echo htmlspecialchars($animal_object['belezhka'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">Запази промените</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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