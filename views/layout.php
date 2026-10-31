<?php defined('CULINA_ROOT') || exit;
$nav ??= '';
$links = [
    'week' => ['Woche', url('week')],
    'shop' => ['Einkauf', url('shop')],
    'recipes' => ['Rezepte', url('recipes')],
    'catalog' => ['Zutaten', url('catalog')],
    'settings' => ['Einstellungen', url('settings')],
];
?><!doctype html>
<html lang="de-CH">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(!empty($title) ? $title . ' · Culina' : 'Culina') ?></title>
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= h(CULINA_VERSION) ?>">
<meta name="theme-color" content="#b5532f">
</head>
<body class="<?= h($bodyClass ?? '') ?>">
<header class="top">
    <div class="top-inner">
        <a class="brand" href="<?= h(url('week')) ?>"><img src="assets/icon.svg" alt="" width="28" height="28"><span>Culina</span></a>
        <nav class="nav" aria-label="Hauptnavigation">
            <?php foreach ($links as $key => [$label, $href]): ?>
                <a href="<?= h($href) ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="<?= h(url('week')) ?>" class="logout">
            <?= csrf_field() ?>
            <button name="a" value="logout" class="linkbtn">Abmelden</button>
        </form>
    </div>
</header>
<main class="wrap">
    <?php foreach (take_flashes() as [$type, $msg]): ?>
        <div class="flash flash-<?= h($type) ?>" role="status"><?= h($msg) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="foot">Culina v<?= h(CULINA_VERSION) ?></footer>
<script src="assets/app.js?v=<?= h(CULINA_VERSION) ?>"></script>
</body>
</html>
