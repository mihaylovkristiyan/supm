<?php

require_once __DIR__ . '/../config/init.php';


// Require admin role for this page

requireAdmin();



error_reporting(E_ALL);

ini_set('display_errors', 1);



require '../config/db_connect.php';



// Validate CSRF token for POST requests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {

        $_SESSION['error_message'] = 'Invalid CSRF token';

        header('Location: import_data.php');

        exit;

    }

}



// Check if vendor directory exists, if not create it

if (!file_exists(__DIR__ . '/vendor')) {

    mkdir(__DIR__ . '/vendor', 0777, true);

}



// Check if composer.json exists

if (!file_exists(__DIR__ . '/composer.json')) {

    file_put_contents(__DIR__ . '/composer.json', json_encode([

        "require" => [

            "phpoffice/phpspreadsheet" => "^1.29"

        ]

    ], JSON_PRETTY_PRINT));

}



// Check if vendor/autoload.php exists

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {

    die("Please run 'composer install' in the import directory. You can do this by:<br>

        1. Open Command Prompt as Administrator<br>

        2. Navigate to " . __DIR__ . "<br>

        3. Run: composer install<br>

        <br>

        If composer is not recognized, try:<br>

        - Close and reopen Command Prompt after installing Composer<br>

        - Run: C:\\ProgramData\\ComposerSetup\\bin\\composer.bat install<br>

        - Or run: php C:\\ProgramData\\ComposerSetup\\bin\\composer.phar install");

}



require __DIR__ . '/vendor/autoload.php';



use PhpOffice\PhpSpreadsheet\IOFactory;



function importAnimalObjects($filepath) {

    global $pdo;

    

    try {

        $spreadsheet = IOFactory::load($filepath);

        $worksheet = $spreadsheet->getActiveSheet();

        $rows = $worksheet->toArray();

        

        // Skip header row

        array_shift($rows);

        

        $stmt = $pdo->prepare("

            INSERT INTO animal_objects 

            (bulstat, eik_egn, proizvoditel, nov_jo, star_jo, oblast, obshtina, naseleno_miasto, telefon, email, belezhka)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ");

        

        $count = 0;

        foreach ($rows as $row) {

            if (!empty($row[0])) { // Check if row is not empty

                try {

                    $stmt->execute([
                        $row[0], // bulstat
                        $row[1], // eik_egn
                        $row[2], // proizvoditel
                        $row[3], // nov_jo
                        $row[4], // star_jo
                        $row[5], // oblast
                        $row[6], // obshtina
                        $row[7], // naseleno_miasto
                        $row[8], // telefon
                        $row[9], // email
                        $row[10] // belezhka

                    ]);

                    $count++;

                } catch (PDOException $e) {

                    echo "Error importing row: " . implode(", ", $row) . "<br>";

                    echo "Error message: " . $e->getMessage() . "<br><br>";

                }

            }

        }

        return $count;

    } catch (Exception $e) {

        throw new Exception("Error reading Excel file: " . $e->getMessage());

    }

}



function importTakers($filepath) {

    global $pdo;

    

    try {

        $spreadsheet = IOFactory::load($filepath);

        $worksheet = $spreadsheet->getActiveSheet();

        $rows = $worksheet->toArray();

        

        // Skip header row

        array_shift($rows);

        

        $stmt = $pdo->prepare("INSERT INTO takers (ime) VALUES (?)");

        

        $count = 0;

        foreach ($rows as $row) {

            if (!empty($row[0])) { // Check if row is not empty

                try {

                    $stmt->execute([$row[0]]);

                    $count++;

                } catch (PDOException $e) {

                    echo "Error importing row: " . $row[0] . "<br>";

                    echo "Error message: " . $e->getMessage() . "<br><br>";

                }

            }

        }

        return $count;

    } catch (Exception $e) {

        throw new Exception("Error reading Excel file: " . $e->getMessage());

    }

}



function importSamples($filepath) {

    global $pdo;

    

    try {

        $spreadsheet = IOFactory::load($filepath);

        $worksheet = $spreadsheet->getActiveSheet();

        $rows = $worksheet->toArray();

        

        // Skip header row

        array_shift($rows);

        

        // First, get all takers for reference

        $takers_stmt = $pdo->query("SELECT id, ime FROM takers");

        $takers = $takers_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        

        $stmt = $pdo->prepare("

            INSERT INTO samples 

            (protokol_nomer, data, probovzemach_id, barkod, vid_mliako, faktura, plashtane, nov_jo, star_jo)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)

        ");

        

        $count = 0;

        foreach ($rows as $row) {

            if (!empty($row[0])) { // Check if row is not empty

                try {

                    // Convert date format if needed

                    $date = !empty($row[1]) ? date('Y-m-d', strtotime($row[1])) : null;

                    

                    // Find taker ID by name

                    $taker_id = array_search($row[2], $takers) ?: null;

                    

                    $stmt->execute([

                        $row[0], // protokol_nomer
                        $row[1], // data
                        $row[2], // probovzemach_id
                        $row[3], // barkod
                        $row[4], // vid_mliako
                        $row[5], // faktura
                        $row[6], // plashtane
                        $row[7], // nov_jo
                        $row[8]  // star_jo

                    ]);

                    $count++;

                } catch (PDOException $e) {

                    echo "Error importing row: " . implode(", ", $row) . "<br>";

                    echo "Error message: " . $e->getMessage() . "<br><br>";

                }

            }

        }

        return $count;

    } catch (Exception $e) {

        throw new Exception("Error reading Excel file: " . $e->getMessage());

    }

}



// Add barcode import function

function importBarcodes($filepath) {

    global $pdo;

    try {

        $spreadsheet = IOFactory::load($filepath);

        $worksheet = $spreadsheet->getActiveSheet();

        $rows = $worksheet->toArray();

        // Skip header row

        array_shift($rows);

        $stmt = $pdo->prepare("INSERT INTO barcodes (barcode, is_given) VALUES (?, ?)");

        $count = 0;

        foreach ($rows as $row) {

            if (!empty($row[0])) {

                $barcode = trim($row[0]);

                $is_given = (isset($row[1]) && strtolower(trim($row[1])) === 'true') ? 1 : 0;

                try {

                    $stmt->execute([$barcode, $is_given]);

                    $count++;

                } catch (PDOException $e) {

                    echo "Error importing barcode: $barcode<br>";

                    echo "Error message: " . $e->getMessage() . "<br><br>";

                }

            }

        }

        return $count;

    } catch (Exception $e) {

        throw new Exception("Error reading Excel file: " . $e->getMessage());

    }

}

?>

<!DOCTYPE html>

<html lang="bg">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Импортиране на данни</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

    <div class="container mt-5">

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">

                <h2>Импортиране на данни от Excel</h2>

                <a href="../index.php" class="btn btn-secondary">Назад</a>

            </div>

            <div class="card-body">

                <?php

                if (isset($_SESSION['error_message'])) {

                    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';

                    unset($_SESSION['error_message']);

                }

                ?>

                <form method="POST" enctype="multipart/form-data" class="mb-4">

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">

                        <label for="animal_objects" class="form-label">Excel файл с животновъдни обекти</label>

                        <input type="file" class="form-control" id="animal_objects" name="animal_objects" accept=".xlsx,.xls">

                    </div>

                    <div class="mb-3">

                        <label for="takers" class="form-label">Excel файл с пробовземачи</label>

                        <input type="file" class="form-control" id="takers" name="takers" accept=".xlsx,.xls">

                    </div>

                    <div class="mb-3">

                        <label for="samples" class="form-label">Excel файл с проби</label>

                        <input type="file" class="form-control" id="samples" name="samples" accept=".xlsx,.xls">

                    </div>

                    <div class="mb-3">

                        <label for="barcodes" class="form-label">Excel файл с баркодове (1-ва колона: баркод, 2-ра: true/false)</label>

                        <input type="file" class="form-control" id="barcodes" name="barcodes" accept=".xlsx,.xls">

                    </div>

                    <button type="submit" class="btn btn-primary">Импортирай данните</button>

                </form>



                <?php

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                    try {

                        $results = [];

                        

                        // Import animal objects

                        if (isset($_FILES['animal_objects']) && $_FILES['animal_objects']['error'] === UPLOAD_ERR_OK) {

                            $count = importAnimalObjects($_FILES['animal_objects']['tmp_name']);

                            $results[] = "Импортирани $count животновъдни обекта.";

                        }

                        

                        // Import takers

                        if (isset($_FILES['takers']) && $_FILES['takers']['error'] === UPLOAD_ERR_OK) {

                            $count = importTakers($_FILES['takers']['tmp_name']);

                            $results[] = "Импортирани $count пробовземача.";

                        }

                        

                        // Import samples

                        if (isset($_FILES['samples']) && $_FILES['samples']['error'] === UPLOAD_ERR_OK) {

                            $count = importSamples($_FILES['samples']['tmp_name']);

                            $results[] = "Импортирани $count проби.";

                        }

                        

                        // Import barcodes

                        if (isset($_FILES['barcodes']) && $_FILES['barcodes']['error'] === UPLOAD_ERR_OK) {

                            $count = importBarcodes($_FILES['barcodes']['tmp_name']);

                            $results[] = "Импортирани $count баркода.";

                        }

                        

                        if (!empty($results)) {

                            echo '<div class="alert alert-success">' . implode("<br>", $results) . '</div>';

                        }

                    } catch (Exception $e) {

                        echo '<div class="alert alert-danger">Грешка при импортиране: ' . $e->getMessage() . '</div>';

                    }

                }

                ?>

            </div>

        </div>

    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html> 