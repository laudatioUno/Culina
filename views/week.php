<?php defined('CULINA_ROOT') || exit;
$ws = $plan['week_start'];
$hasPlanned = (bool) array_filter($days, static fn($d) => $d['recipe_id'] !== null && !$d['locked'] && !$d['skip']);
?>
<div class="pagehead">
    <div>
        <h1>Woche <?= week_number($ws) ?></h1>
        <p class="muted"><?= h(week_label($ws)) ?><?= $isCurrent ? ' · diese Woche' : '' ?></p>
    </div>
    <nav class="weeknav" aria-label="Woche wechseln">
        <a class="btn ghost" href="<?= h(url('week', ['w' => $prevWs])) ?>" aria-label="Vorherige Woche">‹</a>
        <?php if (!$isCurrent): ?><a class="btn ghost" href="<?= h(url('week')) ?>">Heute</a><?php endif; ?>
        <a class="btn ghost" href="<?= h(url('week', ['w' => $nextWs])) ?>" aria-label="Nächste Woche">›</a>
    </nav>
</div>

<div class="toolbar">
    <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="a" value="plan_persons">
        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
        <label class="inline-label">Personen
            <input type="number" name="persons" min="1" max="40" value="<?= (int) $plan['persons'] ?>" class="num" data-autosubmit>
        </label>
        <noscript><button class="btn small">OK</button></noscript>
    </form>
    <form method="post" class="inline"<?= $hasPlanned ? ' data-confirm="Alle nicht fixierten Tage werden neu belegt. Weiter?"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
        <button class="btn primary" name="a" value="generate">Plan würfeln</button>
    </form>
    <a class="btn" href="<?= h(url('shop', ['w' => $ws])) ?>">Einkaufsliste</a>
    <a class="linkbtn" href="<?= h(url('history')) ?>">Verlauf</a>
</div>

<?php if ($recipeCount === 0): ?>
    <div class="hint">Noch keine Rezepte erfasst. <a href="<?= h(url('recipe_edit')) ?>">Rezept erfassen</a> oder <a href="<?= h(url('import')) ?>">Rezepte importieren</a>.</div>
<?php endif; ?>

<ol class="days">
<?php foreach ($days as $i => $d):
    $isFree = $d['free_title'] !== '';
    $persons = $d['persons'] ?: $plan['persons'];
    $classes = ['day'];
    if ($d['skip']) { $classes[] = 'is-skip'; }
    if ($d['locked']) { $classes[] = 'is-locked'; }
    if ($d['day'] === date('Y-m-d')) { $classes[] = 'is-today'; }
?>
    <li class="<?= h(implode(' ', $classes)) ?>" id="d<?= (int) $d['id'] ?>">
        <div class="day-date">
            <span class="dow"><?= h(dow($d['day'])) ?></span>
            <span class="date"><?= h(date_short($d['day'])) ?></span>
        </div>
        <div class="day-main">
            <?php if ($d['skip']): ?>
                <p class="day-title muted">Auswärts / frei</p>
            <?php elseif ($d['recipe_id'] !== null): ?>
                <p class="day-title"><a href="<?= h(url('recipe', ['id' => $d['recipe_id'], 'persons' => $persons, 'cook' => 1, 'back' => $ws])) ?>"><?= h($d['recipe_title']) ?></a></p>
            <?php elseif ($isFree): ?>
                <p class="day-title"><?= h($d['free_title']) ?> <span class="tag">manuell</span></p>
                <?php if (trim($d['free_items']) !== ''): ?>
                    <p class="small muted"><?= h(implode(', ', array_filter(array_map('trim', explode("\n", $d['free_items']))))) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="day-title muted">Noch nichts geplant</p>
            <?php endif; ?>
            <div class="day-meta">
                <?php if (!$d['skip']): ?>
                    <?= cat_badge($d['category_id'], $cats) ?>
                    <?php if ($d['recipe_effort'] === 'schnell'): ?><span class="tag">schnell</span><?php endif; ?>
                    <?php if ($d['locked']): ?><span class="tag tag-lock">fixiert</span><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php foreach ($warnings[$i] ?? [] as $w): ?>
                <p class="warn"><?= h($w) ?></p>
            <?php endforeach; ?>
        </div>
        <div class="day-actions">
            <form method="post" class="actions">
                <?= csrf_field() ?>
                <input type="hidden" name="day_id" value="<?= (int) $d['id'] ?>">
                <?php if (!$d['skip']): ?>
                    <?php if (!$isFree): ?>
                        <button class="btn small" name="a" value="day_reroll" title="Anderes Rezept vorschlagen">Würfeln</button>
                    <?php endif; ?>
                    <?php if ($d['recipe_id'] !== null || $isFree): ?>
                        <button class="btn small" name="a" value="day_lock" aria-pressed="<?= $d['locked'] ? 'true' : 'false' ?>"><?= $d['locked'] ? 'Lösen' : 'Fixieren' ?></button>
                    <?php endif; ?>
                    <a class="btn small" href="<?= h(url('pick', ['id' => $d['id']])) ?>">Wählen</a>
                    <a class="btn small" href="<?= h(url('manual', ['id' => $d['id']])) ?>">Manuell</a>
                <?php endif; ?>
                <button class="btn small ghost" name="a" value="day_skip"><?= $d['skip'] ? 'Wieder planen' : 'Auswärts' ?></button>
            </form>
            <?php if (!$d['skip']): ?>
            <form method="post" class="persons-form">
                <?= csrf_field() ?>
                <input type="hidden" name="a" value="day_persons">
                <input type="hidden" name="day_id" value="<?= (int) $d['id'] ?>">
                <label class="inline-label small"><input type="number" name="persons" min="1" max="40" value="<?= (int) $persons ?>" class="num tiny" data-autosubmit aria-label="Personen am <?= h(WEEKDAYS_LONG[$i] ?? '') ?>"> P.</label>
            </form>
            <?php endif; ?>
        </div>
    </li>
<?php endforeach; ?>
</ol>
