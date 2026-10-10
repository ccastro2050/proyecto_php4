<?php
/**
 * roles.php — los roles del sistema.
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
 * LO QUE ESTA PANTALLA ENSENA DEL MODELO: un rol es una fila con un nombre, y
 * eso es TODO. No tiene permisos adentro ni usuarios adentro: los permisos
 * viven en `rutarol` y los usuarios en `rol_usuario`, cada uno en su
 * pantalla. Meterlos aqui habria sido comodo y habria escondido las dos
 * tablas puente que son el corazon del control de acceso.
 *
 * Y EL ID NO SE ESCRIBE: lo genera la base de datos. En el formulario de
 * agregar no hay campo para el; en el de editar se muestra y no se deja
 * cambiar —una llave que cambia no es una llave—.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Roles</h1>
    <p class="text-body-secondary mb-0">
      Los papeles que puede tener un usuario. Los permisos de cada uno se dan
      en <a href="/permisos">Permisos por rol</a>.
    </p>
  </div>
  <a class="btn btn-primary" href="/roles/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Id</th>
            <th scope="col">Nombre</th>
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
              <td><?= htmlspecialchars((string) $f['nombre']) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/roles/<?= (int) $f['id'] ?>/editar">Editar</a>
                <form class="d-inline" method="post"
                      action="/roles/<?= (int) $f['id'] ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar el rol <?= htmlspecialchars((string) $f['nombre']) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> rol(es).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay roles</p>
      <p class="text-body-secondary">Sin roles, nadie alcanza ninguna ruta.</p>
      <a class="btn btn-primary" href="/roles/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>


<?php if ($seccion === 'formulario'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $editando ? 'Editar rol' : 'Agregar rol' ?></h1>
    <p class="text-body-secondary mb-0">
      <?= $editando
            ? 'El id no se cambia: una llave que cambia no es una llave.'
            : 'El id lo genera la base de datos.' ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/roles">Volver</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post"
          action="<?= $editando
                      ? '/roles/' . (int) $ficha['id'] . '/editar'
                      : '/roles/nuevo' ?>">

      <?php if ($editando): ?>
        <div class="mb-3">
          <label class="form-label">Id</label>
          <input class="form-control font-monospace" type="text" disabled
                 value="<?= (int) $ficha['id'] ?>">
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label class="form-label" for="nombre">Nombre</label>
        <input class="form-control" type="text" id="nombre" name="nombre"
               value="<?= htmlspecialchars((string) ($ficha['nombre'] ?? '')) ?>"
               required>
      </div>

      <?php if ($editando): ?>
        <?php /* Los dos verbos, igual que en las pantallas de la v2. Con un
                 solo campo mandan lo mismo, y siguen separados porque lo que
                 cambia es lo que cada uno PROMETE. */ ?>
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
