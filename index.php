<?php

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Formats.php';
require_once __DIR__ . '/src/LocalRunner.php';

$config = clh_config();
$formats = Formats::catalog();
$aiRemote = trim((string) ($config['replicate_token'] ?? '')) !== '';
$aiReady = $aiRemote || LocalRunner::isReady();
$maxFiles = max(1, (int) $config['max_files']);
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
    'maxBytes' => $maxBytes,
];
$cssVersion = (int) @filemtime(__DIR__ . '/assets/app.css');
$jsVersion = (int) @filemtime(__DIR__ . '/assets/app.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>ConvertLH — Quitar fondo, escalar y convertir</title>
  <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23e2a15a'/%3E%3Ctext x='32' y='42' text-anchor='middle' font-family='Segoe UI' font-size='26' font-weight='700' fill='%2324180c'%3ELH%3C/text%3E%3C/svg%3E">
  <link rel="stylesheet" href="assets/app.css?v=<?= $cssVersion ?>">
</head>
<body>
  <div class="app">
    <header class="top">
      <div class="brand">
        <div class="mark" aria-hidden="true">LH</div>
        <div>
          <h1>ConvertLH</h1>
          <p>Quitar fondo, escalar y convertir</p>
        </div>
      </div>
      <p class="lead"><?= $aiRemote
        ? 'Quitar fondo y escalar usan Replicate. Convertir formatos corre en esta PC y no gasta crédito.'
        : 'Quitar fondo y escalar corren en esta PC. Convertir formatos también, y no usa la IA.' ?></p>
    </header>

    <?php if ($aiRemote): ?>
      <p class="banner">Fondo con BiRefNet y escala con Real-ESRGAN, en Replicate. Cada imagen descuenta el crédito de tu cuenta y suele tardar unos segundos.</p>
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
        <span>Lote, calidad y ZIP. Sin costo de API.</span>
      </button>
    </div>

    <div class="layout">
      <form class="panel" id="job" action="api/process.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="tool" id="tool" value="remove_bg">
        <label class="drop" id="drop">
          <input id="file-input" type="file" multiple accept="image/png,image/jpeg,image/webp,image/gif,image/bmp">
          <strong>Soltá imágenes o hacé clic para elegirlas</strong>
          <span>PNG, JPG, WEBP, GIF o BMP · hasta <?= (int) $maxFiles ?> archivos · <?= (int) $maxMb ?> MB cada uno</span>
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

          <label class="check">
            <input id="crop" name="crop" type="checkbox" value="1">
            Recortar al contenido
          </label>
          <p class="hint">Saca el margen transparente o el color parejo de las esquinas.</p>

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
        <p class="hint" id="ai-hint"><?= $aiRemote ? 'Usa el crédito de Replicate. Suele tardar unos segundos por imagen.' : 'Corre en esta PC, sin costo por imagen. La primera vez también carga el modelo.' ?></p>
        <p id="feedback" role="status"></p>
      </form>

      <section class="results" id="results" aria-live="polite">
        <p class="empty">El resultado aparece acá, con la comparación antes / después.</p>
      </section>
    </div>

    <p class="foot">Los archivos se procesan y se borran al responder. No queda una copia en el servidor.</p>
  </div>
  <script>
    window.APP = <?= json_encode($client, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="assets/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
