<?php

require_once __DIR__ . '/src/Site.php';

clh_site_boot();
$catalog = null;
try {
    $catalog = clh_catalog();
} catch (Exception $e) {
    $catalog = null;
}
$stories = clh_tool_stories();

clh_head('SubliEdit — Imágenes listas para producir');
clh_nav('inicio');
?>
<main class="site" id="contenido">
  <p class="kicker">Taller de imágenes para imprimir y entregar</p>
  <h1>El archivo sale en un paso, no en una tarde.</h1>
  <p class="lede">Siete herramientas en el navegador. Quitar fondo y escalar usan IA y créditos. Crear mockup usa 1 crédito. Convertir, recortar, espejar y semitono corren en esta máquina y no descuentan nada.</p>
  <div class="hero-actions">
    <a class="btn" href="login.php?alta=1">Crear cuenta</a>
    <a class="btn-line" href="herramientas.php">Ver herramientas</a>
    <a class="btn-line" href="taller.php">Abrir taller</a>
  </div>
  <ul class="checks">
    <li>Los créditos se usan solo cuando interviene la IA.</li>
    <li>La imagen se procesa para responder y después se borra.</li>
    <li>El precio está en pesos y se cobra con Mercado Pago.</li>
  </ul>

  <section class="band">
    <h2>El editor no tiene que ser el cuello de botella.</h2>
    <p>Cuando el trabajo es repetir fondo, tamaño, formato o margen, el taller lo saca del medio.</p>
    <div class="cards">
      <article class="card">
        <h3>Fondo que no sale</h3>
        <p>Aro, texto y trazos que la varita se come o se deja pegados.</p>
      </article>
      <article class="card">
        <h3>Foto que no llega</h3>
        <p>El cliente manda una imagen chica y la plancha pide más tamaño.</p>
      </article>
      <article class="card">
        <h3>Formato que no abre</h3>
        <p>Hay que pasar una imagen a PNG, JPG, WEBP, BMP o GIF sin instalar nada.</p>
      </article>
      <article class="card">
        <h3>Margen y espejo</h3>
        <p>Sobra lienzo vacío, o el calco tiene que mirar hacia el otro lado.</p>
      </article>
    </div>
  </section>

  <section class="band">
    <h2>Siete herramientas, en la misma pantalla.</h2>
    <p>Cada una abre directo en el taller. El permiso de usarla depende del plan.</p>
    <div class="cards tools">
      <?php foreach ($stories as $story): ?>
        <article class="card">
          <p class="kicker"><?= clh_h($story['kicker']) ?></p>
          <h3><?= clh_h($story['name']) ?></h3>
          <p><?= clh_h($story['lead']) ?></p>
          <p><a href="herramientas.php#<?= clh_h($story['anchor']) ?>">Cómo se usa</a></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="band">
    <h2>Para quien vive de sacar el trabajo.</h2>
    <p>Da igual si son diez encargos o una cola larga: el recorrido es el mismo.</p>
    <div class="cards people">
      <article class="card">
        <h3>Estampado y DTF</h3>
        <p>Fondo limpio, foto agrandada y archivo volteado antes de mandarlo a la máquina.</p>
      </article>
      <article class="card">
        <h3>Entrega de diseño</h3>
        <p>Convertís la imagen, fijás calidad y DPI, y la bajás lista.</p>
      </article>
      <article class="card">
        <h3>Foto de producto</h3>
        <p>Fondo parejo o transparente, recorte al motivo y un tamaño que se pueda publicar.</p>
      </article>
    </div>
  </section>

  <section class="band">
    <h2>Qué queda de este lado y qué no.</h2>
    <div class="cards">
      <article class="card">
        <h3>La imagen no se archiva</h3>
        <p>Se usa para armar la respuesta y se borra. No armamos un banco con los archivos del taller.</p>
      </article>
      <article class="card">
        <h3>La IA sale, el resto no</h3>
        <p>Fondo y escala van a Replicate. Convertir, recortar y espejar se resuelven en esta PC.</p>
      </article>
      <article class="card">
        <h3>Permiso por herramienta</h3>
        <p>Cada plan habilita o no cada una de las siete. No es un paquete cerrado para siempre.</p>
      </article>
      <article class="card">
        <h3>Tres colores de tema</h3>
        <p>Naranja, cobre o ámbar. Se guarda en este navegador y, si entrás, también en la cuenta.</p>
      </article>
    </div>
  </section>

  <section class="band">
    <h2>Números del taller, sin promesas de velocidad.</h2>
    <div class="stats">
      <article class="stat"><strong>7</strong><span>herramientas en la misma pantalla</span></article>
      <article class="stat"><strong>2</strong><span>créditos por fondo o por escala 2×</span></article>
      <article class="stat"><strong>0</strong><span>créditos por convertir, recortar, espejar o semitono</span></article>
    </div>
  </section>

  <section class="band" id="planes">
    <h2>Precios de lista, en pesos.</h2>
    <p>Crear una cuenta no cobra y entra en Mesa. Un plan pago se activa cuando Mercado Pago aprueba el pago.</p>
    <?php if ($catalog): ?>
      <div class="plans">
        <?php foreach ($catalog['levels'] as $level): ?>
          <article class="plan<?= (int) $level['highlighted'] === 1 ? ' is-on' : '' ?>">
            <?php if ((int) $level['highlighted'] === 1): ?><span class="flag">Para el día a día</span><?php endif; ?>
            <h3><?= clh_h($level['name']) ?></h3>
            <p class="price"><?= clh_h(clh_money($level['price_ars'])) ?></p>
            <p><?= (int) $level['monthly_credits'] ?> créditos por mes</p>
            <a class="btn-line" href="precios.php">Ver qué incluye</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p><a class="btn" href="precios.php">Ver precios</a></p>
    <?php endif; ?>
  </section>

  <section class="band">
    <h2>Preguntas frecuentes</h2>
    <?php foreach (clh_faqs() as $faq): ?>
      <details>
        <summary><?= clh_h($faq['q']) ?></summary>
        <p><?= clh_h($faq['a']) ?></p>
      </details>
    <?php endforeach; ?>
  </section>
</main>
<?php
clh_footer();
clh_end();
