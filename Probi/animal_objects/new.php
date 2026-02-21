<?php
require_once __DIR__ . '/../config/init.php';



// Require login for this page

requireLogin();



// Check if user has permission

if (!hasPermission('manage_animal_objects')) {

    header('Location: ../index.php');

    $_SESSION['error_message'] = 'Нямате достъп до тази страница.';

    exit;

}



// Set security headers

setSecurityHeaders();



// Validate POST request if submitted

validatePOSTRequest();



$success_message = '';

$error_message = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // Validate required fields

        $required_fields = ['bulstat', 'eik_egn', 'proizvoditel', 'star_jo', 'oblast', 'obshtina', 'naseleno_miasto'];

        $errors = [];

        

        // Validate and sanitize all inputs

        $sanitized_input = [];

        foreach ($_POST as $key => $value) {

            if (in_array($key, ['email', 'email_mandra'])) {

                $sanitized_input[$key] = validateInput($value, 'email');

            } elseif (in_array($key, ['telefon'])) {

                $sanitized_input[$key] = validateInput($value, 'string');

            } else {

                $sanitized_input[$key] = validateInput($value);

            }

        }

        

        foreach ($required_fields as $field) {

            if (empty($sanitized_input[$field])) {

                $errors[] = "Полето " . $field . " е задължително!";

            }

        }



        // Check if nov_jo or star_jo already exist

        $stmt = $pdo->prepare("SELECT nov_jo, star_jo FROM animal_objects WHERE nov_jo = ? OR star_jo = ?");

        $stmt->execute([$sanitized_input['nov_jo'], $sanitized_input['star_jo']]);

        if ($stmt->rowCount() > 0) {

            $errors[] = "Обектът вече съществува!";

        }



        if (empty($errors)) {

            $sql = "INSERT INTO animal_objects (bulstat, eik_egn, proizvoditel, nov_jo, star_jo, oblast, obshtina, naseleno_miasto, telefon, email, email_mandra, belezhka) 

                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                $sanitized_input['bulstat'],

                $sanitized_input['eik_egn'],

                $sanitized_input['proizvoditel'],

                $sanitized_input['nov_jo'],

                $sanitized_input['star_jo'],

                $sanitized_input['oblast'],

                $sanitized_input['obshtina'],

                $sanitized_input['naseleno_miasto'],

                $sanitized_input['telefon'] ?? null,

                $sanitized_input['email'] ?? null,

                $sanitized_input['email_mandra'] ?? null,

                $sanitized_input['belezhka'] ?? null

            ]);

            

            $success_message = "Животновъдният обект е добавен успешно!";

            // Clear form data after successful submission

            $_POST = array();

        } else {

            $error_message = implode("<br>", $errors);

        }

    } catch (PDOException $e) {

        $error_message = "Грешка при добавяне на записа: " . $e->getMessage();

    }

}

?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Нов Животновъден Обект</title>
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

        <div class="row">

            <div class="col-md-12">

                <div class="card">

                    <div class="card-header d-flex justify-content-between align-items-center">

                        <h2>Добавяне на нов животновъден обект</h2>

                        <a href="../index.php" class="btn btn-secondary">Назад</a>

                    </div>

                    <div class="card-body">

                        <?php if ($success_message): ?>

                            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>

                        <?php endif; ?>

                        

                        <?php if ($error_message): ?>

                            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>

                        <?php endif; ?>



                        <form method="POST" class="needs-validation" novalidate>

                            <?php outputCSRFToken(); ?>

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="bulstat" class="form-label required">Булстат</label>

                                    <input type="text" class="form-control" id="bulstat" name="bulstat" 

                                           value="<?php echo htmlspecialchars($_POST['bulstat'] ?? ''); ?>" required

                                           pattern="[0-9]+" title="Моля, въведете само цифри">

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="eik_egn" class="form-label required">ЕИК/ЕГН</label>

                                    <input type="text" class="form-control" id="eik_egn" name="eik_egn" 

                                           value="<?php echo htmlspecialchars($_POST['eik_egn'] ?? ''); ?>" required

                                           pattern="[0-9]+" title="Моля, въведете само цифри">

                                </div>

                            </div>



                            <div class="mb-3">

                                <label for="proizvoditel" class="form-label required">Производител</label>

                                <input type="text" class="form-control" id="proizvoditel" name="proizvoditel" 

                                       value="<?php echo htmlspecialchars($_POST['proizvoditel'] ?? ''); ?>" required>

                            </div>



                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="nov_jo" class="form-label">Нов ЖО</label>

                                    <input type="text" class="form-control" id="nov_jo" name="nov_jo" 

                                           value="<?php echo htmlspecialchars($_POST['nov_jo'] ?? ''); ?>">

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="star_jo" class="form-label required">Стар ЖО</label>

                                    <input type="text" class="form-control" id="star_jo" name="star_jo" 

                                           value="<?php echo htmlspecialchars($_POST['star_jo'] ?? ''); ?>" required>

                                </div>

                            </div>



                            <div class="row">

                                <div class="col-md-4 mb-3">

                                    <label for="oblast" class="form-label required">Област</label>

                                    <input type="text" class="form-control" id="oblast" name="oblast" 

                                           value="<?php echo htmlspecialchars($_POST['oblast'] ?? ''); ?>" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label for="obshtina" class="form-label required">Община</label>

                                    <input type="text" class="form-control" id="obshtina" name="obshtina" 

                                           value="<?php echo htmlspecialchars($_POST['obshtina'] ?? ''); ?>" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label for="naseleno_miasto" class="form-label required">Населено място</label>

                                    <input type="text" class="form-control" id="naseleno_miasto" name="naseleno_miasto" 

                                           value="<?php echo htmlspecialchars($_POST['naseleno_miasto'] ?? ''); ?>" required>

                                </div>

                            </div>



                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="telefon" class="form-label">Телефон</label>

                                    <input type="tel" class="form-control" id="telefon" name="telefon" 

                                           value="<?php echo htmlspecialchars($_POST['telefon'] ?? ''); ?>">

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="email" class="form-label">Email</label>

                                    <input type="email" class="form-control" id="email" name="email" 

                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email_mandra" class="form-label">Имейл на Мандра</label>
                                    <input type="email" class="form-control" id="email_mandra" name="email_mandra" 
                                           value="<?php echo htmlspecialchars($_POST['email_mandra'] ?? ''); ?>">
                                </div>
                            </div>



                            <div class="mb-3">

                                <label for="belezhka" class="form-label">Бележка</label>

                                <textarea class="form-control" id="belezhka" name="belezhka" rows="3"><?php echo htmlspecialchars($_POST['belezhka'] ?? ''); ?></textarea>

                            </div>



                            <div class="d-grid gap-2">

                                <button type="submit" class="btn btn-primary btn-lg">Добави животновъден обект</button>

                            </div>

                        </form>

                    </div>

                </div>

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