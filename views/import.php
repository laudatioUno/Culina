<?php defined('CULINA_ROOT') || exit;
$prompt = import_prompt(array_column($cats, 'name'), array_column($aisles, 'name'));
?>
<div class="pagehead">
    <h1>Rezepte importieren</h1>
    <a class="btn ghost" href="<?= h(url('recipes')) ?>">Zurück</a>
</div>

<div class="steps-intro">
    <ol>
        <li>Prompt unten kopieren und in einem Chat mit Claude zusammen mit dem Rezept einfügen (Text, Foto oder Link).</li>
        <li>Den JSON-Codeblock aus der Antwort hier einfügen.</li>
        <li>In der Vorschau prüfen, unbekannte Zutaten zuordnen und importieren.</li>
    </ol>
</div>

<details class="card prompt">
    <summary>Prompt für Claude anzeigen</summary>
    <textarea id="prompt" rows="14" readonly><?= h($prompt) ?></textarea>
    <button type="button" class="btn small" data-copy="#prompt">Prompt kopieren</button>
</details>

<?php if ($errors): ?>
    <div class="flash flash-error"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= h(url('import')) ?>" class="card form">
    <?= csrf_field() ?>
    <label>JSON <textarea name="json" rows="14" class="mono" required placeholder='{"title": "…", "servings": 4, …}'><?= h($raw) ?></textarea></label>
    <div class="form-actions">
        <button class="btn primary" name="a" value="import_preview">Vorschau</button>
    </div>
</form>
