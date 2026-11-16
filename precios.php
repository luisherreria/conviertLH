<?php

require_once __DIR__ . '/src/Site.php';
require_once __DIR__ . '/src/Payments.php';

clh_site_boot();
$user = clh_user();
$flash = clh_flash_take();

clh_head('Precios — SubliEdit');
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
  <p class="lede">Elegir un plan pago abre Mercado Pago. El plan y los créditos del mes se cargan cuando el pago queda aprobado. Crear una cuenta no cobra y entra en Mesa. Quitar fondo y escalar 2× usan 2 créditos. Escalar 4× usa 4. Convertir, recortar, espejar, semitono y sólidos no usan.</p>
  <?php
    $sandbox = false;
    try {
        $sandbox = MercadoPago::fromConfig()->isTest();
    } catch (Exception $e) {
        $sandbox = false;
    }
  ?>
  <?php if ($sandbox): ?>
    <p class="alert">La credencial cargada es de prueba. El vendedor de ese checkout no puede pagarse a sí mismo: abrí una ventana de incógnito, entrá con otro usuario de prueba (el comprador) y usá la tarjeta Visa que muestra tu panel, con titular APRO.</p>
  <?php endif; ?>
  <?php if ($flash !== ''): ?>
    <p class="alert"><?= clh_h($flash) ?></p>
  <?php endif; ?>

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
          <?php if ((int) $level['price_ars'] <= 0): ?>
            <a class="btn" href="<?= $user ? 'cuenta.php' : 'login.php?alta=1&amp;plan=mesa' ?>"><?= $user ? 'Tu plan sin cargo' : 'Crear cuenta' ?></a>
          <?php elseif (!$user): ?>
            <a class="btn" href="login.php?alta=1&amp;plan=<?= clh_h($level['slug']) ?>">Elegir plan</a>
          <?php else: ?>
            <form method="post" action="pagar.php">
              <?php clh_csrf_field(); ?>
              <input type="hidden" name="plan" value="<?= clh_h($level['slug']) ?>">
              <button class="btn" type="submit">Elegir plan</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="narrow">Al volver del pago, la cuenta se actualiza una sola vez. Recargar esa página no vuelve a sumar los créditos. Los créditos extra, fuera del plan, todavía no se venden. El botón de arrepentimiento y el de baja quedan en el pie.</p>
  <?php endif; ?>
</main>
<?php
clh_footer();
clh_end();
