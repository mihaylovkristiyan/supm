<?php
require_once __DIR__ . '/../config/init.php';

// Require login for this page
requireLogin();

$success_message = '';
$error_message = '';
$grouped_samples = []; // Initialize grouped samples array

// Get date range from GET parameters or set defaults
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get samples with producers that have email addresses and group them by star_jo
try {
    $sql = "SELECT DISTINCT 
                ao.proizvoditel,
                ao.star_jo,
                ao.email,
                GROUP_CONCAT(DISTINCT s.protokol_snimka) as protokol_snimki,
                GROUP_CONCAT(DISTINCT s.faktura_snimka) as faktura_snimki,
                MIN(s.data) as first_date,
                MAX(s.data) as last_date
            FROM samples s
            LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
            WHERE s.data BETWEEN :start_date AND :end_date
            AND ao.email IS NOT NULL AND ao.email != ''
            GROUP BY ao.star_jo, ao.proizvoditel, ao.email
            ORDER BY ao.proizvoditel";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $grouped_samples = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Грешка при зареждане на данните: " . $e->getMessage();
}

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_to']) && is_array($_POST['send_to'])) {
        $success_count = 0;
        $error_count = 0;

        foreach ($_POST['send_to'] as $index) {
            if (isset($grouped_samples[$index])) {
                $sample = $grouped_samples[$index];
                
                // Generate a unique boundary
                $boundary = md5(uniqid(time()));
                
                // Email headers
                $to = $sample['email'];
                $subject = "=?UTF-8?B?" . base64_encode("Фактури и протоколи за проби мляко на " . $sample['proizvoditel']) . "?=";
                
                // Headers
                $headers = [];
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'From: Централна ветеринарна лаборатория <cvl@supm.online>';
                $headers[] = 'Reply-To: cvl@supm.online';
                $headers[] = 'BCC: cvl@supm.online';
                $headers[] = 'Return-Path: cvl@supm.online';
                $headers[] = 'X-Mailer: PHP/' . phpversion();
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                
                // Email body
                $message = "--" . $boundary . "\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $message .= "Здравейте,\n\n";
                $message .= "Приложено към този имейл, Ви изпращаме издадените към Вас фактури и протоколи за пробите мляко. ";
                $message .= "Можете да платите по банков път или на каса на изипей(EasyPay). При плащането моля да бъде вписан номера на фактурата.\n\n";
                $message .= "Поздрави,\nДаниела Михайлова\n+359898655955\r\n";

                // Handle multiple protokol files
                if (!empty($sample['protokol_snimki'])) {
                    $protokol_files = explode(',', $sample['protokol_snimki']);
                    foreach ($protokol_files as $i => $protokol_file) {
                        $protokolPath = str_replace('/home/supmonli/public_html/Probi/', '', $protokol_file);
                        $protokolPath = '/home/supmonli/public_html/Probi/' . ltrim($protokolPath, '/');
                        
                        if (file_exists($protokolPath)) {
                            try {
                                $fileContent = file_get_contents($protokolPath);
                                if ($fileContent !== false) {
                                    $message .= "\r\n--" . $boundary . "\r\n";
                                    $message .= "Content-Type: application/pdf; name=\"Protokol" . ($i + 1) . ".pdf\"\r\n";
                                    $message .= "Content-Transfer-Encoding: base64\r\n";
                                    $message .= "Content-Disposition: attachment; filename=\"Protokol" . ($i + 1) . ".pdf\"\r\n\r\n";
                                    
                                    $encodedContent = base64_encode($fileContent);
                                    if (!empty($encodedContent)) {
                                        $message .= chunk_split($encodedContent) . "\r\n";
                                    }
                                }
                            } catch (Exception $e) {
                                // Silently continue if there's an error
                            }
                        }
                    }
                }
                
                // Handle multiple faktura files
                if (!empty($sample['faktura_snimki'])) {
                    $faktura_files = explode(',', $sample['faktura_snimki']);
                    foreach ($faktura_files as $i => $faktura_file) {
                        $fakturaPath = str_replace('/home/supmonli/public_html/Probi/', '', $faktura_file);
                        $fakturaPath = '/home/supmonli/public_html/Probi/' . ltrim($fakturaPath, '/');
                        
                        if (file_exists($fakturaPath)) {
                            try {
                                $fileContent = file_get_contents($fakturaPath);
                                if ($fileContent !== false) {
                                    $message .= "\r\n--" . $boundary . "\r\n";
                                    $message .= "Content-Type: application/pdf; name=\"Faktura" . ($i + 1) . ".pdf\"\r\n";
                                    $message .= "Content-Transfer-Encoding: base64\r\n";
                                    $message .= "Content-Disposition: attachment; filename=\"Faktura" . ($i + 1) . ".pdf\"\r\n\r\n";
                                    
                                    $encodedContent = base64_encode($fileContent);
                                    if (!empty($encodedContent)) {
                                        $message .= chunk_split($encodedContent) . "\r\n";
                                    }
                                }
                            } catch (Exception $e) {
                                // Silently continue if there's an error
                            }
                        }
                    }
                }

                // Close message boundary
                $message .= "--" . $boundary . "--";

                // Send email
                $mail_result = mail($to, $subject, $message, implode("\r\n", $headers));

                if ($mail_result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $success_message = "Успешно изпратени имейли: " . $success_count;
            if ($error_count > 0) {
                $success_message .= ", Неуспешни: " . $error_count;
            }
        } else {
            $error_message = "Грешка при изпращане на имейлите.";
        }
    } else {
        $error_message = "Моля, изберете поне един получател.";
    }
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Изпращане на имейли</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container-fluid mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Изпращане на имейли</h2>
                <div>
                    <a href="../index.php" class="btn btn-secondary">Назад</a>
                </div>
            </div>
            <div class="card-body">
                <!-- Date Filter Form -->
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">От дата:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">До дата:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Филтрирай</button>
                        </div>
                    </div>
                </form>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="table-responsive">
                        <table id="samplesTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Производител</th>
                                    <th>Стар ЖО</th>
                                    <th>Имейл</th>
                                    <th>Период</th>
                                    <th>Брой файлове</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grouped_samples as $index => $sample): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="send_to[]" 
                                                       value="<?php echo $index; ?>">
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($sample['proizvoditel']); ?></td>
                                        <td><?php echo htmlspecialchars($sample['star_jo']); ?></td>
                                        <td><?php echo htmlspecialchars($sample['email']); ?></td>
                                        <td>
                                            <?php 
                                            echo date('d.m.Y', strtotime($sample['first_date']));
                                            if ($sample['first_date'] != $sample['last_date']) {
                                                echo ' - ' . date('d.m.Y', strtotime($sample['last_date']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $protokol_count = !empty($sample['protokol_snimki']) ? count(explode(',', $sample['protokol_snimki'])) : 0;
                                            $faktura_count = !empty($sample['faktura_snimki']) ? count(explode(',', $sample['faktura_snimki'])) : 0;
                                            echo "Протоколи: " . $protokol_count . "<br>";
                                            echo "Фактури: " . $faktura_count;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Изпрати избраните</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#samplesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json'
                },
                pageLength: 25,
                order: [[1, "asc"]]
            });

            // Handle select all checkbox
            $('#selectAll').change(function() {
                $('input[name="send_to[]"]').prop('checked', this.checked);
            });
        });
    </script>
</body>
</html> 