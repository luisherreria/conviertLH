<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Términos y condiciones — ConvertLH');
clh_nav('');
?>
<main class="site prose" id="contenido">
  <p class="kicker">Términos y condiciones</p>
  <h1>Cómo se usa ConvertLH.</h1>
  <p>Estos términos son de ConvertLH, el taller de imágenes de esta instalación. No reemplazan el asesoramiento de un abogado. La ley aplicable es la de la República Argentina.</p>

  <h2>El servicio</h2>
  <p>ConvertLH ofrece cinco herramientas: quitar fondo, escalar, convertir, recortar y espejar. Quitar fondo y escalar usan modelos de IA a través de Replicate. Convertir, recortar y espejar se procesan en el servidor donde está instalado el taller.</p>
  <p>El resultado depende de la imagen de origen. No prometemos un archivo listo para imprimir en todos los casos, ni un tiempo fijo de respuesta.</p>

  <h2>La cuenta</h2>
  <p>Para crear una cuenta hace falta un nombre, un email y una contraseña. La cuenta nueva entra en el plan Mesa. Ese plan habilita convertir, recortar y espejar. Los planes Puesto, Cuadrilla y Planta agregan quitar fondo y escalar, con una cantidad mensual de créditos.</p>
  <p>Sos responsable de la contraseña y de lo que se suba desde tu sesión.</p>

  <h2>Créditos y precios</h2>
  <p>Quitar fondo usa 2 créditos. Escalar 2× usa 2 y escalar 4× usa 4. Convertir, recortar y espejar no usan créditos. Los precios publicados están en pesos argentinos y son una lista: Mercado Pago todavía no está conectado, así que elegir un plan pago no genera un cobro ni activa ese plan.</p>
  <p>Cuando el cobro exista, los créditos del plan se van a cargar según lo que diga la oferta vigente en ese momento. El saldo y cada movimiento van a quedar en la cuenta.</p>

  <h2>Archivos</h2>
  <p>Subís imágenes que tenés derecho a usar. No uses el taller para archivos ajenos, ilegales o que violen derechos de otra persona.</p>
  <p>Los archivos se procesan para generar la respuesta y se borran al terminar. No armamos un archivo histórico de tus imágenes. Hay un barrido de temporales viejos por si un proceso queda a medias.</p>
  <p>Quitar fondo y escalar envían la imagen a Replicate. Esa parte del trabajo sigue las condiciones de ese proveedor, además de estas.</p>

  <h2>Límites</h2>
  <p>Hay un tope de archivos por envío, de peso y de megapíxeles. El GIF no conserva la animación. TIFF, ICO y AVIF no están disponibles en este PHP.</p>

  <h2>Pagos, arrepentimiento y baja</h2>
  <p>Cuando haya pagos, se van a procesar en pesos con Mercado Pago. El botón de arrepentimiento permite revocar la aceptación de una compra dentro de los 10 días corridos, conforme el artículo 34 de la Ley 24.240. El botón de baja cancela la cuenta y, cuando exista, la suscripción.</p>
  <p>Hoy podés dejar ambos pedidos en los formularios. Quedan guardados para revisarlos.</p>

  <h2>Responsabilidad</h2>
  <p>El taller se ofrece en el estado en que está. No respondemos por pérdida de un encargo, por un recorte que no te sirva o por una interrupción del proveedor de IA. El tope, si correspondiera responder, no supera lo que hayas pagado por el servicio en el mes del hecho. Si no hubo pago, no hay un monto que reintegrar por esa vía.</p>

  <h2>Cambios</h2>
  <p>Estos términos se pueden actualizar. La versión que ves en esta pantalla es la que está vigente en esta instalación.</p>
</main>
<?php
clh_footer();
clh_end();
