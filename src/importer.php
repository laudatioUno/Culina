<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

/** Decode pasted JSON (single recipe, list, or {"recipes": [...]}); tolerates Markdown code fences. */
function import_decode(string $raw): array
{
    $raw = trim($raw);
    $raw = (string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $raw);
    if ($raw === '') {
        return [[], ['Kein JSON eingefügt.']];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [[], ['Ungültiges JSON: ' . json_last_error_msg()]];
    }
    if (isset($data['recipes']) && is_array($data['recipes'])) {
        $data = $data['recipes'];
    } elseif (!array_is_list($data)) {
        $data = [$data];
    }
    return [array_values(array_filter($data, 'is_array')), []];
}

function import_key(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', str_replace('&', 'und', mb_strtolower($s))));
}

function import_effort(string $s): string
{
    $s = import_key($s);
    return match (true) {
        in_array($s, ['schnell', 'kurz', 'einfach', 'fast', 'quick'], true) => 'schnell',
        in_array($s, ['aufwendig', 'aufwändig', 'lang', 'gross', 'complex'], true) => 'aufwendig',
        default => 'mittel',
    };
}

function import_meal(string $s): string
{
    $s = import_key($s);
    return match (true) {
        in_array($s, ['mittag', 'mittagessen', 'zmittag', 'lunch'], true) => 'mittag',
        in_array($s, ['beides', 'mittag und abend', 'both'], true) => 'beides',
        default => 'abend',
    };
}

function import_seasons(mixed $v): array
{
    $list = is_array($v) ? $v : preg_split('/[,;\/]+/', (string) $v);
    $out = [];
    foreach ($list ?: [] as $s) {
        $s = import_key((string) $s);
        $s = str_replace('fruehling', 'frühling', $s);
        foreach (SEASONS as $season) {
            if ($s === mb_strtolower($season)) {
                $out[] = $season;
            }
        }
    }
    return array_values(array_unique($out));
}

/**
 * Normalize one imported recipe.
 * @param array $catMap lowercase category name => id
 * @return array [normalized recipe, errors[]]
 */
