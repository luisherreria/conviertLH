<?php

require_once __DIR__ . '/src/Site.php';
require_once __DIR__ . '/src/Payments.php';

clh_site_boot();

$user = clh_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$reference = isset($_GET['ref']) ? (string) $_GET['ref'] : '';
if ($reference === '') {
    $open = clh_payment_open($user);
    if ($open) {
        $reference = $open['external_reference'];
    }
}

$result = '';
$error = '';
if ($reference !== '') {
    try {
        $result = clh_payment_confirm($user, $reference);
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No pude confirmar el pago.';
    }
}

if ($result === 'approved') {
    clh_flash('Pago aprobado. El plan y los créditos del mes ya están en la cuenta.');
    header('Location: cuenta.php');
    exit;
}
if ($result === 'already') {
    clh_flash('Ese pago ya estaba acreditado.');
    header('Location: cuenta.php');
    exit;
}

clh_head('Pago — SubliEdit');
clh_nav('precios');
?>
<main class="site" id="contenido">
  <p class="kicker">Mercado Pago</p>
  <h1>El pago todavía no está aprobado.</h1>
  <?php if ($error !== ''): ?>
    <p class="alert"><?= clh_h($error) ?></p>
  <?php elseif ($reference === ''): ?>
    <p class="lede">No hay un cobro pendiente en esta cuenta.</p>
  <?php else: ?>
    <p class="lede">Mercado Pago todavía no marca este pedido como pago. Si acabás de pagar, esperá un momento y confirmá de nuevo. Recargar esta página no suma créditos dos veces.</p>
    <div class="hero-actions">
      <a class="btn" href="pago-vuelta.php?ref=<?= clh_h(rawurlencode($reference)) ?>">Confirmar de nuevo</a>
      <a class="btn-line" href="precios.php">Volver a precios</a>
    </div>
  <?php endif; ?>
</main>
<?php
clh_footer();
clh_end();
