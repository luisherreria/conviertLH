<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Herramientas — ConvertLH');
clh_nav('herramientas');
?>
<main class="site" id="contenido">
  <p class="kicker">Las cinco</p>
  <h1>Cada herramienta hace una sola cosa, y la hace en el lote.</h1>
  <p class="lede">Podés subir hasta 8 archivos. Si hay más de un resultado, bajan en un ZIP. El permiso de cada una sale del plan, no de un interruptor escondido.</p>

  <div class="story-grid">
    <?php foreach (clh_tool_stories() as $story): ?>
      <article class="story" id="<?= clh_h($story['anchor']) ?>">
        <p class="kicker"><?= clh_h($story['kicker']) ?></p>
        <h2><?= clh_h($story['name']) ?></h2>
        <p><?= clh_h($story['lead']) ?></p>
        <p><?= clh_h($story['body']) ?></p>
        <ul>
          <?php foreach ($story['points'] as $point): ?>
            <li><?= clh_h($point) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn" href="taller.php?tool=<?= clh_h($story['slug']) ?>">Usar en el taller</a>
      </article>
    <?php endforeach; ?>
  </div>
</main>
<?php
clh_footer();
clh_end();