function import_normalize(array $r, array $catMap): array
{
    $errors = [];
    $title = trim((string) ($r['title'] ?? $r['titel'] ?? ''));
    if ($title === '') {
        $errors[] = 'Titel fehlt.';
    }
    $catName = (string) ($r['category'] ?? $r['kategorie'] ?? '');
    $catId = $catMap[import_key($catName)] ?? null;
    if ($catId === null) {
        $errors[] = $catName === '' ? 'Grundnahrungsmittel (category) fehlt.' : 'Unbekanntes Grundnahrungsmittel «' . $catName . '».';
    }
    $ings = [];
    foreach ((array) ($r['ingredients'] ?? $r['zutaten'] ?? []) as $k => $i) {
        if (is_string($i)) {
            $p = parse_item_line($i);
            $i = $p ? ['name' => $p['name'], 'quantity' => $p['quantity'], 'unit' => $p['unit']] : [];
        }
        $name = trim((string) ($i['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Zutat ' . ($k + 1) . ' ohne Namen.';
            continue;
        }
        $qty = $i['quantity'] ?? $i['menge'] ?? null;
        $parsed = parse_quantity($qty);
        if ($qty !== null && $qty !== '' && $parsed === null) {
            $errors[] = 'Menge «' . (is_scalar($qty) ? $qty : '?') . '» bei ' . $name . ' ist ungültig.';
        }
        $ings[] = [
            'name' => $name, 'quantity' => $parsed, 'unit' => normalize_unit((string) ($i['unit'] ?? $i['einheit'] ?? '')),
            'note' => trim((string) ($i['note'] ?? $i['notiz'] ?? '')), 'optional' => !empty($i['optional']),
            'aisle' => trim((string) ($i['aisle'] ?? $i['rubrik'] ?? '')), 'pantry' => !empty($i['pantry'] ?? $i['vorrat'] ?? false),
        ];
    }
    if (!$ings) {
        $errors[] = 'Keine Zutaten.';
    }
    $steps = $r['steps'] ?? $r['schritte'] ?? [];
    $steps = is_array($steps) ? $steps : explode("\n", (string) $steps);
    $steps = array_values(array_filter(array_map(static fn($s) => trim((string) preg_replace('/^\s*\d+[.)]\s*/', '', (string) $s)), $steps), 'strlen'));

    $servings = (int) ($r['servings'] ?? $r['portionen'] ?? 4);
    return [[
        'title' => $title,
        'servings' => $servings > 0 ? $servings : 4,
        'category_id' => $catId,
        'category' => $catName,
        'effort' => import_effort((string) ($r['effort'] ?? $r['aufwand'] ?? 'mittel')),
        'seasons' => import_seasons($r['seasons'] ?? $r['saison'] ?? []),
        'meal' => import_meal((string) ($r['meal'] ?? $r['mahlzeit'] ?? 'abend')),
        'protein' => trim((string) ($r['protein'] ?? '')),
        'ingredients' => $ings,
        'steps' => implode("\n", $steps),
        'notes' => trim((string) ($r['notes'] ?? $r['notizen'] ?? '')),
        'source' => trim((string) ($r['source'] ?? $r['quelle'] ?? '')),
    ], $errors];
}

/** Analyse pasted JSON for the preview: validation, duplicates, unknown ingredients. */
function import_analyze(string $raw): array
{
    [$list, $errors] = import_decode($raw);
    $catMap = [];
    foreach (categories() as $id => $c) {
        $catMap[import_key($c['name'])] = $id;
    }
    $aisleMap = [];
    foreach (aisles() as $id => $a) {
        $aisleMap[import_key($a['name'])] = $id;
    }
    $catalogIdx = ingredient_name_index(ingredients_all());
    $titles = array_map('mb_strtolower', q('SELECT title FROM recipes')->fetchAll(PDO::FETCH_COLUMN));

    $recipes = [];
    $unknown = [];
    foreach ($list as $raw) {
        [$norm, $errs] = import_normalize($raw, $catMap);
        foreach ($norm['ingredients'] as $i) {
            $k = mb_strtolower($i['name']);
            if (!isset($catalogIdx[$k]) && !isset($unknown[$k])) {
                $unknown[$k] = [
                    'name' => $i['name'], 'unit' => $i['unit'],
                    'aisle_id' => $aisleMap[import_key($i['aisle'])] ?? null, 'pantry' => $i['pantry'],
                ];
            }
        }
        $recipes[] = ['data' => $norm, 'errors' => $errs, 'duplicate' => in_array(mb_strtolower($norm['title']), $titles, true)];
    }
    ksort($unknown);
    return ['recipes' => $recipes, 'unknown' => $unknown, 'errors' => $errors];
}

/**
 * Save the recipes selected in the preview.
 * @param int[] $include indexes of recipes to import
 * @param array $mapping lowercase name => ['target' => 'new'|ingredient id, 'aisle_id', 'pantry', 'unit']
 */
function import_commit(string $raw, array $include, array $mapping): int
{
    $analysis = import_analyze($raw);
    $catalogIdx = ingredient_name_index(ingredients_all());
    return db_transaction(static function () use ($analysis, $include, $mapping, &$catalogIdx) {
        $saved = 0;
        foreach ($analysis['recipes'] as $idx => $r) {
            if ($r['errors'] || !in_array($idx, $include, true)) {
                continue;
            }
            $d = $r['data'];
            foreach ($d['ingredients'] as &$ing) {
                $k = mb_strtolower($ing['name']);
                if (!isset($catalogIdx[$k])) {
                    $m = $mapping[$k] ?? ['target' => 'new'];
                    if (($m['target'] ?? 'new') !== 'new' && is_numeric($m['target'])) {
                        $catalogIdx[$k] = (int) $m['target'];
                    } else {
                        $catalogIdx[$k] = ingredient_create(
                            $ing['name'], (string) ($m['unit'] ?? $ing['unit']),
                            !empty($m['aisle_id']) ? (int) $m['aisle_id'] : null, !empty($m['pantry'])
                        );
                    }
                }
                $ing['ingredient_id'] = $catalogIdx[$k];
            }
            unset($ing);
            recipe_save($d);
            $saved++;
        }
        return $saved;
    });
}

/** Prompt for a Claude chat that turns any recipe into Culina's import JSON. */
function import_prompt(array $categoryNames, array $aisleNames): string
{
    $cats = implode(', ', $categoryNames);
    $aisles = implode(', ', $aisleNames);
    return <<<TXT
Bereite das folgende Rezept für meinen Menüplaner «Culina» auf. Antworte nur mit einem JSON-Codeblock nach diesem Schema:

{
  "title": "Älplermagronen",
  "servings": 4,
  "category": "Teigwaren",
  "effort": "mittel",
  "seasons": ["Herbst", "Winter"],
  "meal": "abend",
  "protein": "Vegetarisch",
  "ingredients": [
    {"name": "Hörnli", "quantity": 300, "unit": "g", "aisle": "Teigwaren, Reis, Konserven"},
    {"name": "Kartoffeln", "quantity": 400, "unit": "g", "note": "geschält, gewürfelt", "aisle": "Früchte und Gemüse"},
    {"name": "Salz", "quantity": null, "unit": "", "pantry": true}
  ],
  "steps": ["Kartoffeln in Salzwasser 5 Minuten vorkochen.", "Hörnli dazugeben und al dente kochen."],
  "notes": "Mit Apfelmus servieren.",
  "source": "Familienrezept"
}

Regeln:
- category: genau eine Hauptkategorie aus: {$cats}
- effort: schnell (unter 30 Minuten), mittel oder aufwendig
- seasons: Liste aus Frühling, Sommer, Herbst, Winter; leere Liste = ganzjährig
- meal: abend, mittag oder beides
- protein: Fleisch, Geflügel, Fisch, Vegetarisch oder Vegan
- quantity: Zahl für die angegebenen servings, null bei «nach Belieben»
- unit: g, kg, ml, dl, l, EL, TL, Stk, Prise, Bund, Pkg, Dose, Zehe, Scheibe, Becher oder leer
- name: Zutat in der Einzahl oder Grundform, ohne Mengen und ohne Zubereitung (die gehört in note), z. B. «Zwiebel» statt «2 gehackte Zwiebeln»
- aisle (optional): Ladenrubrik aus: {$aisles}
- pantry: true für Vorratsartikel wie Salz, Pfeffer, Öl, Gewürze
- steps: ein kurzer Schritt pro Eintrag, Schweizer Hochdeutsch (ss statt ß)
- Mehrere Rezepte als {"recipes": [ … ]}

Rezept:

TXT;
}
