<?php

session_start();


// Load secure password

$configFile = '/etc/freeloader/.config.php';

if (file_exists($configFile)) {

    include $configFile;

} else {

    die("Configuration file not found. Contact the administrator.");

}


// Session timeout (30 minutes)

$TIMEOUT = 1800; // seconds


if (isset($_SESSION['freeloader_loggedin']) && isset($_SESSION['last_activity'])) {

    if (time() - $_SESSION['last_activity'] > $TIMEOUT) {

        session_unset();

        session_destroy();

    }

}

$_SESSION['last_activity'] = time();


$logged_in = isset($_SESSION['freeloader_loggedin']);


// Handle login

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {

    if ($_POST['password'] === $FREELoader_PASSWORD) {

        $_SESSION['freeloader_loggedin'] = true;

        $_SESSION['last_activity'] = time();

        header("Location: index.php");

        exit;

    } else {

        $error = "Incorrect password.";

    }

}


// Handle logout

if (isset($_GET['logout'])) {

    session_destroy();

    header("Location: index.php");

    exit;

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Freeloader</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>

        body { font-family: Arial, sans-serif; margin:0; padding:20px; background:#f4f4f4; }

        .container { max-width: 60%; margin:0 auto; background:white; padding:25px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); }

        .logout { float:right; color:#e74c3c; text-decoration:none; }

    </style>

</head>

<body>

    <div class="container">

        <?php if (!$logged_in): ?>

            <h2 style="text-align:center;">Freeloader Login</h2>

            <?php if (isset($error)) echo "<p style='color:red;text-align:center;'>$error</p>"; ?>

            <form method="post" style="max-width:400px;margin:0 auto;">

                <input type="password" name="password" placeholder="Enter Password" style="width:100%;padding:12px;margin:10px 0;" required autofocus>

                <button type="submit" style="width:100%;padding:12px;background:#2c3e50;color:white;border:none;border-radius:5px;cursor:pointer;">Login</button>

            </form>

        <?php else: ?>

            <a href="?logout=1" class="logout">[Logout]</a>

            <?php include 'freeloader.inc'; ?>

        <?php endif; ?>

    </div>

</body>

</html>
