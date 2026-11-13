<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Precios — ConvertLH');
clh_nav('precios');

$catalog = null;
$dbError = '';
try {
    $catalog = clh_catalog();
} catch (Exception $e) {
    $dbError = 'No pude leer los planes. En esta PC hay que crear la base una vez con sql/schema.sql.';
}
?>
<main class="site" id="contenido">
  <p class="kicker">Precios de lista</p>
  <h1>Simple, en pesos, con permiso por herramienta.</h1>
  <p class="lede">Estos montos son la lista para cuando conectemos Mercado Pago. Crear una cuenta hoy no cobra y abre Mesa. Quitar fondo y escalar 2× usan 2 créditos. Escalar 4× usa 4. Convertir, recortar y espejar no usan.</p>

  <?php if ($dbError !== ''): ?>
    <p class="alert"><?= clh_h($dbError) ?></p>
  <?php else: ?>
    <div class="plans">
      <?php foreach ($catalog['levels'] as $level): ?>
        <?php $levelId = (int) $level['id']; ?>
        <article class="plan<?= (int) $level['highlighted'] === 1 ? ' is-on' : '' ?>">
          <?php if ((int) $level['highlighted'] === 1): ?><span class="flag">Para el día a día</span><?php endif; ?>
          <h3><?= clh_h($level['name']) ?></h3>
          <p class="price"><?= clh_h(clh_money($level['price_ars'])) ?><?php if ((int) $level['price_ars'] > 0): ?><small> / mes</small><?php endif; ?></p>
          <p><?= clh_h($level['blurb']) ?></p>
          <p><strong><?= (int) $level['monthly_credits'] ?></strong> créditos por mes</p>
          <ul>
            <?php foreach ($catalog['tools'] as $tool): ?>
              <?php $allowed = !empty($catalog['perms'][$levelId][$tool['slug']]); ?>
              <li class="<?= $allowed ? '' : 'is-off' ?>"><?= $allowed ? 'Incluye' : 'No incluye' ?> <?= clh_h($tool['name']) ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="btn" href="login.php?alta=1&amp;plan=<?= clh_h($level['slug']) ?>"><?= (int) $level['price_ars'] > 0 ? 'Elegir plan' : 'Crear cuenta' ?></a>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="narrow">Los créditos extra, fuera del plan, todavía no se venden. Cuando exista un cobro, el botón de arrepentimiento y el de baja quedan en el pie de todas las pantallas.</p>
  <?php endif; ?>
</main>
<?php
clh_footer();
clh_end();
