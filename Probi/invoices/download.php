<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

$file = $_GET['file'] ?? null;

if ($file) {
    $filePath = __DIR__ . '/files/' . basename($file);
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        echo "Файлът не съществува.";
    }
} else {
    echo "Невалиден файл.";
}
?> 