<?php
/**
 * plantilla.php — el MARCO de todas las pantallas.
 *
 * Todo lo que se repite vive aquí una sola vez: la cabecera, el menú, los
 * avisos y el pie. Cada vista aporta solo su parte, y `index.php` la mete en
 * el hueco del `require $contenido`.
 *
 * Si mañana se agrega una entrada al menú, se agrega AQUÍ y aparece en todas
 * las pantallas. Ése es el motivo de que exista este archivo.
 *
 * El aspecto lo pone **Bootstrap**, y la hoja está en `publico/`, no en
 * internet: el salón puede quedarse sin red y la pantalla se sigue viendo
 * igual. `estilos.css` va después y solo trae los pocos retoques del
 * proyecto — el orden importa, porque lo último que se carga es lo que manda.
 */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Facturas</title>
  <link rel="stylesheet" href="/publico/bootstrap.min.css">
  <link rel="stylesheet" href="/publico/estilos.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">

  <nav class="navbar navbar-expand navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/">
        Facturas
        <span class="fs-6 fw-normal text-white-50 ms-2 d-none d-sm-inline">
          Empresa de Ejemplo
        </span>
      </a>

      <?php /* ==============================================================
           EL MENU DE LA v4, Y COMO SE DECIDE CADA ENTRADA

           Cada entrada se pinta SI la API dijo que esta persona alcanza esa
           ruta. La lista viene de `GET /api/permisos/mios` y se guardo en la
           sesion al entrar; aqui solo se consulta con `puede()`.

           **ESCONDER UN ENLACE NO ES CONTROL DE ACCESO.** Esto es comodidad:
           no ofrecer un boton que va a responder 403. Quien escriba la
           direccion a mano llega igual al enrutador, y la API le contesta
           403 porque consulta `verificar_acceso_ruta` en la base de datos en
           CADA peticion. Las dos cosas son necesarias y hacen cosas
           distintas: una cuida la experiencia, la otra cuida los datos.

           Y el nombre del permiso es el de la tabla `ruta`, letra por letra
           —`/producto`, no `/productos`—: el plural es la direccion de la
           PANTALLA, el singular es el nombre del PERMISO. Confundirlos deja
           el menu vacio y cuesta un rato encontrarlo.

           La clase `active` sale de $ruta, que es la direccion de verdad, no
           de una variable que alguien tenga que acordarse de cambiar.
           ============================================================== */ ?>
      <ul class="navbar-nav ms-auto">
        <?php if (!empty($sesion['email'])): ?>

          <li class="nav-item">
            <a class="nav-link <?= $ruta === '/' ? 'active' : '' ?>" href="/">Inicio</a>
          </li>

          <?php if (puede('/home')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/tablero') ? 'active' : '' ?>"
                 href="/tablero">Tablero</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/factura')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/facturas') ? 'active' : '' ?>"
                 href="/facturas">Facturas</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/producto')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/productos') ? 'active' : '' ?>"
                 href="/productos">Productos</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/cliente')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/clientes') ? 'active' : '' ?>"
                 href="/clientes">Clientes</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/vendedor')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/vendedores') ? 'active' : '' ?>"
                 href="/vendedores">Vendedores</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/persona')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/personas') ? 'active' : '' ?>"
                 href="/personas">Personas</a>
            </li>
          <?php endif; ?>

          <?php if (puede('/empresa')): ?>
            <li class="nav-item">
              <a class="nav-link <?= str_starts_with($ruta, '/empresas') ? 'active' : '' ?>"
                 href="/empresas">Empresas</a>
            </li>
          <?php endif; ?>

          <?php /* ----------------------------------------------------------
               LA ADMINISTRACION, EN UN MENU APARTE

               Cinco pantallas que casi nadie ve —solo el Administrador tiene
               `/usuario`, `/rol`, `/ruta` y `/permiso`— y que no tienen por
               que ocupar la barra de todos. Se agrupan en un desplegable, y
               el desplegable entero desaparece si no se alcanza ninguna.
               ---------------------------------------------------------- */ ?>
          <?php $admin = puede('/usuario') || puede('/rol') || puede('/ruta') || puede('/permiso'); ?>
          <?php if ($admin): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= preg_match('#^/(usuarios|roles|rutas|permisos)#', $ruta) ? 'active' : '' ?>"
                 href="#" role="button" data-bs-toggle="dropdown">Administrar</a>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php if (puede('/usuario')): ?>
                  <li><a class="dropdown-item" href="/usuarios">Usuarios</a></li>
                  <li><a class="dropdown-item" href="/usuarios-con-roles">Usuarios con sus roles</a></li>
                  <li><a class="dropdown-item" href="/usuarios-roles">Asignar roles</a></li>
                <?php endif; ?>
                <?php if (puede('/rol')): ?>
                  <li><a class="dropdown-item" href="/roles">Roles</a></li>
                <?php endif; ?>
                <?php if (puede('/ruta')): ?>
                  <li><a class="dropdown-item" href="/rutas">Rutas</a></li>
                <?php endif; ?>
                <?php if (puede('/permiso')): ?>
                  <li><a class="dropdown-item" href="/permisos">Permisos por rol</a></li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>

          <?php /* ----------------------------------------------------------
               QUIEN ESTA DENTRO, Y LA SALIDA

               El correo y los roles a la vista: sin eso, dos pestanas con dos
               cuentas distintas son indistinguibles —y es exactamente lo que
               uno hace para probar el 403—.

               Y salir es un BOTON dentro de un formulario, no un enlace: un
               GET que cierra la sesion lo dispara cualquier cosa que cargue
               una direccion, desde una imagen hasta el prefetch del
               navegador.
               ---------------------------------------------------------- */ ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <?= htmlspecialchars((string) $sesion['email']) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="px-3 py-1 small text-body-secondary">
                <?= $sesion['roles'] === []
                      ? 'sin roles'
                      : htmlspecialchars(implode(' · ', $sesion['roles'])) ?>
              </li>
              <li class="px-3 pb-1 small text-body-secondary">
                alcanza <?= count($permisos) ?>
                <?= count($permisos) === 1 ? 'ruta' : 'rutas' ?>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="/salir" class="px-3 py-1">
                  <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                    Salir
                  </button>
                </form>
              </li>
            </ul>
          </li>

        <?php else: ?>
          <?php /* Sin sesion solo se sirve /entrar, asi que el menu no tiene
                   a donde llevar. */ ?>
          <li class="nav-item">
            <span class="navbar-text small">sin sesión</span>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>

  <main class="container flex-grow-1 py-4">

    <?php /* ==============================================================
         LOS AVISOS

         Dos fuentes distintas, y el usuario no tiene por qué notar la
         diferencia:

           · $aviso  — lo que dejó la pantalla ANTERIOR («Se agregó…»),
                       que viajó en la sesión porque hubo una redirección;
           · $errores — lo que acaba de responder la API en ESTA petición.

         Los dos se pintan igual: en español y sin números de estado.
         ============================================================== */ ?>

    <?php if (!empty($aviso)): ?>
      <?php foreach ($aviso['mensajes'] as $mensaje): ?>
        <div class="alert alert-<?= $aviso['tipo'] === 'exito' ? 'success' : 'danger' ?> alert-dismissible fade show">
          <?= htmlspecialchars((string) $mensaje) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
      <div class="alert alert-danger">
        <p class="fw-semibold mb-1">No se pudo completar la operación:</p>
        <ul class="mb-0">
          <?php foreach ($errores as $error): ?>
            <li><?= htmlspecialchars((string) $error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php require $contenido; ?>

  </main>

  <footer class="border-top bg-white py-3">
    <div class="container">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <p class="text-body-secondary small mb-0">
          Sistema de facturas — ejemplo de referencia del curso ·
          versión 4: el mismo sistema contra tres motores
        </p>

        <?php /* ==============================================================
             EL MOTOR ACTIVO

             Está aquí, en letra pequeña y al pie, y ese sitio es la mitad del
             mensaje: **al usuario no le importa**. Puede usar el sistema
             entero sin mirarlo nunca.

             Se muestra porque la ruta de versiones promete que todo funciona
             igual contra los TRES motores, y una promesa así hay que poder
             comprobarla: cambie `MOTOR`, refresque, y verá cambiar esta
             etiqueta **y nada más**.
             ============================================================== */ ?>
        <?php
        // El nombre bonito, no el valor de la variable de entorno: al usuario
        // «sqlserver» no le dice nada y «SQL Server» sí. Es lo único que esta
        // plantilla sabe de los motores, y es cómo se escriben.
        $nombresDeMotor = [
            'mariadb'   => 'MariaDB',
            'postgres'  => 'PostgreSQL',
            'sqlserver' => 'SQL Server',
        ];
        ?>
        <?php if (!empty($motor)): ?>
          <span class="badge text-bg-light border font-monospace" title="El motor de base de datos que está usando la API">
            <?= htmlspecialchars($nombresDeMotor[$motor] ?? $motor) ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </footer>

  <?php /* Lo ÚNICO de JavaScript en todo el front, y solo para que la «x» de
           los avisos los cierre. Las pantallas funcionan completas sin él:
           quítelo y todo sigue andando, solo que los avisos no se cierran a
           mano. Eso es lo que quiere decir que el front no dependa de JS. */ ?>
  <script src="/publico/bootstrap.bundle.min.js"></script>

</body>
</html>
