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

// Get samples grouped by mandra email
try {
    $sql = "SELECT DISTINCT 
                ao.email_mandra,
                GROUP_CONCAT(DISTINCT ao.proizvoditel SEPARATOR ', ') as proizvoditeli,
                GROUP_CONCAT(DISTINCT ao.star_jo SEPARATOR ', ') as star_jos,
                GROUP_CONCAT(DISTINCT s.protokol_snimka) as protokol_snimki,
                MIN(s.data) as first_date,
                MAX(s.data) as last_date,
                COUNT(DISTINCT ao.star_jo) as producer_count,
                COUNT(DISTINCT s.id) as sample_count
            FROM samples s
            LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo
            WHERE s.data BETWEEN :start_date AND :end_date
            AND ao.email_mandra IS NOT NULL AND ao.email_mandra != ''
            GROUP BY ao.email_mandra
            ORDER BY ao.email_mandra";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $grouped_samples = $stmt->fetchAll();
    
    // Debug information - let's check what we have in the database
    $debug_sql = "SELECT COUNT(*) as total_samples FROM samples WHERE data BETWEEN :start_date AND :end_date";
    $debug_stmt = $pdo->prepare($debug_sql);
    $debug_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $total_samples = $debug_stmt->fetchColumn();
    
    $debug_sql2 = "SELECT COUNT(*) as total_with_mandra FROM samples s LEFT JOIN animal_objects ao ON s.star_jo = ao.star_jo WHERE s.data BETWEEN :start_date AND :end_date AND ao.email_mandra IS NOT NULL AND ao.email_mandra != ''";
    $debug_stmt2 = $pdo->prepare($debug_sql2);
    $debug_stmt2->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $total_with_mandra = $debug_stmt2->fetchColumn();
    
    // Debug information
    if (empty($grouped_samples)) {
        $error_message = "Няма намерени записи за периода от " . $start_date . " до " . $end_date . ".<br>";
        $error_message .= "Общо проби за периода: " . $total_samples . "<br>";
        $error_message .= "Проби с имейл на мандра: " . $total_with_mandra . "<br>";
        $error_message .= "Проверете дали колоната email_mandra съществува и има данни.";
    }
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
                $to = $sample['email_mandra'];
                $subject = "=?UTF-8?B?" . base64_encode("Протоколи за проби мляко") . "?=";
                
                // Headers
                $headers = [];
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'From: Централна ветеринарна лаборатория <cvl@supm.online>';
                $headers[] = 'Reply-To: cvl@supm.online';
                $headers[] = 'BCC: cvl@supm.online';
                $headers[] = 'X-Mailer: PHP/' . phpversion();
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                
                // Email body
                $message = "--" . $boundary . "\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $message .= "Здравейте,\n\n";
                $message .= "Приложено към този имейл, Ви изпращаме издадените протоколи за пробите мляко за следните производители:\n";
                $message .= $sample['proizvoditeli'] . "\n\n";
                $message .= "Поздрави,\nДаниела Михайлова\n+359898655955\r\n";

                // Create ZIP file with protocols
                if (!empty($sample['protokol_snimki'])) {
                    $protokol_files = array_filter(explode(',', $sample['protokol_snimki']));
                    
                    if (!empty($protokol_files)) {
                        // Create temporary ZIP file
                        $zip_filename = 'protokoli_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.zip';
                        $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
                        
                        $zip = new ZipArchive();
                        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                            $added_files = 0;
                            
                            foreach ($protokol_files as $i => $protokol_file) {
                                $protokol_file = trim($protokol_file);
                                if (empty($protokol_file)) continue;
                                
                                $protokolPath = str_replace('/home/supmonli/public_html/Probi/', '', $protokol_file);
                                $protokolPath = '/home/supmonli/public_html/Probi/' . ltrim($protokolPath, '/');
                                
                                if (file_exists($protokolPath)) {
                                    $filename_in_zip = 'Protokol_' . ($i + 1) . '.pdf';
                                    if ($zip->addFile($protokolPath, $filename_in_zip)) {
                                        $added_files++;
                                    }
                                }
                            }
                            
                            $zip->close();
                            
                            // Attach ZIP file to email if it contains files
                            if ($added_files > 0 && file_exists($zip_path)) {
                                $zip_content = file_get_contents($zip_path);
                                if ($zip_content !== false) {
                                    $message .= "\r\n--" . $boundary . "\r\n";
                                    $message .= "Content-Type: application/zip; name=\"Protokoli.zip\"\r\n";
                                    $message .= "Content-Transfer-Encoding: base64\r\n";
                                    $message .= "Content-Disposition: attachment; filename=\"Protokoli.zip\"\r\n\r\n";
                                    
                                    $encodedContent = base64_encode($zip_content);
                                    if (!empty($encodedContent)) {
                                        $message .= chunk_split($encodedContent) . "\r\n";
                                    }
                                }
                                
                                // Clean up temporary ZIP file
                                unlink($zip_path);
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
    <title>Изпращане на имейли към мандри</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" href="../icon.png">
</head>
<body>
    <div class="container-fluid mt-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Изпращане на имейли към мандри</h2>
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

                <div class="alert alert-info">
                    <strong>Филтриране:</strong> От <?php echo date('d.m.Y', strtotime($start_date)); ?> до <?php echo date('d.m.Y', strtotime($end_date)); ?>
                    <br><strong>Намерени мандри:</strong> <?php echo count($grouped_samples); ?>
                </div>

                <form method="POST">
                    <div class="table-responsive">
                        <table id="mandraTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Имейл на Мандра</th>
                                    <th>Производители</th>
                                    <th>Брой производители</th>
                                    <th>Брой проби</th>
                                    <th>Период</th>
                                    <th>Приложения</th>
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
                                        <td><?php echo htmlspecialchars($sample['email_mandra']); ?></td>
                                        <td>
                                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" 
                                                 title="<?php echo htmlspecialchars($sample['proizvoditeli']); ?>">
                                                <?php echo htmlspecialchars($sample['proizvoditeli']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo $sample['producer_count']; ?></td>
                                        <td><?php echo $sample['sample_count']; ?></td>
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
                                            $protokol_files = !empty($sample['protokol_snimki']) ? array_filter(explode(',', $sample['protokol_snimki'])) : [];
                                            $protokol_count = count($protokol_files);
                                            if ($protokol_count > 0) {
                                                echo "ZIP файл (" . $protokol_count . " протокола)";
                                            } else {
                                                echo "Няма файлове";
                                            }
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
            $('#mandraTable').DataTable({
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