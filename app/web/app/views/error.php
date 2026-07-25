<?php
/** @var int $code */
/** @var string $msg */
?>
<div class="login-wrap">
    <div class="login-card" style="text-align:center;">
        <h1 style="font-size:48px;margin:0;color:var(--primary);"><?= e($code) ?></h1>
        <p class="muted"><?= e($msg) ?></p>
        <a href="<?= e(url('')) ?>" class="btn btn-primary btn-block" style="margin-top:16px;">Volver al inicio</a>
    </div>
</div>
