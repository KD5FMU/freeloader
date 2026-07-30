<?php

// freeloader_delete.php (hardened)

// N5AD - July 2026


session_start();

require_once __DIR__ . '/freeloader_common.php';

freeloader_require_auth();


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['file'])) {

    echo 'Invalid request.';

    exit;

}


if (!freeloader_verify_csrf($_POST['csrf'] ?? null)) {

    http_response_code(403);

    die('Invalid CSRF token.');

}


$filename = freeloader_validate_filename($_POST['file']);

$targetDir = freeloader_validate_dir($_POST['dir'] ?? '');


if ($filename === false || $targetDir === false) {

    echo 'Invalid target directory or filename.';

    exit;

}


$path = $targetDir . '/' . $filename;


if (!file_exists($path)) {

    echo 'File not found: ' . htmlspecialchars($filename);

    exit;

}

if (is_dir($path)) {

    echo 'Cannot delete a directory.';

    exit;

}




[$ok, $out] = freeloader_helper('rm', [$path]);

if ($ok) {

    echo htmlspecialchars($filename) . ' deleted successfully from ' . htmlspecialchars($targetDir);

    exit;

}




if (@unlink($path)) {

    echo htmlspecialchars($filename) . ' deleted successfully from ' . htmlspecialchars($targetDir);

} else {

    echo 'Failed to delete ' . htmlspecialchars($filename) . '. ' . htmlspecialchars($out);

}

