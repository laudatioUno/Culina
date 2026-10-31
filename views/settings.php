<?php defined('CULINA_ROOT') || exit;
$listEditor = static function (string $action, string $id, string $heading, string $hint, array $rows, string $deleteHint): void { ?>
    <section class="card" id="<?= h($id) ?>">
        <h2><?= h($heading) ?></h2>
        <p class="small muted"><?= h($hint) ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <ol class="sortable" data-sortable>
                <?php foreach ($rows as $rid => $row): ?>
                <li>
                    <span class="grip" draggable="true" aria-hidden="true">⋮⋮</span>
                    <input type="hidden" name="ids[]" value="<?= (int) $rid ?>">
                    <input type="text" name="names[]" value="<?= h($row['name']) ?>" required aria-label="Name">
                    <button type="button" class="btn small ghost" data-move="-1" aria-label="Nach oben">↑</button>
                    <button type="button" class="btn small ghost" data-move="1" aria-label="Nach unten">↓</button>
                    <label class="check small" title="<?= h($deleteHint) ?>"><input type="checkbox" name="delete[]" value="<?= (int) $rid ?>"> löschen</label>
                </li>
                <?php endforeach; ?>
            </ol>
            <label class="small">Neu hinzufügen <input type="text" name="new_name" maxlength="60"></label>
            <div class="form-actions"><button class="btn primary" name="a" value="<?= h($action) ?>">Speichern</button></div>
        </form>
    </section>
<?php };
?>
<div class="pagehead"><h1>Einstellungen</h1></div>

<section class="card">
    <h2>Planung</h2>
    <form method="post" class="form grid3">
        <?= csrf_field() ?>
        <label>Standard-Personen <input type="number" name="persons" min="1" max="40" value="<?= (int) $persons ?>"></label>
        <label>Gleiches Grundnahrungsmittel max. pro Woche <input type="number" name="max_per_category" min="1" max="7" value="<?= (int) $maxPerCategory ?>"></label>
        <label>Rezepte der letzten … Wochen meiden <input type="number" name="avoid_weeks" min="0" max="12" value="<?= (int) $avoidWeeks ?>"></label>
        <div class="form-actions span3"><button class="btn primary" name="a" value="settings_general">Speichern</button></div>
    </form>
    <p class="small muted">Zusätzlich gilt: nie zweimal hintereinander dasselbe Grundnahrungsmittel. Findet der Generator kein passendes Rezept, lockert er die Regeln schrittweise.</p>
</section>

<?php $listEditor('aisles_save', 'aisles', 'Ladenrubriken', 'Reihenfolge = Laufweg im Laden. Die Einkaufsliste und das PDF werden so sortiert.', $aisles, 'Zutaten dieser Rubrik werden «Ohne Rubrik»'); ?>

<?php $listEditor('categories_save', 'categories', 'Grundnahrungsmittel', 'Jedes Rezept hat genau ein Grundnahrungsmittel. Darüber steuert Culina die Abwechslung.', $cats, 'Nur möglich, wenn kein Rezept die Kategorie verwendet'); ?>

<section class="card" id="passwort">
    <h2>Passwort ändern</h2>
    <form method="post" class="form grid3">
        <?= csrf_field() ?>
        <label>Aktuelles Passwort <input type="password" name="current" required autocomplete="current-password"></label>
        <label>Neues Passwort <input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
        <label>Wiederholen <input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
        <div class="form-actions span3"><button class="btn primary" name="a" value="settings_password">Passwort ändern</button></div>
    </form>
</section>

<section class="card">
    <h2>Daten</h2>
    <p><a class="btn" href="<?= h(url('export')) ?>">Alle Rezepte als JSON exportieren</a></p>
    <p class="small muted">Die ganze Datenbank liegt in <code>data/culina.sqlite</code>. Für ein vollständiges Backup diese Datei sichern.</p>
</section>
