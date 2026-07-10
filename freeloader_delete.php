<?php
// freeloader_delete.php
// File deletion utility for Freeloader
// Supports any target directory + confirmation
// N5AD - July 2026
?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) {
    $filename = basename($_POST['file']);
    $targetDir = isset($_POST['dir']) ? realpath($_POST['dir']) : '/my_uploads';
   
    if (!$targetDir || !is_dir($targetDir)) {
        echo "Invalid target directory.";
        exit;
    }
   
    $path = $targetDir . '/' . $filename;
   
    if (!file_exists($path)) {
        echo "File not found: " . htmlspecialchars($filename);
        exit;
    }
   
    // Optional: Extra server-side safety check (e.g. prevent deleting certain critical files)
    if (strpos($targetDir, '/etc') === 0 || strpos($targetDir, '/bin') === 0) {
        echo "Safety block: Cannot delete files from system directories.";
        exit;
    }
   
    if (unlink($path)) {
        echo htmlspecialchars($filename) . " was successfully deleted from " . htmlspecialchars($targetDir);
    } else {
        echo "Failed to delete " . htmlspecialchars($filename) . " (permission error?)";
    }
} else {
    echo "Invalid request.";
}
?>
