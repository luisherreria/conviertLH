<?php

require_once __DIR__ . '/Auth.php';

function clh_themes()
{
    return [
        'naranja' => ['label' => 'Naranja'],
        'cobre' => ['label' => 'Cobre'],
        'ambar' => ['label' => 'Ámbar'],
    ];
}

function clh_apply_theme($theme)
{
    if (!in_array($theme, clh_theme_ids(), true)) {
        return;
    }
    clh_remember_theme($theme);
    $user = clh_user();
    if (!$user) {
        return;
    }
    try {
        $st = clh_db()->prepare('UPDATE users SET theme = ?, updated_at = ? WHERE id = ?');
        $st->execute([$theme, date('Y-m-d H:i:s'), (int) $user['id']]);
    } catch (Exception $e) {
    }
}

function clh_site_boot()
{
    if (defined('CLH_SITE_BOOTED')) {
        return;
    }
    define('CLH_SITE_BOOTED', true);
    if (!isset($_GET['tema'])) {
        return;
    }
    $tema = (string) $_GET['tema'];
    if (!isset(clh_themes()[$tema])) {
        return;
    }
    clh_apply_theme($tema);
    $path = parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (!is_string($path) || $path === '' || $path[0] !== '/' || strpos($path, '//') === 0) {
        $path = clh_cookie_path();
    }
    header('Location: ' . $path);
    exit;
}

function clh_theme_href($theme)
{
    $path = parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (!is_string($path) || $path === '' || $path[0] !== '/' || strpos($path, '//') === 0) {
        $path = clh_cookie_path();
    }
    return $path . '?tema=' . rawurlencode($theme);
}

function clh_money($pesos)
{
    $pesos = (int) $pesos;
    if ($pesos <= 0) {
        return 'Sin cargo';
    }
    return '$ ' . number_format($pesos, 0, ',', '.');
}

