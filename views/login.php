<?php defined('CULINA_ROOT') || exit; ?>
<form method="post" action="<?= h(url('login')) ?>" class="card auth-card">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <label>Passwort <input type="password" name="password" required autocomplete="current-password" autofocus></label>
    <button class="btn primary block">Anmelden</button>
</form>
