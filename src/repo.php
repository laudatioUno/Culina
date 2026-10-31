<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

const EFFORTS = ['schnell' => 'Schnell (< 30 min)', 'mittel' => 'Mittel', 'aufwendig' => 'Aufwendig'];
const MEALS = ['abend' => 'Abendessen', 'mittag' => 'Mittagessen', 'beides' => 'Mittag- und Abendessen'];
const PROTEINS = ['Fleisch', 'Geflügel', 'Fisch', 'Vegetarisch', 'Vegan'];

/* ---------- Settings ---------- */

function setting(string $key, ?string $default = null): ?string
{
    $v = q('SELECT value FROM settings WHERE key = ?', [$key])->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function setting_int(string $key, int $default): int
{
    $v = setting($key);
    return $v !== null && is_numeric($v) ? (int) $v : $default;
}

function set_setting(string $key, string $value): void
{
    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
}

/* ---------- Categories & aisles ---------- */

/** @return array<int,array> id => row, in display order (with "idx" = position) */
function categories(): array
{
    $out = [];
    foreach (q('SELECT id, name, sort FROM categories ORDER BY sort, id')->fetchAll() as $i => $r) {
        $r['idx'] = $i;
        $out[(int) $r['id']] = $r;
    }
    return $out;
}

function category_names(): array
{
    return array_map(static fn($c) => $c['name'], categories());
}

/** @return array<int,array> id => row, in shop walking order */
function aisles(): array
{
    $out = [];
    foreach (q('SELECT id, name, sort FROM aisles ORDER BY sort, id')->fetchAll() as $r) {
        $out[(int) $r['id']] = $r;
    }
    return $out;
}

/** Save an ordered list editor (categories or aisles): rename, reorder, add, delete unused. */
function save_ordered_list(string $table, array $ids, array $names, string $newName, array $delete): array
{
    $errors = [];
    $usage = $table === 'categories'
        ? 'SELECT (SELECT COUNT(*) FROM recipes WHERE category_id = :id) + (SELECT COUNT(*) FROM plan_days WHERE free_category_id = :id)'
        : 'SELECT (SELECT COUNT(*) FROM ingredients WHERE aisle_id = :id) + (SELECT COUNT(*) FROM shop_extras WHERE aisle_id = :id)';
    db_transaction(static function () use ($table, $ids, $names, $newName, $delete, $usage, &$errors) {
        $sort = 0;
        foreach ($ids as $i => $id) {
            $id = (int) $id;
            $name = trim((string) ($names[$i] ?? ''));
            if (in_array((string) $id, array_map('strval', $delete), true)) {
                $used = (int) q($usage, ['id' => $id])->fetchColumn();
                if ($used > 0 && $table === 'categories') {
                    $errors[] = '«' . $name . '» wird noch verwendet und wurde nicht gelöscht.';
                } else {
                    q("DELETE FROM $table WHERE id = ?", [$id]);
                    continue;
                }
            }
            if ($name === '') {
                $errors[] = 'Leere Namen sind nicht erlaubt.';
                continue;
            }
            $sort++;
            try {
                q("UPDATE $table SET name = ?, sort = ? WHERE id = ?", [$name, $sort, $id]);
            } catch (PDOException) {
                $errors[] = '«' . $name . '» gibt es bereits.';
            }
        }
        $newName = trim($newName);
        if ($newName !== '') {
            try {
                q("INSERT INTO $table (name, sort) VALUES (?, ?)", [$newName, $sort + 1]);
            } catch (PDOException) {
                $errors[] = '«' . $newName . '» gibt es bereits.';
            }
        }
    });
    return $errors;
}

/* ---------- Ingredients ---------- */

/** @return array<int,array> id => row incl. aisle name, usage count and parsed conversions */
function ingredients_all(): array
{
    $rows = q('SELECT i.*, a.name AS aisle,
            (SELECT COUNT(DISTINCT recipe_id) FROM recipe_ingredients WHERE ingredient_id = i.id) AS used
        FROM ingredients i LEFT JOIN aisles a ON a.id = i.aisle_id
        ORDER BY i.name COLLATE NOCASE')->fetchAll();
    sort_by_name($rows, 'name');
    $out = [];
    foreach ($rows as $r) {
        $r['conv'] = parse_conversions((string) $r['conversions']);
        $out[(int) $r['id']] = $r;
    }
    return $out;
}

/** lowercase name => id, for matching free text against the catalog */
function ingredient_name_index(array $catalog): array
{
    $idx = [];
    foreach ($catalog as $id => $r) {
        $idx[mb_strtolower($r['name'])] = $id;
    }
    return $idx;
}

function ingredient_by_name(string $name): ?array
{
    $needle = mb_strtolower(trim($name));
    foreach (q('SELECT * FROM ingredients')->fetchAll() as $r) {
        if (mb_strtolower($r['name']) === $needle) {
            return $r;
        }
    }
    return null;
}

function ingredient_create(string $name, string $unit = '', ?int $aisleId = null, bool $pantry = false, string $conversions = ''): int
{
    q('INSERT INTO ingredients (name, unit, aisle_id, pantry, conversions) VALUES (?, ?, ?, ?, ?)',
        [trim($name), normalize_unit($unit), $aisleId, $pantry ? 1 : 0, $conversions]);
    return (int) db()->lastInsertId();
}

function find_or_create_ingredient(string $name, string $unit = ''): int
{
    $existing = ingredient_by_name($name);
    return $existing ? (int) $existing['id'] : ingredient_create($name, $unit);
}

/** Move all uses of one ingredient to another and delete the first. */
function ingredient_merge(int $fromId, int $intoId): void
{
    if ($fromId === $intoId) {
        return;
    }
    db_transaction(static function () use ($fromId, $intoId) {
        q('UPDATE recipe_ingredients SET ingredient_id = ? WHERE ingredient_id = ?', [$intoId, $fromId]);
        q('DELETE FROM ingredients WHERE id = ?', [$fromId]);
    });
}

/* ---------- Recipes ---------- */

function seasons_from(string $stored): array
{
    return array_values(array_filter(array_map('trim', explode(',', $stored)), static fn($s) => in_array($s, SEASONS, true)));
}

function recipe_get(int $id): ?array
{
    $r = q('SELECT r.*, c.name AS category FROM recipes r LEFT JOIN categories c ON c.id = r.category_id WHERE r.id = ?', [$id])->fetch();
    if (!$r) {
        return null;
    }
    $r['ingredients'] = q('SELECT ri.*, i.name, i.unit AS base_unit, i.aisle_id, i.pantry, i.conversions
        FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ? ORDER BY ri.sort, ri.id', [$id])->fetchAll();
    $r['seasons_list'] = seasons_from((string) $r['seasons']);
    $r['steps_list'] = array_values(array_filter(array_map('trim', explode("\n", (string) $r['steps'])), 'strlen'));
    return $r;
}

/** Validate normalized recipe data; returns a list of error messages. */
function recipe_validate(array $d): array
{
    $errors = [];
    if (trim((string) ($d['title'] ?? '')) === '') {
        $errors[] = 'Titel fehlt.';
    }
    if ((int) ($d['servings'] ?? 0) < 1) {
        $errors[] = 'Portionen müssen mindestens 1 sein.';
    }
    if (empty($d['category_id'])) {
        $errors[] = 'Grundnahrungsmittel fehlt.';
    }
    if (empty($d['ingredients'])) {
        $errors[] = 'Mindestens eine Zutat ist nötig.';
    }
    return $errors;
}

/**
 * Insert or update a recipe.
 * $d: title, servings, category_id, effort, seasons[], meal, protein, steps, notes, source,
 *     ingredients[] of [name, quantity, unit, note, optional, ingredient_id?]
 */
function recipe_save(array $d, ?int $id = null): int
{
    return db_transaction(static function () use ($d, $id) {
        $fields = [
            trim($d['title']), max(1, (int) $d['servings']), (int) $d['category_id'],
            isset(EFFORTS[$d['effort'] ?? '']) ? $d['effort'] : 'mittel',
            implode(',', array_intersect(SEASONS, $d['seasons'] ?? [])),
            isset(MEALS[$d['meal'] ?? '']) ? $d['meal'] : 'abend',
            trim((string) ($d['protein'] ?? '')), trim((string) ($d['steps'] ?? '')),
            trim((string) ($d['notes'] ?? '')), trim((string) ($d['source'] ?? '')),
        ];
        if ($id === null) {
            q('INSERT INTO recipes (title, servings, category_id, effort, seasons, meal, protein, steps, notes, source)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $fields);
            $id = (int) db()->lastInsertId();
        } else {
            $fields[] = $id;
            q("UPDATE recipes SET title = ?, servings = ?, category_id = ?, effort = ?, seasons = ?, meal = ?, protein = ?,
               steps = ?, notes = ?, source = ?, updated_at = datetime('now') WHERE id = ?", $fields);
            q('DELETE FROM recipe_ingredients WHERE recipe_id = ?', [$id]);
        }
        foreach (array_values($d['ingredients']) as $i => $ing) {
            $ingredientId = !empty($ing['ingredient_id'])
                ? (int) $ing['ingredient_id']
                : find_or_create_ingredient((string) $ing['name'], (string) ($ing['unit'] ?? ''));
            q('INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, note, optional, sort) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $id, $ingredientId, $ing['quantity'] ?? null, normalize_unit($ing['unit'] ?? ''),
                trim((string) ($ing['note'] ?? '')), !empty($ing['optional']) ? 1 : 0, $i,
            ]);
        }
        return $id;
    });
}

/** Recipe as import/export JSON structure (same schema as the import). */
function recipe_export(array $r): array
{
    $ings = [];
    foreach ($r['ingredients'] as $i) {
        $row = ['name' => $i['name'], 'quantity' => $i['quantity'] !== null ? (float) $i['quantity'] : null, 'unit' => $i['unit']];
        if ($i['note'] !== '') {
            $row['note'] = $i['note'];
        }
        if ($i['optional']) {
            $row['optional'] = true;
        }
        $ings[] = $row;
    }
    return array_filter([
        'title' => $r['title'], 'servings' => (int) $r['servings'], 'category' => $r['category'],
        'effort' => $r['effort'], 'seasons' => $r['seasons_list'], 'meal' => $r['meal'], 'protein' => $r['protein'],
        'ingredients' => $ings, 'steps' => $r['steps_list'], 'notes' => $r['notes'], 'source' => $r['source'],
    ], static fn($v) => $v !== '' && $v !== []);
}

/**
 * Search recipes. Filters: q (title or ingredient), category, effort, season, archived.
 * Text matching happens in PHP so umlauts match case-insensitively.
 */
function recipes_search(array $f): array
{
    $where = ['r.archived = ?'];
    $params = [!empty($f['archived']) ? 1 : 0];
    if (!empty($f['category'])) {
        $where[] = 'r.category_id = ?';
        $params[] = (int) $f['category'];
    }
    if (!empty($f['effort']) && isset(EFFORTS[$f['effort']])) {
        $where[] = 'r.effort = ?';
        $params[] = $f['effort'];
    }
    if (!empty($f['season']) && in_array($f['season'], SEASONS, true)) {
        $where[] = "(r.seasons = '' OR (',' || r.seasons || ',') LIKE ?)";
        $params[] = '%,' . $f['season'] . ',%';
    }
    if (!empty($f['meal']) && $f['meal'] === 'abend') {
        $where[] = "r.meal IN ('abend', 'beides')";
    }
    $rows = q('SELECT r.id, r.title, r.servings, r.category_id, c.name AS category, r.effort, r.seasons, r.protein,
            r.favorite, r.archived,
            (SELECT MAX(pd.day) FROM plan_days pd WHERE pd.recipe_id = r.id AND pd.skip = 0) AS last_day,
            (SELECT GROUP_CONCAT(i.name, \' \') FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.recipe_id = r.id) AS ing_names
        FROM recipes r LEFT JOIN categories c ON c.id = r.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY r.title COLLATE NOCASE', $params)->fetchAll();

    sort_by_name($rows, 'title');
    $needle = mb_strtolower(trim((string) ($f['q'] ?? '')));
    if ($needle === '') {
        return $rows;
    }
    $terms = preg_split('/\s+/u', $needle) ?: [];
    return array_values(array_filter($rows, static function ($r) use ($terms) {
        $hay = mb_strtolower($r['title'] . ' ' . $r['ing_names']);
        foreach ($terms as $t) {
            if (!str_contains($hay, $t)) {
                return false;
            }
        }
        return true;
    }));
}

/* ---------- Plans ---------- */

/** Monday (Y-m-d) of the week containing $date. */
function monday_of(?string $date = null): string
{
    try {
        $d = new DateTimeImmutable($date !== null && $date !== '' ? $date : 'today');
    } catch (Exception) {
        $d = new DateTimeImmutable('today');
    }
    return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
}

function plan_find(string $weekStart): ?array
{
    $p = q('SELECT * FROM plans WHERE week_start = ?', [$weekStart])->fetch();
    return $p ?: null;
}

function plan_by_id(int $id): ?array
{
    $p = q('SELECT * FROM plans WHERE id = ?', [$id])->fetch();
    return $p ?: null;
}

function plan_get_or_create(string $weekStart): array
{
    $plan = plan_find($weekStart);
    if ($plan) {
        return $plan;
    }
    db_transaction(static function () use ($weekStart) {
        q('INSERT INTO plans (week_start, persons) VALUES (?, ?)', [$weekStart, setting_int('persons', 6)]);
        $planId = (int) db()->lastInsertId();
        $d = new DateTimeImmutable($weekStart);
        for ($i = 0; $i < 7; $i++) {
            q("INSERT INTO plan_days (plan_id, day, meal) VALUES (?, ?, 'abend')", [$planId, $d->modify("+$i days")->format('Y-m-d')]);
        }
    });
    return (array) plan_find($weekStart);
}

/** Days of a plan with recipe info and the effective staple category. */
function plan_days(int $planId): array
{
    return q("SELECT pd.*, r.title AS recipe_title, r.servings AS recipe_servings, r.effort AS recipe_effort,
            COALESCE(r.category_id, NULLIF(pd.free_category_id, '')) AS category_id
        FROM plan_days pd LEFT JOIN recipes r ON r.id = pd.recipe_id
        WHERE pd.plan_id = ? AND pd.meal = 'abend' ORDER BY pd.day", [$planId])->fetchAll();
}

function plan_day(int $dayId): ?array
{
    $d = q('SELECT pd.*, p.week_start, p.persons AS plan_persons, r.title AS recipe_title
        FROM plan_days pd JOIN plans p ON p.id = pd.plan_id LEFT JOIN recipes r ON r.id = pd.recipe_id
        WHERE pd.id = ?', [$dayId])->fetch();
    return $d ?: null;
}

function recent_recipe_ids(string $weekStart, int $weeks): array
{
    if ($weeks <= 0) {
        return [];
    }
    $from = (new DateTimeImmutable($weekStart))->modify('-' . (7 * $weeks) . ' days')->format('Y-m-d');
    return array_map('intval', q('SELECT DISTINCT recipe_id FROM plan_days WHERE recipe_id IS NOT NULL AND skip = 0 AND day >= ? AND day < ?',
        [$from, $weekStart])->fetchAll(PDO::FETCH_COLUMN));
}

/** Staple category of the Sunday before the given week (for the "not two days in a row" rule). */
function previous_day_category(string $weekStart): ?int
{
    $day = (new DateTimeImmutable($weekStart))->modify('-1 day')->format('Y-m-d');
    $c = q("SELECT COALESCE(r.category_id, pd.free_category_id) FROM plan_days pd LEFT JOIN recipes r ON r.id = pd.recipe_id
        WHERE pd.day = ? AND pd.meal = 'abend' AND pd.skip = 0", [$day])->fetchColumn();
    return $c !== false && $c !== null ? (int) $c : null;
}

function plan_slots(array $days, ?int $openDayId = null): array
{
    $slots = [];
    foreach ($days as $d) {
        $isFree = $d['free_title'] !== '';
        $open = !$d['skip'] && !$isFree && ($openDayId === null ? !$d['locked'] : (int) $d['id'] === $openDayId);
        $slots[] = [
            'day' => $d['day'],
            'open' => $open,
            'skip' => (bool) $d['skip'],
            'recipe_id' => $open ? null : ($d['recipe_id'] !== null ? (int) $d['recipe_id'] : null),
            'category_id' => $open ? null : ($d['category_id'] !== null ? (int) $d['category_id'] : null),
        ];
    }
    return $slots;
}

/** Generate the whole week (unlocked days) or re-roll a single day. */
function plan_generate_run(array $plan, ?int $onlyDayId = null): int
{
    $days = plan_days((int) $plan['id']);
    $recipes = [];
    foreach (q("SELECT id, category_id, favorite, seasons FROM recipes WHERE archived = 0 AND meal IN ('abend', 'beides')")->fetchAll() as $r) {
        $r['seasons'] = seasons_from((string) $r['seasons']);
        $recipes[] = $r;
    }
    $used = [];
    if ($onlyDayId !== null) {
        foreach ($days as $d) {
            if ((int) $d['id'] === $onlyDayId && $d['recipe_id'] !== null) {
                $used[] = (int) $d['recipe_id'];
            }
        }
    }
    $result = plan_generate(plan_slots($days, $onlyDayId), $recipes, recent_recipe_ids($plan['week_start'], setting_int('avoid_weeks', 3)), [
        'max_per_category' => setting_int('max_per_category', 2),
        'prev_category' => previous_day_category($plan['week_start']),
        'season' => season_for($plan['week_start']),
        'used' => $used,
    ]);
    $filled = 0;
    foreach ($result as $day => $recipeId) {
        q("UPDATE plan_days SET recipe_id = ? WHERE plan_id = ? AND day = ? AND meal = 'abend'", [$recipeId, $plan['id'], $day]);
        if ($recipeId !== null) {
            $filled++;
        }
    }
    return $filled;
}
