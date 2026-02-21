<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/vendor/autoload.php';

use Spatie\PdfToImage\Pdf;
use ZipStream\ZipStream;

// Fetch all distinct star_jo for the dropdown
$star_jo_list = [];
try {
    $stmt = $db->query("SELECT DISTINCT star_jo FROM animal_objects WHERE star_jo IS NOT NULL AND star_jo != '' ORDER BY star_jo");
    $star_jo_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $star_jo_list = [];
}

// Get filter values
$default_start = date('Y-m-d', strtotime('-1 month'));
$default_end = date('Y-m-d');
$start_val = !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_val = !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end;
$star_jo_val = isset($_GET['star_jo']) ? $_GET['star_jo'] : '';

// Show filter form if dates are not set
if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
    ?>
    <!DOCTYPE html>
    <html lang="bg">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Изтегли JPEG от PDF по дати и Стар ЖО</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="icon" href="../icon.png">
    </head>
    <body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Изтегли JPEG от PDF</h2>
                <div>
                    <a href="../index.php" class="btn btn-secondary">Назад</a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">От дата:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_val); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">До дата:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_val); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="star_jo" class="form-label">Стар ЖО (по избор):</label>
                            <select class="form-control" id="star_jo" name="star_jo">
                                <option value="">-- Всички --</option>
                                <?php foreach ($star_jo_list as $star_jo): ?>
                                    <option value="<?php echo htmlspecialchars($star_jo); ?>" <?php if ($star_jo_val === $star_jo) echo 'selected'; ?>><?php echo htmlspecialchars($star_jo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Филтрирай и изтегли ZIP</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#star_jo').select2({
                width: '100%',
                placeholder: '-- Всички --',
                allowClear: true,
                language: 'bg'
            });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];
$star_jo_val = isset($_GET['star_jo']) ? $_GET['star_jo'] : '';

// Prepare SQL to fetch all samples in date range and by star_jo if set
$sql = "SELECT id, protokol_snimka, faktura_snimka FROM samples WHERE data BETWEEN :start_date AND :end_date";
$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];
if ($star_jo_val !== '') {
    $sql .= " AND star_jo = :star_jo";
    $params[':star_jo'] = $star_jo_val;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$samples = $stmt->fetchAll();

// Collect all PDF file paths and names
$pdfs = [];
foreach ($samples as $sample) {
    foreach (['protokol_snimka', 'faktura_snimka'] as $col) {
        if (!empty($sample[$col])) {
            $files = explode(',', $sample[$col]);
            foreach ($files as $file) {
                $file = trim($file);
                if ($file && file_exists($file)) {
                    $pdfs[] = $file;
                }
            }
        }
    }
}

if (empty($pdfs)) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "No PDF files found for the selected filters.";
    exit;
}

// Prepare ZIP stream
$zip = new ZipStream(outputName: 'converted_jpegs.zip', sendHttpHeaders: true);

foreach ($pdfs as $pdf_path) {
    try {
        $original_name = basename($pdf_path, '.pdf');
        $original_name = str_replace([' ', '.pdf'], ['_', ''], $original_name);

        $pdf = new Pdf($pdf_path);
        $pdf->setOutputFormat('jpeg');
        $pdf->setResolution(150); // reasonable quality
        $page_count = 1;
        try {
            $page_count = $pdf->getNumberOfPages();
        } catch (Exception $e) {
            $page_count = 1;
        }
        for ($page = 1; $page <= $page_count; $page++) {
            $pdf->setPage($page);
            $jpeg_name = $original_name . ($page_count > 1 ? ('_' . $page) : '') . '.jpeg';
            $tmp_jpeg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pdf2jpeg_', true) . '.jpeg';
            $pdf->saveImage($tmp_jpeg);
            if (file_exists($tmp_jpeg)) {
                $zip->addFileFromPath($jpeg_name, $tmp_jpeg);
                unlink($tmp_jpeg);
            }
        }
    } catch (Exception $e) {
        // Optionally log error, but do not output
        continue;
    }
}

$zip->finish();
exit; 