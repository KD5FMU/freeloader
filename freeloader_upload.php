<?php
// freeloader_upload.php
// Freeloader Upload + File Listing + Download Utility
// N5AD - July 2026
?>
<?php
// ==========================================================
// File Listing
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    $uploadDir = isset($_GET['dir']) ? realpath($_GET['dir']) : '/my_uploads';
   
    if (!is_dir($uploadDir)) {
        echo "<p style='color:red;'>Directory not found or not accessible: " . htmlspecialchars($uploadDir) . "</p>";
        exit;
    }
    $files = scandir($uploadDir);
    echo '<table style="width:100%; border-collapse:collapse; font-size:14px;">';
    echo '<tr style="background:#34495e;color:white;">';
    echo '<th style="padding:8px;text-align:left;">File</th>';
    echo '<th style="padding:8px;text-align:right;">Size</th>';
    echo '<th style="padding:8px;">Modified</th>';
    echo '<th style="padding:8px;">Action</th>';
    echo '</tr>';
    foreach ($files as $f) {
        if ($f === '.' || $f === '..' || is_dir("$uploadDir/$f")) {
            continue;
        }
        $size = round(filesize("$uploadDir/$f") / 1024, 2) . ' KB';
        $mtime = date('Y-m-d H:i', filemtime("$uploadDir/$f"));
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:8px; text-align:left;'>" . htmlspecialchars($f) . "</td>";
        echo "<td style='padding:8px;text-align:right;'>$size</td>";
        echo "<td style='padding:8px;'>$mtime</td>";
        echo "<td style='padding:8px; white-space:nowrap;'>
                <button onclick=\"downloadFreeloaderFile('" . addslashes($f) . "')\" 
                        class='download-btn'
                        style='background:#28a745;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;margin-right:5px;'>
                    Download
                </button>
                <button onclick=\"deleteFreeloaderFile('" . addslashes($f) . "')\" 
                        style='background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;'>
                    Delete
                </button>
              </td>";
        echo "</tr>";
    }
    echo '</table>';
    exit;
}

if (!isset($_FILES['file'])) {
    echo "No file uploaded.";
    exit;
}
$file = $_FILES['file'];
$filename = basename($file['name']);

// Prevent path traversal
if (preg_match('/(\.\.|\/|\\\\|%00)/', $filename)) {
    echo "Invalid filename.";
    exit;
}

// Max size 200 MB
if ($file['size'] > 200 * 1024 * 1024) {
    echo "File too large (maximum 200 MB).";
    exit;
}

$targetDirInput = isset($_POST['target_dir']) ? trim($_POST['target_dir']) : '/my_uploads';
$targetDir = realpath($targetDirInput) ?: $targetDirInput;

// Create dir if missing (for user dirs)
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0775, true)) {
        echo "Failed to create directory.";
        exit;
    }
    chown($targetDir, 'www-data');
    chmod($targetDir, 0775);
}

$targetFile = $targetDir . '/' . $filename;
$tmpFile = $file['tmp_name'];

if (strpos($targetDir, '/etc/') === 0 || strpos($targetDir, '/usr/') === 0) {
    echo "<strong>Warning:</strong> Writing to system directory.<br>";
    $cmd = "sudo cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($targetFile);
    exec($cmd, $output, $returnCode);
   
    if ($returnCode === 0) {
        chmod($targetFile, 0644);
        echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " copied to " . htmlspecialchars($targetDir);
    } else {
        echo "Failed to copy file.";
    }
} else {
   
    if (move_uploaded_file($tmpFile, $targetFile)) {
        chmod($targetFile, 0664);
        @chown($targetFile, 'www-data');
        echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " uploaded to " . htmlspecialchars($targetDir);
    } else {
        echo "Failed to upload file.";
    }
}
?>
