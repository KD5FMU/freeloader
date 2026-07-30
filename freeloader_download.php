<?php

// freeloader_download.php (hardened)

// N5AD - July 2026


session_start();

require_once __DIR__ . '/freeloader_common.php';

freeloader_require_auth();


if (!isset($_GET['file']) || !isset($_GET['dir'])) {

    die('Missing file or directory parameter.');

}


$filename = freeloader_validate_filename($_GET['file']);

$targetDir = freeloader_validate_dir($_GET['dir']);


if ($filename === false || $targetDir === false) {

    die('Invalid directory or filename.');

}


$filepath = $targetDir . '/' . $filename;


if (!is_file($filepath)) {

    die('File not found: ' . htmlspecialchars($filename));

}


// Try normal read

if (is_readable($filepath)) {

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

}


// Fall back to helper for protected files

[$ok, $content] = freeloader_helper('cat', [$filepath]);

if (!$ok) {

    die('Cannot read file (permission denied).');

}


header('Content-Description: File Transfer');

header('Content-Type: application/octet-stream');

header('Content-Disposition: attachment; filename="' . basename($filename) . '"');

header('Expires: 0');

header('Cache-Control: must-revalidate');

header('Pragma: public');

header('Content-Length: ' . strlen($content));

flush();

echo $content;

exit;

