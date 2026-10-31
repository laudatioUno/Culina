<?php defined('CULINA_ROOT') || exit;
$backUrl = $back !== '' ? url('week', ['w' => $back]) : url('recipes');
?>
<article class="recipe<?= $cook ? ' is-cook' : '' ?>">
    <div class="pagehead">
        <div>
            <h1><?= h($r['title']) ?><?= $r['favorite'] ? ' <span class="star" title="Favorit">★</span>' : '' ?></h1>
            <p class="recipe-meta">
                <?= cat_badge($r['category_id'], $cats) ?>
                <span class="tag"><?= h(EFFORTS[$r['effort']] ?? $r['effort']) ?></span>
                <?php if ($r['protein'] !== ''): ?><span class="tag"><?= h($r['protein']) ?></span><?php endif; ?>
                <span class="tag"><?= h($r['seasons_list'] ? implode(', ', $r['seasons_list']) : 'ganzjährig') ?></span>
                <?php if ($r['archived']): ?><span class="tag tag-warn">archiviert</span><?php endif; ?>
            </p>
        </div>
        <a class="btn ghost" href="<?= h($backUrl) ?>">Zurück</a>
    </div>

    <form method="get" class="persons-bar">
        <input type="hidden" name="p" value="recipe">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <?php if ($cook): ?><input type="hidden" name="cook" value="1"><?php endif; ?>
        <?php if ($back !== ''): ?><input type="hidden" name="back" value="<?= h($back) ?>"><?php endif; ?>
        <label class="inline-label">Für <input type="number" name="persons" min="1" max="40" value="<?= (int) $persons ?>" class="num" data-autosubmit> Personen</label>
        <span class="small muted">Rezept ist für <?= (int) $r['servings'] ?> Personen erfasst</span>
        <?php if ($cook): ?><button type="button" class="btn small ghost" data-wakelock hidden>Bildschirm wach halten</button><?php endif; ?>
    </form>

    <div class="recipe-grid">
        <section class="card">
            <h2>Zutaten</h2>
            <ul class="ingredients">
                <?php foreach ($r['ingredients'] as $i): ?>
                    <li>
                        <span class="qty"><?= $i['quantity'] !== null ? h(format_amount((float) $i['quantity'] * $factor, $i['unit'], 'cook')) : '' ?></span>
                        <span><?= h($i['name']) ?><?php if ($i['note'] !== ''): ?><span class="muted">, <?= h($i['note']) ?></span><?php endif; ?><?php if ($i['optional']): ?> <span class="tag">optional</span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="card">
            <h2>Zubereitung</h2>
            <?php if ($r['steps_list']): ?>
                <ol class="steps">
                    <?php foreach ($r['steps_list'] as $s): ?><li><?= h($s) ?></li><?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="muted">Keine Schritte erfasst.</p>
            <?php endif; ?>
            <?php if ($r['notes'] !== ''): ?>
                <h3>Notizen</h3>
                <p class="prewrap"><?= h($r['notes']) ?></p>
            <?php endif; ?>
            <?php if ($r['source'] !== ''): ?>
                <p class="small muted">Quelle: <?= h($r['source']) ?></p>
            <?php endif; ?>
        </section>
    </div>

    <?php if (!$cook): ?>
    <div class="recipe-foot">
        <p class="small muted">Zuletzt geplant: <?= h(last_cooked_label($lastDay)) ?></p>
        <form method="post" class="btnrow">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <a class="btn primary" href="<?= h(url('recipe_edit', ['id' => $r['id']])) ?>">Bearbeiten</a>
            <button class="btn" name="a" value="recipe_favorite"><?= $r['favorite'] ? 'Kein Favorit mehr' : 'Als Favorit' ?></button>
            <button class="btn ghost" name="a" value="recipe_archive"<?= $r['archived'] ? '' : ' data-confirm="Rezept archivieren? Es wird dann nicht mehr vorgeschlagen."' ?>><?= $r['archived'] ? 'Wieder aktivieren' : 'Archivieren' ?></button>
            <a class="btn ghost" href="<?= h(url('export', ['id' => $r['id']])) ?>">JSON</a>
        </form>
    </div>
    <?php else: ?>
        <p class="spaced"><a class="small" href="<?= h(url('recipe', ['id' => $r['id']])) ?>">Rezept bearbeiten oder archivieren</a></p>
    <?php endif; ?>
</article>
