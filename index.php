<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/actions.php';
require __DIR__ . '/src/pages.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

start_session();

$page = get_str('p', 'week');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if (!has_password()) {
    $error = null;
    if ($isPost) {
        csrf_check();
        $pw = (string) ($_POST['password'] ?? '');
        $error = password_problem($pw, (string) ($_POST['password2'] ?? ''));
        if ($error === null) {
            set_password($pw);
            login($pw);
            flash('Willkommen bei Culina! Das Passwort ist gesetzt.');
            redirect(url('week'));
        }
    }
    render('setup', ['title' => 'Einrichten', 'error' => $error], 'bare');
    exit;
}

if (!is_logged_in()) {
    $error = null;
    if ($isPost && $page === 'login') {
        csrf_check();
        $error = login((string) ($_POST['password'] ?? ''));
        if ($error === null) {
            redirect(url('week'));
        }
    }
    render('login', ['title' => 'Anmelden', 'error' => $error], 'bare');
    exit;
}

if ($isPost) {
    csrf_check();
    handle_action(post_str('a'));
}

route_page($page);
