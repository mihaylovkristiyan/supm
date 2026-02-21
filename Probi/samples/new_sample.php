<?php
require_once __DIR__ . '/../config/init.php';




// Require login for this page

requireLogin();



$success_message = '';

$error_message = '';



// Get all animal objects for dropdown

try {

    $stmt = $pdo->query("SELECT id, star_jo, nov_jo, proizvoditel FROM animal_objects ORDER BY star_jo");

    $animal_objects = $stmt->fetchAll();

} catch (PDOException $e) {

    $error_message = "Грешка при зареждане на животновъдните обекти: " . $e->getMessage();

}



// Get all takers for dropdown

try {

    $stmt = $pdo->query("SELECT id, ime FROM takers ORDER BY ime");

    $takers = $stmt->fetchAll();

} catch (PDOException $e) {

    $error_message = "Грешка при зареждане на пробовземачите: " . $e->getMessage();

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // Validate required fields

        $required_fields = ['animal_object_id', 'probovzemach_id', 'barkod', 'vid_mliako'];

        $errors = [];

        

        foreach ($required_fields as $field) {

            if (empty($_POST[$field])) {

                $errors[] = "Полето " . $field . " е задължително!";

            }

        }



        if (empty($errors)) {

            // Get nov_jo and star_jo from the selected animal object

            $stmt = $pdo->prepare("SELECT nov_jo, star_jo FROM animal_objects WHERE id = ?");

            $stmt->execute([$_POST['animal_object_id']]);

            $selected_object = $stmt->fetch();



            $sql = "INSERT INTO samples (data, probovzemach_id, barkod, vid_mliako, nov_jo, star_jo) 

                    VALUES (?, ?, ?, ?, ?, ?)";

            

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                $_POST['data'] ?? date('Y-m-d'),

                $_POST['probovzemach_id'],

                $_POST['barkod'],

                $_POST['vid_mliako'],

                $selected_object['nov_jo'],

                $selected_object['star_jo']

            ]);

            

            $success_message = "Пробата е добавена успешно!";

            // Clear form data after successful submission

            $_POST = array();

        } else {

            $error_message = implode("<br>", $errors);

        }

    } catch (PDOException $e) {

        $error_message = "Грешка при добавяне на пробата: " . $e->getMessage();

    }

}

?>

<!DOCTYPE html>

<html lang="bg">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Вземане на нова проба</title>

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

                <h2>Вземане на нова проба</h2>

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

                        <label for="animal_object_id" class="form-label required">Животновъден обект</label>

                        <select class="form-select" id="animal_object_id" name="animal_object_id" required>

                            <option value="">Изберете животновъден обект</option>

                            <?php foreach ($animal_objects as $object): ?>

                                <option value="<?php echo $object['id']; ?>" 

                                        <?php echo (isset($_POST['animal_object_id']) && $_POST['animal_object_id'] == $object['id']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($object['star_jo'] . ' - ' . $object['proizvoditel']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label for="data" class="form-label required">Дата</label>

                            <input type="date" class="form-control" id="data" name="data" 

                                   value="<?php echo $_POST['data'] ?? date('Y-m-d'); ?>" required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label for="probovzemach_id" class="form-label required">Пробовземач</label>

                            <select class="form-select" id="probovzemach_id" name="probovzemach_id" required>

                                <option value="">Изберете пробовземач</option>

                                <?php foreach ($takers as $taker): ?>

                                    <option value="<?php echo $taker['id']; ?>"

                                            <?php echo (isset($_POST['probovzemach_id']) && $_POST['probovzemach_id'] == $taker['id']) ? 'selected' : ''; ?>>

                                        <?php echo htmlspecialchars($taker['ime']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>



                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label for="barkod" class="form-label required">Баркод</label>

                            <input type="text" class="form-control" id="barkod" name="barkod" 

                                   value="<?php echo $_POST['barkod'] ?? ''; ?>" required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label for="vid_mliako" class="form-label required">Вид мляко</label>

                            <select class="form-select" id="vid_mliako" name="vid_mliako" required>

                                <option value="">Изберете вид мляко</option>

                                <option value="овче" <?php echo (isset($_POST['vid_mliako']) && $_POST['vid_mliako'] == 'овче') ? 'selected' : ''; ?>>Овче</option>

                                <option value="козе" <?php echo (isset($_POST['vid_mliako']) && $_POST['vid_mliako'] == 'козе') ? 'selected' : ''; ?>>Козе</option>

                                <option value="краве" <?php echo (isset($_POST['vid_mliako']) && $_POST['vid_mliako'] == 'краве') ? 'selected' : ''; ?>>Краве</option>

                                <option value="биволско" <?php echo (isset($_POST['vid_mliako']) && $_POST['vid_mliako'] == 'биволско') ? 'selected' : ''; ?>>Биволско</option>

                            </select>

                        </div>

                    </div>



                    <div class="d-grid gap-2">

                        <button type="submit" class="btn btn-success btn-lg">Добави проба</button>

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

            $('#animal_object_id').select2({

                placeholder: "Изберете животновъден обект",

                allowClear: true

            });

            

            $('#probovzemach_id').select2({

                placeholder: "Изберете пробовземач",

                allowClear: true

            });



            // Set default date to today

            if (!$('#data').val()) {

                $('#data').val(new Date().toISOString().split('T')[0]);

            }

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