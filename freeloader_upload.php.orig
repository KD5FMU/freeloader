<?php
// freeloader_upload.php
// Freeloader Upload + File Listing Utility
// Uploads any file to /my_uploads 
// Created by James N5AD
// June 2026
?>
<?php
// ==========================================================
// File Listing
// ==========================================================

if (isset($_GET['action']) && $_GET['action'] === 'list') {

    $uploadDir = '/my_uploads';
    $files = scandir($uploadDir);

    echo '<table style="width:100%; border-collapse:collapse; font-size:14px;">';
    echo '<tr style="background:#34495e;color:white;">';
    echo '<th style="padding:8px;text-align:left;">File</th>';
    echo '<th style="padding:8px;text-align:right;">Size</th>';
    echo '<th style="padding:8px;">Modified</th>';
    echo '<th style="padding:8px;">Action</th>';
    echo '</tr>';

    foreach ($files as $f) {

        if (
            $f === '.' ||
            $f === '..' ||
            is_dir("$uploadDir/$f")
        ) {
            continue;
        }

        $size = round(filesize("$uploadDir/$f") / 1024, 2) . ' KB';
        $mtime = date('Y-m-d H:i', filemtime("$uploadDir/$f"));

        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:8px;'>" . ($f) . "</td>";
        echo "<td style='padding:8px;text-align:right;'>$size</td>";
        echo "<td style='padding:8px;'>$mtime</td>";
        echo "<td style='padding:8px;'>
                <button
                    onclick=\"deleteFreeloaderFile('" . addslashes($f) . "')\"
                    style='background:#dc3545;
                           color:white;
                           border:none;
                           padding:5px 10px;
                           border-radius:4px;
                           cursor:pointer;'>
                    Delete
                </button>
              </td>";
        echo "</tr>";
    }

    echo '</table>';
    exit;
}

// ==========================================================
// Upload Handler
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

// Maximum upload size: 200 MB
if ($file['size'] > 200 * 1024 * 1024) {
    echo "File too large (maximum 200 MB).";
    exit;
}

$targetDir  = "/my_uploads/";
$targetFile = $targetDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {

    chmod($targetFile, 0664);

    // Ignore ownership errors if not permitted
    @chown($targetFile, 'www-data');

    echo "<strong>" . ($filename) .
         "</strong> uploaded successfully to /my_uploads";

} else {

    echo "Failed to upload file.";

}
?>
