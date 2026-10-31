<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

function route_page(string $page): void
{
    match ($page) {
        'pick' => page_pick(),
        'manual' => page_manual(),
        'recipes' => page_recipes(),
        'recipe' => page_recipe(),
        'recipe_edit' => page_recipe_edit(),
        'import' => page_import(),
        'export' => page_export(),
        'catalog' => page_catalog(),
        'settings' => page_settings(),
        'shop' => page_shop(),
        'shop_pdf' => page_shop_pdf(),
        'history' => page_history(),
        default => page_week(),
    };
}

function not_found(string $msg): never
{
    http_response_code(404);
    render('message', ['title' => 'Nicht gefunden', 'message' => $msg]);
    exit;
}

function page_week(): void
{
    $ws = monday_of(get_str('w'));
    $plan = plan_get_or_create($ws);
    $days = plan_days((int) $plan['id']);
    $cats = categories();
    $prevCat = previous_day_category($ws);
    $warnings = plan_warnings(
        array_map(static fn($d) => ['skip' => (bool) $d['skip'], 'category_id' => $d['category_id'] !== null ? (int) $d['category_id'] : null], $days),
        setting_int('max_per_category', 2), category_names(), $prevCat
    );
    $recipeCount = (int) q('SELECT COUNT(*) FROM recipes WHERE archived = 0')->fetchColumn();
    render('week', [
        'title' => 'Woche ' . week_number($ws), 'nav' => 'week', 'plan' => $plan, 'days' => $days, 'cats' => $cats,
        'warnings' => $warnings, 'recipeCount' => $recipeCount,
        'prevWs' => (new DateTimeImmutable($ws))->modify('-7 days')->format('Y-m-d'),
        'nextWs' => (new DateTimeImmutable($ws))->modify('+7 days')->format('Y-m-d'),
        'isCurrent' => $ws === monday_of(),
    ]);
}

function page_pick(): void
{
    $day = require_day(get_int('id'));
    $filters = ['q' => get_str('q'), 'category' => get_int('category'), 'effort' => get_str('effort'), 'meal' => 'abend'];
    render('pick', [
        'title' => 'Rezept wählen', 'nav' => 'week', 'day' => $day, 'filters' => $filters,
        'recipes' => recipes_search($filters), 'cats' => categories(),
    ]);
}

function page_manual(): void
{
    $day = require_day(get_int('id'));
    render('manual', ['title' => 'Manuelles Menü', 'nav' => 'week', 'day' => $day, 'cats' => categories()]);
}

function page_recipes(): void
{
    $filters = [
        'q' => get_str('q'), 'category' => get_int('category'), 'effort' => get_str('effort'),
        'season' => get_str('season'), 'archived' => get_int('archived', 0),
    ];
    render('recipes', [
        'title' => 'Rezepte', 'nav' => 'recipes', 'filters' => $filters,
        'recipes' => recipes_search($filters), 'cats' => categories(),
        'total' => (int) q('SELECT COUNT(*) FROM recipes WHERE archived = 0')->fetchColumn(),
    ]);
}

function page_recipe(): void
{
    $r = recipe_get((int) get_int('id', 0));
    if (!$r) {
        not_found('Dieses Rezept gibt es nicht.');
    }
    $persons = max(1, min(40, (int) get_int('persons', (int) $r['servings'])));
    $lastDay = q('SELECT MAX(day) FROM plan_days WHERE recipe_id = ? AND skip = 0', [$r['id']])->fetchColumn();
    render('recipe', [
        'title' => $r['title'], 'nav' => 'recipes', 'r' => $r, 'persons' => $persons,
        'factor' => $persons / max(1, (int) $r['servings']), 'cook' => get_int('cook') === 1,
        'lastDay' => $lastDay ?: null, 'cats' => categories(), 'back' => get_str('back'),
        'bodyClass' => get_int('cook') === 1 ? 'cook' : '',
    ]);
}

