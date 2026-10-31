<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const WEEKDAYS_LONG = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a relative link to a page of the front controller. */
function url(string $page, array $params = []): string
{
    $params = array_filter(['p' => $page] + $params, static fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($params);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte Seite neu laden und nochmals versuchen.');
    }
}

function render(string $view, array $vars = [], ?string $layout = 'layout'): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require CULINA_ROOT . '/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require CULINA_ROOT . '/views/' . $layout . '.php';
}

function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    return is_string($v) && is_numeric(trim($v)) ? (int) $v : $default;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key, ?int $default = null): ?int
{
    $v = $_GET[$key] ?? null;
    return is_string($v) && is_numeric(trim($v)) ? (int) $v : $default;
}

function is_fetch(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function weekday_index(string $date): int
{
    return (int) (new DateTimeImmutable($date))->format('N') - 1;
}

function dow(string $date): string
{
    return WEEKDAYS[weekday_index($date)];
}

function date_short(string $date): string
{
    return (new DateTimeImmutable($date))->format('j.n.');
}

function date_long(string $date): string
{
    $d = new DateTimeImmutable($date);
    return $d->format('j') . '. ' . MONTHS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
}

function week_number(string $weekStart): int
{
    return (int) (new DateTimeImmutable($weekStart))->format('W');
}

/** "2.–8. November 2026" or "30. November – 6. Dezember 2026". */
function week_label(string $weekStart): string
{
    $a = new DateTimeImmutable($weekStart);
    $b = $a->modify('+6 days');
    $ma = MONTHS[(int) $a->format('n') - 1];
    $mb = MONTHS[(int) $b->format('n') - 1];
    if ($a->format('n') === $b->format('n')) {
        return $a->format('j') . '.–' . $b->format('j') . '. ' . $mb . ' ' . $b->format('Y');
    }
    $ya = $a->format('Y') !== $b->format('Y') ? ' ' . $a->format('Y') : '';
    return $a->format('j') . '. ' . $ma . $ya . ' – ' . $b->format('j') . '. ' . $mb . ' ' . $b->format('Y');
}

/** Human label for when a recipe was last on a plan, relative to a week. */
function last_cooked_label(?string $day, ?string $reference = null): string
{
    if ($day === null || $day === '') {
        return 'noch nie geplant';
    }
    $ref = new DateTimeImmutable($reference ?? 'today');
    $d = new DateTimeImmutable($day);
    $diff = (int) $ref->diff($d)->format('%r%a');
    if ($diff > 0) {
        return 'geplant am ' . date_short($day);
    }
    $days = -$diff;
    if ($days < 7) {
        return 'diese Woche';
    }
    $weeks = intdiv($days, 7);
    if ($weeks < 9) {
        return 'vor ' . $weeks . ' ' . ($weeks === 1 ? 'Woche' : 'Wochen');
    }
    $months = intdiv($days, 30);
    return 'vor ' . $months . ' Monaten';
}

/** Coloured badge for a staple category. */
function cat_badge(mixed $id, array $cats): string
{
    if ($id === null || $id === '' || !isset($cats[(int) $id])) {
        return '';
    }
    $c = $cats[(int) $id];
    return '<span class="cat cat-c' . ($c['idx'] % 9) . '">' . h($c['name']) . '</span>';
}

/** Sort key that orders umlauts like their base letter (Ä next to A). */
function sort_key(string $s): string
{
    return strtr(mb_strtolower($s), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c', 'ß' => 'ss']);
}

function sort_by_name(array &$rows, string $field): void
{
    usort($rows, static fn($a, $b) => strcmp(sort_key((string) $a[$field]), sort_key((string) $b[$field])));
}
