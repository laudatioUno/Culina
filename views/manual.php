<?php defined('CULINA_ROOT') || exit;
$isFree = $day['free_title'] !== '';
?>
<div class="pagehead">
    <div>
        <h1>Manuelles Menü</h1>
        <p class="muted"><?= h(WEEKDAYS_LONG[weekday_index($day['day'])]) ?>, <?= h(date_long($day['day'])) ?></p>
    </div>
    <a class="btn ghost" href="<?= h(url('week', ['w' => $day['week_start']]) . '#d' . (int) $day['id']) ?>">Zurück</a>
</div>

<form method="post" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="day_id" value="<?= (int) $day['id'] ?>">
    <p class="muted">Für Menüs, die nicht in der Rezeptsammlung sind, z. B. wenn jemand anderes kocht.</p>
    <label>Menü <input type="text" name="title" maxlength="120" required value="<?= h($day['free_title']) ?>" placeholder="z. B. Lasagne bei Oma"></label>
    <label>Grundnahrungsmittel <span class="muted">(optional, zählt dann zur Abwechslung)</span>
        <select name="category_id">
            <option value="">–</option>
            <?php foreach ($cats as $id => $c): ?>
                <option value="<?= (int) $id ?>"<?= (int) $day['free_category_id'] === $id ? ' selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Zutaten für die Einkaufsliste <span class="muted">(optional, eine pro Zeile, Mengen werden nicht skaliert)</span>
        <textarea name="items" rows="6" placeholder="500 g Hackfleisch&#10;2 Zwiebel&#10;1 Pkg Lasagneblätter"><?= h($day['free_items']) ?></textarea>
    </label>
    <div class="form-actions">
        <button class="btn primary" name="a" value="day_manual">Speichern</button>
        <?php if ($isFree): ?>
            <button class="btn ghost danger" name="a" value="day_clear" formnovalidate>Entfernen</button>
        <?php endif; ?>
    </div>
</form>
