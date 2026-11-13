<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();
$user = clh_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$catalog = null;
$dbError = '';
try {
    $catalog = clh_catalog();
} catch (Exception $e) {
    $dbError = 'No pude leer los permisos del plan.';
}

clh_head('Mi cuenta — ConvertLH');
clh_nav('cuenta');
?>
<main class="site" id="contenido">
  <p class="kicker">Cuenta</p>
  <h1><?= clh_h($user['name']) ?></h1>
  <p class="lede"><?= clh_h($user['email']) ?> · plan <?= clh_h($user['level_name']) ?> · <?= (int) $user['credits'] ?> créditos. El tema activo es <?= clh_h(clh_themes()[clh_current_theme()]['label']) ?>.</p>
  <div class="hero-actions">
    <a class="btn" href="taller.php">Abrir taller</a>
    <a class="btn-line" href="precios.php">Ver planes</a>
    <a class="btn-line" href="baja.php">Botón de baja</a>
  </div>

  <?php if ($dbError !== ''): ?>
    <p class="alert"><?= clh_h($dbError) ?></p>
  <?php else: ?>
    <div class="cards tools">
      <?php foreach ($catalog['tools'] as $tool): ?>
        <?php $allowed = !empty($catalog['perms'][(int) $user['level_id']][$tool['slug']]); ?>
        <article class="card">
          <p class="kicker"><?= $allowed ? 'Habilitada' : 'No incluida' ?></p>
          <h3><?= clh_h($tool['name']) ?></h3>
          <p><?= clh_h($tool['summary']) ?></p>
          <p><?= clh_h($tool['credit_note']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="narrow">El saldo está en la cuenta, pero todavía no se descuenta al procesar una imagen. El próximo paso es cobrar los planes con Mercado Pago y restar los créditos en cada trabajo de IA.</p>
</main>
<?php
clh_footer();
clh_end();
