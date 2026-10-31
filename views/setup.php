<?php defined('CULINA_ROOT') || exit; ?>
<form method="post" class="card auth-card">
    <?= csrf_field() ?>
    <h2>Einrichten</h2>
    <p class="muted">Lege das Passwort fest, mit dem Culina geschützt wird.</p>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <label>Passwort <input type="password" name="password" minlength="8" required autocomplete="new-password" autofocus></label>
    <label>Passwort wiederholen <input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
    <button class="btn primary block">Passwort setzen</button>
</form>
