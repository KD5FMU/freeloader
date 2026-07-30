<?php
// freeloader_upload.php
// Freeloader Upload + File Listing + Get/Save (Edit) Utility
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

// Update activity
$_SESSION['last_activity'] = time();

// ==========================================================
// Restart Asterisk
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restart_asterisk') {
    $cmd = "sudo systemctl restart asterisk 2>&1";
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0) {
        echo "<strong>SUCCESS:</strong> Asterisk service restarted successfully.";
    } else {
        $msg = htmlspecialchars(implode("\n", $output));
        echo "Failed to restart Asterisk (exit code $returnCode).<br>" . ($msg ? $msg : "Check sudoers and that Asterisk is installed.");
    }
    exit;
}

// ==========================================================
// File Listing
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    $uploadDir = isset($_GET['dir']) ? realpath($_GET['dir']) : '/my_uploads';
   
    if (!$uploadDir || !is_dir($uploadDir)) {
        echo "<p style='color:red;'>Directory not found or not accessible: " . htmlspecialchars($_GET['dir'] ?? '') . "</p>";
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

    $editableExts = ['ini','conf','php','sh','txt','cfg','json','xml','log','md','yml','yaml','bash','js','css','html','htm','c','h','py','pl','rb','sql','env','htaccess','service','timer'];

    foreach ($files as $f) {
        if ($f === '.' || $f === '..' || is_dir("$uploadDir/$f")) {
            continue;
        }
        $size = round(filesize("$uploadDir/$f") / 1024, 2) . ' KB';
        $mtime = date('Y-m-d H:i', filemtime("$uploadDir/$f"));
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $isEditable = in_array($ext, $editableExts);

        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:8px; text-align:left;'>" . htmlspecialchars($f) . "</td>";
        echo "<td style='padding:8px;text-align:right;'>$size</td>";
        echo "<td style='padding:8px;'>$mtime</td>";
        echo "<td style='padding:8px; white-space:nowrap;'>";
        echo "<button onclick=\"downloadFreeloaderFile('" . addslashes($f) . "')\" 
                        class='download-btn'
                        style='background:#28a745;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;margin-right:5px;'>
                    Download
                </button>";
        if ($isEditable) {
            echo "<button onclick=\"editFreeloaderFile('" . addslashes($f) . "')\" 
                            class='edit-btn'
                            style='background:#007bff;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;margin-right:5px;'>
                        Edit
                    </button>";
        }
        echo "<button onclick=\"deleteFreeloaderFile('" . addslashes($f) . "')\" 
                        class='delete-btn'
                        style='background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;'>
                    Delete
                </button>";
        echo "</td>";
        echo "</tr>";
    }
    echo '</table>';
    exit;
}

// ==========================================================
// Get file content for editing
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    $filename = isset($_GET['file']) ? basename($_GET['file']) : '';
    $targetDirInput = isset($_GET['dir']) ? trim($_GET['dir']) : '/my_uploads';
    $targetDir = realpath($targetDirInput);

    if (!$filename || !$targetDir || !is_dir($targetDir)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: Invalid directory or filename.";
        exit;
    }

    $path = $targetDir . '/' . $filename;

    if (!file_exists($path) || is_dir($path)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: File not found.";
        exit;
    }

    // Try normal read first
    $content = @file_get_contents($path);
    if ($content === false) {
        // Fallback to sudo cat for protected system files
        $cmd = "sudo cat " . escapeshellarg($path) . " 2>/dev/null";
        $content = shell_exec($cmd);
        if ($content === null || $content === false) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "ERROR: Cannot read file (permission denied).";
            exit;
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
    exit;
}

// ==========================================================
// Save edited file
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $filename = isset($_POST['file']) ? basename($_POST['file']) : '';
    $targetDirInput = isset($_POST['dir']) ? trim($_POST['dir']) : '/my_uploads';
    $targetDir = realpath($targetDirInput) ?: $targetDirInput;
    $content = isset($_POST['content']) ? $_POST['content'] : '';

    if (!$filename || empty($targetDir) || !is_dir($targetDir)) {
        echo "Invalid parameters.";
        exit;
    }

    // Basic safety: reject path traversal in filename (already basename)
    if (preg_match('/(\.\.|\/|\\\\|%00)/', $filename)) {
        echo "Invalid filename.";
        exit;
    }

    $path = rtrim($targetDir, '/') . '/' . $filename;

    // Determine if system / protected path (needs sudo)
    $isSystem = (strpos($targetDir, '/etc/') === 0 ||
                 strpos($targetDir, '/usr/') === 0 ||
                 strpos($targetDir, '/var/www/html/supermon') === 0 ||
                 strpos($targetDir, '/asterisk') !== false);

    if ($isSystem) {
        // Write via temporary file + sudo cp
        $tmp = tempnam(sys_get_temp_dir(), 'freeloader_edit_');
        if ($tmp === false || file_put_contents($tmp, $content) === false) {
            echo "Failed to create temporary file.";
            exit;
        }
        chmod($tmp, 0644);

        $cmd = "sudo cp " . escapeshellarg($tmp) . " " . escapeshellarg($path);
        exec($cmd, $output, $returnCode);
        @unlink($tmp);

        if ($returnCode === 0) {
            // Ensure reasonable perms
            exec("sudo chmod 644 " . escapeshellarg($path) . " 2>/dev/null");
            echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " saved to " . htmlspecialchars($targetDir);
        } else {
            echo "Failed to save file (sudo cp failed). Check permissions/sudoers.";
        }
    } else {
        if (file_put_contents($path, $content) !== false) {
            @chmod($path, 0664);
            @chown($path, 'www-data');
            echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " saved to " . htmlspecialchars($targetDir);
        } else {
            echo "Failed to save file. Check directory permissions.";
        }
    }
    exit;
}

// ==========================================================
// Upload handling (original)
// ==========================================================
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
    @chown($targetDir, 'www-data');
    @chmod($targetDir, 0775);
}

$targetFile = rtrim($targetDir, '/') . '/' . $filename;
$tmpFile = $file['tmp_name'];

if (strpos($targetDir, '/etc/') === 0 || strpos($targetDir, '/usr/') === 0) {
    echo "<strong>Warning:</strong> Writing to system directory.<br>";
    $cmd = "sudo cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($targetFile);
    exec($cmd, $output, $returnCode);
   
    if ($returnCode === 0) {
        @chmod($targetFile, 0644);
        echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " copied to " . htmlspecialchars($targetDir);
    } else {
        echo "Failed to copy file.";
    }
} else {
    if (move_uploaded_file($tmpFile, $targetFile)) {
        @chmod($targetFile, 0664);
        @chown($targetFile, 'www-data');
        echo "<strong>SUCCESS:</strong> " . htmlspecialchars($filename) . " uploaded to " . htmlspecialchars($targetDir);
    } else {
        echo "Failed to upload file.";
    }
}
?>
