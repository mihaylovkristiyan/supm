<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/request_handler.php';
handleRequest();

// Set headers for security first, before any possible output
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/html; charset=utf-8');

// Require login for this page
requireLogin();

// Check if user has permission to access reports
if (!hasPermission('view_reports')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Нямате достъп до тази страница.']);
    exit;
}

// CSRF protection
$csrf_token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : 
              (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null);

if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Невалиден CSRF токен.']);
    exit;
}

// CSRF protection for AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Невалидна заявка.']);
    exit;
}

// Validate required parameters
if (!isset($_GET['report_type'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Липсващ тип справка.']);
    exit;
}

// Validate and sanitize input
$report_type = isset($_GET['report_type']) ? htmlspecialchars($_GET['report_type'], ENT_QUOTES, 'UTF-8') : '';

// Validate report type
$allowed_report_types = ['unpaid', 'star_jo_unpaid', 'taker_unpaid', 'belejka', 'samples', 'animal_objects', 'request_sample', 'mandra_report', 'plateni_fakturi'];
if (!in_array($report_type, $allowed_report_types)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Невалиден тип справка.']);
    exit;
}

// Validate dates only for report types that need them
if ($report_type !== 'animal_objects' && (!isset($_GET['start_date']) || !isset($_GET['end_date']))) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Липсващи задължителни дати.']);
    exit;
}

// Validate date format if dates are provided
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
$start_date = DateTime::createFromFormat('d.m.Y', $_GET['start_date']);
$end_date = DateTime::createFromFormat('d.m.Y', $_GET['end_date']);

if (!$start_date || !$end_date) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Невалиден формат на дата.']);
    exit;
}

// Convert dates to MySQL format
$start_date = $start_date->format('Y-m-d');
$end_date = $end_date->format('Y-m-d');

// Validate date range (prevent querying too large date ranges)
$date_diff = strtotime($end_date) - strtotime($start_date);
if ($date_diff < 0 || $date_diff > (365 * 2 * 24 * 60 * 60)) { // Max 2 years
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Невалиден период. Максималният период е 2 години.']);
    exit;
}
}

// Base SQL for all reports
$base_sql = "SELECT 
    ao.bulstat,
    ao.eik_egn,
    ao.proizvoditel,
    ao.nov_jo,
    ao.star_jo,
    ao.oblast,
    ao.obshtina,
    ao.naseleno_miasto,
    ao.telefon,
    ao.email,
    s.faktura,
    t.ime as probovzemach,
    s.data as sample_date
FROM samples s
JOIN animal_objects ao ON s.star_jo = ao.star_jo
JOIN takers t ON s.probovzemach_id = t.id";

// Initialize params array
$params = [];

