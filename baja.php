<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();

$error = '';
$ok = isset($_GET['ok']) ? (string) $_GET['ok'] : '';
$user = clh_user();
$old = [
    'name' => $user ? $user['name'] : '',
    'email' => $user ? $user['email'] : '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = isset($_POST['name']) ? (string) $_POST['name'] : '';
    $old['email'] = isset($_POST['email']) ? (string) $_POST['email'] : '';
    $old['message'] = isset($_POST['message']) ? (string) $_POST['message'] : '';
    try {
        if (clh_honeypot_tripped()) {
            header('Location: baja.php?ok=1');
            exit;
        }
        clh_csrf_check();
        if (empty($_POST['confirmo'])) {
            throw new UserException('Confirmá que querés la baja.');
        }
        $contact = clh_posted_contact();
        $pdo = clh_db();
        $pdo->beginTransaction();
        try {
            clh_save_solicitud('baja', $contact['name'], $contact['email'], $contact['message'], '');
            $closed = false;
            $sessionUser = clh_user();
            if ($sessionUser && strtolower($sessionUser['email']) === strtolower($contact['email'])) {
                $st = $pdo->prepare('UPDATE users SET status = ?, updated_at = ? WHERE id = ? AND status = ?');
                $st->execute(['baja', date('Y-m-d H:i:s'), (int) $sessionUser['id'], 'active']);
                $closed = $st->rowCount() > 0;
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        if ($closed) {
            clh_logout();
            header('Location: baja.php?ok=cuenta');
            exit;
        }
        header('Location: baja.php?ok=1');
        exit;
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No se pudo guardar la baja. Revisá que la base esté instalada.';
    }
}

clh_head('Botón de baja — ConvertLH');
clh_nav('');
?>
<main class="site" id="contenido">
  <p class="kicker">Botón de baja</p>
  <h1>Dar de baja la cuenta, sin letra chica.</h1>
  <div class="split">
    <section class="prose">
      <p>Este botón es para cancelar la cuenta y, cuando exista una suscripción paga, para frenarla. No hace falta llamar ni mandar una carta.</p>
      <p>Si estás adentro y el email coincide, la cuenta pasa a inactiva en el momento y se cierra la sesión. Si el email no coincide con una sesión abierta, guardamos el pedido y no tocamos ninguna cuenta: así nadie da de baja a otra persona solo con escribir su correo.</p>
      <p>La baja no borra sola los pedidos de ayuda o arrepentimiento ya guardados. Esos quedan para poder responderlos.</p>
    </section>
    <section class="panel">
      <?php if ($ok === 'cuenta'): ?><p class="alert ok">La cuenta quedó inactiva y cerramos la sesión.</p><?php endif; ?>
      <?php if ($ok === '1'): ?><p class="alert ok">Recibimos el pedido de baja.</p><?php endif; ?>
      <?php if ($error !== ''): ?><p class="alert"><?= clh_h($error) ?></p><?php endif; ?>
      <form method="post" action="baja.php">
        <?php clh_csrf_field(); ?>
        <?php clh_honeypot_field(); ?>
        <label class="field">Nombre
          <input type="text" name="name" required maxlength="80" value="<?= clh_h($old['name']) ?>">
        </label>
        <label class="field">Email
          <input type="email" name="email" required maxlength="190" value="<?= clh_h($old['email']) ?>">
        </label>
        <label class="field">Motivo
          <textarea name="message" required maxlength="2000"><?= clh_h($old['message']) ?></textarea>
        </label>
        <label class="check">
          <input type="checkbox" name="confirmo" value="1" required>
          Quiero dar de baja la cuenta y la suscripción cuando exista.
        </label>
        <button class="btn" type="submit">Confirmar baja</button>
      </form>
    </section>
  </div>
</main>
<?php
clh_footer();
clh_end();
