<?php
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/html; charset=utf-8');

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to the appropriate system based on the request
    $redirect = isset($_GET['system']) ? $_GET['system'] : 'Probi';
    header("Location: /$redirect/index.php");
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect to the database (using the same connection as in init.php)
        $pdo = new PDO(
            "mysql:host=localhost;dbname=supmonli_db;charset=utf8mb4",
            "supmonli_admin",
            "fckTT7e}UG)A",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $system = $_POST['system'] ?? 'Probi';

        // Validate input
        if (empty($username) || empty($password)) {
            $error = "Моля, въведете потребителско име и парола.";
        } else {
            // Check credentials
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, full_name, role 
                FROM users 
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // For debugging
            error_log("Login attempt - Username: " . $username);
            error_log("User found: " . ($user ? 'Yes' : 'No'));
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Redirect to the appropriate system
                header("Location: /$system/index.php");
                exit;
            } else {
                $error = "Невалидно потребителско име или парола.";
            }
        }
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        $error = "Възникна грешка при влизане. Моля, опитайте отново.";
    }
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в системата</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .system-selector {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="login-title">Вход в системата</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Потребителско име</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Парола</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="mb-3 system-selector">
                    <label for="system" class="form-label">Изберете система</label>
                    <select class="form-select" id="system" name="system">
                        <option value="Probi">Проби</option>
                        <option value="SZHP">СЖП</option>
                        <option value="Marshruti">Маршрути и разходна норма</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">Вход</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 