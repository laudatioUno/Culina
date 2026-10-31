<?php defined('CULINA_ROOT') || exit; ?><!doctype html>
<html lang="de-CH">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(!empty($title) ? $title . ' · Culina' : 'Culina') ?></title>
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= h(CULINA_VERSION) ?>">
<meta name="theme-color" content="#b5532f">
</head>
<body class="bare">
<main class="auth">
    <div class="auth-brand"><img src="assets/icon.svg" alt="" width="56" height="56"><h1>Culina</h1><p>Rezepte · Wochenplan · Einkauf</p></div>
    <?= $content ?>
</main>
<footer class="foot">Culina v<?= h(CULINA_VERSION) ?></footer>
</body>
</html>
