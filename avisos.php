<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();
$user = clh_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$note = '';
$error = '';
$isAdmin = !empty($user['is_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        clh_csrf_check();
        if (!$isAdmin) {
            throw new UserException('Solo el administrador puede activar una cuenta.');
        }
        $target = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $note = clh_approve_user($target)
            ? 'La cuenta quedó activa.'
            : 'Esa cuenta ya no estaba en revisión.';
    } catch (UserException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'No pude actualizar la cuenta.';
    }
}

$rows = [];
try {
    clh_install_signals();
    $st = clh_db()->prepare(
        'SELECT n.id, n.kind, n.body, n.ref_user_id, u.status AS ref_status
         FROM notices n
         LEFT JOIN users u ON u.id = n.ref_user_id
         WHERE n.user_id = ?
         ORDER BY n.id DESC
         LIMIT 80'
    );
    $st->execute([(int) $user['id']]);
    $rows = $st->fetchAll();
    $mark = clh_db()->prepare('UPDATE notices SET read_at = ? WHERE user_id = ? AND read_at IS NULL');
    $mark->execute([date('Y-m-d H:i:s'), (int) $user['id']]);
} catch (Exception $e) {
    $error = $error !== '' ? $error : 'No pude leer los avisos.';
}

clh_head('Avisos — SubliEdit');
clh_nav('');
?>
<main class="site" id="contenido">
  <p class="kicker">Avisos</p>
  <h1><?= $isAdmin ? 'Actividad del taller' : 'Tu actividad' ?></h1>
  <p class="lede">Acá queda la fecha y lo que se procesó. El saldo de la cuenta todavía no se descuenta solo.</p>
  <?php if ($isAdmin): ?>
    <p>Si alguien pide otra cuenta desde la misma IP o el mismo navegador, no entra solo. La activás desde acá.</p>
  <?php endif; ?>
  <?php if ($note !== ''): ?><p class="alert ok"><?= clh_h($note) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="alert"><?= clh_h($error) ?></p><?php endif; ?>

  <?php if (!$rows): ?>
    <p>Todavía no hay actividad.</p>
  <?php else: ?>
    <div class="notice-list">
      <?php foreach ($rows as $row): ?>
        <article class="notice<?= $row['kind'] === 'review' ? ' notice-review' : '' ?>">
          <p><?= clh_h($row['body']) ?></p>
          <?php if ($isAdmin && $row['kind'] === 'review' && $row['ref_status'] === 'pending'): ?>
            <form method="post" action="avisos.php">
              <?php clh_csrf_field(); ?>
              <input type="hidden" name="user_id" value="<?= (int) $row['ref_user_id'] ?>">
              <button class="btn" type="submit">Activar cuenta</button>
            </form>
          <?php elseif ($isAdmin && $row['kind'] === 'review' && $row['ref_status'] === 'active'): ?>
            <p class="fine">Ya está activa.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
clh_footer();
clh_end();
