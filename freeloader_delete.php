<?php
// freeloader_delete.php
// N5AD - July 2026

session_start();

$configFile = '/etc/freeloader/.config.php';
if (file_exists($configFile)) {
    include $configFile;
} else {
    http_response_code(500);
    die("Configuration file not found.");
}

if (!isset($_SESSION['freeloader_loggedin'])) {
    http_response_code(403);
    die("Access denied. Please log in.");
}

$_SESSION['last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) {
    $filename = basename($_POST['file']);
    $targetDirInput = isset($_POST['dir']) ? trim($_POST['dir']) : '/my_uploads';
    $targetDir = realpath($targetDirInput) ?: $targetDirInput;

    if (!$targetDir || !is_dir($targetDir)) {
        echo "Invalid target directory.";
        exit;
    }

    // Safety: reject traversal attempts
    if (preg_match('/(\.\.|\/|\\\\|%00)/', $filename)) {
        echo "Invalid filename.";
        exit;
    }

    $path = rtrim($targetDir, '/') . '/' . $filename;

    if (!file_exists($path)) {
        echo "File not found: " . htmlspecialchars($filename);
        exit;
    }

    if (is_dir($path)) {
        echo "Cannot delete a directory.";
        exit;
    }

    // Use sudo for protected/system locations
    $isSystem = (strpos($targetDir, '/etc/') === 0 ||
                 strpos($targetDir, '/usr/') === 0 ||
                 strpos($targetDir, '/var/www/html/supermon') === 0 ||
                 strpos($targetDir, '/asterisk') !== false);

    if ($isSystem) {
        $cmd = "sudo rm -f " . escapeshellarg($path);
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0) {
            echo htmlspecialchars($filename) . " deleted successfully from " . htmlspecialchars($targetDir);
        } else {
            echo "Failed to delete " . htmlspecialchars($filename) . " (sudo rm failed)";
        }
    } else {
        if (unlink($path)) {
            echo htmlspecialchars($filename) . " deleted successfully from " . htmlspecialchars($targetDir);
        } else {
            echo "Failed to delete " . htmlspecialchars($filename);
        }
    }
} else {
    echo "Invalid request.";
}
?>
