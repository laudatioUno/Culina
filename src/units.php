<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

/** Spelling variants → canonical unit. Matching is case-insensitive. */
const UNIT_ALIASES = [
    'g' => 'g', 'gr' => 'g', 'gr.' => 'g', 'gramm' => 'g',
    'kg' => 'kg', 'kilo' => 'kg', 'mg' => 'mg',
    'ml' => 'ml', 'cl' => 'cl', 'dl' => 'dl', 'l' => 'l', 'lt' => 'l', 'liter' => 'l',
    'el' => 'EL', 'el.' => 'EL', 'essl' => 'EL', 'essl.' => 'EL', 'esslöffel' => 'EL',
    'tl' => 'TL', 'tl.' => 'TL', 'teel' => 'TL', 'teel.' => 'TL', 'teelöffel' => 'TL',
    'stk' => 'Stk', 'stk.' => 'Stk', 'st' => 'Stk', 'st.' => 'Stk', 'stück' => 'Stk', 'stueck' => 'Stk',
    'prise' => 'Prise', 'prisen' => 'Prise', 'msp' => 'Msp', 'msp.' => 'Msp',
    'bund' => 'Bund', 'pkg' => 'Pkg', 'pkg.' => 'Pkg', 'pack' => 'Pkg', 'packung' => 'Pkg', 'packungen' => 'Pkg', 'päckchen' => 'Pkg',
    'dose' => 'Dose', 'dosen' => 'Dose', 'zehe' => 'Zehe', 'zehen' => 'Zehe',
    'scheibe' => 'Scheibe', 'scheiben' => 'Scheibe', 'becher' => 'Becher', 'glas' => 'Glas', 'gläser' => 'Glas',
    'zweig' => 'Zweig', 'zweige' => 'Zweig', 'blatt' => 'Blatt', 'blätter' => 'Blatt', 'tasse' => 'Tasse', 'tassen' => 'Tasse',
];

/** Units that convert among each other: [dimension, factor to g resp. ml]. */
const UNIT_DIMENSIONS = [
    'mg' => ['m', 0.001], 'g' => ['m', 1.0], 'kg' => ['m', 1000.0],
    'ml' => ['v', 1.0], 'cl' => ['v', 10.0], 'dl' => ['v', 100.0], 'l' => ['v', 1000.0],
    'EL' => ['v', 15.0], 'TL' => ['v', 5.0],
];

/** Units that are bought in whole pieces; '' means "no unit" (e.g. 3 Eier). */
const COUNT_UNITS = ['', 'Stk', 'Bund', 'Pkg', 'Dose', 'Zehe', 'Scheibe', 'Becher', 'Glas', 'Zweig', 'Blatt'];

/** Plural forms used when displaying more than one piece. */
const UNIT_PLURALS = ['Dose' => 'Dosen', 'Zehe' => 'Zehen', 'Scheibe' => 'Scheiben', 'Glas' => 'Gläser', 'Zweig' => 'Zweige', 'Blatt' => 'Blätter', 'Tasse' => 'Tassen', 'Prise' => 'Prisen'];

/** Canonical units offered in forms. */
function unit_list(): array
{
    return ['g', 'kg', 'ml', 'dl', 'l', 'EL', 'TL', 'Stk', 'Prise', 'Bund', 'Pkg', 'Dose', 'Zehe', 'Scheibe', 'Becher', 'Glas', 'Zweig', 'Blatt', 'Msp', 'Tasse'];
}

function normalize_unit(?string $unit): string
{
    $u = trim((string) $unit);
    if ($u === '') {
        return '';
    }
    return UNIT_ALIASES[mb_strtolower($u)] ?? $u;
}

function is_known_unit(string $token): bool
{
    return isset(UNIT_ALIASES[mb_strtolower(trim($token))]);
}

