<?php defined('CULINA_ROOT') || exit;
$valid = array_filter($analysis['recipes'], static fn($r) => !$r['errors']);
?>
<div class="pagehead">
    <div>
        <h1>Import prüfen</h1>
        <p class="muted"><?= count($analysis['recipes']) ?> Rezept(e) gefunden, <?= count($valid) ?> importierbar</p>
    </div>
</div>

<form method="post" action="<?= h(url('import')) ?>">
    <?= csrf_field() ?>
    <textarea name="json" hidden><?= h($raw) ?></textarea>

    <section class="card">
        <h2>Rezepte</h2>
        <ul class="list">
        <?php foreach ($analysis['recipes'] as $idx => $r): $d = $r['data']; ?>
            <li class="list-row import-row">
                <label class="check">
                    <input type="checkbox" name="include[]" value="<?= (int) $idx ?>"<?= $r['errors'] ? ' disabled' : ($r['duplicate'] ? '' : ' checked') ?>>
                    <span class="list-title"><?= h($d['title'] !== '' ? $d['title'] : '(ohne Titel)') ?></span>
                </label>
                <span class="small muted">
                    <?= h($d['category']) ?> · <?= (int) $d['servings'] ?> Pers. · <?= count($d['ingredients']) ?> Zutaten · <?= count(array_filter(explode("\n", $d['steps']))) ?> Schritte
                </span>
                <?php if ($r['duplicate']): ?><p class="warn">Ein Rezept mit diesem Titel gibt es bereits. Nur ankreuzen, wenn es trotzdem neu angelegt werden soll.</p><?php endif; ?>
                <?php foreach ($r['errors'] as $e): ?><p class="error-text"><?= h($e) ?></p><?php endforeach; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($analysis['unknown']): ?>
    <section class="card">
        <h2>Neue Zutaten</h2>
        <p class="muted">Diese Zutaten sind noch nicht im Katalog. Leer lassen, um sie neu anzulegen, oder eine bestehende Zutat wählen, z. B. «Zwiebel» für «Zwiebeln». So zählt die Einkaufsliste richtig zusammen.</p>
        <div class="map-rows">
        <?php foreach ($analysis['unknown'] as $key => $u): $f = 'map[' . $key . ']'; ?>
            <div class="map-row">
                <strong><?= h($u['name']) ?></strong>
                <input type="text" name="<?= h($f) ?>[target]" list="ingnames" placeholder="Neu anlegen oder bestehende wählen" aria-label="Zuordnung für <?= h($u['name']) ?>">
                <select name="<?= h($f) ?>[aisle_id]" aria-label="Rubrik für <?= h($u['name']) ?>">
                    <option value="">Rubrik …</option>
                    <?php foreach ($aisles as $aid => $a): ?>
                        <option value="<?= (int) $aid ?>"<?= (int) $u['aisle_id'] === $aid ? ' selected' : '' ?>><?= h($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="<?= h($f) ?>[unit]" value="<?= h($u['unit']) ?>" list="units" placeholder="Basiseinheit" class="ing-unit" aria-label="Basiseinheit">
                <label class="check small"><input type="checkbox" name="<?= h($f) ?>[pantry]" value="1"<?= $u['pantry'] ? ' checked' : '' ?>> Vorrat</label>
            </div>
        <?php endforeach; ?>
        </div>
        <datalist id="ingnames"><?php foreach ($catalog as $c): ?><option value="<?= h($c['name']) ?>"><?php endforeach; ?></datalist>
        <datalist id="units"><?php foreach (unit_list() as $un): ?><option value="<?= h($un) ?>"><?php endforeach; ?></datalist>
    </section>
    <?php endif; ?>

    <div class="form-actions">
        <button class="btn primary" name="a" value="import_commit"<?= $valid ? '' : ' disabled' ?>>Importieren</button>
        <button class="btn ghost" name="a" value="import_back" formaction="<?= h(url('import')) ?>">Zurück zum JSON</button>
    </div>
</form>
