<?php

// freeloader_delete.php

// Supports sudo rm for system directories

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


    // Use sudo rm for system directories

    if (strpos($targetDir, '/etc/') === 0 || strpos($targetDir, '/var/www/html/supermon') === 0) {

        $cmd = "sudo rm -f " . escapeshellarg($path);

        exec($cmd, $output, $returnCode);

        

        if ($returnCode === 0) {

            echo htmlspecialchars($filename) . " deleted successfully from " . htmlspecialchars($targetDir);

        } else {

            echo "Failed to delete " . htmlspecialchars($filename) . " (sudo rm failed)";

        }

    } else {

        // Normal delete for user directories

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