/** Recipe form data from POST. */
function recipe_from_post(): array
{
    $ings = [];
    foreach ((array) ($_POST['ing'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $ings[] = [
            'name' => mb_substr($name, 0, 120), 'quantity' => parse_quantity($row['qty'] ?? null),
            'qty_raw' => trim((string) ($row['qty'] ?? '')), 'unit' => normalize_unit((string) ($row['unit'] ?? '')),
            'note' => trim((string) ($row['note'] ?? '')), 'optional' => !empty($row['optional']),
        ];
    }
    $cat = post_int('category_id');
    return [
        'title' => mb_substr(post_str('title'), 0, 160), 'servings' => clamp_int(post_int('servings'), 1, 40, 4),
        'category_id' => $cat && isset(categories()[$cat]) ? $cat : null, 'effort' => post_str('effort', 'mittel'),
        'seasons' => array_values(array_intersect(SEASONS, (array) ($_POST['seasons'] ?? []))),
        'meal' => post_str('meal', 'abend'), 'protein' => post_str('protein'), 'steps' => post_str('steps'),
        'notes' => post_str('notes'), 'source' => post_str('source'), 'ingredients' => $ings,
    ];
}

function recipe_to_form(array $r): array
{
    $ings = [];
    foreach ($r['ingredients'] as $i) {
        $ings[] = [
            'name' => $i['name'], 'qty_raw' => $i['quantity'] !== null ? format_number((float) $i['quantity'], 3) : '',
            'unit' => $i['unit'], 'note' => $i['note'], 'optional' => (bool) $i['optional'],
        ];
    }
    return [
        'title' => $r['title'], 'servings' => (int) $r['servings'], 'category_id' => (int) $r['category_id'],
        'effort' => $r['effort'], 'seasons' => $r['seasons_list'], 'meal' => $r['meal'], 'protein' => $r['protein'],
        'steps' => $r['steps'], 'notes' => $r['notes'], 'source' => $r['source'], 'ingredients' => $ings,
    ];
}

function page_recipe_edit(): void
{
    $id = get_int('id');
    $existing = $id ? recipe_get($id) : null;
    if ($id && !$existing) {
        not_found('Dieses Rezept gibt es nicht.');
    }
    $errors = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && post_str('a') === 'recipe_save') {
        $data = recipe_from_post();
        foreach ($data['ingredients'] as $i) {
            if ($i['qty_raw'] !== '' && $i['quantity'] === null) {
                $errors[] = 'Menge «' . $i['qty_raw'] . '» bei ' . $i['name'] . ' ist ungültig.';
            }
        }
        $errors = array_merge(recipe_validate($data), $errors);
        if (!$errors) {
            $newId = recipe_save($data, $existing ? (int) $existing['id'] : null);
            flash('Rezept «' . $data['title'] . '» gespeichert.');
            redirect(url('recipe', ['id' => $newId]));
        }
    } else {
        $data = $existing ? recipe_to_form($existing) : [
            'title' => '', 'servings' => setting_int('persons', 6), 'category_id' => null, 'effort' => 'mittel', 'seasons' => [],
            'meal' => 'abend', 'protein' => '', 'steps' => '', 'notes' => '', 'source' => '', 'ingredients' => [],
        ];
    }
    render('recipe_form', [
        'title' => $existing ? 'Rezept bearbeiten' : 'Neues Rezept', 'nav' => 'recipes', 'id' => $existing ? (int) $existing['id'] : null,
        'd' => $data, 'errors' => $errors, 'cats' => categories(), 'catalog' => ingredients_all(),
    ]);
}

function page_import(): void
{
    $a = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? post_str('a') : '';
    $raw = (string) ($_POST['json'] ?? '');
    if ($a === 'import_commit') {
        $include = array_map('intval', (array) ($_POST['include'] ?? []));
        $mapping = [];
        foreach ((array) ($_POST['map'] ?? []) as $k => $m) {
            if (is_array($m)) {
                $existing = trim((string) ($m['target'] ?? '')) !== '' ? ingredient_by_name((string) $m['target']) : null;
                $mapping[mb_strtolower((string) $k)] = [
                    'target' => $existing ? (string) $existing['id'] : 'new', 'aisle_id' => $m['aisle_id'] ?? null,
                    'pantry' => !empty($m['pantry']), 'unit' => (string) ($m['unit'] ?? ''),
                ];
            }
        }
        $n = import_commit($raw, $include, $mapping);
        flash($n . ' ' . ($n === 1 ? 'Rezept' : 'Rezepte') . ' importiert.');
        redirect(url('recipes'));
    }
    if ($a === 'import_preview') {
        $analysis = import_analyze($raw);
        if (!$analysis['errors'] && $analysis['recipes']) {
            render('import_preview', [
                'title' => 'Import prüfen', 'nav' => 'recipes', 'raw' => $raw, 'analysis' => $analysis,
                'aisles' => aisles(), 'catalog' => ingredients_all(),
            ]);
            return;
        }
        render('import', ['title' => 'Rezepte importieren', 'nav' => 'recipes', 'raw' => $raw,
            'errors' => $analysis['errors'] ?: ['Keine Rezepte gefunden.'], 'cats' => categories(), 'aisles' => aisles()]);
        return;
    }
    render('import', ['title' => 'Rezepte importieren', 'nav' => 'recipes', 'raw' => $raw, 'errors' => [],
        'cats' => categories(), 'aisles' => aisles()]);
}

function page_export(): void
{
    $id = get_int('id');
    $ids = $id ? [$id] : array_map('intval', q('SELECT id FROM recipes ORDER BY title COLLATE NOCASE')->fetchAll(PDO::FETCH_COLUMN));
    $recipes = [];
    foreach ($ids as $rid) {
        if ($r = recipe_get($rid)) {
            $recipes[] = recipe_export($r);
        }
    }
    if ($id && !$recipes) {
        not_found('Dieses Rezept gibt es nicht.');
    }
    $name = $id ? 'culina-rezept-' . $id : 'culina-rezepte-' . date('Y-m-d');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '.json"');
    echo json_encode($id ? $recipes[0] : ['recipes' => $recipes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function page_catalog(): void
{
    $qStr = get_str('q');
    $filter = get_str('filter');
    $all = ingredients_all();
    $list = array_filter($all, static function ($i) use ($qStr, $filter) {
        if ($qStr !== '' && !str_contains(mb_strtolower($i['name']), mb_strtolower($qStr))) {
            return false;
        }
        if ($filter === 'noaisle' && $i['aisle_id'] !== null) {
            return false;
        }
        if ($filter === 'unused' && (int) $i['used'] > 0) {
            return false;
        }
        return true;
    });
    render('catalog', [
        'title' => 'Zutaten', 'nav' => 'catalog', 'list' => $list, 'all' => $all, 'aisles' => aisles(),
        'q' => $qStr, 'filter' => $filter,
        'noAisle' => count(array_filter($all, static fn($i) => $i['aisle_id'] === null)),
    ]);
}

function page_settings(): void
{
    render('settings', [
        'title' => 'Einstellungen', 'nav' => 'settings', 'cats' => categories(), 'aisles' => aisles(),
        'persons' => setting_int('persons', 6), 'maxPerCategory' => setting_int('max_per_category', 2),
        'avoidWeeks' => setting_int('avoid_weeks', 3),
    ]);
}

function page_shop(): void
{
    $ws = monday_of(get_str('w'));
    $plan = plan_get_or_create($ws);
    render('shop', [
        'title' => 'Einkaufsliste', 'nav' => 'shop', 'plan' => $plan, 'list' => shopping_build($plan), 'aisles' => aisles(),
        'prevWs' => (new DateTimeImmutable($ws))->modify('-7 days')->format('Y-m-d'),
        'nextWs' => (new DateTimeImmutable($ws))->modify('+7 days')->format('Y-m-d'),
    ]);
}

function page_shop_pdf(): void
{
    $ws = monday_of(get_str('w'));
    $plan = plan_get_or_create($ws);
    $pdf = shopping_pdf($plan, shopping_build($plan));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="einkaufsliste-kw' . week_number($ws) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}

function page_history(): void
{
    $plans = q('SELECT * FROM plans ORDER BY week_start DESC LIMIT 104')->fetchAll();
    $weeks = [];
    foreach ($plans as $p) {
        $days = plan_days((int) $p['id']);
        $planned = array_filter($days, static fn($d) => !$d['skip'] && ($d['recipe_id'] !== null || $d['free_title'] !== ''));
        if (!$planned) {
            continue;
        }
        $weeks[] = ['plan' => $p, 'days' => $days];
    }
    render('history', ['title' => 'Verlauf', 'nav' => 'week', 'weeks' => $weeks, 'cats' => categories()]);
}