// Only add date parameters if they exist and are needed
if (isset($start_date) && isset($end_date) && $report_type !== 'animal_objects') {
    $base_sql .= " WHERE s.data BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

// Add unpaid condition for all unpaid reports
$unpaid_condition = " AND (s.faktura IS NULL OR s.faktura = '' OR s.plashtane IS NULL OR s.plashtane = '')";

// Modify query based on report type
switch ($report_type) {
    case 'unpaid':
        $base_sql .= $unpaid_condition;
        if (!empty($_GET['star_jo'])) {
            $star_jo = htmlspecialchars($_GET['star_jo'], ENT_QUOTES, 'UTF-8');
            $base_sql .= " AND ao.star_jo = :star_jo";
            $params[':star_jo'] = $star_jo;
        }
        break;
        
    case 'star_jo_unpaid':
        $star_jo = isset($_GET['star_jo']) ? htmlspecialchars($_GET['star_jo'], ENT_QUOTES, 'UTF-8') : '';
        $base_sql .= $unpaid_condition . " AND ao.star_jo = :star_jo";
        $params[':star_jo'] = $star_jo;
        break;
        
    case 'taker_unpaid':
        $taker_id = isset($_GET['taker_id']) ? htmlspecialchars($_GET['taker_id'], ENT_QUOTES, 'UTF-8') : '';
        $base_sql .= $unpaid_condition . " AND s.probovzemach_id = :taker_id";
        $params[':taker_id'] = $taker_id;
        break;
        
    case 'belejka':
        $belejka = isset($_GET['belejka']) ? htmlspecialchars($_GET['belejka'], ENT_QUOTES, 'UTF-8') : '';
        $base_sql = "SELECT 
                    s.id as protokol_nomer,
                    s.data,
                    t.ime as probovzemach,
                    s.barkod,
                    ao.proizvoditel,
                    ao.star_jo,
                    s.vid_mliako,
                    ao.oblast,
                    ao.obshtina,
                    ao.naseleno_miasto,
                    ao.telefon,
                    s.faktura,
                    s.plashtane,
                    ao.email,
                    ao.belezhka
                FROM animal_objects ao
                LEFT JOIN samples s ON s.star_jo = ao.star_jo
                LEFT JOIN takers t ON s.probovzemach_id = t.id";
        
        $params = [];

        if (!empty($belejka)) {
            // If belezhka text is provided, filter only by text content
            $base_sql .= " WHERE ao.belezhka LIKE :belejka";
            $params[':belejka'] = '%' . $belejka . '%';
        } else {
            // If no belezhka text is provided, show only records that have a belezhka and filter by date
            $base_sql .= " WHERE ao.belezhka IS NOT NULL AND ao.belezhka != '' 
                         AND (s.data BETWEEN :start_date AND :end_date OR s.data IS NULL)";
            $params[':start_date'] = $start_date;
            $params[':end_date'] = $end_date;
        }

        $base_sql .= " ORDER BY s.data DESC";
        break;
        
    case 'samples':
        $params = [];
        $where_conditions = [];
        $samples_view = isset($_GET['samples_view']) ? htmlspecialchars($_GET['samples_view'], ENT_QUOTES, 'UTF-8') : 'normal';

        if (!empty($_GET['start_date'])) {
            $where_conditions[] = "s.data >= :start_date";
            $params[':start_date'] = $start_date;
        }

        if (!empty($_GET['end_date'])) {
            $where_conditions[] = "s.data <= :end_date";
            $params[':end_date'] = $end_date;
        }

        if (!empty($_GET['taker_id'])) {
            $where_conditions[] = "s.probovzemach_id = :taker_id";
            $params[':taker_id'] = $_GET['taker_id'];
        }

        if (!empty($_GET['oblast'])) {
            $where_conditions[] = "ao.oblast = :oblast";
            $params[':oblast'] = $_GET['oblast'];
        }

        if (!empty($_GET['naseleno_miasto'])) {
            $where_conditions[] = "ao.naseleno_miasto = :naseleno_miasto";
            $params[':naseleno_miasto'] = $_GET['naseleno_miasto'];
        }

        // Add condition for paid invoices view
        if ($samples_view === 'paid_invoices') {
            $where_conditions[] = "(s.faktura IS NOT NULL AND s.faktura != '' AND s.data IS NOT NULL AND s.plashtane IS NOT NULL AND s.plashtane != '')";
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        if ($samples_view === 'multiple') {
            // Query for multiple samples view
            $base_query = "WITH SamplesRanked AS (
                SELECT 
                    s.data,
                    s.protokol_nomer,
                    s.barkod,
                    s.vid_mliako,
                    s.star_jo,
                    ao.nov_jo,
                    ao.proizvoditel,
                    ao.oblast,
                    ao.obshtina,
                    ao.naseleno_miasto,
                    t.ime as taker_name,
                    ROW_NUMBER() OVER (PARTITION BY s.star_jo ORDER BY s.data) as sample_number,
                    COUNT(*) OVER (PARTITION BY s.star_jo) as sample_count
                FROM samples s
                LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
                LEFT JOIN takers t ON s.probovzemach_id = t.id
                $where_clause
            )
            SELECT DISTINCT
                sr1.star_jo,
                sr1.nov_jo,
                sr1.proizvoditel,
                sr1.oblast,
                sr1.obshtina,
                sr1.naseleno_miasto,
                sr1.taker_name,
                sr1.vid_mliako,
                MIN(sr1.data) OVER (PARTITION BY sr1.star_jo) as first_sample_date,
                MIN(CASE WHEN sr1.data > MIN(sr1.data) OVER (PARTITION BY sr1.star_jo) THEN sr1.data END) 
                    OVER (PARTITION BY sr1.star_jo) as second_sample_date
            FROM SamplesRanked sr1
            WHERE sr1.sample_count >= 1
            ORDER BY first_sample_date DESC";

            $stmt = $pdo->prepare($base_query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo '<table id="reportTable" class="table table-striped table-bordered">';
                echo '<thead><tr>';
                echo '<th>№</th>';
                echo '<th>Стар ЖО</th>';
                echo '<th>Нов ЖО</th>';
                echo '<th>Производител</th>';
                echo '<th>Област</th>';
                echo '<th>Община</th>';
                echo '<th>Населено място</th>';
                echo '<th>Пробовземач</th>';
                echo '<th>Вид мляко</th>';
                echo '<th>Първа проба</th>';
                echo '<th>Втора проба</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                $row_number = 1;
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row_number++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['nov_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['obshtina'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['taker_name'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                    echo '<td>' . ($row['first_sample_date'] ? date('d.m.Y', strtotime($row['first_sample_date'])) : '') . '</td>';
                    echo '<td>' . ($row['second_sample_date'] ? date('d.m.Y', strtotime($row['second_sample_date'])) : '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
            }
        } else if ($samples_view === 'unlimited') {
            // Query for unlimited samples view
            $base_query = "WITH SamplesRanked AS (
                SELECT 
                    s.data,
                    s.protokol_nomer,
                    s.barkod,
                    s.vid_mliako,
                    s.star_jo,
                    ao.nov_jo,
                    ao.proizvoditel,
                    ao.oblast,
                    ao.obshtina,
                    ao.naseleno_miasto,
                    t.ime as taker_name,
                    ROW_NUMBER() OVER (PARTITION BY s.star_jo ORDER BY s.data) as sample_number,
                    COUNT(*) OVER (PARTITION BY s.star_jo) as sample_count
                FROM samples s
                LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
                LEFT JOIN takers t ON s.probovzemach_id = t.id
                $where_clause
            )
            SELECT 
                sr.star_jo,
                sr.nov_jo,
                sr.proizvoditel,
                sr.oblast,
                sr.obshtina,
                sr.naseleno_miasto,
                sr.vid_mliako,
                sr.sample_count,
                MAX(CASE WHEN sr.sample_number = 1 THEN sr.data END) as sample_1_date,
                MAX(CASE WHEN sr.sample_number = 2 THEN sr.data END) as sample_2_date,
                MAX(CASE WHEN sr.sample_number = 3 THEN sr.data END) as sample_3_date,
                MAX(CASE WHEN sr.sample_number = 4 THEN sr.data END) as sample_4_date,
                MAX(CASE WHEN sr.sample_number = 5 THEN sr.data END) as sample_5_date,
                MAX(CASE WHEN sr.sample_number = 6 THEN sr.data END) as sample_6_date,
                MAX(CASE WHEN sr.sample_number = 7 THEN sr.data END) as sample_7_date,
                MAX(CASE WHEN sr.sample_number = 8 THEN sr.data END) as sample_8_date,
                MAX(CASE WHEN sr.sample_number = 9 THEN sr.data END) as sample_9_date,
                MAX(CASE WHEN sr.sample_number = 10 THEN sr.data END) as sample_10_date,
                MAX(CASE WHEN sr.sample_number = 11 THEN sr.data END) as sample_11_date,
                MAX(CASE WHEN sr.sample_number = 12 THEN sr.data END) as sample_12_date,
                MAX(CASE WHEN sr.sample_number = 13 THEN sr.data END) as sample_13_date,
                MAX(CASE WHEN sr.sample_number = 14 THEN sr.data END) as sample_14_date,
                MAX(CASE WHEN sr.sample_number = 15 THEN sr.data END) as sample_15_date,
                MAX(CASE WHEN sr.sample_number = 16 THEN sr.data END) as sample_16_date,
                MAX(CASE WHEN sr.sample_number = 17 THEN sr.data END) as sample_17_date,
                MAX(CASE WHEN sr.sample_number = 18 THEN sr.data END) as sample_18_date,
                MAX(CASE WHEN sr.sample_number = 19 THEN sr.data END) as sample_19_date,
                MAX(CASE WHEN sr.sample_number = 20 THEN sr.data END) as sample_20_date,
                MAX(CASE WHEN sr.sample_number = 21 THEN sr.data END) as sample_21_date,
                MAX(CASE WHEN sr.sample_number = 22 THEN sr.data END) as sample_22_date,
                MAX(CASE WHEN sr.sample_number = 23 THEN sr.data END) as sample_23_date,
                MAX(CASE WHEN sr.sample_number = 24 THEN sr.data END) as sample_24_date
            FROM SamplesRanked sr
            GROUP BY 
                sr.star_jo,
                sr.nov_jo,
                sr.proizvoditel,
                sr.oblast,
                sr.obshtina,
                sr.naseleno_miasto,
                sr.vid_mliako,
                sr.sample_count
            ORDER BY sr.star_jo";

            $stmt = $pdo->prepare($base_query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo '<table id="reportTable" class="table table-striped table-bordered">';
                echo '<thead><tr>';
                echo '<th>№</th>';
                echo '<th>Стар ЖО</th>';
                echo '<th>Нов ЖО</th>';
                echo '<th>Производител</th>';
                echo '<th>Област</th>';
                echo '<th>Община</th>';
                echo '<th>Населени място</th>';
                echo '<th>Вид мляко</th>';
                echo '<th>Брой проби</th>';
                for ($i = 1; $i <= 24; $i++) {
                    echo '<th>' . $i . ' Проба</th>';
                }
                echo '</tr></thead>';
                echo '<tbody>';

                $row_number = 1;
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row_number++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['nov_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['obshtina'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                    echo '<td>' . $row['sample_count'] . '</td>';
                    for ($i = 1; $i <= 24; $i++) {
                        $date_field = 'sample_' . $i . '_date';
                        echo '<td>' . ($row[$date_field] ? date('d.m.Y', strtotime($row[$date_field])) : '') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
            }
        } else if ($samples_view === 'paid_invoices') {
            // Query for paid invoices view
            $base_query = "WITH InvoicesRanked AS (
                SELECT 
                    s.data,
                    s.protokol_nomer,
                    s.barkod,
                    s.vid_mliako,
                    s.star_jo,
                    ao.nov_jo,
                    ao.proizvoditel,
                    ao.oblast,
                    ao.obshtina,
                    ao.naseleno_miasto,
                    t.ime as taker_name,
                    ROW_NUMBER() OVER (PARTITION BY s.star_jo ORDER BY s.data) as invoice_number,
                    COUNT(*) OVER (PARTITION BY s.star_jo) as invoice_count
                FROM samples s
                LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
                LEFT JOIN takers t ON s.probovzemach_id = t.id
                $where_clause
            )
            SELECT 
                ir.star_jo,
                ir.nov_jo,
                ir.proizvoditel,
                ir.oblast,
                ir.obshtina,
                ir.naseleno_miasto,
                ir.vid_mliako,
                ir.invoice_count,
                MAX(CASE WHEN ir.invoice_number = 1 THEN ir.data END) as invoice_1_date,
                MAX(CASE WHEN ir.invoice_number = 2 THEN ir.data END) as invoice_2_date,
                MAX(CASE WHEN ir.invoice_number = 3 THEN ir.data END) as invoice_3_date,
                MAX(CASE WHEN ir.invoice_number = 4 THEN ir.data END) as invoice_4_date,
                MAX(CASE WHEN ir.invoice_number = 5 THEN ir.data END) as invoice_5_date,
                MAX(CASE WHEN ir.invoice_number = 6 THEN ir.data END) as invoice_6_date,
                MAX(CASE WHEN ir.invoice_number = 7 THEN ir.data END) as invoice_7_date,
                MAX(CASE WHEN ir.invoice_number = 8 THEN ir.data END) as invoice_8_date,
                MAX(CASE WHEN ir.invoice_number = 9 THEN ir.data END) as invoice_9_date,
                MAX(CASE WHEN ir.invoice_number = 10 THEN ir.data END) as invoice_10_date,
                MAX(CASE WHEN ir.invoice_number = 11 THEN ir.data END) as invoice_11_date,
                MAX(CASE WHEN ir.invoice_number = 12 THEN ir.data END) as invoice_12_date,
                MAX(CASE WHEN ir.invoice_number = 13 THEN ir.data END) as invoice_13_date,
                MAX(CASE WHEN ir.invoice_number = 14 THEN ir.data END) as invoice_14_date,
                MAX(CASE WHEN ir.invoice_number = 15 THEN ir.data END) as invoice_15_date,
                MAX(CASE WHEN ir.invoice_number = 16 THEN ir.data END) as invoice_16_date,
                MAX(CASE WHEN ir.invoice_number = 17 THEN ir.data END) as invoice_17_date,
                MAX(CASE WHEN ir.invoice_number = 18 THEN ir.data END) as invoice_18_date,
                MAX(CASE WHEN ir.invoice_number = 19 THEN ir.data END) as invoice_19_date,
                MAX(CASE WHEN ir.invoice_number = 20 THEN ir.data END) as invoice_20_date,
                MAX(CASE WHEN ir.invoice_number = 21 THEN ir.data END) as invoice_21_date,
                MAX(CASE WHEN ir.invoice_number = 22 THEN ir.data END) as invoice_22_date,
                MAX(CASE WHEN ir.invoice_number = 23 THEN ir.data END) as invoice_23_date,
                MAX(CASE WHEN ir.invoice_number = 24 THEN ir.data END) as invoice_24_date
            FROM InvoicesRanked ir
            GROUP BY 
                ir.star_jo,
                ir.nov_jo,
                ir.proizvoditel,
                ir.oblast,
                ir.obshtina,
                ir.naseleno_miasto,
                ir.vid_mliako,
                ir.invoice_count
            ORDER BY ir.star_jo";

            $stmt = $pdo->prepare($base_query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo '<table id="reportTable" class="table table-striped table-bordered">';
                echo '<thead><tr>';
                echo '<th>№</th>';
                echo '<th>Стар ЖО</th>';
                echo '<th>Нов ЖО</th>';
                echo '<th>Производител</th>';
                echo '<th>Област</th>';
                echo '<th>Община</th>';
                echo '<th>Населени място</th>';
                echo '<th>Вид мляко</th>';
                echo '<th>Брой фактури</th>';
                for ($i = 1; $i <= 24; $i++) {
                    echo '<th>' . $i . ' Фактура</th>';
                }
                echo '</tr></thead>';
                echo '<tbody>';

                $row_number = 1;
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row_number++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['nov_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['obshtina'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                    echo '<td>' . $row['invoice_count'] . '</td>';
                    for ($i = 1; $i <= 24; $i++) {
                        $date_field = 'invoice_' . $i . '_date';
                        echo '<td>' . ($row[$date_field] ? date('d.m.Y', strtotime($row[$date_field])) : '') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
            }
        } else {
            // Normal view query
            $base_query = "SELECT 
                s.protokol_nomer,
                s.data,
                t.ime as probovzemach,
                s.barkod,
                ao.bulstat,
                ao.eik_egn,
                ao.proizvoditel,
                ao.star_jo,
                s.vid_mliako,
                ao.oblast,
                ao.obshtina,
                ao.naseleno_miasto,
                ao.telefon,
                s.faktura as faktura_nomer,
                s.plashtane,
                ao.email
            FROM samples s
            JOIN animal_objects ao ON s.star_jo = ao.star_jo
            LEFT JOIN takers t ON s.probovzemach_id = t.id
            $where_clause
            ORDER BY s.data DESC";

            $stmt = $pdo->prepare($base_query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo '<table id="reportTable" class="table table-striped table-bordered">';
                echo '<thead><tr>';
                echo '<th>№</th>';
                echo '<th>Протокол №</th>';
                echo '<th>Дата</th>';
                echo '<th>Пробовземач</th>';
                echo '<th>Баркод</th>';
                echo '<th>Булстат</th>';
                echo '<th>ЕИК/ЕГН</th>';
                echo '<th>Производител</th>';
                echo '<th>Стар ЖО</th>';
                echo '<th>Вид мляко</th>';
                echo '<th>Област</th>';
                echo '<th>Населени място</th>';
                echo '<th>Телефон</th>';
                echo '<th>Фактура</th>';
                echo '<th>Плащане</th>';
                echo '<th>Имейл</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                $row_number = 1;
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row_number++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['protokol_nomer'] ?? '') . '</td>';
                    echo '<td>' . date('d.m.Y', strtotime($row['data'] ?? '')) . '</td>';
                    echo '<td>' . htmlspecialchars($row['probovzemach'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['barkod'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['bulstat'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['eik_egn'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['telefon'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['faktura_nomer'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['plashtane'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
            }
        }
        return;
        break;
        
    case 'animal_objects':
        $params = [];
        $where_conditions = [];

        if (!empty($_GET['oblast'])) {
            $oblast = htmlspecialchars($_GET['oblast'], ENT_QUOTES, 'UTF-8');
            $where_conditions[] = "oblast = :oblast";
            $params[':oblast'] = $oblast;
        }

        if (!empty($_GET['naseleno_miasto'])) {
            $naseleno_miasto = htmlspecialchars($_GET['naseleno_miasto'], ENT_QUOTES, 'UTF-8');
            $where_conditions[] = "naseleno_miasto = :naseleno_miasto";
            $params[':naseleno_miasto'] = $naseleno_miasto;
        }

        if (!empty($_GET['star_jo'])) {
            $star_jo = htmlspecialchars($_GET['star_jo'], ENT_QUOTES, 'UTF-8');
            $where_conditions[] = "star_jo = :star_jo";
            $params[':star_jo'] = $star_jo;
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        $query = "SELECT 
                    COALESCE(bulstat, '') as bulstat,
                    COALESCE(eik_egn, '') as eik_egn,
                    COALESCE(proizvoditel, '') as proizvoditel,
                    COALESCE(star_jo, '') as star_jo,
                    COALESCE(nov_jo, '') as nov_jo,
                    COALESCE(oblast, '') as oblast,
                    COALESCE(obshtina, '') as obshtina,
                    COALESCE(naseleno_miasto, '') as naseleno_miasto,
                    COALESCE(telefon, '') as telefon,
                    COALESCE(email, '') as email
                FROM animal_objects
                $where_clause
                ORDER BY proizvoditel";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo '<div class="table-responsive">';
                echo '<table id="reportTable" class="table table-striped table-bordered">';
                echo '<thead><tr>';
                echo '<th>№</th>';
                echo '<th>Булстат</th>';
                echo '<th>ЕИК/ЕГН</th>';
                echo '<th>Име на ферма</th>';
                echo '<th>Стар ЖО</th>';
                echo '<th>Нов ЖО</th>';
                echo '<th>Област</th>';
                echo '<th>Населени място</th>';
                echo '<th>Телефон</th>';
                echo '<th>Имейл</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                $row_number = 1;
                foreach ($results as $row) {
                    echo '<tr>';
                    echo '<td>' . $row_number++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['bulstat']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['eik_egn']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['proizvoditel']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['nov_jo'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['telefon']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-info">Няма намерени ферми за зададените критерии.</div>';
            }
        } catch (PDOException $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Грешка при генериране на справката: ' . $e->getMessage()]);
        }
        return;
        break;
        
    case 'request_sample':
        try {
            // Get and format date parameters first
            $start_date = isset($_GET['start_date']) ? DateTime::createFromFormat('d.m.Y', $_GET['start_date'])->format('Y-m-d') : date('Y-m-d', strtotime('-1 month'));
            $end_date = isset($_GET['end_date']) ? DateTime::createFromFormat('d.m.Y', $_GET['end_date'])->format('Y-m-d') : date('Y-m-d');

            // Build the base query
            $sql = "SELECT DISTINCT
                rs.id,
                rs.date,
                rs.star_jo,
                ao.proizvoditel,
                COALESCE(s.vid_mliako, amt.milk_type) as vid_mliako,
                ao.oblast,
                ao.obshtina,
                ao.naseleno_miasto,
                ao.telefon,
                t.ime as probovzemach
            FROM request_sample rs
            LEFT JOIN animal_objects ao ON rs.star_jo = ao.star_jo
            LEFT JOIN takers t ON rs.taker_id = t.id
            LEFT JOIN samples s ON rs.star_jo = s.star_jo AND DATE(rs.date) = DATE(s.data)
            LEFT JOIN animal_milk_types amt ON rs.star_jo = amt.star_jo
            WHERE 1=1";

            // Initialize parameters array
            $params = array();

            // Add date conditions
            if ($start_date) {
                $sql .= " AND DATE(rs.date) >= ?";
                $params[] = $start_date;
            }
            if ($end_date) {
                $sql .= " AND DATE(rs.date) <= ?";
                $params[] = $end_date;
            }

            // Add taker condition if set
            if (!empty($_GET['taker_id'])) {
                $taker_id = filter_var($_GET['taker_id'], FILTER_SANITIZE_NUMBER_INT);
                if ($taker_id !== false && $taker_id !== '') {
                    $sql .= " AND rs.taker_id = ?";
                    $params[] = $taker_id;
                }
            }

            // Add ordering
            $sql .= " ORDER BY rs.date DESC, rs.id DESC";

            // Debug information
            error_log('SQL Query: ' . $sql);
            error_log('Parameters: ' . print_r($params, true));

            // Prepare and execute
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                echo '<div class="alert alert-info">Няма намерени заявки за избрания период.</div>';
                return;
            }
            
            // Display results in a table
            echo '<div class="table-responsive">';
            echo '<table id="reportTable" class="table table-striped table-bordered">';
            echo '<thead><tr>';
            echo '<th>№</th>';
            echo '<th>Дата</th>';
            echo '<th>Производител</th>';
            echo '<th>Стар ЖО</th>';
            echo '<th>Вид мляко</th>';
            echo '<th>Област</th>';
            echo '<th>Населени място</th>';
            echo '<th>Телефон</th>';
            echo '<th>Пробовземач</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            $counter = 1;
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . $counter++ . '</td>';
                echo '<td>' . date('d.m.Y', strtotime($row['date'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['proizvoditel']) . '</td>';
                echo '<td>' . htmlspecialchars($row['star_jo']) . '</td>';
                echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['oblast']) . '</td>';
                echo '<td>' . htmlspecialchars($row['naseleno_miasto']) . '</td>';
                echo '<td>' . htmlspecialchars($row['telefon'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['probovzemach']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            echo '<div class="alert alert-danger">Грешка при зареждане на данните: ' . $e->getMessage() . '</div>';
        }
        return;
        break;
        
    case 'mandra_report':
        // Mandra report - same as samples normal view but with email_mandra field
        $params = [];
        $where_conditions = [];

        if (!empty($_GET['start_date'])) {
            $where_conditions[] = "s.data >= :start_date";
            $params[':start_date'] = $start_date;
        }

        if (!empty($_GET['end_date'])) {
            $where_conditions[] = "s.data <= :end_date";
            $params[':end_date'] = $end_date;
        }

        if (!empty($_GET['taker_id'])) {
            $where_conditions[] = "s.probovzemach_id = :taker_id";
            $params[':taker_id'] = $_GET['taker_id'];
        }

        if (!empty($_GET['oblast'])) {
            $where_conditions[] = "ao.oblast = :oblast";
            $params[':oblast'] = $_GET['oblast'];
        }

        if (!empty($_GET['naseleno_miasto'])) {
            $where_conditions[] = "ao.naseleno_miasto = :naseleno_miasto";
            $params[':naseleno_miasto'] = $_GET['naseleno_miasto'];
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        // Normal view query with email_mandra field
        $base_query = "SELECT 
            s.protokol_nomer,
            s.data,
            t.ime as probovzemach,
            s.barkod,
            ao.bulstat,
            ao.eik_egn,
            ao.proizvoditel,
            ao.star_jo,
            s.vid_mliako,
            ao.oblast,
            ao.obshtina,
            ao.naseleno_miasto,
            ao.telefon,
            s.faktura as faktura_nomer,
            s.plashtane,
            ao.email,
            ao.email_mandra
        FROM samples s
        JOIN animal_objects ao ON s.star_jo = ao.star_jo
        LEFT JOIN takers t ON s.probovzemach_id = t.id
        $where_clause
        ORDER BY s.data DESC";

        $stmt = $pdo->prepare($base_query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            echo '<table id="reportTable" class="table table-striped table-bordered">';
            echo '<thead><tr>';
            echo '<th>№</th>';
            echo '<th>Протокол №</th>';
            echo '<th>Дата</th>';
            echo '<th>Пробовземач</th>';
            echo '<th>Баркод</th>';
            echo '<th>Булстат</th>';
            echo '<th>ЕИК/ЕГН</th>';
            echo '<th>Производител</th>';
            echo '<th>Стар ЖО</th>';
            echo '<th>Вид мляко</th>';
            echo '<th>Област</th>';
            echo '<th>Населени място</th>';
            echo '<th>Телефон</th>';
            echo '<th>Фактура</th>';
            echo '<th>Плащане</th>';
            echo '<th>Имейл</th>';
            echo '<th>Имейл на мандра</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            $row_number = 1;
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . $row_number++ . '</td>';
                echo '<td>' . htmlspecialchars($row['protokol_nomer'] ?? '') . '</td>';
                echo '<td>' . date('d.m.Y', strtotime($row['data'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars($row['probovzemach'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['barkod'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['bulstat'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['eik_egn'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['obshtina'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['telefon'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['faktura_nomer'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['plashtane'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['email_mandra'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
        }
        return;
        break;
        
    case 'plateni_fakturi':
        // Платени фактури report - samples with specific text in plashtane column
        $params = [];
        $where_conditions = [];
        $plashtane_text = isset($_GET['plashtane_text']) ? htmlspecialchars($_GET['plashtane_text'], ENT_QUOTES, 'UTF-8') : '';

        if (!empty($_GET['start_date'])) {
            $where_conditions[] = "s.data >= :start_date";
            $params[':start_date'] = $start_date;
        }

        if (!empty($_GET['end_date'])) {
            $where_conditions[] = "s.data <= :end_date";
            $params[':end_date'] = $end_date;
        }

        if (!empty($_GET['taker_id'])) {
            $where_conditions[] = "s.probovzemach_id = :taker_id";
            $params[':taker_id'] = $_GET['taker_id'];
        }

        if (!empty($_GET['oblast'])) {
            $where_conditions[] = "ao.oblast = :oblast";
            $params[':oblast'] = $_GET['oblast'];
        }

        if (!empty($_GET['naseleno_miasto'])) {
            $where_conditions[] = "ao.naseleno_miasto = :naseleno_miasto";
            $params[':naseleno_miasto'] = $_GET['naseleno_miasto'];
        }

        // Add condition for plashtane text
        if (!empty($plashtane_text)) {
            $where_conditions[] = "s.plashtane LIKE :plashtane_text";
            $params[':plashtane_text'] = '%' . $plashtane_text . '%';
        } else {
            // If no specific text provided, show all records that have any text in plashtane
            $where_conditions[] = "(s.plashtane IS NOT NULL AND s.plashtane != '')";
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        $base_query = "SELECT 
            s.protokol_nomer,
            s.data,
            t.ime as probovzemach,
            s.barkod,
            ao.bulstat,
            ao.eik_egn,
            ao.proizvoditel,
            ao.star_jo,
            s.vid_mliako,
            ao.oblast,
            ao.obshtina,
            ao.naseleno_miasto,
            ao.telefon,
            s.faktura as faktura_nomer,
            s.plashtane,
            ao.email
        FROM samples s
        JOIN animal_objects ao ON s.star_jo = ao.star_jo
        LEFT JOIN takers t ON s.probovzemach_id = t.id
        $where_clause
        ORDER BY s.data DESC";

        $stmt = $pdo->prepare($base_query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            echo '<table id="reportTable" class="table table-striped table-bordered">';
            echo '<thead><tr>';
            echo '<th>№</th>';
            echo '<th>Протокол №</th>';
            echo '<th>Дата</th>';
            echo '<th>Пробовземач</th>';
            echo '<th>Баркод</th>';
            echo '<th>Булстат</th>';
            echo '<th>ЕИК/ЕГН</th>';
            echo '<th>Производител</th>';
            echo '<th>Стар ЖО</th>';
            echo '<th>Вид мляко</th>';
            echo '<th>Област</th>';
            echo '<th>Населени място</th>';
            echo '<th>Телефон</th>';
            echo '<th>Фактура</th>';
            echo '<th>Плащане</th>';
            echo '<th>Имейл</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            $row_number = 1;
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . $row_number++ . '</td>';
                echo '<td>' . htmlspecialchars($row['protokol_nomer'] ?? '') . '</td>';
                echo '<td>' . date('d.m.Y', strtotime($row['data'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars($row['probovzemach'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['barkod'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['bulstat'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['eik_egn'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['proizvoditel'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['star_jo'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['vid_mliako'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['oblast'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['naseleno_miasto'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['telefon'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['faktura_nomer'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['plashtane'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">Няма намерени резултати за зададените критерии.</div>';
        }
        return;
        break;
        
    default:
        die("Invalid report type");
}

try {
    $stmt = $pdo->prepare($base_sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) === 0) {
        if ($report_type === 'belejka') {
            echo "<div class='alert alert-info'>
                    <p>Няма намерени резултати със следните параметри:</p>
                    <ul>
                        <li>Бележка: " . htmlspecialchars($belejka) . "</li>
                        <li>От дата: " . htmlspecialchars($start_date) . "</li>
                        <li>До дата: " . htmlspecialchars($end_date) . "</li>
                    </ul>
                  </div>";
        } else {
            echo "<div class='alert alert-info'>Няма намерени резултати за избрания период.</div>";
        }
        exit;
    }

    // Different table structure for belejka reports
    if ($report_type === 'belejka') {
        ?>
        <div class="table-responsive">
            <table id="reportTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Бележка</th>
                        <th>Дата</th>
                        <th>Протокол №</th>
                        <th>Пробовземач</th>
                        <th>Баркод</th>
                        <th>Производител</th>
                        <th>Стар ЖО</th>
                        <th>Вид мляко</th>
                        <th>Област</th>
                        <th>Населени място</th>
                        <th>Телефон</th>
                        <th>Фактура</th>
                        <th>Плащане</th>
                        <th>Имейл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['belezhka']) ?></td>
                        <td><?= htmlspecialchars($row['data'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['protokol_nomer'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['probovzemach'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['barkod'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['proizvoditel'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['star_jo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['vid_mliako'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['oblast'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['naseleno_miasto'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['telefon'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['faktura'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['plashtane'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else if ($report_type === 'samples') {
        ?>
        <div class="table-responsive">
            <table id="reportTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Протокол №</th>
                        <th>Дата</th>
                        <th>Пробовземач</th>
                        <th>Баркод</th>
                        <th>Производител</th>
                        <th>Стар ЖО</th>
                        <th>Вид мляко</th>
                        <th>Област</th>
                        <th>Населени място</th>
                        <th>Телефон</th>
                        <th>Фактура</th>
                        <th>Плащане</th>
                        <th>Имейл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_number = 1;
                    foreach ($results as $row): 
                    ?>
                    <tr>
                        <td><?= $row_number++ ?></td>
                        <td><?= htmlspecialchars($row['protokol_nomer']) ?></td>
                        <td><?= htmlspecialchars($row['data']) ?></td>
                        <td><?= htmlspecialchars($row['probovzemach']) ?></td>
                        <td><?= htmlspecialchars($row['barkod']) ?></td>
                        <td><?= htmlspecialchars($row['proizvoditel']) ?></td>
                        <td><?= htmlspecialchars($row['star_jo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['vid_mliako'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['oblast'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['naseleno_miasto'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['telefon']) ?></td>
                        <td><?= htmlspecialchars($row['faktura_nomer']) ?></td>
                        <td><?= htmlspecialchars($row['plashtane']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        ?>
        <div class="table-responsive">
            <table id="reportTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Булстат</th>
                        <th>ЕИК/ЕГН</th>
                        <th>Производител</th>
                        <th>Нов ЖО</th>
                        <th>Стар ЖО</th>
                        <th>Област</th>
                        <th>Населени място</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Фактура</th>
                        <th>Пробовземач</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['sample_date']) ?></td>
                        <td><?= htmlspecialchars($row['bulstat']) ?></td>
                        <td><?= htmlspecialchars($row['eik_egn']) ?></td>
                        <td><?= htmlspecialchars($row['proizvoditel'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['nov_jo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['star_jo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['oblast'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['naseleno_miasto'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['telefon'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['faktura'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['probovzemach']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Грешка при генериране на справката: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?> 