function clh_tool_stories()
{
    return [
        [
            'slug' => 'remove_bg',
            'anchor' => 'quitar-fondo',
            'name' => 'Quitar fondo',
            'kicker' => 'IA · 2 créditos',
            'lead' => 'Separa el motivo del fondo y entrega un PNG transparente, o lo apoya sobre un color plano.',
            'points' => [
                'El control “Qué conservar” pide más o menos del aro, el texto y los trazos.',
                'Hasta 3 imágenes, de 8 MB cada una.',
                'Corre con BiRefNet en Replicate.',
                'Cada imagen usa 2 créditos.',
            ],
            'body' => 'Subís la foto, elegís transparente o un color, y comparás el antes y el después antes de bajar el archivo. Si el recorte se come un borde que tenía que quedar, movés el control hacia “Todo el diseño”.',
        ],
        [
            'slug' => 'upscale',
            'anchor' => 'escalar',
            'name' => 'Escalar',
            'kicker' => 'IA · 2 o 4 créditos',
            'lead' => 'Agranda 2× o 4× reconstruyendo detalle, en vez de estirar los píxeles.',
            'points' => [
                '2× usa 2 créditos. 4× usa 4.',
                'Hasta 3 imágenes, de 8 MB cada una.',
                'El lado resultante no puede pasar de 8000 px.',
                'Corre con Real-ESRGAN en Replicate.',
            ],
            'body' => 'Sirve cuando llega una foto chica y la plancha, el DTF o el marketplace piden más tamaño. 4× tarda más y el archivo pesa más.',
        ],
        [
            'slug' => 'convert',
            'anchor' => 'convertir',
            'name' => 'Convertir',
            'kicker' => 'Local · sin créditos',
            'lead' => 'Pasa una imagen a PNG, JPG, WEBP, BMP o GIF, con calidad.',
            'points' => [
                'No usa créditos ni sale a Replicate.',
                'TIFF, ICO y AVIF no están disponibles en este PHP.',
                'El GIF se guarda en un solo cuadro.',
            ],
            'body' => 'Elegís el formato y la calidad. El DPI de salida se puede fijar en PNG y JPG.',
        ],
        [
            'slug' => 'crop',
            'anchor' => 'recortar',
            'name' => 'Recortar',
            'kicker' => 'Local · sin créditos',
            'lead' => 'Saca el margen transparente o el color parejo de las esquinas.',
            'points' => [
                'No usa créditos.',
                'Una imagen, de hasta 8 MB.',
                'Deja el motivo cerca del borde.',
            ],
            'body' => 'Después de quitar el fondo suele sobrar lienzo vacío. Recortar lo saca de una vez, sin medir a mano.',
        ],
        [
            'slug' => 'flip',
            'anchor' => 'espejar',
            'name' => 'Espejar',
            'kicker' => 'Local · sin créditos',
            'lead' => 'Voltea una imagen en horizontal, en vertical o en ambos sentidos.',
            'points' => [
                'No usa créditos ni sale a Replicate.',
                'Horizontal invierte izquierda y derecha.',
                'Vertical invierte arriba y abajo.',
            ],
            'body' => 'Sirve para calcos, pares y archivos que tienen que mirar hacia el otro lado antes de imprimir.',
        ],
        [
            'slug' => 'halftone',
            'anchor' => 'semitono',
            'name' => 'Semitono',
            'kicker' => 'Local · sin créditos',
            'lead' => 'Convierte la imagen en puntos redondos a 45°, listos para una lámina DTF.',
            'points' => [
                'No usa créditos ni sale a Replicate.',
                'La foto tramita los tonos. La base blanca usa la opacidad.',
                '35, 45 o 55 LPI, con tope para que el plano no quede macizo.',
            ],
            'body' => 'Sale un PNG. El punto negro es la tinta que va a leer el RIP. En la base blanca, un tope de 85 deja aire en los planos para que el polvo no forme una lámina.',
        ],
        [
            'slug' => 'solid',
            'anchor' => 'solidos',
            'name' => 'Sólidos',
            'kicker' => 'Local · sin créditos',
            'lead' => 'Borra el borde semitransparente y deja macizo lo que pasa el corte.',
            'points' => [
                'No usa créditos ni sale a Replicate.',
                'El corte elige desde qué opacidad el píxel se conserva.',
                'Sale un PNG con el halo ya sacado.',
            ],
            'body' => 'Sirve para que el RIP no imprima el velo del borde. Lo que queda por debajo del corte desaparece. Lo que lo pasa queda opaco.',
        ],
        [
            'slug' => 'mockup',
            'anchor' => 'mockup',
            'name' => 'Crear Mockup',
            'kicker' => 'Local · 1 crédito',
            'lead' => 'Apoya el diseño en una remera, una taza, una gorra, una medalla, un set matero, un jarro térmico o una cartuchera.',
            'points' => [
                'Cada imagen usa 1 crédito.',
                'La medalla deja elegir el color de la cinta.',
                'No sale a Replicate.',
                'Baja un JPG con el diseño en la zona de estampa.',
            ],
            'body' => 'Sirve para mostrarle al cliente cómo queda el archivo puesto en el producto, antes de imprimir.',
        ],
    ];
}

