<?php defined('CULINA_ROOT') || exit; ?>
<div class="pagehead">
    <div>
        <h1>Zutaten</h1>
        <p class="muted"><?= count($all) ?> Zutaten im Katalog<?= $noAisle ? ' · ' . $noAisle . ' ohne Rubrik' : '' ?></p>
    </div>
</div>

<p class="small muted">Die Basiseinheit bestimmt, worin die Einkaufsliste zusammenzählt. Umrechnungen gelten für andere Einheiten, z. B. bei Basiseinheit g: <code>Stk=150, EL=15</code> (1 Stk = 150 g).</p>

<form method="get" class="filters">
    <input type="hidden" name="p" value="catalog">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Zutat suchen …">
    <select name="filter" data-autosubmit aria-label="Filter">
        <option value="">Alle</option>
        <option value="noaisle"<?= $filter === 'noaisle' ? ' selected' : '' ?>>Ohne Rubrik</option>
        <option value="unused"<?= $filter === 'unused' ? ' selected' : '' ?>>Unbenutzt</option>
    </select>
    <button class="btn">Suchen</button>
</form>

<details class="card" id="neu">
    <summary>Neue Zutat</summary>
    <form method="post" class="ing-edit">
        <?= csrf_field() ?>
        <input type="text" name="name" placeholder="Name" required aria-label="Name">
        <input type="text" name="unit" placeholder="Basiseinheit" list="units" class="ing-unit" aria-label="Basiseinheit">
        <select name="aisle_id" aria-label="Rubrik">
            <option value="">Rubrik …</option>
            <?php foreach ($aisles as $aid => $a): ?><option value="<?= (int) $aid ?>"><?= h($a['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="conversions" placeholder="Umrechnungen" aria-label="Umrechnungen">
        <label class="check small"><input type="checkbox" name="pantry" value="1"> Vorrat</label>
        <button class="btn small primary" name="a" value="ingredient_create">Anlegen</button>
    </form>
</details>

<div class="ing-list">
<?php foreach ($list as $id => $i): ?>
    <form method="post" class="card ing-edit" id="i<?= (int) $id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="hidden" name="filter" value="<?= h($filter) ?>">
        <input type="text" name="name" value="<?= h($i['name']) ?>" required aria-label="Name" class="ing-name">
        <input type="text" name="unit" value="<?= h($i['unit']) ?>" placeholder="Basiseinheit" list="units" class="ing-unit" aria-label="Basiseinheit">
        <select name="aisle_id" aria-label="Rubrik"<?= $i['aisle_id'] === null ? ' class="missing"' : '' ?>>
            <option value="">Ohne Rubrik</option>
            <?php foreach ($aisles as $aid => $a): ?>
                <option value="<?= (int) $aid ?>"<?= (int) $i['aisle_id'] === $aid ? ' selected' : '' ?>><?= h($a['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="conversions" value="<?= h($i['conversions']) ?>" placeholder="Umrechnungen" aria-label="Umrechnungen">
        <label class="check small"><input type="checkbox" name="pantry" value="1"<?= $i['pantry'] ? ' checked' : '' ?>> Vorrat</label>
        <span class="small muted"><?= (int) $i['used'] ?> Rez.</span>
        <button class="btn small primary" name="a" value="ingredient_save">Speichern</button>
        <details class="more">
            <summary class="small">Mehr</summary>
            <div class="more-body">
                <label class="small">Zusammenführen in
                    <input type="text" name="into_name" list="ingnames" placeholder="Zutat wählen">
                </label>
                <button class="btn small" name="a" value="ingredient_merge" data-confirm="«<?= h($i['name']) ?>» in die gewählte Zutat zusammenführen? Alle Rezepte werden umgestellt.">Zusammenführen</button>
                <?php if ((int) $i['used'] === 0): ?>
                    <button class="btn small ghost danger" name="a" value="ingredient_delete" data-confirm="«<?= h($i['name']) ?>» löschen?" formnovalidate>Löschen</button>
                <?php endif; ?>
            </div>
        </details>
    </form>
<?php endforeach; ?>
<?php if (!$list): ?><p class="empty">Keine Zutaten gefunden.</p><?php endif; ?>
</div>
<datalist id="ingnames"><?php foreach ($all as $o): ?><option value="<?= h($o['name']) ?>"><?php endforeach; ?></datalist>
<datalist id="units"><?php foreach (unit_list() as $u): ?><option value="<?= h($u) ?>"><?php endforeach; ?></datalist>
