<?php
/**
 * entrar.php — la puerta de la calle.
 *
 * ======================================================================
 * LO QUE ESTA PANTALLA TIENE DE DISTINTO A LAS DEMÁS
 * ======================================================================
 *
 * Es la única que se sirve SIN sesión, y por eso está en la lista blanca del
 * enrutador. Todo lo demás pasa por la guardia.
 *
 * Y hay cuatro decisiones aquí que se ven poco y pesan:
 *
 *   1. `type="password"` — el navegador no muestra lo que se escribe. Es lo
 *      mínimo, y aun así hay formularios que lo olvidan.
 *
 *   2. EL CAMPO DE LA CONTRASEÑA SALE VACÍO cuando el intento falla. El
 *      correo sí se repinta —escribirlo otra vez es molesto y no es
 *      secreto—; la contraseña no vuelve nunca a la pantalla, ni dentro de
 *      un campo oculto. Si volviera, quedaría en el HTML que cualquiera ve
 *      con «ver código fuente».
 *
 *   3. `autocomplete` — `username` y `current-password` son los valores que
 *      los gestores de contraseñas entienden. No es decoración: sin ellos,
 *      el gestor guarda basura o no ofrece nada, y la gente acaba usando
 *      contraseñas más fáciles.
 *
 *   4. NO DICE CUÁL DE LOS DOS DATOS FALLÓ. El mensaje de la API es «el
 *      correo o la contraseña no son correctos», y el front lo repite tal
 *      cual. Decir «ese correo no existe» le confirma a quien prueba al azar
 *      que los demás correos sí existen.
 *
 * ======================================================================
 * LAS CREDENCIALES DE PRUEBA ESTÁN A LA VISTA, Y ESO ES PARTE DEL CURSO
 * ======================================================================
 *
 * Están impresas abajo porque este es un proyecto de aula y sin ellas no se
 * puede comprobar un solo criterio del control de acceso: un hash no se
 * puede deshacer. En un sistema de verdad esta tarjeta no existe.
 *
 * Y las tres que están puestas son las tres que enseñan el 403:
 * `admin` entra a las 15 rutas, `vendedor1` a tres, `cliente1` a dos.
 */
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-7 col-lg-5">

    <div class="card shadow-sm">
      <div class="card-body p-4">

        <h1 class="h4 mb-1">Entrar</h1>
        <p class="text-body-secondary small mb-4">
          El sistema de facturas pide identificarse desde la versión 3.
        </p>

        <form method="post" action="/entrar" novalidate>

          <div class="mb-3">
            <label class="form-label" for="email">Correo</label>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?= htmlspecialchars((string) ($email ?? '')) ?>"
                   autocomplete="username" autofocus required>
          </div>

          <div class="mb-4">
            <label class="form-label" for="contrasena">Contraseña</label>
            <?php /* Sin `value`: la contraseña no se repinta nunca. */ ?>
            <input class="form-control" type="password" id="contrasena"
                   name="contrasena" autocomplete="current-password" required>
          </div>

          <button class="btn btn-primary w-100" type="submit">Entrar</button>
        </form>

      </div>
    </div>

    <?php /* ==================================================================
         LA TARJETA DE PRUEBAS

         Existe porque el proyecto es de aula. Las tres cuentas que están
         aquí son las que permiten comprobar el 403 **sin tocar la base de
         datos**: entre como `vendedor1`, escriba a mano la dirección
         `/usuarios` y vea qué contesta.
         ================================================================== */ ?>
    <div class="card mt-3 border-0 bg-body-tertiary">
      <div class="card-body">
        <p class="small fw-semibold text-body-secondary mb-2">
          Cuentas de prueba del curso
        </p>
        <table class="table table-sm small mb-2">
          <thead>
            <tr><th>Correo</th><th>Contraseña</th><th>Alcanza</th></tr>
          </thead>
          <tbody class="font-monospace">
            <tr>
              <td>admin@correo.com</td><td>admin123</td>
              <td class="font-monospace">las 15 rutas</td>
            </tr>
            <tr>
              <td>vendedor1@correo.com</td><td>vendedor123</td>
              <td>facturas, clientes</td>
            </tr>
            <tr>
              <td>cliente1@correo.com</td><td>cliente123</td>
              <td>solo productos</td>
            </tr>
          </tbody>
        </table>
        <p class="small text-body-secondary mb-0">
          Entre con una de las dos últimas y escriba a mano
          <code>/usuarios</code>: la API responde 403 aunque el menú no
          muestre el enlace. <strong>Esconder un botón no es control de
          acceso.</strong>
        </p>
      </div>
    </div>

  </div>
</div>
