<?php

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Site.php';
require_once __DIR__ . '/src/Formats.php';
require_once __DIR__ . '/src/Mockup.php';
require_once __DIR__ . '/src/LocalRunner.php';

clh_site_boot();

$config = clh_config();
$formats = Formats::catalog();
$aiRemote = trim((string) ($config['replicate_token'] ?? '')) !== '';
$aiReady = $aiRemote || LocalRunner::isReady();
$maxFiles = max(1, (int) $config['max_files']);
$aiMaxFiles = max(1, (int) ($config['ai_max_files'] ?? 3));
$maxBytes = max(1024, (int) $config['max_upload_bytes']);
$maxMb = (int) round($maxBytes / (1024 * 1024));

$readyLabels = [];
$missingLabels = [];
foreach ($formats as $meta) {
    if ($meta['ok']) {
        $readyLabels[] = $meta['label'];
    } else {
        $missingLabels[] = $meta['label'];
    }
}

$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base = rtrim($base, '/');
$baseHref = ($base === '' ? '' : $base) . '/';
$client = [
    'aiReady' => $aiReady,
    'aiRemote' => $aiRemote,
    'maxFiles' => $maxFiles,
    'aiMaxFiles' => $aiMaxFiles,
    'maxBytes' => $maxBytes,
];
$cssVersion = (int) @filemtime(__DIR__ . '/assets/app.css');
$jsVersion = (int) @filemtime(__DIR__ . '/assets/app.js');
$themeCss = (int) @filemtime(__DIR__ . '/assets/theme.css');
$siteCss = (int) @filemtime(__DIR__ . '/assets/site.css');
$account = clh_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Taller — SubliEdit</title>
  <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" href="assets/favicon.png?v=<?= (int) @filemtime(__DIR__ . '/assets/favicon.png') ?>" type="image/png">
  <link rel="stylesheet" href="assets/theme.css?v=<?= $themeCss ?>">
  <link rel="stylesheet" href="assets/site.css?v=<?= $siteCss ?>">
  <link rel="stylesheet" href="assets/app.css?v=<?= $cssVersion ?>">