function clh_faqs()
{
    return [
        [
            'q' => '¿Hace falta saber diseño?',
            'a' => 'No. Elegís la herramienta, subís la imagen y bajás el resultado. El preset de producción deja listos el recorte y los 300 DPI cuando el formato los guarda.',
        ],
        [
            'q' => '¿Qué gasta créditos?',
            'a' => 'Quitar fondo usa 2 créditos. Escalar 2× usa 2 y escalar 4× usa 4. Crear mockup usa 1. Convertir, recortar, espejar, semitono y sólidos no gastan créditos.',
        ],
        [
            'q' => '¿Los créditos ya se descuentan al procesar?',
            'a' => 'La cuenta guarda el saldo y el permiso de cada herramienta. El cobro del plan ya pasa por Mercado Pago y carga los créditos del mes. El descuento en cada imagen todavía no está activo.',
        ],
        [
            'q' => '¿Guardan las imágenes?',
            'a' => 'No las guardamos. Se procesan para armar la respuesta y se borran. Quitar fondo y escalar envían la imagen a Replicate para hacer ese trabajo. Convertir, recortar, espejar, semitono y sólidos quedan en esta máquina.',
        ],
        [
            'q' => '¿Puedo pagar en pesos?',
            'a' => 'Sí. Los planes pagos se cobran en pesos con Mercado Pago. Crear una cuenta no cobra y entra en Mesa hasta que el pago quede aprobado.',
        ],
        [
            'q' => '¿Todos los planes abren las mismas herramientas?',
            'a' => 'No. Mesa incluye convertir, recortar, espejar, semitono, sólidos y crear mockup. Puesto, Cuadrilla y Planta suman quitar fondo y escalar. Ese permiso está en la base, uno por herramienta, y se puede cambiar sin tocar el taller.',
        ],
    ];
}

function clh_head($title)
{
    if (!defined('CLH_SITE_BOOTED')) {
        clh_site_boot();
    }
    $theme = clh_current_theme();
    $themeCss = (int) @filemtime(CLH_ROOT . '/assets/theme.css');
    $siteCss = (int) @filemtime(CLH_ROOT . '/assets/site.css');
    echo '<!DOCTYPE html><html lang="es"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex">';
    echo '<title>' . clh_h($title) . '</title>';
    $icon = (int) @filemtime(CLH_ROOT . '/assets/favicon.png');
    echo '<link rel="icon" href="assets/favicon.png?v=' . $icon . '" type="image/png">';
    echo '<link rel="stylesheet" href="assets/theme.css?v=' . $themeCss . '">';
    echo '<link rel="stylesheet" href="assets/site.css?v=' . $siteCss . '">';
    echo '</head><body data-theme="' . clh_h($theme) . '">';
    echo '<a class="skip" href="#contenido">Ir al contenido</a>';
}

function clh_end()
{
    echo '</body></html>';
}

function clh_nav($active)
{
    $user = clh_user();
    $theme = clh_current_theme();
    $items = [
        'herramientas' => ['herramientas.php', 'Herramientas'],
        'precios' => ['precios.php', 'Precios'],
        'ayuda' => ['ayuda.php', 'Ayuda'],
        'taller' => ['taller.php', 'Abrir taller'],
    ];

    echo '<header class="bar"><div class="bar-in">';
    echo '<a class="logo" href="index.php"><img src="assets/mark.png" alt="" width="36" height="40"><span><strong>SubliEdit</strong><small>Taller de imágenes</small></span></a>';
    echo '<nav class="links" aria-label="Secciones">';
    foreach ($items as $key => $item) {
        $class = $key === $active ? ' class="is-on"' : '';
        $current = $key === $active ? ' aria-current="page"' : '';
        echo '<a' . $class . $current . ' href="' . clh_h($item[0]) . '">' . clh_h($item[1]) . '</a>';
    }
    echo '</nav><div class="bar-end">';
    echo '<div class="themes" role="group" aria-label="Color del tema">';
    foreach (clh_themes() as $id => $meta) {
        $on = $id === $theme ? ' is-on' : '';
        echo '<a class="swatch swatch-' . clh_h($id) . $on . '" href="' . clh_h(clh_theme_href($id)) . '" title="' . clh_h($meta['label']) . '" aria-label="Tema ' . clh_h($meta['label']) . '"></a>';
    }
    echo '</div>';
    if ($user) {
        $unread = clh_notice_unread((int) $user['id']);
        $bellLabel = $unread > 0 ? 'Avisos, ' . $unread . ' sin leer' : 'Avisos';
        echo '<a class="bell" href="avisos.php" aria-label="' . clh_h($bellLabel) . '">';
        echo '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 3a5 5 0 0 0-5 5v2.1c0 .7-.2 1.4-.7 2L4.6 14.4A1.6 1.6 0 0 0 5.8 17h12.4a1.6 1.6 0 0 0 1.2-2.6l-1.7-2.3c-.5-.6-.7-1.3-.7-2V8a5 5 0 0 0-5-5zm0 18a2.4 2.4 0 0 0 2.3-1.7H9.7A2.4 2.4 0 0 0 12 21z"/></svg>';
        if ($unread > 0) {
            echo '<span class="bell-count">' . ($unread > 9 ? '9+' : (string) $unread) . '</span>';
        }
        echo '</a>';
        echo '<a class="who" href="cuenta.php"><span>' . clh_h($user['name']) . '</span><small>' . (int) $user['credits'] . ' créditos</small></a>';
        echo '<a class="btn-line" href="salir.php">Salir</a>';
    } else {
        echo '<a class="btn" href="login.php">Entrar</a>';
    }
    echo '</div></div></header>';
}

