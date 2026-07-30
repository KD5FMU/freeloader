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

	'/var/www/html/freeloader',

	'/etc/allmon3',

	'/etc/asterisk/local',

	'/usr/share/allmon3',

	'/usr/local/bin',



    ];

}


/**

 * Resolve and validate a user-supplied directory against the whitelist.

 * Returns the realpath string or false on failure.

 */

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


/**

 * Validate a filename (no path components).

 */

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


/**

 * Require a valid logged-in session. Dies with 403 if not.

 */

function freeloader_require_auth(): void {

    if (session_status() !== PHP_SESSION_ACTIVE) {

        session_start();

    }

    if (empty($_SESSION['freeloader_loggedin'])) {

        http_response_code(403);

        die('Access denied. Please log in.');

    }

    // Sliding timeout (30 minutes)

    $timeout = 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {

        session_unset();

        session_destroy();

        http_response_code(403);

        die('Session expired. Please log in again.');

    }

    $_SESSION['last_activity'] = time();

}


/**

 * CSRF token helpers

 */

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


/**

 * Call the restricted helper. Returns [success: bool, output: string]

 */

function freeloader_helper(string $command, array $args = []): array {

    $helper = '/usr/local/bin/freeloader-helper';

    if (!file_exists($helper)) {

        return [false, 'Helper script not found.'];

    }


    // Must run via sudo — sudoers allows only this binary for www-data

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

