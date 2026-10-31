<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

/**
 * Sum up shopping lines per ingredient.
 *
 * @param array $lines ['key', 'name', 'quantity' => ?float, 'unit', 'base_unit', 'conv' => array,
 *                      'aisle_id' => ?int, 'pantry' => bool, 'source' => string, 'optional' => bool]
 * @return array<string,array> key => ['key', 'name', 'aisle_id', 'pantry', 'amounts' => [unit => qty],
 *                             'unquantified' => bool, 'sources' => string[], 'optional' => bool]
 */
function shopping_aggregate(array $lines): array
{
    $items = [];
    foreach ($lines as $l) {
        $key = $l['key'];
        if (!isset($items[$key])) {
            $items[$key] = [
                'key' => $key, 'name' => $l['name'], 'aisle_id' => $l['aisle_id'] ?? null,
                'pantry' => !empty($l['pantry']), 'amounts' => [], 'unquantified' => false,
                'sources' => [], 'optional' => true,
            ];
        }
        $item = &$items[$key];
        if (empty($l['optional'])) {
            $item['optional'] = false;
        }
        if (!empty($l['source']) && !in_array($l['source'], $item['sources'], true)) {
            $item['sources'][] = $l['source'];
        }
        $q = $l['quantity'] ?? null;
        if ($q === null || $q <= 0) {
            $item['unquantified'] = true;
            unset($item);
            continue;
        }
        $unit = normalize_unit($l['unit'] ?? '');
        $base = normalize_unit($l['base_unit'] ?? '');
        if ($base === '') {
            $base = isset(UNIT_DIMENSIONS[$unit]) ? (UNIT_DIMENSIONS[$unit][0] === 'm' ? 'g' : 'ml') : $unit;
        }
        $converted = convert_qty((float) $q, $unit, $base, $l['conv'] ?? []);
        if ($converted === null) {
            $base = $unit;
            $converted = (float) $q;
        }
        $item['amounts'][$base] = ($item['amounts'][$base] ?? 0.0) + $converted;
        unset($item);
    }
    return $items;
}

function format_amounts(array $amounts, string $mode = 'shop'): string
{
    $parts = [];
    foreach ($amounts as $unit => $q) {
        $parts[] = format_amount((float) $q, (string) $unit, $mode);
    }
    return implode(' + ', $parts);
}

/** Build the shopping list of a plan, grouped by aisle in walking order. */
function shopping_build(array $plan): array
{
    $catalog = ingredients_all();
    $nameIdx = ingredient_name_index($catalog);
    $lines = [];
    $menu = [];
    $recipes = [];

    foreach (plan_days((int) $plan['id']) as $d) {
        if ($d['skip']) {
            $menu[] = ['day' => $d['day'], 'title' => 'auswärts / frei', 'skip' => true];
            continue;
        }
        $persons = (int) ($d['persons'] ?: $plan['persons']);
        if ($d['recipe_id'] !== null) {
            $rid = (int) $d['recipe_id'];
            $recipes[$rid] ??= recipe_get($rid);
            $r = $recipes[$rid];
            if (!$r) {
                continue;
            }
            $menu[] = ['day' => $d['day'], 'title' => $r['title'], 'skip' => false];
            $factor = $persons / max(1, (int) $r['servings']);
            foreach ($r['ingredients'] as $i) {
                $lines[] = [
                    'key' => 'i' . $i['ingredient_id'], 'name' => $i['name'],
                    'quantity' => $i['quantity'] !== null ? (float) $i['quantity'] * $factor : null,
                    'unit' => $i['unit'], 'base_unit' => $i['base_unit'], 'conv' => parse_conversions((string) $i['conversions']),
                    'aisle_id' => $i['aisle_id'] !== null ? (int) $i['aisle_id'] : null, 'pantry' => (bool) $i['pantry'],
                    'source' => $r['title'], 'optional' => (bool) $i['optional'],
                ];
            }
        } elseif ($d['free_title'] !== '') {
            $menu[] = ['day' => $d['day'], 'title' => $d['free_title'], 'skip' => false];
            foreach (explode("\n", (string) $d['free_items']) as $raw) {
                $p = parse_item_line($raw);
                if (!$p) {
                    continue;
                }
                $lines[] = shopping_line_for_name($p, $catalog, $nameIdx, $d['free_title']);
            }
        } else {
            $menu[] = ['day' => $d['day'], 'title' => '', 'skip' => false];
        }
    }

    $items = shopping_aggregate($lines);
    $hidden = array_fill_keys(q('SELECT item_key FROM shop_hidden WHERE plan_id = ?', [$plan['id']])->fetchAll(PDO::FETCH_COLUMN), true);

    foreach (q('SELECT * FROM shop_extras WHERE plan_id = ? ORDER BY id', [$plan['id']])->fetchAll() as $x) {
        $key = 'x' . $x['id'];
        $items[$key] = [
            'key' => $key, 'name' => $x['name'], 'aisle_id' => $x['aisle_id'] !== null ? (int) $x['aisle_id'] : null,
            'pantry' => false, 'amounts' => $x['quantity'] !== null ? [$x['unit'] => (float) $x['quantity']] : [],
            'unquantified' => $x['quantity'] === null, 'sources' => [], 'optional' => false,
            'extra' => $x,
        ];
    }

    $aisles = aisles();
    $sections = [];
    foreach ($aisles as $id => $a) {
        $sections[$id] = ['aisle_id' => $id, 'aisle' => $a['name'], 'items' => []];
    }
    $sections[0] = ['aisle_id' => null, 'aisle' => 'Ohne Rubrik', 'items' => []];
    $pantry = [];
    foreach ($items as $item) {
        $item['amount'] = format_amounts($item['amounts']);
        $item['hidden'] = isset($hidden[$item['key']]);
        if ($item['pantry']) {
            $pantry[] = $item;
            continue;
        }
        $sid = $item['aisle_id'] !== null && isset($sections[$item['aisle_id']]) ? $item['aisle_id'] : 0;
        $sections[$sid]['items'][] = $item;
    }
    foreach ($sections as &$s) {
        sort_by_name($s['items'], 'name');
    }
    unset($s);
    sort_by_name($pantry, 'name');

    return [
        'sections' => array_values(array_filter($sections, static fn($s) => $s['items'] !== [])),
        'pantry' => $pantry,
        'menu' => $menu,
    ];
}

