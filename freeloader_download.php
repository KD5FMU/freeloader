<?php
// freeloader_download.php
// Download handler for Freeloader
// N5AD - July 2026
session_start();

$configFile = '/etc/freeloader/.config.php';
if (file_exists($configFile)) {
    include $configFile;
} else {
    die("Configuration file not found.");
}

if (!isset($_SESSION['freeloader_loggedin'])) {
    http_response_code(403);
    die("Access denied. Please log in.");
}

if (!isset($_GET['file']) || !isset($_GET['dir'])) {
    die("Missing file or directory parameter.");
}

$filename = basename($_GET['file']);
$targetDirInput = $_GET['dir'];
$targetDir = realpath($targetDirInput) ?: $targetDirInput;

if (!$targetDir || !is_dir($targetDir)) {
    die("Invalid directory.");
}

$filepath = rtrim($targetDir, '/') . '/' . $filename;

if (!file_exists($filepath) || is_dir($filepath)) {
    die("File not found: " . htmlspecialchars($filename));
}

// Optional: Add extra security restrictions here if needed

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
flush();
readfile($filepath);
exit;
?>