function clh_legal_nav()
{
    echo '<nav class="legal" aria-label="Información legal">';
    $links = [
        ['ayuda.php', 'Ayuda y contacto', false],
        ['terminos.php', 'Términos y condiciones', false],
        ['privacidad.php', 'Privacidad', false],
        ['arrepentimiento.php', 'Botón de arrepentimiento', true],
        ['baja.php', 'Botón de baja', true],
    ];
    foreach ($links as $link) {
        $class = $link[2] ? ' class="pill"' : '';
        echo '<a' . $class . ' href="' . clh_h($link[0]) . '">' . clh_h($link[1]) . '</a>';
    }
    echo '<a href="https://www.argentina.gob.ar/produccion/defensadelconsumidor" rel="noopener noreferrer">Defensa del Consumidor</a>';
    echo '</nav>';
}

function clh_footer()
{
    echo '<footer class="colophon"><div class="colophon-in">';
    echo '<div><strong>SubliEdit</strong><p>Ocho herramientas para dejar imágenes listas. Fondo y escala con IA. El resto, en esta máquina.</p></div>';
    echo '<div><span>Mapa</span><a href="herramientas.php">Herramientas</a><a href="precios.php">Precios</a><a href="ayuda.php">Ayuda</a><a href="taller.php">Abrir taller</a></div>';
    echo '<div><span>Cuenta y ley</span>';
    clh_legal_nav();
    echo '</div></div>';
    echo '<p class="made">SubliEdit · planes en pesos · el cobro pasa por Mercado Pago</p>';
    echo '</footer>';
}

function clh_csrf_field()
{
    echo '<input type="hidden" name="csrf" value="' . clh_h(clh_csrf_token()) . '">';
}

function clh_honeypot_field()
{
    echo '<div class="hp"><label>Empresa <input type="text" name="company" tabindex="-1" autocomplete="off"></label></div>';
}

function clh_honeypot_tripped()
{
    return trim(isset($_POST['company']) ? (string) $_POST['company'] : '') !== '';
}

function clh_posted_contact()
{
    $name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
    $email = trim(isset($_POST['email']) ? (string) $_POST['email'] : '');
    $message = trim(isset($_POST['message']) ? (string) $_POST['message'] : '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        throw new UserException('Escribí tu nombre.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new UserException('El email no es válido.');
    }
    if (mb_strlen($message) < 8 || mb_strlen($message) > 2000) {
        throw new UserException('El mensaje tiene que explicar un poco mejor el pedido.');
    }
    return ['name' => $name, 'email' => $email, 'message' => $message];
}

function clh_save_solicitud($kind, $name, $email, $message, $reference)
{
    if (!in_array($kind, ['ayuda', 'arrepentimiento', 'baja'], true)) {
        throw new UserException('Tipo de pedido desconocido.');
    }
    $reference = trim((string) $reference);
    if (mb_strlen($reference) > 80) {
        $reference = mb_substr($reference, 0, 80);
    }
    $st = clh_db()->prepare(
        'INSERT INTO solicitudes (kind, name, email, reference_code, message, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$kind, $name, $email, $reference, $message, date('Y-m-d H:i:s')]);
}
