<?php defined('CULINA_ROOT') || exit;
$rows = $d['ingredients'] ?: [['name' => '', 'qty_raw' => '', 'unit' => '', 'note' => '', 'optional' => false]];
$ingRow = static function (string $idx, array $i): string {
    ob_start(); ?>
    <div class="ing-row">
        <input type="text" name="ing[<?= h($idx) ?>][qty]" value="<?= h($i['qty_raw'] ?? '') ?>" placeholder="Menge" class="ing-qty" inputmode="decimal" aria-label="Menge">
        <input type="text" name="ing[<?= h($idx) ?>][unit]" value="<?= h($i['unit'] ?? '') ?>" placeholder="Einheit" class="ing-unit" list="units" aria-label="Einheit">
        <input type="text" name="ing[<?= h($idx) ?>][name]" value="<?= h($i['name'] ?? '') ?>" placeholder="Zutat" class="ing-name" list="catalog" aria-label="Zutat">
        <input type="text" name="ing[<?= h($idx) ?>][note]" value="<?= h($i['note'] ?? '') ?>" placeholder="Hinweis, z. B. gehackt" class="ing-note" aria-label="Hinweis">
        <label class="check small"><input type="checkbox" name="ing[<?= h($idx) ?>][optional]" value="1"<?= !empty($i['optional']) ? ' checked' : '' ?>> optional</label>
        <button type="button" class="btn small ghost" data-remove-row aria-label="Zeile entfernen">✕</button>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="pagehead">
    <h1><?= h($title) ?></h1>
    <a class="btn ghost" href="<?= h($id ? url('recipe', ['id' => $id]) : url('recipes')) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= h(url('recipe_edit', ['id' => $id])) ?>" class="card form recipe-form">
    <?= csrf_field() ?>
    <input type="hidden" name="a" value="recipe_save">
    <div class="grid2">
        <label class="span2">Titel <input type="text" name="title" value="<?= h($d['title']) ?>" maxlength="160" required></label>
        <label>Grundnahrungsmittel
            <select name="category_id" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($cats as $cid => $c): ?>
                    <option value="<?= (int) $cid ?>"<?= (int) $d['category_id'] === $cid ? ' selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Portionen (Basis) <input type="number" name="servings" min="1" max="40" value="<?= (int) $d['servings'] ?>" required></label>
        <label>Aufwand
            <select name="effort">
                <?php foreach (EFFORTS as $k => $label): ?>
                    <option value="<?= h($k) ?>"<?= $d['effort'] === $k ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Mahlzeit
            <select name="meal">
                <?php foreach (MEALS as $k => $label): ?>
                    <option value="<?= h($k) ?>"<?= $d['meal'] === $k ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Proteinquelle <input type="text" name="protein" value="<?= h($d['protein']) ?>" list="proteins" maxlength="40"></label>
        <fieldset class="seasons">
            <legend>Saison <span class="muted">(keine = ganzjährig)</span></legend>
            <?php foreach (SEASONS as $s): ?>
                <label class="check"><input type="checkbox" name="seasons[]" value="<?= h($s) ?>"<?= in_array($s, $d['seasons'], true) ? ' checked' : '' ?>> <?= h($s) ?></label>
            <?php endforeach; ?>
        </fieldset>
    </div>

    <fieldset class="ingredients-edit">
        <legend>Zutaten <span class="muted">(Mengen für die Basis-Portionen; leere Menge = nach Belieben)</span></legend>
        <div data-ing-rows>
            <?php foreach ($rows as $k => $i) { echo $ingRow((string) $k, $i); } ?>
        </div>
        <template id="ing-template"><?= $ingRow('__i__', []) ?></template>
        <button type="button" class="btn small" data-add-row>+ Zutat</button>
    </fieldset>

    <label>Zubereitung <span class="muted">(ein Schritt pro Zeile)</span>
        <textarea name="steps" rows="8"><?= h($d['steps']) ?></textarea>
    </label>
    <label>Notizen <textarea name="notes" rows="3"><?= h($d['notes']) ?></textarea></label>
    <label>Quelle <input type="text" name="source" value="<?= h($d['source']) ?>" maxlength="200" placeholder="z. B. Grosi, Betty Bossi S. 42"></label>

    <div class="form-actions">
        <button class="btn primary">Speichern</button>
    </div>

    <datalist id="units"><?php foreach (unit_list() as $u): ?><option value="<?= h($u) ?>"><?php endforeach; ?></datalist>
    <datalist id="catalog"><?php foreach ($catalog as $c): ?><option value="<?= h($c['name']) ?>"><?php endforeach; ?></datalist>
    <datalist id="proteins"><?php foreach (PROTEINS as $p): ?><option value="<?= h($p) ?>"><?php endforeach; ?></datalist>
</form>