/** Shopping line for a free text item, matched against the catalog where possible. */
function shopping_line_for_name(array $parsed, array $catalog, array $nameIdx, string $source): array
{
    $id = $nameIdx[mb_strtolower($parsed['name'])] ?? null;
    $c = $id !== null ? $catalog[$id] : null;
    return [
        'key' => $c ? 'i' . $id : 'n' . mb_strtolower($parsed['name']),
        'name' => $c ? $c['name'] : $parsed['name'],
        'quantity' => $parsed['quantity'], 'unit' => $parsed['unit'],
        'base_unit' => $c ? $c['unit'] : '', 'conv' => $c ? $c['conv'] : [],
        'aisle_id' => $c && $c['aisle_id'] !== null ? (int) $c['aisle_id'] : null,
        'pantry' => $c ? (bool) $c['pantry'] : false, 'source' => $source, 'optional' => false,
    ];
}

/** Render the shopping list as an A4 PDF. */
function shopping_pdf(array $plan, array $list): string
{
    $pdf = new SimplePdf();
    $pdf->setTitle('Einkaufsliste KW ' . week_number($plan['week_start']));
    $m = 36.0;
    $gap = 22.0;
    $colW = (SimplePdf::W - 2 * $m - $gap) / 2;
    $bottom = SimplePdf::H - 42;
    $accent = [0.71, 0.33, 0.18];
    $grey = [0.42, 0.38, 0.35];

    $footer = static function (SimplePdf $pdf) use ($m, $grey) {
        $pdf->text($m, SimplePdf::H - 22, 'Culina v' . CULINA_VERSION . ' · erstellt am ' . date('j.n.Y'), 7, false, $grey);
    };

    $pdf->addPage();
    $footer($pdf);
    $pdf->text($m, 52, 'Einkaufsliste', 20, true);
    $pdf->text($m, 68, 'KW ' . week_number($plan['week_start']) . ' · ' . week_label($plan['week_start']) . ' · ' . (int) $plan['persons'] . ' Personen', 9.5, false, $grey);

    // Menu overview in two columns
    $y = 88.0;
    $rows = array_chunk($list['menu'], 4);
    foreach ($rows as $col => $chunk) {
        $yy = $y;
        foreach ($chunk as $entry) {
            $x = $m + $col * ($colW + $gap);
            $pdf->text($x, $yy, dow($entry['day']), 8.5, true, $grey);
            $title = $entry['title'] !== '' ? $entry['title'] : '–';
            $lines = $pdf->wrap($title, $colW - 22, 8.5);
            $pdf->text($x + 20, $yy, $lines[0] . (count($lines) > 1 ? ' …' : ''), 8.5, false, $entry['skip'] ? $grey : [0, 0, 0]);
            $yy += 12;
        }
    }
    $y += 4 * 12 + 4;
    $pdf->line($m, $y, SimplePdf::W - $m, $y, 0.5, 0.8);
    $top = $y + 20;

    $col = 0;
    $y = $top;
    $nextColumn = static function () use (&$col, &$y, $top, $pdf, $footer) {
        $col++;
        $y = $top;
        if ($col > 1) {
            $col = 0;
            $pdf->addPage();
            $footer($pdf);
            $y = 50.0;
        }
    };

    $size = 9.5;
    $lh = 13.0;
    $blocks = [];
    foreach ($list['sections'] as $s) {
        $blocks[] = [$s['aisle'], $s['items']];
    }
    if ($list['pantry']) {
        $blocks[] = ['Vorrat prüfen', $list['pantry']];
    }

    foreach ($blocks as [$heading, $items]) {
        $items = array_values(array_filter($items, static fn($i) => !$i['hidden']));
        if (!$items) {
            continue;
        }
        if ($y + 16 + $lh > $bottom) {
            $nextColumn();
        }
        $x = $m + $col * ($colW + $gap);
        $pdf->text($x, $y, mb_strtoupper($heading), 8, true, $accent);
        $y += 14;
        foreach ($items as $item) {
            $amount = $item['amount'];
            $name = $item['name'] . ($item['optional'] ? ' (optional)' : '');
            $aw = $amount !== '' ? SimplePdf::width($amount . ' ', $size, true) : 0;
            $lines = $pdf->wrap($name, $colW - 14 - $aw, $size);
            $need = $lh * count($lines);
            if ($y + $need > $bottom) {
                $nextColumn();
                $x = $m + $col * ($colW + $gap);
            }
            $pdf->rect($x, $y - 7.5, 8, 8, 0.7);
            if ($amount !== '') {
                $pdf->text($x + 14, $y, $amount, $size, true);
            }
            foreach ($lines as $k => $ln) {
                $pdf->text($x + 14 + $aw, $y, $ln, $size);
                $y += $lh;
            }
        }
        $y += 8;
    }

    return $pdf->output();
}
