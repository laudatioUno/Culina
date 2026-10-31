<?php defined('CULINA_ROOT') || exit; ?>
<div class="pagehead">
    <div>
        <h1>Rezept wählen</h1>
        <p class="muted"><?= h(WEEKDAYS_LONG[weekday_index($day['day'])]) ?>, <?= h(date_long($day['day'])) ?></p>
    </div>
    <a class="btn ghost" href="<?= h(url('week', ['w' => $day['week_start']]) . '#d' . (int) $day['id']) ?>">Zurück</a>
</div>

<form method="get" class="filters">
    <input type="hidden" name="p" value="pick">
    <input type="hidden" name="id" value="<?= (int) $day['id'] ?>">
    <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Titel oder Zutat suchen …" autofocus>
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
    <button class="btn">Suchen</button>
</form>

<?php if (!$recipes): ?>
    <p class="empty">Keine passenden Rezepte gefunden.</p>
<?php else: ?>
<ul class="list">
    <?php foreach ($recipes as $r): ?>
    <li class="list-row">
        <div class="list-main">
            <span class="list-title"><?= h($r['title']) ?><?= $r['favorite'] ? ' <span class="star" title="Favorit">★</span>' : '' ?></span>
            <span class="list-meta"><?= cat_badge($r['category_id'], $cats) ?> <span class="small muted"><?= h(last_cooked_label($r['last_day'], $day['week_start'])) ?></span></span>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="day_id" value="<?= (int) $day['id'] ?>">
            <input type="hidden" name="recipe_id" value="<?= (int) $r['id'] ?>">
            <button class="btn small primary" name="a" value="day_pick">Wählen</button>
        </form>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
