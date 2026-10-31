<?php defined('CULINA_ROOT') || exit;
$ws = $plan['week_start'];
$hiddenCount = 0;
foreach ($list['sections'] as $s) { foreach ($s['items'] as $i) { $hiddenCount += $i['hidden'] ? 1 : 0; } }
foreach ($list['pantry'] as $i) { $hiddenCount += $i['hidden'] ? 1 : 0; }
$item = static function (array $i) use ($plan, $aisles): void { ?>
    <li class="shop-item<?= $i['hidden'] ? ' is-hidden' : '' ?>">
        <form method="post" data-ajax class="shop-toggle">
            <?= csrf_field() ?>
            <input type="hidden" name="a" value="shop_toggle">
            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
            <input type="hidden" name="key" value="<?= h($i['key']) ?>">
            <label>
                <input type="checkbox" name="hidden" value="1"<?= $i['hidden'] ? ' checked' : '' ?> data-autosubmit>
                <span class="shop-text"><strong><?= h($i['amount']) ?></strong> <?= h($i['name']) ?><?= $i['optional'] ? ' <span class="tag">optional</span>' : '' ?></span>
            </label>
        </form>
        <?php if (!empty($i['extra'])): $x = $i['extra']; ?>
            <details class="extra-edit">
                <summary class="small" aria-label="Bearbeiten">ändern</summary>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                    <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                    <input type="text" name="line" value="<?= h(trim(($x['quantity'] !== null ? format_number((float) $x['quantity'], 2) . ' ' : '') . ($x['unit'] !== '' ? $x['unit'] . ' ' : '') . $x['name'])) ?>" aria-label="Artikel">
                    <select name="aisle_id" aria-label="Rubrik">
                        <option value="">Ohne Rubrik</option>
                        <?php foreach ($aisles as $aid => $a): ?><option value="<?= (int) $aid ?>"<?= (int) $x['aisle_id'] === $aid ? ' selected' : '' ?>><?= h($a['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn small" name="a" value="shop_extra_update">OK</button>
                    <button class="btn small ghost danger" name="a" value="shop_extra_delete">Entfernen</button>
                </form>
            </details>
        <?php elseif ($i['sources']): ?>
            <span class="small muted sources"><?= h(implode(', ', $i['sources'])) ?></span>
        <?php endif; ?>
    </li>
<?php };
?>
<div class="pagehead">
    <div>
        <h1>Einkaufsliste</h1>
        <p class="muted">KW <?= week_number($ws) ?> · <?= h(week_label($ws)) ?> · <?= (int) $plan['persons'] ?> Personen</p>
    </div>
    <nav class="weeknav" aria-label="Woche wechseln">
        <a class="btn ghost" href="<?= h(url('shop', ['w' => $prevWs])) ?>" aria-label="Vorherige Woche">‹</a>
        <a class="btn ghost" href="<?= h(url('shop', ['w' => $nextWs])) ?>" aria-label="Nächste Woche">›</a>
    </nav>
</div>

<div class="toolbar">
    <a class="btn primary" href="<?= h(url('shop_pdf', ['w' => $ws])) ?>" target="_blank" rel="noopener">PDF drucken</a>
    <a class="btn" href="<?= h(url('week', ['w' => $ws])) ?>">Zum Wochenplan</a>
    <?php if ($hiddenCount): ?>
    <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
        <button class="linkbtn" name="a" value="shop_unhide_all"><?= $hiddenCount ?> abgehakt – alle zurücksetzen</button>
    </form>
    <?php endif; ?>
</div>
<p class="small muted">Abgehakte Artikel (schon zu Hause) erscheinen nicht im PDF.</p>

<form method="post" class="card add-form" id="add">
    <?= csrf_field() ?>
    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
    <input type="text" name="line" placeholder="Artikel ergänzen, z. B. 2 l Milch" required aria-label="Artikel">
    <select name="aisle_id" aria-label="Rubrik">
        <option value="">Rubrik automatisch</option>
        <?php foreach ($aisles as $aid => $a): ?><option value="<?= (int) $aid ?>"><?= h($a['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn primary" name="a" value="shop_add">Hinzufügen</button>
</form>

<?php if (!$list['sections'] && !$list['pantry']): ?>
    <p class="empty">Die Liste ist leer. Plane zuerst die Woche oder ergänze Artikel von Hand.</p>
<?php endif; ?>

<div class="shop-sections">
<?php foreach ($list['sections'] as $s): ?>
    <section class="shop-section">
        <h2><?= h($s['aisle']) ?></h2>
        <ul><?php foreach ($s['items'] as $i) { $item($i); } ?></ul>
    </section>
<?php endforeach; ?>
<?php if ($list['pantry']): ?>
    <section class="shop-section pantry">
        <h2>Vorrat prüfen</h2>
        <ul><?php foreach ($list['pantry'] as $i) { $item($i); } ?></ul>
    </section>
<?php endif; ?>
</div>
