<?php
ob_start();
require_once('../config/db.php');
session_start();

// PhpSpreadsheet classes for Excel export
require_once __DIR__ . '/../import/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit();
}

function generateBarcode($code) {
    if (empty($code)) return '';
    try {
        require_once('lib/BarcodeGeneratorPNG.php');
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        return base64_encode($generator->getBarcode($code, $generator::TYPE_CODE_128));
    } catch (Exception $e) {
        return '';
    }
}

$randomBarcodes = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['count'])) {
    $count = (int)$_POST['count'];
    if ($count > 0) {
        // Start transaction
        $pdo->beginTransaction();
        // Select random barcodes that are not given
        $stmt = $pdo->prepare('SELECT barcode FROM barcodes WHERE is_given = 0 ORDER BY RAND() LIMIT ? FOR UPDATE');
        $stmt->bindValue(1, $count, PDO::PARAM_INT);
        $stmt->execute();
        $randomBarcodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($randomBarcodes) {
            // Mark them as given
            $barcodes = array_column($randomBarcodes, 'barcode');
            if (count($barcodes) > 0) {
                $in = str_repeat('?,', count($barcodes) - 1) . '?';
                $update = $pdo->prepare("UPDATE barcodes SET is_given = 1 WHERE barcode IN ($in)");
                $update->execute($barcodes);
                $pdo->commit();

                // --- Excel download logic ---
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setCellValue('A1', 'Barcode');
                $row = 2;
                foreach ($barcodes as $barcode) {
                    $sheet->setCellValue('A' . $row, $barcode);
                    $row++;
                }
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="randomized_barcodes.xlsx"');
                header('Cache-Control: max-age=0');
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
                // --- End Excel download logic ---
            } else {
                $pdo->rollBack();
                $error = 'Няма достатъчно свободни баркодове!';
            }
        } else {
            $pdo->rollBack();
            $error = 'Няма достатъчно свободни баркодове!';
        }
    } else {
        $error = 'Моля, въведете валиден брой.';
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Разбъркване на баркодове</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
    <style>
        .results {
            column-count: 2;
            column-gap: 20px;
            margin-top: 20px;
        }
        .barcode-container { 
            break-inside: avoid;
            page-break-inside: avoid;
            display: block;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
        }
        .barcode-image {
            text-align: center;
            padding: 5px 0;
        }
        .barcode-image img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 5px auto;
        }
        .barcode-number {
            font-size: 0.8em;
            text-align: center;
            margin-top: 2px !important;
        }
        @media print {
            body { 
                font-size: 9pt;
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .no-print { 
                display: none; 
            }
            .results {
                column-count: 2;
                column-gap: 15px;
                margin: 0;
            }
            h1 { 
                font-size: 14pt;
                margin: 0 0 10px 0;
            }
            .barcode-container { 
                border: none;
                border-bottom: 1px dashed #ccc;
                padding: 5px 0;
                margin-bottom: 5px;
                page-break-inside: avoid;
            }
            .barcode-image {
                padding: 3px 0;
            }
            .barcode-image img {
                margin: 3px auto;
            }
            @page {
                margin: 1cm;
                size: A4;
            }
        }
    </style>
</head>
<body class="container py-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2>Разбъркване на баркодове</h2>
        <a href="../" class="btn btn-secondary">Назад</a>
    </div>
    <form method="POST" class="no-print mb-4">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="count" class="form-label">Брой баркодове за разбъркване:</label>
                <input type="number" class="form-control" id="count" name="count" min="1" required value="<?= htmlspecialchars($_POST['count'] ?? '') ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Разбъркай</button>
                <button type="button" onclick="window.print()" class="btn btn-secondary">Принтирай</button>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>
    </form>
    <?php if ($randomBarcodes): ?>
        <div class="results">
            <?php foreach ($randomBarcodes as $item): ?>
                <?php $barcodeImage = generateBarcode($item['barcode']); ?>
                <div class="barcode-container">
                    <div class="barcode-image">
                        <?php if (!empty($barcodeImage)): ?>
                            <img src="data:image/png;base64,<?= $barcodeImage ?>" alt="Barcode">
                            <p class="barcode-number"> <?= htmlspecialchars($item['barcode']) ?> </p>
                        <?php else: ?>
                            <p class="text-danger">Невалиден баркод</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 