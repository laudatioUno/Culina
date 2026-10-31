<?php
declare(strict_types=1);

// Run: php tests/run.php
if (PHP_SAPI !== 'cli') {
    exit;
}

$tmp = sys_get_temp_dir() . '/culina-test-' . getmypid();
putenv('CULINA_DATA_DIR=' . $tmp);
require dirname(__DIR__) . '/src/bootstrap.php';

$failed = 0;
$passed = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: $name" . ($detail !== '' ? " ($detail)" : '') . "\n");
}
function eq(string $name, mixed $expected, mixed $actual): void
{
    check($name, $expected === $actual, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

/* ---------- units ---------- */
eq('qty int', 2.0, parse_quantity('2'));
eq('qty comma', 1.5, parse_quantity('1,5'));
eq('qty fraction', 0.5, parse_quantity('1/2'));
eq('qty mixed', 1.5, parse_quantity('1 1/2'));
eq('qty unicode', 1.5, parse_quantity('1½'));
eq('qty range', 3.0, parse_quantity('2-3'));
eq('qty empty', null, parse_quantity(''));
eq('qty text', null, parse_quantity('etwas'));
eq('unit alias', 'EL', normalize_unit('Essl.'));
eq('unit stück', 'Stk', normalize_unit('Stück'));
eq('convert kg', 1500.0, convert_qty(1.5, 'kg', 'g'));
eq('convert dl', 200.0, convert_qty(2, 'dl', 'ml'));
eq('convert EL to ml', 30.0, convert_qty(2, 'EL', 'ml'));
eq('convert by ingredient', 300.0, convert_qty(2, 'Stk', 'g', ['Stk' => 150.0]));
eq('convert none', null, convert_qty(2, 'Bund', 'g'));
eq('convert stk/blank', 2.0, convert_qty(2, '', 'Stk'));
eq('format kg', '1,25 kg', format_amount(1250, 'g'));
eq('format dl', '2,5 dl', format_amount(250, 'ml'));
eq('format l', '1,5 l', format_amount(15, 'dl'));
eq('format ceil pieces', '3 Stk', format_amount(2.1, 'Stk'));
eq('format plural', '2 Dosen', format_amount(1.5, 'Dose'));
eq('format cook half', '1½ Stk', format_amount(1.4, 'Stk', 'cook'));
eq('format EL', '1½ EL', format_amount(1.5, 'EL'));
eq('item line', ['quantity' => 200.0, 'unit' => 'g', 'name' => 'Hackfleisch'], parse_item_line('200 g Hackfleisch'));
eq('item line no space', ['quantity' => 200.0, 'unit' => 'g', 'name' => 'Hackfleisch'], parse_item_line('200g Hackfleisch'));
eq('item line count', ['quantity' => 2.0, 'unit' => '', 'name' => 'Eier'], parse_item_line('2 Eier'));
eq('item line plain', ['quantity' => null, 'unit' => '', 'name' => 'WC-Papier'], parse_item_line('- WC-Papier'));
eq('item line litre', ['quantity' => 2.0, 'unit' => 'l', 'name' => 'Milch'], parse_item_line('2 l Milch'));
eq('conversions parse', ['Stk' => 150.0, 'EL' => 15.0], parse_conversions('Stk=150, 1 EL = 15'));

/* ---------- planner ---------- */
$recipes = [];
foreach ([1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 2, 6 => 3, 7 => 3, 8 => 4, 9 => 5, 10 => 6] as $id => $cat) {
    $recipes[] = ['id' => $id, 'category_id' => $cat, 'favorite' => 0, 'seasons' => []];
}
$week = static function (array $fixed = []): array {
    $slots = [];
    $d = new DateTimeImmutable('2026-11-02');
    for ($i = 0; $i < 7; $i++) {
        $slots[] = ['day' => $d->modify("+$i days")->format('Y-m-d'), 'open' => !isset($fixed[$i]), 'skip' => false]
            + ($fixed[$i] ?? []);
    }
    return $slots;
};
$byId = array_column($recipes, 'category_id', 'id');
for ($run = 0; $run < 200; $run++) {
    $res = plan_generate($week(), $recipes, [], ['max_per_category' => 2]);
    $ids = array_values($res);
    $cats = array_map(static fn($id) => $byId[$id], $ids);
    check('plan fills all days', count(array_filter($ids)) === 7);
    check('plan unique recipes', count(array_unique($ids)) === 7);
    $counts = array_count_values($cats);
    check('plan max per category', max($counts) <= 2, json_encode($counts));
    for ($i = 1; $i < 7; $i++) {
        check('plan no consecutive category', $cats[$i] !== $cats[$i - 1], json_encode($cats));
    }
}
// recent recipes avoided while enough alternatives exist
$res = plan_generate($week(), $recipes, [1, 2], ['max_per_category' => 2], static fn() => 0.0);
check('plan avoids recent', !in_array(1, $res, true) && !in_array(2, $res, true), json_encode($res));
// fixed days are respected and count for the rules
$res = plan_generate($week([0 => ['open' => false, 'recipe_id' => 1, 'category_id' => 1]]), $recipes, [], ['max_per_category' => 2]);
check('plan keeps fixed day out of result', !isset($res['2026-11-02']));
check('plan no consecutive after fixed', $byId[$res['2026-11-03']] !== 1);
check('plan fixed recipe not repeated', !in_array(1, $res, true));
// previous week's Sunday counts for Monday
$res = plan_generate($week(), $recipes, [], ['prev_category' => 1]);
check('plan respects previous sunday', $byId[$res['2026-11-02']] !== 1);
// rules relax when too few recipes
$res = plan_generate($week(), array_slice($recipes, 0, 2), [], ['max_per_category' => 1]);
check('plan relaxes rules', count(array_filter($res)) === 7);
// no recipes at all
$res = plan_generate($week(), [], []);
check('plan empty', array_filter($res) === []);
// warnings
$w = plan_warnings([['category_id' => 1], ['category_id' => 1], ['category_id' => 1]], 2, [1 => 'Teigwaren']);
check('warnings consecutive', in_array('Gleiche Kategorie wie am Vortag', $w[1] ?? [], true));
check('warnings max', isset($w[0]) && str_contains($w[0][0], '3×'));

/* ---------- shopping aggregation ---------- */
$items = shopping_aggregate([
    ['key' => 'i1', 'name' => 'Kartoffeln', 'quantity' => 600.0, 'unit' => 'g', 'base_unit' => 'g', 'conv' => ['Stk' => 150.0], 'source' => 'A'],
    ['key' => 'i1', 'name' => 'Kartoffeln', 'quantity' => 1.0, 'unit' => 'kg', 'base_unit' => 'g', 'conv' => ['Stk' => 150.0], 'source' => 'B'],
    ['key' => 'i1', 'name' => 'Kartoffeln', 'quantity' => 2.0, 'unit' => 'Stk', 'base_unit' => 'g', 'conv' => ['Stk' => 150.0], 'source' => 'B'],
    ['key' => 'i2', 'name' => 'Petersilie', 'quantity' => 1.0, 'unit' => 'Bund', 'base_unit' => 'g', 'conv' => [], 'source' => 'A'],
    ['key' => 'i2', 'name' => 'Petersilie', 'quantity' => 10.0, 'unit' => 'g', 'base_unit' => 'g', 'conv' => [], 'source' => 'A'],
    ['key' => 'i3', 'name' => 'Salz', 'quantity' => null, 'unit' => '', 'base_unit' => '', 'conv' => [], 'pantry' => true],
    ['key' => 'i4', 'name' => 'Milch', 'quantity' => 2.0, 'unit' => 'dl', 'base_unit' => '', 'conv' => []],
    ['key' => 'i4', 'name' => 'Milch', 'quantity' => 1.0, 'unit' => 'l', 'base_unit' => '', 'conv' => []],
]);
eq('aggregate sum with conversions', ['g' => 1900.0], $items['i1']['amounts']);
eq('aggregate sources', ['A', 'B'], $items['i1']['sources']);
eq('aggregate incompatible units', '1 Bund + 10 g', format_amounts($items['i2']['amounts']));
check('aggregate unquantified', $items['i3']['unquantified'] && $items['i3']['amounts'] === []);
eq('aggregate without base unit', '1,2 l', format_amounts($items['i4']['amounts']));

/* ---------- importer ---------- */
$catMap = ['teigwaren' => 1, 'brot und teig' => 4];
[$n, $errs] = import_normalize([
    'title' => 'Test', 'servings' => '6', 'category' => 'Brot & Teig', 'effort' => 'Schnell', 'seasons' => 'Herbst, Winter',
    'ingredients' => [['name' => 'Mehl', 'quantity' => '1,5', 'unit' => 'kg'], '2 Eier', ['name' => 'Salz', 'quantity' => null]],
    'steps' => "1. Teig machen\n2) Backen",
], $catMap);
eq('import no errors', [], $errs);
eq('import category alias', 4, $n['category_id']);
eq('import effort', 'schnell', $n['effort']);
eq('import seasons string', ['Herbst', 'Winter'], $n['seasons']);
eq('import qty', 1.5, $n['ingredients'][0]['quantity']);
eq('import string ingredient', 'Eier', $n['ingredients'][1]['name']);
eq('import steps numbering removed', "Teig machen\nBacken", $n['steps']);
[, $errs] = import_normalize(['title' => '', 'category' => 'Pizza', 'ingredients' => [['name' => 'X', 'quantity' => 'viel']]], $catMap);
check('import errors', count($errs) === 3, json_encode($errs, JSON_UNESCAPED_UNICODE));
[$list, $errs] = import_decode("```json\n{\"recipes\": [{\"title\": \"A\"}, {\"title\": \"B\"}]}\n```");
check('import decode fences', count($list) === 2 && $errs === []);
[, $errs] = import_decode('{nope');
check('import decode invalid', count($errs) === 1);

/* ---------- database round trip ---------- */
$raw = (string) file_get_contents(__DIR__ . '/fixtures/recipes.json');
$analysis = import_analyze($raw);
check('db analyze recipes', count($analysis['recipes']) === 12);
check('db analyze unknown', isset($analysis['unknown']['kokosmilch']));
$saved = import_commit($raw, range(0, 11), ['kokosmilch' => ['target' => 'new', 'aisle_id' => null, 'pantry' => false, 'unit' => 'Dose']]);
eq('db import commit', 12, $saved);
check('db duplicates detected', import_analyze($raw)['recipes'][0]['duplicate']);
$r = recipe_get(1);
eq('db recipe title', 'Älplermagronen', $r['title']);
eq('db recipe ingredients', 8, count($r['ingredients']));
eq('db recipe export roundtrip', 'Teigwaren', recipe_export($r)['category']);
eq('db search ingredient', ['Älplermagronen'], array_column(recipes_search(['q' => 'apfelmus']), 'title'));
eq('db search umlaut', ['Älplermagronen'], array_column(recipes_search(['q' => 'älpler']), 'title'));

$plan = plan_get_or_create('2026-11-02');
eq('db plan days', 7, count(plan_days((int) $plan['id'])));
eq('db generate', 7, plan_generate_run($plan));
$days = plan_days((int) $plan['id']);
q("UPDATE plan_days SET skip = 1 WHERE id = ?", [$days[6]['id']]);
q("UPDATE plan_days SET recipe_id = NULL, free_title = 'Pizza', free_items = ? WHERE id = ?", ["2 Pkg Pizzateig\n1 l Milch", $days[5]['id']]);
q('INSERT INTO shop_extras (plan_id, name, quantity, unit) VALUES (?, ?, ?, ?)', [$plan['id'], 'WC-Papier', null, '']);
$list = shopping_build($plan);
eq('db shopping menu entries', 7, count($list['menu']));
$names = [];
foreach ($list['sections'] as $s) {
    foreach ($s['items'] as $i) {
        $names[] = $i['name'];
    }
}
check('db shopping free items', in_array('Pizzateig', $names, true) && in_array('WC-Papier', $names, true));
$aisleOrder = array_column($list['sections'], 'aisle');
eq('db shopping unsorted last', 'Ohne Rubrik', end($aisleOrder));

$pdf = shopping_pdf($plan, $list);
check('pdf header', str_starts_with($pdf, '%PDF-1.4'));
check('pdf trailer', str_contains($pdf, 'startxref') && str_ends_with(trim($pdf), '%%EOF'));
preg_match('/startxref\n(\d+)/', $pdf, $m);
check('pdf xref offset', substr($pdf, (int) $m[1], 4) === 'xref');

/* ---------- cleanup ---------- */
foreach (glob($tmp . '/*') ?: [] as $f) {
    is_dir($f) ? @rmdir($f) : @unlink($f);
}
@rmdir($tmp);

echo "$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
