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
        '/etc/asterisk/local',
        '/etc/allmon3',
        '/var/lib/asterisk',
        '/var/www/html/supermon',
        '/usr/share/allmon3',
    ];
}

/**
 * Normalize a path string without requiring the path to be readable by the current user.
 * Rejects ".." components and null bytes. Does not resolve symlinks.
 */
function freeloader_normalize_path(string $input): string|false {
    $input = trim($input);
    if ($input === '' || str_contains($input, "\0")) {
        return false;
    }
    // Must be absolute
    if ($input[0] !== '/') {
        return false;
    }
    // Collapse multiple slashes, remove trailing slash (except root)
    $input = preg_replace('#/+#', '/', $input);
    if ($input !== '/' ) {
        $input = rtrim($input, '/');
    }
    $parts = explode('/', $input);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') {
            continue;
        }
        if ($p === '..') {
            return false; // no parent traversal
        }
        $out[] = $p;
    }
    return '/' . implode('/', $out);
}

/**
 * Resolve and validate a user-supplied directory against the whitelist.
 * Returns a canonical absolute path string or false on failure.
 *
 * Important: does NOT require www-data to be able to realpath() the target.
 * Protected dirs like /etc/asterisk are still accepted if they match the whitelist.
 */
function freeloader_validate_dir(string $input): string|false {
    $normalized = freeloader_normalize_path($input);
    if ($normalized === false) {
        return false;
    }

    // Prefer realpath when the process can see the path
    $resolved = realpath($normalized);
    if ($resolved !== false && is_dir($resolved)) {
        $check = $resolved;
    } else {
        // Fall back to normalized string (www-data may lack traverse rights)
        $check = $normalized;
    }

    foreach (freeloader_allowed_dirs() as $base) {
        $base = rtrim($base, '/');
        // Also try realpath on the base when possible
        $baseReal = realpath($base);
        if ($baseReal !== false) {
            $base = $baseReal;
        }
        if ($check === $base || str_starts_with($check, $base . '/')) {
            return $check;
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
    if (preg_match('/[\/\\\\\x00]/', $name)) {
        return false;
    }
    return $name;
}

/**
 * True when the request arrived over HTTPS (direct or via reverse proxy).
 */
function freeloader_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/**
 * Start session with hardened cookie flags (HttpOnly, SameSite=Strict,
 * Secure when the request is HTTPS). Call instead of raw session_start().
 */
function freeloader_bootstrap_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => freeloader_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Require a valid logged-in session. Dies with 403 if not.
 */
function freeloader_require_auth(): void {
    freeloader_bootstrap_session();
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
    freeloader_bootstrap_session();
    if (empty($_SESSION['freeloader_csrf'])) {
        $_SESSION['freeloader_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['freeloader_csrf'];
}

function freeloader_verify_csrf(?string $token): bool {
    freeloader_bootstrap_session();
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
