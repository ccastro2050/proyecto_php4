<?php
/**
 * rutas.php — las interfaces del sistema a las que se da permiso.
 *
 * UN SOLO ARCHIVO POR ENTIDAD, con sus secciones adentro: `$seccion` dice
 * cual se pinta y la pone `index.php` en cada llamada a `pintar()`. Es la
 * forma de las seis pantallas de la v2, y las dos lecciones que costaron
 * siguen valiendo aqui:
 *
 *   · las secciones son `if ... endif` INDEPENDIENTES, no un `if/elseif`:
 *     el HTML de la lista trae su propio `else` —el «todavia no hay nada»— y
 *     PHP empareja por ORDEN, no por intencion;
 *   · se llama `$seccion` y no `$vista`, porque `pintar()` hace
 *     `extract($datos)` y una clave `vista` sobreescribiria el nombre del
 *     archivo.
 *
 * CUIDADO CON LA PALABRA «RUTA», que aqui significa dos cosas distintas y
 * conviene no mezclarlas:
 *
 *   `/factura`    una fila de la tabla `ruta`: el PERMISO que se da a un rol
 *   `/facturas`   la direccion de la pantalla de este front
 *
 * El singular es el permiso; el plural, la pantalla. Y el nombre del permiso
 * tiene que coincidir LETRA POR LETRA con lo que la API consulta: si no
 * coincide, `verificar_acceso_ruta` no lo encuentra y nadie entra —falla
 * cerrado, que es como debe fallar—.
 *
 * Crear una ruta aqui NO le da acceso a nadie: hay que darsela a un rol en
 * <a href="/permisos">Permisos por rol</a>. Una ruta sin rol sale en la
 * consulta «interfaces sin usuarios» del tablero.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Rutas</h1>
    <p class="text-body-secondary mb-0">
      Las interfaces del sistema. Una ruta recién creada no le da acceso a
      nadie hasta que se le asigne un rol.
    </p>
  </div>
  <a class="btn btn-primary" href="/rutas/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Id</th>
            <th scope="col">Ruta</th>
            <th scope="col">Descripción</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td>
                <span class="badge text-bg-secondary font-monospace">
                  <?= (int) $f['id'] ?>
                </span>
              </td>
              <td class="font-monospace"><?= htmlspecialchars((string) $f['ruta']) ?></td>
              <td><?= htmlspecialchars((string) ($f['descripcion'] ?? '')) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/rutas/<?= (int) $f['id'] ?>/editar">Editar</a>
                <form class="d-inline" method="post"
                      action="/rutas/<?= (int) $f['id'] ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar la ruta <?= htmlspecialchars((string) $f['ruta']) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> ruta(s) declarada(s).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay rutas</p>
      <a class="btn btn-primary" href="/rutas/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>


<?php if ($seccion === 'formulario'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $editando ? 'Editar ruta' : 'Agregar ruta' ?></h1>
    <p class="text-body-secondary mb-0">
      El nombre tiene que coincidir letra por letra con lo que la API
      consulta.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/rutas">Volver</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post"
          action="<?= $editando
                      ? '/rutas/' . (int) $ficha['id'] . '/editar'
                      : '/rutas/nuevo' ?>">

      <?php if ($editando): ?>
        <div class="mb-3">
          <label class="form-label">Id</label>
          <input class="form-control font-monospace" type="text" disabled
                 value="<?= (int) $ficha['id'] ?>">
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label" for="ruta">Ruta</label>
        <input class="form-control font-monospace" type="text" id="ruta" name="ruta"
               value="<?= htmlspecialchars((string) ($ficha['ruta'] ?? '')) ?>"
               placeholder="/factura" required>
        <div class="form-text">
          En singular y con la barra al principio, como las 15 que ya existen.
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="descripcion">Descripción</label>
        <input class="form-control" type="text" id="descripcion" name="descripcion"
               value="<?= htmlspecialchars((string) ($ficha['descripcion'] ?? '')) ?>">
      </div>

      <?php if ($editando): ?>
        <button class="btn btn-primary" type="submit" name="verbo" value="completa">
          Guardar la ficha completa
        </button>
        <button class="btn btn-outline-primary" type="submit" name="verbo" value="parcial">
          Guardar solo lo que cambié
        </button>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Agregar</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php endif; ?>