/** Parse "1,5", "1/2", "1 1/2", "½", "2-3" (takes the upper bound) into a float; null if empty/invalid. */
function parse_quantity(mixed $v): ?float
{
    if ($v === null || is_bool($v)) {
        return null;
    }
    if (is_int($v) || is_float($v)) {
        return $v > 0 ? (float) $v : null;
    }
    $s = str_replace(['½', '¼', '¾', '⅓', '⅔'], [' 1/2', ' 1/4', ' 3/4', ' 1/3', ' 2/3'], (string) $v);
    $s = trim(str_replace(',', '.', $s));
    if ($s === '') {
        return null;
    }
    if (preg_match('/^(\d+(?:\.\d+)?)\s*[-–]\s*(\d+(?:\.\d+)?)$/u', $s, $m)) {
        return (float) $m[2];
    }
    if (preg_match('/^(?:(\d+)\s+)?(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
        if ((int) $m[3] === 0) {
            return null;
        }
        return (float) ($m[1] !== '' ? $m[1] : 0) + (int) $m[2] / (int) $m[3];
    }
    if (is_numeric($s) && (float) $s > 0) {
        return (float) $s;
    }
    return null;
}

/** "Stk=100, Bund=50" → ['Stk' => 100.0, 'Bund' => 50.0] (amount of base unit per 1 unit). */
function parse_conversions(string $s): array
{
    $out = [];
    foreach (preg_split('/[;,\n]+/', $s) ?: [] as $part) {
        if (preg_match('/^\s*1?\s*([^=\s]+)\s*=\s*([\d.,]+)/u', $part, $m)) {
            $f = (float) str_replace(',', '.', $m[2]);
            if ($f > 0) {
                $out[normalize_unit($m[1])] = $f;
            }
        }
    }
    return $out;
}

function format_conversions(array $conv): string
{
    $parts = [];
    foreach ($conv as $u => $f) {
        $parts[] = $u . '=' . format_number((float) $f, 3);
    }
    return implode(', ', $parts);
}

/**
 * Convert a quantity into the ingredient's base unit.
 * Uses g/ml families first, then ingredient-specific conversions ("1 Stk = 150 g").
 */
function convert_qty(float $q, string $from, string $to, array $conv = []): ?float
{
    // "2 Zwiebeln" and "2 Stk Zwiebeln" mean the same.
    if ($from === $to || ($from === '' && $to === 'Stk') || ($from === 'Stk' && $to === '')) {
        return $q;
    }
    $df = UNIT_DIMENSIONS[$from] ?? null;
    $dt = UNIT_DIMENSIONS[$to] ?? null;
    if ($df && $dt && $df[0] === $dt[0]) {
        return $q * $df[1] / $dt[1];
    }
    if (isset($conv[$from])) {
        return $q * $conv[$from];
    }
    if ($df) {
        foreach ($conv as $cu => $factor) {
            $dc = UNIT_DIMENSIONS[$cu] ?? null;
            if ($dc && $dc[0] === $df[0]) {
                return $q * $df[1] / $dc[1] * $factor;
            }
        }
    }
    return null;
}

/** German number formatting without trailing zeros: 1.50 → "1,5". */
function format_number(float $n, int $decimals = 1): string
{
    $s = number_format($n, $decimals, ',', '');
    if (str_contains($s, ',')) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s === '-0' ? '0' : $s;
}

function format_fraction(float $q): string
{
    $whole = (int) floor($q + 1e-9);
    $rest = $q - $whole;
    if (abs($rest - 0.5) < 0.01) {
        return ($whole > 0 ? (string) $whole : '') . '½';
    }
    return format_number($q, 1);
}

/**
 * Format an amount for display.
 * mode "shop": round up whole pieces, convert to kg/dl/l where useful.
 * mode "cook": keep halves, round grams to sensible steps.
 */
function format_amount(float $q, string $unit, string $mode = 'shop'): string
{
    if (isset(UNIT_DIMENSIONS[$unit]) && UNIT_DIMENSIONS[$unit][0] === 'm') {
        $g = $q * UNIT_DIMENSIONS[$unit][1];
        if ($g >= 1000) {
            return format_number($g / 1000, 2) . ' kg';
        }
        if ($g < 10) {
            return format_number($g, 1) . ' g';
        }
        $step = ($mode === 'cook' && $g >= 50) ? 5 : 1;
        return format_number(round($g / $step) * $step, 0) . ' g';
    }
    if (in_array($unit, ['ml', 'cl', 'dl', 'l'], true)) {
        $ml = $q * UNIT_DIMENSIONS[$unit][1];
        if ($ml >= 1000) {
            return format_number($ml / 1000, 2) . ' l';
        }
        if ($ml >= 100) {
            return format_number($ml / 100, 1) . ' dl';
        }
        return format_number(round($ml), 0) . ' ml';
    }
    if (in_array($unit, COUNT_UNITS, true)) {
        $v = $mode === 'shop' ? ceil($q - 1e-9) : max(0.5, round($q * 2) / 2);
        $n = $mode === 'shop' ? (string) (int) $v : format_fraction($v);
        return $unit === '' ? $n : $n . ' ' . ($v > 1 ? (UNIT_PLURALS[$unit] ?? $unit) : $unit);
    }
    if (in_array($unit, ['EL', 'TL', 'Prise', 'Msp', 'Tasse'], true)) {
        $v = max(0.5, round($q * 2) / 2);
        return format_fraction($v) . ' ' . ($v > 1 ? (UNIT_PLURALS[$unit] ?? $unit) : $unit);
    }
    return trim(format_number($q, 1) . ' ' . $unit);
}

/** Parse a free text line like "200 g Hackfleisch", "2 Eier" or "Salz". */
function parse_item_line(string $line): ?array
{
    $line = trim(preg_replace('/^[-*•]\s*/u', '', trim($line)) ?? '');
    if ($line === '') {
        return null;
    }
    $line = str_replace(['½', '¼', '¾'], [' 1/2', ' 1/4', ' 3/4'], $line);
    $line = trim((string) preg_replace('/\s+/u', ' ', $line));
    $qty = null;
    $rest = $line;
    if (preg_match('/^(\d+(?:[.,]\d+)?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?(?:\s+\d+\/\d+|\/\d+)?)\s*(.*)$/u', $line, $m)) {
        $qty = parse_quantity($m[1]);
        $rest = $m[2];
    }
    $unit = '';
    $name = $rest;
    $parts = preg_split('/\s+/u', $rest, 2);
    if ($parts && count($parts) === 2 && is_known_unit($parts[0])) {
        $unit = normalize_unit($parts[0]);
        $name = $parts[1];
    }
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    return ['quantity' => $qty, 'unit' => $unit, 'name' => $name];
}
