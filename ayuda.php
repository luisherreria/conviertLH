<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();

$error = '';
$ok = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
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
            header('Location: ayuda.php?ok=1');
            exit;
        }
        clh_csrf_check();
        $contact = clh_posted_contact();
        clh_save_solicitud('ayuda', $contact['name'], $contact['email'], $contact['message'], '');
        header('Location: ayuda.php?ok=1');
        exit;
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No se pudo guardar el mensaje. Revisá que la base esté instalada.';
    }
}

clh_head('Ayuda y contacto — ConvertLH');
clh_nav('ayuda');
?>
<main class="site" id="contenido">
  <p class="kicker">Ayuda y contacto</p>
  <h1>Escribinos. Todavía no hay un correo automático.</h1>
  <p class="lede">El formulario guarda el pedido en la base de esta PC. Sirve para dudas, para una contraseña olvidada y para decirnos si algo salió mal.</p>

  <div class="split">
    <section class="panel">
      <?php if ($ok): ?><p class="alert ok">Recibimos el mensaje.</p><?php endif; ?>
      <?php if ($error !== ''): ?><p class="alert"><?= clh_h($error) ?></p><?php endif; ?>
      <form method="post" action="ayuda.php">
        <?php clh_csrf_field(); ?>
        <?php clh_honeypot_field(); ?>
        <label class="field">Nombre
          <input type="text" name="name" required maxlength="80" value="<?= clh_h($old['name']) ?>">
        </label>
        <label class="field">Email
          <input type="email" name="email" required maxlength="190" value="<?= clh_h($old['email']) ?>">
        </label>
        <label class="field">Mensaje
          <textarea name="message" required maxlength="2000"><?= clh_h($old['message']) ?></textarea>
        </label>
        <button class="btn" type="submit">Enviar</button>
      </form>
    </section>
    <section>
      <h2>Preguntas frecuentes</h2>
      <?php foreach (clh_faqs() as $faq): ?>
        <details>
          <summary><?= clh_h($faq['q']) ?></summary>
          <p><?= clh_h($faq['a']) ?></p>
        </details>
      <?php endforeach; ?>
    </section>
  </div>
</main>
<?php
clh_footer();
clh_end();
