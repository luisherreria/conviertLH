<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Términos y condiciones — SubliEdit');
clh_nav('');
?>
<main class="site prose" id="contenido">
  <p class="kicker">Términos y condiciones</p>
  <h1>Cómo se usa SubliEdit.</h1>
  <p>Estos términos son de SubliEdit, el taller de imágenes de esta instalación. No reemplazan el asesoramiento de un abogado. La ley aplicable es la de la República Argentina.</p>

  <h2>El servicio</h2>
  <p>SubliEdit ofrece siete herramientas: quitar fondo, escalar, convertir, recortar, espejar, semitono y crear mockup. Quitar fondo y escalar usan modelos de IA a través de Replicate. Crear mockup usa 1 crédito y se arma en este servidor. Convertir, recortar, espejar y semitono se procesan en el servidor donde está instalado el taller.</p>
  <p>El resultado depende de la imagen de origen. No prometemos un archivo listo para imprimir en todos los casos, ni un tiempo fijo de respuesta.</p>

  <h2>La cuenta</h2>
  <p>Para crear una cuenta hace falta un nombre, un email y una contraseña. La cuenta nueva entra en el plan Mesa. Ese plan habilita convertir, recortar y espejar. Los planes Puesto, Cuadrilla y Planta agregan quitar fondo y escalar, con una cantidad mensual de créditos.</p>
  <p>Sos responsable de la contraseña y de lo que se suba desde tu sesión.</p>

  <h2>Créditos y precios</h2>
  <p>Quitar fondo usa 2 créditos. Escalar 2× usa 2 y escalar 4× usa 4. Crear mockup usa 1 crédito. Convertir, recortar, espejar y semitono no usan créditos. Los precios publicados están en pesos argentinos. Elegir un plan pago abre Mercado Pago. El plan y los créditos del mes se cargan cuando ese pago queda aprobado, por el monto de la oferta vigente en ese momento. El saldo y cada movimiento quedan en la cuenta.</p>
  <p>El descuento de créditos al procesar cada imagen todavía no está activo.</p>

  <h2>Archivos</h2>
  <p>Subís imágenes que tenés derecho a usar. No uses el taller para archivos ajenos, ilegales o que violen derechos de otra persona.</p>
  <p>Los archivos se procesan para generar la respuesta y se borran al terminar. No armamos un archivo histórico de tus imágenes. Hay un barrido de temporales viejos por si un proceso queda a medias.</p>
  <p>Quitar fondo y escalar envían la imagen a Replicate. Esa parte del trabajo sigue las condiciones de ese proveedor, además de estas.</p>

  <h2>Límites</h2>
  <p>Quitar fondo y escalar aceptan hasta 3 imágenes de 8 MB. Convertir, recortar, espejar, semitono y crear mockup aceptan una imagen de 8 MB. El GIF no conserva la animación. TIFF, ICO y AVIF no están disponibles en este PHP.</p>

  <h2>Pagos, arrepentimiento y baja</h2>
  <p>Los planes pagos se procesan en pesos con Mercado Pago. El botón de arrepentimiento permite revocar la aceptación de una compra dentro de los 10 días corridos, conforme el artículo 34 de la Ley 24.240. El botón de baja cancela la cuenta.</p>
  <p>Hoy podés dejar ambos pedidos en los formularios. Quedan guardados para revisarlos.</p>

  <h2>Responsabilidad</h2>
  <p>El taller se ofrece en el estado en que está. No respondemos por pérdida de un encargo, por un recorte que no te sirva o por una interrupción del proveedor de IA. El tope, si correspondiera responder, no supera lo que hayas pagado por el servicio en el mes del hecho. Si no hubo pago, no hay un monto que reintegrar por esa vía.</p>

  <h2>Cambios</h2>
  <p>Estos términos se pueden actualizar. La versión que ves en esta pantalla es la que está vigente en esta instalación.</p>
</main>
<?php
clh_footer();
clh_end();
