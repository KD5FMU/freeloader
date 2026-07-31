<?php
// freeloader_upload.php
// Hardened: list, get, save, upload, restart
// N5AD - July 2026

session_start();
require_once __DIR__ . '/freeloader_common.php';
freeloader_require_auth();

// ==========================================================
// Restart Asterisk
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restart_asterisk') {
    if (!freeloader_verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    [$ok, $out] = freeloader_helper('restart_asterisk');
    if ($ok) {
        echo '<strong>SUCCESS:</strong> Asterisk service restarted successfully.';
    } else {
        echo 'Failed to restart Asterisk.<br>' . htmlspecialchars($out);
    }
    exit;
}

// ==========================================================
// File Listing
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    $uploadDir = freeloader_validate_dir($_GET['dir'] ?? '/my_uploads');
    if ($uploadDir === false) {
        echo "<p style='color:red;'>Directory not permitted or not found.</p>";
        exit;
    }

    // Filename patterns for the Edit button (fnmatch, case-insensitive)
    $editablePatterns = [
        '*.ini', 'ini.*',
        '*.conf', 'conf.*', '*.conf.*',
        '*.php', '*.sh', '*.bash',
        '*.txt', '*.cfg', '*.json', '*.xml',
        '*.log', '*.md', '*.yml', '*.yaml',
        '*.js', '*.css', '*.html', '*.htm',
        '*.c', '*.h', '*.py', '*.pl', '*.rb', '*.sql',
        '*.env', '*.service', '*.timer',
        '*.bak', '*.old', '*.dist', '*.sample', '*.tpl', '*.inc',
    ];

    // Build file rows: try native scandir first, fall back to helper ls
    $entries = []; // each: [name, size_bytes, mtime_epoch]
    $files = @scandir($uploadDir);
    if ($files !== false) {
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            if (isset($f[0]) && $f[0] === '.') continue;
            $full = $uploadDir . '/' . $f;
            if (!is_file($full)) continue;
            $entries[] = [$f, @filesize($full) ?: 0, @filemtime($full) ?: time()];
        }
    } else {
        // www-data cannot read this dir — use privileged helper
        [$ok, $out] = freeloader_helper('ls', [$uploadDir]);
        if (!$ok) {
            echo "<p style='color:red;'>Cannot read directory. " . htmlspecialchars($out) . "</p>";
            exit;
        }
        foreach (explode("\n", trim($out)) as $line) {
            if ($line === '') continue;
            $parts = explode("\t", $line);
            $name = $parts[0] ?? '';
            if ($name === '' || (isset($name[0]) && $name[0] === '.')) continue;
            $sz = isset($parts[1]) ? (int)$parts[1] : 0;
            $mt = isset($parts[2]) ? (int)$parts[2] : time();
            $entries[] = [$name, $sz, $mt];
        }
    }

    echo '<table style="width:100%; border-collapse:collapse; font-size:14px;">';
    echo '<tr style="background:#34495e;color:white;">';
    echo '<th style="padding:8px;text-align:left;">File</th>';
    echo '<th style="padding:8px;text-align:right;">Size</th>';
    echo '<th style="padding:8px;">Modified</th>';
    echo '<th style="padding:8px;">Action</th>';
    echo '</tr>';

    foreach ($entries as [$f, $szBytes, $mtEpoch]) {
        $size = round($szBytes / 1024, 2) . ' KB';
        $mtime = date('Y-m-d H:i', $mtEpoch);
        $isEditable = false;
        foreach ($editablePatterns as $pat) {
            if (fnmatch($pat, $f, FNM_CASEFOLD)) {
                $isEditable = true;
                break;
            }
        }
        $safeName = htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
        // json_encode() produces a valid, self-quoting JS string literal;
        // htmlspecialchars() then makes it safe to embed inside the
        // double-quoted onclick="..." attribute (handles filenames that
        // contain a literal double quote, which would otherwise break out
        // of the attribute regardless of JS-level escaping).
        $jsName = htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8');

        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:8px; text-align:left;'>{$safeName}</td>";
        echo "<td style='padding:8px;text-align:right;'>{$size}</td>";
        echo "<td style='padding:8px;'>{$mtime}</td>";
        echo "<td style='padding:8px; white-space:nowrap;'>";
        echo "<button onclick=\"downloadFreeloaderFile({$jsName})\" class='download-btn' style='background:#28a745;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;margin-right:5px;'>Download</button>";
        if ($isEditable) {
            echo "<button onclick=\"editFreeloaderFile({$jsName})\" class='edit-btn' style='background:#007bff;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;margin-right:5px;'>Edit</button>";
        }
        echo "<button onclick=\"deleteFreeloaderFile({$jsName})\" class='delete-btn' style='background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;'>Delete</button>";
        echo "</td></tr>";
    }
    echo '</table>';
    exit;
}

