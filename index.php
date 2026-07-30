<?php

// index.php - Freeloader login (hardened)

// N5AD - July 2026


session_start();


$configFile = '/etc/freeloader/.config.php';

if (!file_exists($configFile)) {

    die('Configuration file not found. Contact the administrator.');

}

require $configFile;


// Rate limiting (file-based, simple)

$rateFile = '/var/lib/freeloader/login_attempts';

$maxAttempts = 5;

$lockoutSeconds = 300; // 5 minutes


function get_client_ip(): string {

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

}


function load_attempts(string $file): array {

    if (!file_exists($file)) {

        return [];

    }

    $data = @file_get_contents($file);

    if ($data === false) {

        return [];

    }

    $decoded = @json_decode($data, true);

    return is_array($decoded) ? $decoded : [];

}


function save_attempts(string $file, array $data): void {

    $dir = dirname($file);

    if (!is_dir($dir)) {

        @mkdir($dir, 0750, true);

    }

    @file_put_contents($file, json_encode($data), LOCK_EX);

}


$ip = get_client_ip();

$attempts = load_attempts($rateFile);

$now = time();


// Clean old entries

foreach ($attempts as $k => $v) {

    if ($now - ($v['first'] ?? 0) > $lockoutSeconds * 2) {

        unset($attempts[$k]);

    }

}


$ipData = $attempts[$ip] ?? ['count' => 0, 'first' => $now];

$locked = ($ipData['count'] >= $maxAttempts) && ($now - $ipData['first'] < $lockoutSeconds);


// Session timeout handling

$TIMEOUT = 1800;

if (!empty($_SESSION['freeloader_loggedin']) && isset($_SESSION['last_activity'])) {

    if (time() - $_SESSION['last_activity'] > $TIMEOUT) {

        session_unset();

        session_destroy();

        session_start();

    }

}

if (!empty($_SESSION['freeloader_loggedin'])) {

    $_SESSION['last_activity'] = time();

}


$logged_in = !empty($_SESSION['freeloader_loggedin']);

$error = null;


// Handle login

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {

    if ($locked) {

        $error = 'Too many failed attempts. Please wait a few minutes and try again.';

    } else {

        $password = $_POST['password'] ?? '';

        $valid = false;


        // Support both new hashed and legacy plaintext (during migration)

        if (!empty($FREELoader_PASSWORD_HASH)) {

            $valid = password_verify($password, $FREELoader_PASSWORD_HASH);

        } elseif (!empty($FREELoader_PASSWORD)) {

            // Legacy plaintext – still works but should be migrated

            $valid = hash_equals($FREELoader_PASSWORD, $password);

        }


        if ($valid) {

            session_regenerate_id(true);

            $_SESSION['freeloader_loggedin'] = true;

            $_SESSION['last_activity'] = time();

            // Clear rate limit for this IP on success

            unset($attempts[$ip]);

            save_attempts($rateFile, $attempts);

            // Ensure CSRF token exists

            if (empty($_SESSION['freeloader_csrf'])) {

                $_SESSION['freeloader_csrf'] = bin2hex(random_bytes(32));

            }

            header('Location: index.php');

            exit;

        } else {

            // Failed attempt

            if ($ipData['count'] === 0 || ($now - $ipData['first']) > $lockoutSeconds) {

                $ipData = ['count' => 1, 'first' => $now];

            } else {

                $ipData['count']++;

            }

            $attempts[$ip] = $ipData;

            save_attempts($rateFile, $attempts);

            $error = 'Incorrect password.';

        }

    }

}


// Handle logout

if (isset($_GET['logout'])) {

    session_unset();

    session_destroy();

    header('Location: index.php');

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

        @media (max-width: 900px) {

            .container { max-width: 95%; }

        }

    </style>

</head>

<body>

    <div class="container">

        <?php if (!$logged_in): ?>

            <h2 style="text-align:center;">Freeloader Login</h2>

            <?php if ($error): ?>

                <p style="color:red;text-align:center;"><?= htmlspecialchars($error) ?></p>

            <?php endif; ?>

            <?php if ($locked): ?>

                <p style="color:#c0392b;text-align:center;font-weight:bold;">Account temporarily locked due to too many failed attempts.</p>

            <?php else: ?>

            <form method="post" style="max-width:400px;margin:0 auto;">

                <input type="password" name="password" placeholder="Enter Password"

                       style="width:100%;padding:12px;margin:10px 0; box-sizing:border-box;" required autofocus>

                <button type="submit" style="width:100%;padding:12px;background:#2c3e50;color:white;border:none;border-radius:5px;cursor:pointer;">Login</button>

            </form>

            <?php endif; ?>

        <?php else: ?>

            <a href="?logout=1" class="logout">[Logout]</a>

            <?php include 'freeloader.inc'; ?>

        <?php endif; ?>

    </div>

</body>

</html>

