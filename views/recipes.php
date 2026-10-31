<?php defined('CULINA_ROOT') || exit; ?>
<div class="pagehead">
    <div>
        <h1>Rezepte</h1>
        <p class="muted"><?= (int) $total ?> aktive Rezepte</p>
    </div>
    <div class="btnrow">
        <a class="btn primary" href="<?= h(url('recipe_edit')) ?>">Neues Rezept</a>
        <a class="btn" href="<?= h(url('import')) ?>">Importieren</a>
    </div>
</div>

<form method="get" class="filters">
    <input type="hidden" name="p" value="recipes">
    <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Titel oder Zutat suchen …">
    <select name="category" data-autosubmit aria-label="Grundnahrungsmittel">
        <option value="">Alle Grundnahrungsmittel</option>
        <?php foreach ($cats as $id => $c): ?>
            <option value="<?= (int) $id ?>"<?= (int) $filters['category'] === $id ? ' selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="effort" data-autosubmit aria-label="Aufwand">
        <option value="">Jeder Aufwand</option>
        <?php foreach (EFFORTS as $k => $label): ?>
            <option value="<?= h($k) ?>"<?= $filters['effort'] === $k ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="season" data-autosubmit aria-label="Saison">
        <option value="">Jede Saison</option>
        <?php foreach (SEASONS as $s): ?>
            <option<?= $filters['season'] === $s ? ' selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" name="archived" value="1"<?= $filters['archived'] ? ' checked' : '' ?> data-autosubmit> Archiv</label>
    <button class="btn">Suchen</button>
</form>

<?php if (!$recipes): ?>
    <p class="empty"><?= $total === 0 && !$filters['archived'] ? 'Noch keine Rezepte. Lege das erste an oder importiere welche.' : 'Keine passenden Rezepte gefunden.' ?></p>
<?php else: ?>
<ul class="list">
    <?php foreach ($recipes as $r): ?>
    <li class="list-row">
        <a class="list-main" href="<?= h(url('recipe', ['id' => $r['id']])) ?>">
            <span class="list-title"><?= h($r['title']) ?><?= $r['favorite'] ? ' <span class="star" title="Favorit">★</span>' : '' ?></span>
            <span class="list-meta">
                <?= cat_badge($r['category_id'], $cats) ?>
                <span class="small muted"><?= h(EFFORTS[$r['effort']] ?? $r['effort']) ?><?= $r['protein'] !== '' ? ' · ' . h($r['protein']) : '' ?> · <?= h(last_cooked_label($r['last_day'])) ?></span>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<p class="small muted spaced"><a href="<?= h(url('export')) ?>">Alle Rezepte als JSON exportieren</a> (Backup)</p>
