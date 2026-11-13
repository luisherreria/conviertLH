<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();

$error = '';
$ok = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
$user = clh_user();
$medios = [
    'mercado_pago' => 'Mercado Pago',
    'otro' => 'Otro medio',
    'sin_pago' => 'Todavía no pagué',
];
$old = [
    'name' => $user ? $user['name'] : '',
    'email' => $user ? $user['email'] : '',
    'fecha' => '',
    'medio' => 'sin_pago',
    'referencia' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['name', 'email', 'fecha', 'medio', 'referencia', 'message'] as $key) {
        $old[$key] = isset($_POST[$key]) ? (string) $_POST[$key] : '';
    }
    try {
        if (clh_honeypot_tripped()) {
            header('Location: arrepentimiento.php?ok=1');
            exit;
        }
        clh_csrf_check();
        if (empty($_POST['confirmo'])) {
            throw new UserException('Confirmá que querés revocar la compra.');
        }
        $contact = clh_posted_contact();
        $fecha = trim($old['fecha']);
        if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new UserException('La fecha no es válida.');
        }
        if (!isset($medios[$old['medio']])) {
            throw new UserException('Elegí el medio de pago.');
        }
        $message = 'Fecha: ' . ($fecha === '' ? 'sin indicar' : $fecha)
            . "\nMedio: " . $medios[$old['medio']]
            . "\n" . $contact['message'];
        clh_save_solicitud('arrepentimiento', $contact['name'], $contact['email'], $message, $old['referencia']);
        header('Location: arrepentimiento.php?ok=1');
        exit;
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No se pudo guardar el pedido. Revisá que la base esté instalada.';
    }
}

clh_head('Botón de arrepentimiento — ConvertLH');
clh_nav('');
?>
<main class="site" id="contenido">
  <p class="kicker">Botón de arrepentimiento</p>
  <h1>Podés revocar una compra dentro de los 10 días.</h1>
  <div class="split">
    <section class="prose">
      <p>El artículo 34 de la Ley 24.240 reconoce el derecho a revocar la aceptación de una contratación hecha por medios electrónicos, dentro de los 10 días corridos desde la compra o desde que recibís el servicio, lo que ocurra último.</p>
      <p>Este botón es ese canal. No hace falta explicar un defecto del producto: alcanza con arrepentirte a tiempo.</p>
      <p>ConvertLH todavía no cobra. Si igual querés dejar el pedido asentado, lo guardamos y lo revisamos cuando exista una operación.</p>
    </section>
    <section class="panel">
      <?php if ($ok): ?><p class="alert ok">Recibimos el arrepentimiento. Si la operación existe, la revisamos dentro del plazo de 10 días corridos.</p><?php endif; ?>
      <?php if ($error !== ''): ?><p class="alert"><?= clh_h($error) ?></p><?php endif; ?>
      <form method="post" action="arrepentimiento.php">
        <?php clh_csrf_field(); ?>
        <?php clh_honeypot_field(); ?>
        <label class="field">Nombre
          <input type="text" name="name" required maxlength="80" value="<?= clh_h($old['name']) ?>">
        </label>
        <label class="field">Email de la cuenta
          <input type="email" name="email" required maxlength="190" value="<?= clh_h($old['email']) ?>">
        </label>
        <label class="field">Fecha de la operación
          <input type="date" name="fecha" value="<?= clh_h($old['fecha']) ?>">
        </label>
        <label class="field">Medio de pago
          <select name="medio">
            <?php foreach ($medios as $id => $label): ?>
              <option value="<?= clh_h($id) ?>"<?= $old['medio'] === $id ? ' selected' : '' ?>><?= clh_h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Número de operación
          <input type="text" name="referencia" maxlength="80" value="<?= clh_h($old['referencia']) ?>">
        </label>
        <label class="field">Pedido
          <textarea name="message" required maxlength="2000"><?= clh_h($old['message']) ?></textarea>
        </label>
        <label class="check">
          <input type="checkbox" name="confirmo" value="1" required>
          Quiero revocar la compra.
        </label>
        <button class="btn" type="submit">Enviar arrepentimiento</button>
      </form>
    </section>
  </div>
</main>
<?php
clh_footer();
clh_end();
