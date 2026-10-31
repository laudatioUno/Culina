<?php defined('CULINA_ROOT') || exit; ?>
<div class="pagehead">
    <h1>Verlauf</h1>
    <a class="btn ghost" href="<?= h(url('week')) ?>">Zur aktuellen Woche</a>
</div>

<?php if (!$weeks): ?>
    <p class="empty">Noch keine Wochen geplant.</p>
<?php endif; ?>

<?php foreach ($weeks as $w): $p = $w['plan']; ?>
<section class="card history-week">
    <h2><a href="<?= h(url('week', ['w' => $p['week_start']])) ?>">KW <?= week_number($p['week_start']) ?></a> <span class="muted small"><?= h(week_label($p['week_start'])) ?></span></h2>
    <ul class="history-days">
        <?php foreach ($w['days'] as $d): ?>
            <li>
                <span class="dow"><?= h(dow($d['day'])) ?></span>
                <?php if ($d['skip']): ?>
                    <span class="muted">auswärts</span>
                <?php elseif ($d['recipe_id'] !== null): ?>
                    <a href="<?= h(url('recipe', ['id' => $d['recipe_id']])) ?>"><?= h($d['recipe_title']) ?></a>
                <?php elseif ($d['free_title'] !== ''): ?>
                    <span><?= h($d['free_title']) ?></span>
                <?php else: ?>
                    <span class="muted">–</span>
                <?php endif; ?>
                <?= cat_badge($d['category_id'], $cats) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
