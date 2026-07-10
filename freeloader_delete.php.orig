<?php
// freeloader_delete.php
// file deletion utility for freeloader file upload utility
// created by James N5AD
// June 2026
?>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) { $filename = basename($_POST['file']); $path = "/my_uploads/" . $filename;
    
    if (file_exists($path) && unlink($path)) { echo " " . ($filename) . " deleted.";
    } else {
        echo " Failed to delete " . ($filename);
    }
} else {
    echo "Invalid request.";
}
?>

