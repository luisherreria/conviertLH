<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Herramientas — SubliEdit');
clh_nav('herramientas');
?>
<main class="site" id="contenido">
  <p class="kicker">Las ocho</p>
  <h1>Cada herramienta hace una sola cosa.</h1>
  <p class="lede">Quitar fondo y escalar aceptan hasta 3 archivos, de 8 MB cada uno. Convertir, recortar, espejar, semitono, sólidos y crear mockup usan una sola imagen, también de 8 MB. Si hay más de un resultado, bajan en un ZIP.</p>

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
