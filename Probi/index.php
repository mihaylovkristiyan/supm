<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?system=Probi');
    exit;
}

// Check user role - redirect non-admin users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Probi/user/');
    exit;
}

// Temporary error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load the initialization file
require_once __DIR__ . '/config/init.php';

// Require login for this page
requireLogin();

// Function to create a form button
function createFormButton($href, $class, $text) {
    $token = $_SESSION['csrf_token'];
    return <<<HTML
    <form method="GET" action="$href" style="display: inline-block; flex: 1; min-width: 200px;">
        <input type="hidden" name="csrf_token" value="$token">
        <button type="submit" class="btn $class section-button" style="width: 100%;">$text</button>
    </form>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система за управление на проби</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="icon.png">
    <style>
        .section-card {
            margin-bottom: 2rem;
            transition: transform 0.2s;
        }
        .section-card:hover {
            transform: translateY(-5px);
        }
        .section-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .section-button {
            padding: 1rem;
            font-size: 1.1rem;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .main-title {
            color: #2c3e50;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #3498db;
        }
        .user-info {
            text-align: right;
            margin-bottom: 1rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="user-info">
            <a href="../index.html" class="btn btn-secondary">Назад</a>
            Здравейте, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Потребител'); ?> |
            <form method="POST" action="../logout.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" class="btn btn-link text-danger p-0">Изход</button>
            </form>
        </div>
        
        <h1 class="text-center main-title">Система за управление на проби</h1>

        <!-- Животновъден Обект Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Животновъден обект</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <a href="animal_objects/new.php" class="btn btn-primary section-button" style="flex: 1; min-width: 200px; width: 100%;">Нов обект</a>
                    <?php
                    echo createFormButton('animal_objects/edit.php', 'btn-warning', 'Редактиране');
                    echo createFormButton('animal_objects/add_milk_type.php', 'btn-info text-white', 'Добавяне на вид мляко');
                    echo createFormButton('animal_objects/delete.php', 'btn-danger', 'Изтриване');
                    ?>
                </div>
            </div>
        </div>

        <!-- Проби Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Проби</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('samples/new_sample.php', 'btn-primary', 'Нова проба');
                    echo createFormButton('samples/edit.php', 'btn-warning', 'Редактиране');
                    echo createFormButton('samples/delete.php', 'btn-danger', 'Изтриване');
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Заявка за проби Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Заявка за проби</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('request_sample/new_request.php', 'btn-primary', 'Нова заявка');
                    echo createFormButton('request_sample/edit.php', 'btn-warning', 'Редактиране');
                    echo createFormButton('request_sample/duplicate.php', 'btn-info text-white', 'Дублиране');
                    echo createFormButton('request_sample/delete.php', 'btn-danger', 'Изтриване');
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Въвеждане на протокол Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Въвеждане на протокол</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('protocols/add_protocol.php', 'btn-info text-white', 'Въвеждане на протокол');
                    echo createFormButton('protocols/edit.php', 'btn-warning', 'Редактиране');
                    echo createFormButton('protocols/list.php', 'btn-dark', 'Импортиране на протокол');
                    ?>
                </div>
            </div>
        </div>

        <!-- Въвеждане на фактура Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Въвеждане на фактура</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('invoices/add_invoice.php', 'btn-primary', 'Въвеждане');
                    echo createFormButton('invoices/edit_invoice.php', 'btn-warning', 'Редактиране');
                    echo createFormButton('invoices/list.php', 'btn-dark', 'Импортиране на фактура');
                    ?>
                </div>
            </div>
        </div>

        <!-- Плащане на фактура Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Плащане на фактура</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('invoices/payment.php', 'btn-dark', 'Плащане на фактура');
                    ?>
                </div>
            </div>
        </div>

        <!-- Справки Section -->
        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Справки</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('reports/index.php', 'btn-dark', 'Справки');
                    ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Баркод</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('barcode', 'btn-primary', 'Баркодове');
                    echo createFormButton('barcode/generate.php', 'btn-warning', 'Генериране');
                    echo createFormButton('barcode/randomizer.php', 'btn-info', 'Разбъркване');
                    ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Импортиране</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('import/import_data.php', 'btn-dark', 'Импортиране');
                    ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header">
                <h2 class="mb-0">Email</h2>
            </div>
            <div class="card-body">
                <div class="section-buttons">
                    <?php
                    echo createFormButton('email/sender.php', 'btn-dark', 'Изпращане на email');
                    echo createFormButton('email/mandra.php', 'btn-info text-white', 'Изпращане към мандри');
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 