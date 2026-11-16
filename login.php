<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();

$error = '';
$held = '';
$tab = (isset($_GET['alta']) && (string) $_GET['alta'] === '1') ? 'alta' : 'login';
$old = [
    'name' => '',
    'email' => '',
];
$planNote = '';
$planSlug = isset($_GET['plan']) ? (string) $_GET['plan'] : '';
if ($planSlug !== '' && $planSlug !== 'mesa') {
    try {
        $st = clh_db()->prepare('SELECT name FROM levels WHERE slug = ? LIMIT 1');
        $st->execute([$planSlug]);
        $plan = $st->fetch();
        if ($plan) {
            $planNote = 'Elegiste ' . $plan['name'] . '. La cuenta nueva entra en Mesa. Después, en Precios, Elegir plan abre el pago y el plan se activa si Mercado Pago lo aprueba.';
            $tab = 'alta';
        }
    } catch (Exception $e) {
        $planNote = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = (isset($_POST['action']) && $_POST['action'] === 'register') ? 'alta' : 'login';
    $old['name'] = isset($_POST['name']) ? (string) $_POST['name'] : '';
    $old['email'] = isset($_POST['email']) ? (string) $_POST['email'] : '';
    try {
        clh_csrf_check();
        if ($tab === 'alta') {
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
            $again = isset($_POST['password_again']) ? (string) $_POST['password_again'] : '';
            if (!hash_equals($password, $again)) {
                throw new UserException('Las contraseñas no coinciden.');
            }
            $state = clh_register($old['name'], $old['email'], $password, clh_current_theme());
            if ($state === 'pending') {
                $held = 'Recibimos la cuenta, pero no la activamos. Esta IP o este navegador ya tiene una cuenta. Cuando la revisemos, vas a poder entrar.';
            } else {
                header('Location: taller.php');
                exit;
            }
        } else {
            clh_login($old['email'], isset($_POST['password']) ? (string) $_POST['password'] : '');
            header('Location: taller.php');
            exit;
        }
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No pude usar la base. Revisá que las tablas estén creadas.';
    }
}

clh_head('Entrar — SubliEdit');
clh_nav('login');
?>
<main class="site" id="contenido">
  <a class="back" href="index.php">← Volver</a>
  <div class="split">
    <section class="panel">
      <input class="auth-radio" type="radio" name="panel" id="panel-login" <?= $tab === 'login' ? 'checked' : '' ?>>
      <input class="auth-radio" type="radio" name="panel" id="panel-alta" <?= $tab === 'alta' ? 'checked' : '' ?>>
      <div class="auth-tabs">
        <label for="panel-login">Ingresar</label>
        <label for="panel-alta">Registrarse</label>
      </div>

      <?php if ($error !== ''): ?><p class="alert"><?= clh_h($error) ?></p><?php endif; ?>
      <?php if ($held !== ''): ?><p class="alert ok"><?= clh_h($held) ?></p><?php endif; ?>
      <?php if ($planNote !== ''): ?><p class="alert ok"><?= clh_h($planNote) ?></p><?php endif; ?>

      <form class="auth-form form-login" method="post" action="login.php">
        <?php clh_csrf_field(); ?>
        <input type="hidden" name="action" value="login">
        <label class="field">Email
          <input type="email" name="email" autocomplete="username" required maxlength="190" value="<?= clh_h($old['email']) ?>">
        </label>
        <label class="field">Contraseña
          <input type="password" name="password" autocomplete="current-password" required maxlength="200">
        </label>
        <button class="btn" type="submit">Ingresar</button>
        <p class="fine"><a href="ayuda.php">¿Olvidaste la contraseña?</a> Escribinos y la reponemos a mano hasta que el correo esté conectado.</p>
      </form>

      <form class="auth-form form-alta" method="post" action="login.php?alta=1">
        <?php clh_csrf_field(); ?>
        <input type="hidden" name="action" value="register">
        <label class="field">Nombre
          <input type="text" name="name" autocomplete="name" required maxlength="80" value="<?= clh_h($old['name']) ?>">
        </label>
        <label class="field">Email
          <input type="email" name="email" autocomplete="email" required maxlength="190" value="<?= clh_h($old['email']) ?>">
        </label>
        <label class="field">Contraseña
          <input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="200">
        </label>
        <label class="field">Repetir contraseña
          <input type="password" name="password_again" autocomplete="new-password" required minlength="8" maxlength="200">
        </label>
        <button class="btn" type="submit">Crear cuenta</button>
        <p class="fine">Al crear la cuenta aceptás los <a href="terminos.php">términos</a> y la <a href="privacidad.php">privacidad</a>. No se cobra nada: entrás en Mesa. Si esta PC o este navegador ya tiene una cuenta, la nueva queda en revisión.</p>
      </form>
    </section>

    <section>
      <p class="kicker">SubliEdit</p>
      <h2>La cuenta guarda el plan, los créditos y el color.</h2>
      <div class="cards" style="grid-template-columns: 1fr; margin-top: 16px;">
        <?php foreach (clh_tool_stories() as $story): ?>
          <article class="card">
            <p class="kicker"><?= clh_h($story['kicker']) ?></p>
            <h3><?= clh_h($story['name']) ?></h3>
            <p><?= clh_h($story['lead']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
      <?php clh_legal_nav(); ?>
    </section>
  </div>
</main>
<?php
clh_footer();
clh_end();
