<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

function clamp_int(?int $v, int $min, int $max, int $default): int
{
    return $v === null ? $default : max($min, min($max, $v));
}

function require_plan(?int $id): array
{
    $plan = $id !== null ? plan_by_id($id) : null;
    if (!$plan) {
        flash('Wochenplan nicht gefunden.', 'error');
        redirect(url('week'));
    }
    return $plan;
}

function require_day(?int $id): array
{
    $day = $id !== null ? plan_day($id) : null;
    if (!$day) {
        flash('Tag nicht gefunden.', 'error');
        redirect(url('week'));
    }
    return $day;
}

function week_url(string $weekStart, ?int $dayId = null): string
{
    return url('week', ['w' => $weekStart]) . ($dayId !== null ? '#d' . $dayId : '');
}

function shop_url(array $plan): string
{
    return url('shop', ['w' => $plan['week_start']]);
}

/** Handle POST actions that end in a redirect. Unknown actions fall through to the page. */
function handle_action(string $a): void
{
    switch ($a) {
        case 'logout':
            logout();
            redirect(url('week'));

        /* ----- Week plan ----- */
        case 'plan_persons':
            $plan = require_plan(post_int('plan_id'));
            q('UPDATE plans SET persons = ? WHERE id = ?', [clamp_int(post_int('persons'), 1, 40, (int) $plan['persons']), $plan['id']]);
            redirect(week_url($plan['week_start']));

        case 'generate':
            $plan = require_plan(post_int('plan_id'));
            $filled = plan_generate_run($plan);
            if ($filled === 0 && (int) q('SELECT COUNT(*) FROM recipes WHERE archived = 0')->fetchColumn() === 0) {
                flash('Noch keine Rezepte vorhanden. Lege zuerst Rezepte an oder importiere welche.', 'error');
            } else {
                flash($filled . ' ' . ($filled === 1 ? 'Tag' : 'Tage') . ' neu belegt.');
            }
            redirect(week_url($plan['week_start']));

        case 'day_lock':
            $d = require_day(post_int('day_id'));
            q('UPDATE plan_days SET locked = 1 - locked WHERE id = ?', [$d['id']]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_reroll':
            $d = require_day(post_int('day_id'));
            if ($d['skip'] || $d['free_title'] !== '') {
                flash('Dieser Tag kann nicht gewürfelt werden.', 'error');
            } else {
                plan_generate_run(require_plan((int) $d['plan_id']), (int) $d['id']);
            }
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_skip':
            $d = require_day(post_int('day_id'));
            q('UPDATE plan_days SET skip = 1 - skip WHERE id = ?', [$d['id']]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_persons':
            $d = require_day(post_int('day_id'));
            $p = post_int('persons');
            q('UPDATE plan_days SET persons = ? WHERE id = ?', [$p !== null && $p > 0 && $p !== (int) $d['plan_persons'] ? min(40, $p) : null, $d['id']]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_pick':
            $d = require_day(post_int('day_id'));
            $rid = post_int('recipe_id');
            if ($rid === null || !q('SELECT 1 FROM recipes WHERE id = ?', [$rid])->fetchColumn()) {
                flash('Rezept nicht gefunden.', 'error');
                redirect(url('pick', ['id' => $d['id']]));
            }
            q("UPDATE plan_days SET recipe_id = ?, free_title = '', free_category_id = NULL, free_items = '', locked = 1, skip = 0 WHERE id = ?", [$rid, $d['id']]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_manual':
            $d = require_day(post_int('day_id'));
            $title = post_str('title');
            if ($title === '') {
                flash('Bitte einen Namen für das Menü eingeben.', 'error');
                redirect(url('manual', ['id' => $d['id']]));
            }
            $cat = post_int('category_id');
            q('UPDATE plan_days SET recipe_id = NULL, free_title = ?, free_category_id = ?, free_items = ?, locked = 1, skip = 0 WHERE id = ?', [
                mb_substr($title, 0, 120), $cat && isset(categories()[$cat]) ? $cat : null, mb_substr(post_str('items'), 0, 4000), $d['id'],
            ]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        case 'day_clear':
            $d = require_day(post_int('day_id'));
            q("UPDATE plan_days SET recipe_id = NULL, free_title = '', free_category_id = NULL, free_items = '', locked = 0 WHERE id = ?", [$d['id']]);
            redirect(week_url($d['week_start'], (int) $d['id']));

        /* ----- Recipes ----- */
        case 'recipe_favorite':
        case 'recipe_archive':
            $id = (int) post_int('id', 0);
            $col = $a === 'recipe_favorite' ? 'favorite' : 'archived';
            q("UPDATE recipes SET $col = 1 - $col WHERE id = ?", [$id]);
            if ($a === 'recipe_archive') {
                $archived = (int) q('SELECT archived FROM recipes WHERE id = ?', [$id])->fetchColumn();
                flash($archived ? 'Rezept archiviert. Es wird nicht mehr vorgeschlagen.' : 'Rezept wieder aktiv.');
            }
            redirect(url('recipe', ['id' => $id]));

        /* ----- Ingredient catalog ----- */
        case 'ingredient_save':
            $id = (int) post_int('id', 0);
            $name = post_str('name');
            if ($name === '') {
                flash('Name darf nicht leer sein.', 'error');
                redirect(url('catalog') . '#i' . $id);
            }
            try {
                q('UPDATE ingredients SET name = ?, unit = ?, aisle_id = ?, pantry = ?, conversions = ? WHERE id = ?', [
                    $name, normalize_unit(post_str('unit')), post_int('aisle_id') ?: null, post_str('pantry') !== '' ? 1 : 0,
                    format_conversions(parse_conversions(post_str('conversions'))), $id,
                ]);
                flash('«' . $name . '» gespeichert.');
            } catch (PDOException) {
                flash('«' . $name . '» gibt es bereits. Zum Zusammenführen die Funktion «Zusammenführen» nutzen.', 'error');
            }
            redirect(url('catalog', ['q' => post_str('q'), 'filter' => post_str('filter')]) . '#i' . $id);

        case 'ingredient_create':
            $name = post_str('name');
            if ($name === '' || ingredient_by_name($name)) {
                flash($name === '' ? 'Name fehlt.' : '«' . $name . '» gibt es bereits.', 'error');
                redirect(url('catalog'));
            }
            $id = ingredient_create($name, post_str('unit'), post_int('aisle_id') ?: null, post_str('pantry') !== '',
                format_conversions(parse_conversions(post_str('conversions'))));
            flash('«' . $name . '» angelegt.');
            redirect(url('catalog') . '#i' . $id);

        case 'ingredient_merge':
            $from = (int) post_int('id', 0);
            $target = ingredient_by_name(post_str('into_name'));
            $into = $target ? (int) $target['id'] : 0;
            if ($into && $from !== $into) {
                ingredient_merge($from, $into);
                flash('Zutaten zusammengeführt in «' . $target['name'] . '».');
            } else {
                flash('Bitte eine andere, bestehende Zutat wählen.', 'error');
            }
            redirect(url('catalog') . '#i' . ($into ?: $from));

        case 'ingredient_delete':
            $id = (int) post_int('id', 0);
            if ((int) q('SELECT COUNT(*) FROM recipe_ingredients WHERE ingredient_id = ?', [$id])->fetchColumn() > 0) {
                flash('Die Zutat wird noch in Rezepten verwendet.', 'error');
            } else {
                q('DELETE FROM ingredients WHERE id = ?', [$id]);
                flash('Zutat gelöscht.');
            }
            redirect(url('catalog'));

        /* ----- Settings ----- */
        case 'settings_general':
            set_setting('persons', (string) clamp_int(post_int('persons'), 1, 40, 6));
            set_setting('max_per_category', (string) clamp_int(post_int('max_per_category'), 1, 7, 2));
            set_setting('avoid_weeks', (string) clamp_int(post_int('avoid_weeks'), 0, 12, 3));
            flash('Einstellungen gespeichert.');
            redirect(url('settings'));

        case 'settings_password':
            if (!password_verify((string) ($_POST['current'] ?? ''), (string) setting('password_hash', ''))) {
                flash('Das aktuelle Passwort ist falsch.', 'error');
            } elseif ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''))) {
                flash($p, 'error');
            } else {
                set_password((string) $_POST['password']);
                flash('Passwort geändert.');
            }
            redirect(url('settings') . '#passwort');

        case 'categories_save':
        case 'aisles_save':
            $table = $a === 'categories_save' ? 'categories' : 'aisles';
            $errors = save_ordered_list($table, (array) ($_POST['ids'] ?? []), (array) ($_POST['names'] ?? []),
                post_str('new_name'), (array) ($_POST['delete'] ?? []));
            foreach ($errors as $e) {
                flash($e, 'error');
            }
            if (!$errors) {
                flash($table === 'categories' ? 'Grundnahrungsmittel gespeichert.' : 'Ladenrubriken gespeichert.');
            }
            redirect(url('settings') . '#' . $table);

        /* ----- Shopping list ----- */
        case 'shop_toggle':
            $plan = require_plan(post_int('plan_id'));
            $key = mb_substr(post_str('key'), 0, 200);
            $hidden = post_str('hidden') !== '';
            if ($hidden) {
                q('INSERT OR IGNORE INTO shop_hidden (plan_id, item_key) VALUES (?, ?)', [$plan['id'], $key]);
            } else {
                q('DELETE FROM shop_hidden WHERE plan_id = ? AND item_key = ?', [$plan['id'], $key]);
            }
            if (is_fetch()) {
                json_out(['ok' => true, 'hidden' => $hidden]);
            }
            redirect(shop_url($plan));

        case 'shop_unhide_all':
            $plan = require_plan(post_int('plan_id'));
            q('DELETE FROM shop_hidden WHERE plan_id = ?', [$plan['id']]);
            redirect(shop_url($plan));

        case 'shop_add':
            $plan = require_plan(post_int('plan_id'));
            $p = parse_item_line(post_str('line'));
            if (!$p) {
                flash('Bitte einen Artikel eingeben, z. B. «2 l Milch».', 'error');
                redirect(shop_url($plan));
            }
            $aisle = post_int('aisle_id') ?: null;
            if ($aisle === null && ($known = ingredient_by_name($p['name']))) {
                $aisle = $known['aisle_id'] !== null ? (int) $known['aisle_id'] : null;
            }
            q('INSERT INTO shop_extras (plan_id, name, quantity, unit, aisle_id) VALUES (?, ?, ?, ?, ?)',
                [$plan['id'], mb_substr($p['name'], 0, 120), $p['quantity'], $p['unit'], $aisle]);
            redirect(shop_url($plan) . '#add');

        case 'shop_extra_update':
            $plan = require_plan(post_int('plan_id'));
            $p = parse_item_line(post_str('line'));
            if ($p) {
                q('UPDATE shop_extras SET name = ?, quantity = ?, unit = ?, aisle_id = ? WHERE id = ? AND plan_id = ?',
                    [mb_substr($p['name'], 0, 120), $p['quantity'], $p['unit'], post_int('aisle_id') ?: null, post_int('id'), $plan['id']]);
            }
            redirect(shop_url($plan));

        case 'shop_extra_delete':
            $plan = require_plan(post_int('plan_id'));
            q('DELETE FROM shop_extras WHERE id = ? AND plan_id = ?', [post_int('id'), $plan['id']]);
            q('DELETE FROM shop_hidden WHERE plan_id = ? AND item_key = ?', [$plan['id'], 'x' . post_int('id')]);
            redirect(shop_url($plan));
    }
}
