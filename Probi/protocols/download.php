<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

$filePath = $_GET['file'] ?? null;

if ($filePath && file_exists($filePath)) {
    // Set headers to force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} else {
    echo "Файлът не е намерен или не съществува.";
}
?> 