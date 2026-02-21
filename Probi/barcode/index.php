<?php
// Start output buffering at the very beginning
ob_start();

// Include required libraries first
require_once('../config/db.php');
require_once('lib/BarcodeGeneratorPNG.php');
require_once('lib/Exceptions/BarcodeException.php');

// Start session after includes
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit();
}

// Function to generate CODE-128 barcode
function generateBarcode($code) {
    if (empty($code)) return '';
    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        return base64_encode($generator->getBarcode($code, $generator::TYPE_CODE_128));
    } catch (Exception $e) {
        return '';
    }
}

// Handle form submission and exports
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    // Fetch barcodes from database
    $query = "SELECT s.barkod, s.data, s.protokol_nomer, s.vid_mliako, ao.proizvoditel 
              FROM samples s 
              LEFT JOIN animal_objects ao ON s.nov_jo = ao.nov_jo 
              WHERE s.data BETWEEN ? AND ?
              ORDER BY s.data DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For now, we'll only show the results in HTML format
    // Excel and PDF export functionality will be added once libraries are properly installed
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Баркодове</title>
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
        .barcode-info { 
            margin-bottom: 5px;
        }
        .barcode-info p { 
            margin: 0;
            line-height: 1.3;
            font-size: 0.9em;
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
            .barcode-info p {
                line-height: 1.2;
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
                <h2>Преглед на баркодове</h2>
                <a href="../" class="btn btn-secondary">Назад</a>
            </div>
    
    <form method="POST" class="no-print mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">От дата:</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required 
                       value="<?= $_POST['start_date'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">До дата:</label>
                <input type="date" class="form-control" id="end_date" name="end_date" required
                       value="<?= $_POST['end_date'] ?? '' ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Покажи</button>
                <button type="button" onclick="window.print()" class="btn btn-secondary">Принтирай</button>
            </div>
        </div>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="results">
            <?php foreach ($results as $item): ?>
                <?php $barcodeImage = generateBarcode($item['barkod']); ?>
                <?php if (!empty($item['barkod'])): ?>
                    <div class="barcode-container">
                        <div class="barcode-info">
                            <p><strong>Дата:</strong> <?= htmlspecialchars($item['data']) ?></p>
                            <p><strong>№:</strong> <?= htmlspecialchars($item['protokol_nomer']) ?></p>
                            <p><strong>Мляко:</strong> <?= htmlspecialchars($item['vid_mliako']) ?></p>
                            <p><strong>Производител:</strong> <?= htmlspecialchars($item['proizvoditel']) ?></p>
                        </div>
                        <div class="barcode-image">
                            <?php if (!empty($barcodeImage)): ?>
                                <img src="data:image/png;base64,<?= $barcodeImage ?>" alt="Barcode">
                                <p class="barcode-number"><?= htmlspecialchars($item['barkod']) ?></p>
                            <?php else: ?>
                                <p class="text-danger">Невалиден баркод</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 