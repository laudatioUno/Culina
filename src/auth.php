<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_SECONDS = 900;

function start_session(): void
{
    $lifetime = (int) config('session_days') * 86400;
    $dir = rtrim((string) config('data_dir'), '/') . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
    }
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    session_name('culina');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function has_password(): bool
{
    return (string) setting('password_hash', '') !== '';
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function set_password(string $password): void
{
    set_setting('password_hash', password_hash($password, PASSWORD_DEFAULT));
}

function password_problem(string $password, string $repeat): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if ($password !== $repeat) {
        return 'Die Passwörter stimmen nicht überein.';
    }
    return null;
}

function login_locked(): bool
{
    $since = time() - LOGIN_WINDOW_SECONDS;
    q('DELETE FROM login_attempts WHERE at < ?', [$since]);
    return (int) q('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [client_ip()])->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

/** Returns null on success, otherwise an error message. */
function login(string $password): ?string
{
    if (login_locked()) {
        return 'Zu viele Fehlversuche. Bitte 15 Minuten warten.';
    }
    if (!password_verify($password, (string) setting('password_hash', ''))) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [client_ip(), time()]);
        return 'Falsches Passwort.';
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    return null;
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}