// ==========================================================
// Get file content for editing
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    $filename = freeloader_validate_filename($_GET['file'] ?? '');
    $targetDir = freeloader_validate_dir($_GET['dir'] ?? '');
    if ($filename === false || $targetDir === false) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ERROR: Invalid directory or filename.';
        exit;
    }

    $path = $targetDir . '/' . $filename;
    if (!is_file($path)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ERROR: File not found.';
        exit;
    }

    // Try normal read first
    $content = @file_get_contents($path);
    if ($content === false) {
        // Fall back to helper (sudo)
        [$ok, $out] = freeloader_helper('cat', [$path]);
        if (!$ok) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ERROR: Cannot read file.';
            exit;
        }
        $content = $out;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
    exit;
}

// ==========================================================
// Save edited file
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!freeloader_verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $filename = freeloader_validate_filename($_POST['file'] ?? '');
    $targetDir = freeloader_validate_dir($_POST['dir'] ?? '');
    $content = $_POST['content'] ?? '';

    if ($filename === false || $targetDir === false) {
        echo 'Invalid parameters.';
        exit;
    }

    if (in_array(strtolower($filename), ['.htaccess', '.htpasswd'], true)) {
        echo 'Saving as ' . htmlspecialchars($filename) . ' is not allowed.';
        exit;
    }

    $path = $targetDir . '/' . $filename;

    // Write via temp file + helper (works for both normal and protected dirs)
    $tmp = tempnam(sys_get_temp_dir(), 'freeloader_');
    if ($tmp === false || file_put_contents($tmp, $content) === false) {
        echo 'Failed to create temporary file.';
        exit;
    }
    chmod($tmp, 0644);

    [$ok, $out] = freeloader_helper('cp', [$tmp, $path]);
    @unlink($tmp);

    if ($ok) {
        echo '<strong>SUCCESS:</strong> ' . htmlspecialchars($filename) . ' saved to ' . htmlspecialchars($targetDir);
    } else {
        echo 'Failed to save file. ' . htmlspecialchars($out);
    }
    exit;
}

// ==========================================================
// Upload handling
// ==========================================================
if (!isset($_FILES['file'])) {
    echo 'No file uploaded.';
    exit;
}

if (!freeloader_verify_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

$file = $_FILES['file'];
$filename = freeloader_validate_filename($file['name'] ?? '');
if ($filename === false) {
    echo 'Invalid filename.';
    exit;
}

// Size limit 50 MB (more conservative than original 200)
if (($file['size'] ?? 0) > 50 * 1024 * 1024) {
    echo 'File too large (maximum 50 MB).';
    exit;
}

// Block Apache control files. These can silently change how a directory is
// served (re-enable script execution, add/remove auth, override MIME types),
// so they're blocked regardless of the target directory. Everything else
// (.php, .sh, etc.) is intentionally allowed for experimenters.
$blockedNames = ['.htaccess', '.htpasswd'];
if (in_array(strtolower($filename), $blockedNames, true)) {
    echo 'Uploading ' . htmlspecialchars($filename) . ' is not allowed.';
    exit;
}

$targetDir = freeloader_validate_dir($_POST['target_dir'] ?? '/my_uploads');
if ($targetDir === false) {
    echo 'Target directory not permitted.';
    exit;
}

// Ensure directory exists (only for /my_uploads which should already exist)
if (!is_dir($targetDir)) {
    echo 'Target directory does not exist.';
    exit;
}

$targetFile = $targetDir . '/' . $filename;
$tmpFile = $file['tmp_name'];

// Always go through the helper for consistency and permission handling
[$ok, $out] = freeloader_helper('cp', [$tmpFile, $targetFile]);
if ($ok) {
    echo '<strong>SUCCESS:</strong> ' . htmlspecialchars($filename) . ' uploaded to ' . htmlspecialchars($targetDir);
} else {
    // Fallback to direct move for user-writable dirs
    if (@move_uploaded_file($tmpFile, $targetFile)) {
        @chmod($targetFile, 0664);
        echo '<strong>SUCCESS:</strong> ' . htmlspecialchars($filename) . ' uploaded to ' . htmlspecialchars($targetDir);
    } else {
        echo 'Failed to upload file. ' . htmlspecialchars($out);
    }
}
