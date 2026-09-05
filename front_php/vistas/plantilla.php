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

      <?php /* La clase `active` marca dónde está parado el usuario. Sale de
               $ruta, que es la dirección de verdad — no de una variable que
               alguien tenga que acordarse de cambiar. */ ?>
      <?php /* El menú de la v2. Seis entradas escritas una por una: cuando
               llegue una tabla nueva se agrega AQUÍ y aparece en todas las
               pantallas. La clase `active` sale de $ruta, que es la
               dirección de verdad — no de una variable que alguien tenga que
               acordarse de cambiar. */ ?>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link <?= $ruta === '/' ? 'active' : '' ?>" href="/">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/facturas') ? 'active' : '' ?>"
             href="/facturas">Facturas</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/productos') ? 'active' : '' ?>"
             href="/productos">Productos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/clientes') ? 'active' : '' ?>"
             href="/clientes">Clientes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/vendedores') ? 'active' : '' ?>"
             href="/vendedores">Vendedores</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/personas') ? 'active' : '' ?>"
             href="/personas">Personas</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_starts_with($ruta, '/empresas') ? 'active' : '' ?>"
             href="/empresas">Empresas</a>
        </li>
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
