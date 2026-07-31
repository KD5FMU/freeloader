<?php

// freeloader_common.php

// Shared security helpers for Freeloader (hardened)

// N5AD - July 2026


if (!defined('FREELOADER_COMMON')) {

    define('FREELOADER_COMMON', true);

}


// ------------------------------------------------------------

// Allowed directories (must match the helper script)

// ------------------------------------------------------------

function freeloader_allowed_dirs(): array {

    return [

        '/my_uploads',

        '/etc/asterisk',

        '/var/lib/asterisk',

        '/var/www/html/supermon',

	    '/etc/allmon3',

	    '/etc/asterisk/local',

	    '/usr/share/allmon3',

    ];

}




function freeloader_validate_dir(string $input): string|false {

    $input = trim($input);

    if ($input === '' || str_contains($input, "\0")) {

        return false;

    }


    $resolved = realpath($input);

    if ($resolved === false || !is_dir($resolved)) {

        return false;

    }


    $allowed = freeloader_allowed_dirs();

    foreach ($allowed as $base) {

        $baseReal = realpath($base);

        if ($baseReal === false) {

            continue;

        }

        if ($resolved === $baseReal || str_starts_with($resolved, $baseReal . DIRECTORY_SEPARATOR)) {

            return $resolved;

        }

    }

    return false;

}




function freeloader_validate_filename(string $name): string|false {

    $name = basename($name);

    if ($name === '' || $name === '.' || $name === '..') {

        return false;

    }

    // Block path separators and null bytes that might survive basename in some edge cases

    if (preg_match('/[\/\\\\\x00]/', $name)) {

        return false;

    }

    return $name;

}




function freeloader_require_auth(): void {

    if (session_status() !== PHP_SESSION_ACTIVE) {

        session_start();

    }

    if (empty($_SESSION['freeloader_loggedin'])) {

        http_response_code(403);

        die('Access denied. Please log in.');

    }

    

    $timeout = 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {

        session_unset();

        session_destroy();

        http_response_code(403);

        die('Session expired. Please log in again.');

    }

    $_SESSION['last_activity'] = time();

}




function freeloader_csrf_token(): string {

    if (empty($_SESSION['freeloader_csrf'])) {

        $_SESSION['freeloader_csrf'] = bin2hex(random_bytes(32));

    }

    return $_SESSION['freeloader_csrf'];

}


function freeloader_verify_csrf(?string $token): bool {

    if (empty($token) || empty($_SESSION['freeloader_csrf'])) {

        return false;

    }

    return hash_equals($_SESSION['freeloader_csrf'], $token);

}




function freeloader_helper(string $command, array $args = []): array {

    $helper = '/usr/local/bin/freeloader-helper';

    if (!file_exists($helper)) {

        return [false, 'Helper script not found.'];

    }


    

    $cmd = 'sudo ' . escapeshellarg($helper) . ' ' . escapeshellarg($command);

    foreach ($args as $a) {

        $cmd .= ' ' . escapeshellarg($a);

    }


    $output = [];

    $returnCode = 0;

    exec($cmd . ' 2>&1', $output, $returnCode);


    return [$returnCode === 0, implode("\n", $output)];

}

?>

