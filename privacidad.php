<?php

require_once __DIR__ . '/src/Site.php';

clh_head('Privacidad — ConvertLH');
clh_nav('');
?>
<main class="site prose" id="contenido">
  <p class="kicker">Privacidad</p>
  <h1>Qué datos quedan y cuáles no.</h1>
  <p>ConvertLH trata datos personales según la Ley 25.326 de la República Argentina. Esta pantalla explica qué guarda esta instalación.</p>

  <h2>Cuenta</h2>
  <p>Si te registrás, guardamos nombre, email, la contraseña hasheada, el plan, el saldo de créditos, el tema de color y la fecha de alta. La contraseña no se puede leer de vuelta: solo se verifica.</p>

  <h2>Pedidos</h2>
  <p>Ayuda, botón de arrepentimiento y botón de baja guardan nombre, email, el texto del pedido y, si lo cargaste, una referencia de la operación. Sirven para responderte. No los usamos para armar una lista de publicidad.</p>

  <h2>Imágenes</h2>
  <p>La imagen se usa para generar el resultado y se borra al responder. No la guardamos en un álbum ni la usamos para entrenar un modelo propio.</p>
  <p>Quitar fondo y escalar envían el archivo a Replicate, que hace ese procesamiento. Convertir, recortar, espejar y semitono no salen de esta máquina.</p>

  <h2>Cookies</h2>
  <p>Hay una cookie de sesión para saber si entraste, y otra para el tema de color (naranja, cobre o ámbar). No usamos cookies de publicidad ni de medición de terceros.</p>

  <h2>Quién los ve</h2>
  <p>Los datos de la cuenta y los pedidos quedan en la base de esta instalación. No los vendemos. Replicate ve la imagen solo cuando pedís quitar fondo o escalar. Si más adelante el pago pasa por Mercado Pago, ese proveedor va a ver los datos del cobro.</p>

  <h2>Tus derechos</h2>
  <p>Podés pedir acceso, corrección o baja de la cuenta desde el formulario de ayuda o con el botón de baja. La baja deja la cuenta inactiva si el email coincide con la sesión abierta. La Agencia de Acceso a la Información Pública es la autoridad de aplicación de la Ley 25.326.</p>
</main>
<?php
clh_footer();
clh_end();
