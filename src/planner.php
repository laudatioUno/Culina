<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

const SEASONS = ['Frühling', 'Sommer', 'Herbst', 'Winter'];

function season_for(string $date): string
{
    $m = (int) (new DateTimeImmutable($date))->format('n');
    return match (true) {
        $m >= 3 && $m <= 5 => 'Frühling',
        $m >= 6 && $m <= 8 => 'Sommer',
        $m >= 9 && $m <= 11 => 'Herbst',
        default => 'Winter',
    };
}

/** Selection weight: favourites and in-season recipes come up more often, frequent categories less. */
function recipe_weight(array $recipe, int $categoryCount, ?string $season): float
{
    $w = 1.0;
    if (!empty($recipe['favorite'])) {
        $w *= 2.0;
    }
    $seasons = $recipe['seasons'] ?? [];
    if ($season !== null && $seasons) {
        $w *= in_array($season, $seasons, true) ? 1.5 : 0.3;
    }
    return $w / (1 + $categoryCount);
}

/** @param array<int,float> $weights id => weight */
function weighted_pick(array $weights, callable $rand): ?int
{
    $total = array_sum($weights);
    if ($total <= 0) {
        return null;
    }
    $r = $rand() * $total;
    $acc = 0.0;
    $last = null;
    foreach ($weights as $id => $w) {
        $acc += $w;
        $last = (int) $id;
        if ($r < $acc) {
            return (int) $id;
        }
    }
    return $last;
}

/**
 * Fill the open slots of a week with recipes.
 *
 * Rules, relaxed step by step when no recipe fits:
 *  1. a recipe appears only once per week
 *  2. recipes planned in the recent weeks are avoided
 *  3. a staple category appears at most max_per_category times
 *  4. the same category never on two consecutive days
 *
 * @param array $slots   in day order: ['day' => 'Y-m-d', 'open' => bool, 'skip' => bool,
 *                       'recipe_id' => ?int, 'category_id' => ?int] (fixed slots carry their recipe/category)
 * @param array $recipes ['id' => int, 'category_id' => ?int, 'favorite' => bool, 'seasons' => string[]]
 * @param int[] $recent  recipe ids planned in the recent weeks
 * @param array $opts    max_per_category, prev_category (last category of the previous week),
 *                       season, used (ids to treat as already used, e.g. when re-rolling)
 * @return array<string,?int> day => recipe id (null when no recipe is available)
 */
function plan_generate(array $slots, array $recipes, array $recent, array $opts = [], ?callable $rand = null): array
{
    $rand ??= static fn(): float => mt_rand() / (mt_getrandmax() + 1);
    $max = max(1, (int) ($opts['max_per_category'] ?? 2));
    $season = $opts['season'] ?? null;

    $byId = [];
    foreach ($recipes as $r) {
        $r['category_id'] = isset($r['category_id']) && $r['category_id'] !== '' ? (int) $r['category_id'] : null;
        $byId[(int) $r['id']] = $r;
    }
    ksort($byId);
    $recentSet = array_fill_keys(array_map('intval', $recent), true);
    $used = array_fill_keys(array_map('intval', $opts['used'] ?? []), true);
    $count = [];
    $cats = [];

    foreach (array_values($slots) as $i => $s) {
        $cats[$i] = null;
        if (!empty($s['open']) || !empty($s['skip'])) {
            continue;
        }
        if (!empty($s['recipe_id'])) {
            $used[(int) $s['recipe_id']] = true;
        }
        if (isset($s['category_id']) && $s['category_id'] !== null) {
            $c = (int) $s['category_id'];
            $cats[$i] = $c;
            $count[$c] = ($count[$c] ?? 0) + 1;
        }
    }

    $tiers = [
        ['used' => true, 'recent' => true, 'max' => true, 'adjacent' => true],
        ['used' => true, 'recent' => false, 'max' => true, 'adjacent' => true],
        ['used' => true, 'recent' => false, 'max' => false, 'adjacent' => true],
        ['used' => true, 'recent' => false, 'max' => false, 'adjacent' => false],
        ['used' => false, 'recent' => false, 'max' => false, 'adjacent' => false],
    ];

    $slots = array_values($slots);
    $n = count($slots);
    $result = [];
    for ($i = 0; $i < $n; $i++) {
        $s = $slots[$i];
        if (empty($s['open']) || !empty($s['skip'])) {
            continue;
        }
        $prev = $i === 0 ? ($opts['prev_category'] ?? null) : $cats[$i - 1];
        $prev = $prev !== null ? (int) $prev : null;
        $next = ($i + 1 < $n && empty($slots[$i + 1]['open'])) ? $cats[$i + 1] : null;

        $pick = null;
        foreach ($tiers as $t) {
            $candidates = [];
            foreach ($byId as $id => $r) {
                $cat = $r['category_id'];
                if ($t['used'] && isset($used[$id])) {
                    continue;
                }
                if ($t['recent'] && isset($recentSet[$id])) {
                    continue;
                }
                if ($cat !== null) {
                    if ($t['max'] && ($count[$cat] ?? 0) >= $max) {
                        continue;
                    }
                    if ($t['adjacent'] && ($cat === $prev || $cat === $next)) {
                        continue;
                    }
                }
                $candidates[$id] = recipe_weight($r, $cat !== null ? ($count[$cat] ?? 0) : 0, $season);
            }
            if ($candidates) {
                $pick = weighted_pick($candidates, $rand);
                break;
            }
        }

        $result[$s['day']] = $pick;
        if ($pick !== null) {
            $used[$pick] = true;
            $cat = $byId[$pick]['category_id'];
            $cats[$i] = $cat;
            if ($cat !== null) {
                $count[$cat] = ($count[$cat] ?? 0) + 1;
            }
        }
    }
    return $result;
}

/**
 * Rule hints for a planned week.
 * @param array $slots ['skip' => bool, 'category_id' => ?int] in day order
 * @param array $categoryNames id => name
 * @return array<int,string[]> slot index => messages
 */
function plan_warnings(array $slots, int $max, array $categoryNames, ?int $prevCategory = null): array
{
    $slots = array_values($slots);
    $count = [];
    foreach ($slots as $s) {
        if (empty($s['skip']) && isset($s['category_id']) && $s['category_id'] !== null) {
            $count[(int) $s['category_id']] = ($count[(int) $s['category_id']] ?? 0) + 1;
        }
    }
    $out = [];
    foreach ($slots as $i => $s) {
        $c = (!empty($s['skip']) || !isset($s['category_id'])) ? null : (int) $s['category_id'];
        if ($c === null) {
            continue;
        }
        $name = $categoryNames[$c] ?? 'Kategorie';
        if (($count[$c] ?? 0) > $max) {
            $out[$i][] = $name . ' ' . $count[$c] . '× diese Woche (max. ' . $max . ')';
        }
        $prev = $i === 0 ? $prevCategory : ((empty($slots[$i - 1]['skip']) && isset($slots[$i - 1]['category_id'])) ? (int) $slots[$i - 1]['category_id'] : null);
        if ($prev !== null && $prev === $c) {
            $out[$i][] = 'Gleiche Kategorie wie am Vortag';
        }
    }
    return $out;
}