</head>
<body data-theme="<?= clh_h(clh_current_theme()) ?>">
  <?php clh_nav('taller'); ?>
  <div class="app">
    <header class="top">
      <div class="brand">
        <img class="mark" src="assets/mark.png" alt="" width="46" height="52">
        <div>
          <h1>SubliEdit</h1>
          <p>Quitar fondo, escalar y convertir</p>
        </div>
      </div>
      <p class="lead"><?php if ($account): ?>Plan <?= clh_h($account['level_name']) ?> · <?= (int) $account['credits'] ?> créditos. <?php endif; ?><?= $aiRemote
        ? 'Quitar fondo y escalar usan Replicate. Convertir, recortar y espejar corren en esta PC.'
        : 'Quitar fondo y escalar corren en esta PC. Convertir, recortar y espejar también.' ?></p>
    </header>

    <?php if ($aiRemote): ?>
      <p class="banner">Fondo con BiRefNet y escala con Real-ESRGAN, en Replicate. Eso gasta el crédito de Replicate de esta instalación. El saldo de tu cuenta todavía no se descuenta solo.</p>
    <?php elseif ($aiReady): ?>
      <p class="banner">IA local: BiRefNet para el fondo y Real-ESRGAN para escalar. En esta PC corre por CPU: quitar el fondo puede tardar unos minutos por imagen.</p>
    <?php else: ?>
      <p class="banner">Falta el token de Replicate. Convertir formatos funciona igual.</p>
    <?php endif; ?>

    <div class="tools" role="tablist" aria-label="Herramientas">
      <button class="tool" type="button" data-tool="remove_bg" role="tab" aria-pressed="true">
        <strong>Quitar fondo</strong>
        <span>PNG transparente o sobre un color.</span>
      </button>
      <button class="tool" type="button" data-tool="upscale" role="tab" aria-pressed="false">
        <strong>Escalar</strong>
        <span>2× o 4× con Real-ESRGAN.</span>
      </button>
      <button class="tool" type="button" data-tool="convert" role="tab" aria-pressed="false">
        <strong>Convertir</strong>
        <span>Una imagen. Calidad y formato. Sin costo.</span>
      </button>
      <button class="tool" type="button" data-tool="crop" role="tab" aria-pressed="false">
        <strong>Recortar</strong>
        <span>Una imagen. Sin costo.</span>
      </button>
      <button class="tool" type="button" data-tool="flip" role="tab" aria-pressed="false">
        <strong>Espejar</strong>
        <span>Horizontal o vertical. Sin costo.</span>
      </button>
      <button class="tool" type="button" data-tool="halftone" role="tab" aria-pressed="false">
        <strong>Semitono</strong>
        <span>Puntos para DTF. Sin costo.</span>
      </button>
      <button class="tool" type="button" data-tool="mockup" role="tab" aria-pressed="false">
        <strong>Crear Mockup</strong>
        <span>1 crédito. Remera, taza, gorra, medalla y más.</span>
      </button>
    </div>

    <div class="layout">
      <form class="panel" id="job" action="api/process.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="tool" id="tool" value="remove_bg">
        <label class="drop" id="drop">
          <input id="file-input" type="file" multiple accept="image/png,image/jpeg,image/webp,image/gif,image/bmp">
          <strong>Soltá imágenes o hacé clic para elegirlas</strong>
          <span id="drop-note">PNG, JPG, WEBP, GIF o BMP · hasta <?= (int) $aiMaxFiles ?> archivos · <?= (int) $maxMb ?> MB cada uno</span>
        </label>
        <ul class="queue" id="queue"></ul>

        <div class="options">
          <div id="panel-bg-mode" class="field">
            <span>Fondo</span>
            <div class="choice">
              <label><input type="radio" name="background" value="transparent" checked> Transparente</label>
              <label><input type="radio" name="background" value="color"> Color sólido</label>
            </div>
          </div>

          <div id="panel-keep" class="field">
            <label for="keep">Qué conservar <output id="keep-out" for="keep">Todo el diseño</output></label>
            <input id="keep" name="keep" type="range" min="0" max="100" value="80">
            <p class="hint">A la izquierda queda solo el objeto principal. A la derecha también el aro, el texto y los trazos sobre el fondo.</p>
          </div>

          <div id="panel-color" class="field" >
            <label id="color-field" class="is-muted">Color
              <input id="background-color" name="background_color" type="color" value="#ffffff">
            </label>
            <p class="hint" id="fill-hint" hidden>En JPG, BMP y GIF este color reemplaza la transparencia.</p>
          </div>

          <div id="panel-scale" class="field" hidden>
            <span>Escala</span>
            <div class="scales">
              <label><input type="radio" name="scale" value="2" checked> 2×</label>
              <label><input type="radio" name="scale" value="4"> 4×</label>
            </div>
            <p class="hint">4× tarda más y el archivo pesa más. El lado resultante no puede pasar de 8000 px.</p>
          </div>

          <div id="panel-format" class="field" hidden>
            <label for="format">Formato</label>
            <select id="format" name="format">
              <?php foreach ($formats as $id => $meta): ?>
                <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"<?= $meta['ok'] ? '' : ' disabled' ?>>
                  <?= htmlspecialchars($meta['label'] . ($meta['ok'] ? '' : ' — no disponible en este PHP'), ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label for="quality">Calidad <output id="quality-out" for="quality">90</output></label>
            <input id="quality" name="quality" type="range" min="40" max="100" value="90">
            <?php if ($missingLabels): ?>
              <p class="note"><?= htmlspecialchars(implode(', ', $readyLabels), ENT_QUOTES, 'UTF-8') ?> se convierten acá. <?= htmlspecialchars(implode(', ', $missingLabels), ENT_QUOTES, 'UTF-8') ?> no: este PHP no trae Imagick ni el soporte de GD para esos formatos.</p>
            <?php endif; ?>
            <p class="hint">El GIF no conserva animación: se guarda un solo cuadro.</p>
          </div>

          <div id="panel-flip" class="field" hidden>
            <span>Espejo</span>
            <div class="choice">
              <label><input type="radio" name="flip" value="horizontal" checked> Horizontal</label>
              <label><input type="radio" name="flip" value="vertical"> Vertical</label>
              <label><input type="radio" name="flip" value="both"> Ambos</label>
            </div>
            <p class="hint">Voltea una imagen en esta PC. No usa Replicate.</p>
          </div>

          <div id="panel-halftone" class="field" hidden>
            <span>Uso</span>
            <div class="choice">
              <label><input type="radio" name="halftone" value="photo" checked> Foto</label>
              <label><input type="radio" name="halftone" value="white"> Base blanca</label>
            </div>
            <span>Lineatura</span>
            <div class="choice">
              <label><input type="radio" name="lpi" value="35"> 35 LPI</label>
              <label><input type="radio" name="lpi" value="45" checked> 45 LPI</label>
              <label><input type="radio" name="lpi" value="55"> 55 LPI</label>
            </div>
            <label for="halftone-cap">Tope del punto <output id="halftone-cap-out" for="halftone-cap">85%</output></label>
            <input id="halftone-cap" name="halftone_cap" type="range" min="40" max="100" value="85">
            <div class="choice">
              <label><input type="radio" name="halftone_ink" value="black" checked> Punto negro</label>
              <label><input type="radio" name="halftone_ink" value="white"> Punto blanco</label>
            </div>
            <p class="hint">La foto pasa los tonos a puntos. La base blanca usa la opacidad, para la capa de blanco del DTF. El tope en 85 deja aire en los planos. Sale PNG a 45°. No usa Replicate.</p>
          </div>

          <div id="panel-mockup" class="field" hidden>
            <label id="mockup-label">Plantilla</label>
            <div class="mockup-pick">
              <?php $mockupOptions = Mockup::options(); $mockupFirst = key($mockupOptions); ?>
              <input type="hidden" id="mockup" name="mockup" value="<?= htmlspecialchars($mockupFirst, ENT_QUOTES, 'UTF-8') ?>">
              <button class="mockup-current" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="mockup-label">
                <span><?= htmlspecialchars($mockupOptions[$mockupFirst]['label'], ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <div class="mockup-menu" hidden>
                <div class="mockup-options" role="listbox" aria-labelledby="mockup-label">
                  <?php foreach ($mockupOptions as $id => $option): ?>
                    <button type="button" role="option" data-value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" data-src="assets/mockups/<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>.jpg"<?= $id === $mockupFirst ? ' aria-selected="true"' : '' ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8') ?></button>
                  <?php endforeach; ?>
                </div>
                <img class="mockup-preview" alt="" src="assets/mockups/<?= htmlspecialchars($mockupFirst, ENT_QUOTES, 'UTF-8') ?>.jpg">
              </div>
            </div>
            <p class="hint">El diseño se apoya en la zona de estampa y baja un JPG. En la medalla, la cinta usa el color elegido. Vale 1 crédito. El saldo de la cuenta todavía no se descuenta solo. No usa Replicate.</p>
            <div id="panel-ribbon" hidden>
              <span id="ribbon-label">Cinta</span>
              <div class="ribbon-swatches" role="radiogroup" aria-labelledby="ribbon-label">
                <?php foreach (Mockup::ribbons() as $ribbonId => $ribbon): ?>
                  <label class="ribbon-swatch">
                    <input type="radio" name="ribbon" value="<?= htmlspecialchars($ribbonId, ENT_QUOTES, 'UTF-8') ?>"<?= $ribbonId === 'azul' ? ' checked' : '' ?>>
                    <span style="background: <?= htmlspecialchars($ribbon['css'], ENT_QUOTES, 'UTF-8') ?>"></span>
                    <small><?= htmlspecialchars($ribbon['label'], ENT_QUOTES, 'UTF-8') ?></small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div id="panel-crop-note" class="field" hidden>
            <p class="hint">Saca el margen transparente o el color parejo de las esquinas. Una imagen. No usa Replicate.</p>
          </div>

          <label class="check" id="panel-crop-option">
            <input id="crop" name="crop" type="checkbox" value="1">
            Recortar al contenido
          </label>
          <p class="hint" id="crop-hint">Saca el margen transparente o el color parejo de las esquinas.</p>

          <label class="field" for="dpi">DPI de salida
            <select id="dpi" name="dpi">
              <option value="0">No cambiar</option>
              <option value="72">72 DPI</option>
              <option value="96">96 DPI</option>
              <option value="150">150 DPI</option>
              <option value="300">300 DPI impresión</option>
              <option value="600">600 DPI</option>
            </select>
          </label>
          <p class="hint">El DPI se guarda en PNG y JPG.</p>
        </div>

        <div class="actions">
          <button class="primary" id="process-btn" type="submit">Procesar</button>
          <button class="secondary" id="preset" type="button">Preset producción</button>
        </div>
        <p class="hint" id="ai-hint"><?= $aiRemote ? 'Usa el crédito de Replicate. Si hay más de una, se procesan de a una para no cortar por tiempo.' : 'Corre en esta PC, sin costo por imagen. La primera vez también carga el modelo.' ?></p>
        <p id="feedback" role="status"></p>
      </form>

      <section class="results" id="results" aria-live="polite">
        <p class="empty">El resultado aparece acá, con la comparación antes / después.</p>
      </section>
    </div>

    <p class="foot">Los archivos se procesan y se borran al responder. No queda una copia en el servidor.</p>
    <?php clh_legal_nav(); ?>
  </div>
  <script>
    window.APP = <?= json_encode($client, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="assets/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
