<?php
/**
 * usuarios_con_roles.php — el usuario y sus roles en una sola operacion. El recurso de la v4.
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
 * LA PANTALLA QUE ESCRIBE EN DOS TABLAS CON UNA PETICION, y la unica del
 * front donde eso pasa. Detras hay un procedimiento almacenado con
 * TRANSACCION, y es lo que esta pantalla permite comprobar:
 *
 *   · crear un usuario con dos roles es UNA peticion, no tres;
 *   · y si uno de los roles no existe, **no se queda el usuario creado sin
 *     roles**: el procedimiento deshace todo. Eso se midio, y la primera
 *     version de MariaDB no lo hacia —un PATCH fallido dejaba al
 *     administrador sin un solo rol—.
 *
 * LOS ROLES SON CASILLAS Y NO UN DESPLEGABLE porque son VARIOS a la vez: un
 * usuario puede ser Administrador y Cajero. Un `select` de uno solo diria lo
 * contrario, y un `select multiple` se usa peor con el raton.
 *
 * Y LAS CASILLAS VIENEN MARCADAS con lo que el usuario ya tiene, porque el
 * formulario REEMPLAZA la lista entera: si salieran vacias, guardar le
 * quitaria todos sus roles sin avisar. Es el riesgo de un formulario que
 * reemplaza en vez de agregar, y la defensa es mostrar el estado actual.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Usuarios con sus roles</h1>
    <p class="text-body-secondary mb-0">
      Una consulta y no N+1: el procedimiento trae cada usuario con su lista
      de roles ya armada.
    </p>
  </div>
  <a class="btn btn-primary" href="/usuarios-con-roles/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Correo</th>
            <th scope="col">Roles</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td class="font-monospace"><?= htmlspecialchars((string) $f['email']) ?></td>
              <td>
                <?php if (empty($f['roles'])): ?>
                  <span class="badge text-bg-warning">sin roles</span>
                <?php else: ?>
                  <?php foreach ($f['roles'] as $rol): ?>
                    <span class="badge text-bg-primary">
                      <?= htmlspecialchars((string) $rol['nombre']) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/usuarios-con-roles/<?= rawurlencode((string) $f['email']) ?>/editar">Editar</a>
                <form class="d-inline" method="post"
                      action="/usuarios-con-roles/<?= rawurlencode((string) $f['email']) ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar <?= htmlspecialchars((string) $f['email']) ?> y todos sus roles?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> usuario(s).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay usuarios</p>
      <a class="btn btn-primary" href="/usuarios-con-roles/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>


<?php if ($seccion === 'formulario'): ?>

<?php
// Los ids que el usuario YA tiene, para marcar las casillas. La API devuelve
// los roles como lista de objetos {idrol, nombre}; aqui solo hacen falta los
// ids, y se sacan con una sola pasada.
$marcados = [];
foreach ((array) ($ficha['roles'] ?? []) as $rol) {
    $marcados[] = (int) (is_array($rol) ? ($rol['idrol'] ?? 0) : $rol);
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">
      <?= $editando ? 'Editar usuario y sus roles' : 'Agregar usuario con sus roles' ?>
    </h1>
    <p class="text-body-secondary mb-0">
      Una sola petición escribe en las dos tablas, y un procedimiento con
      transacción se encarga de que no quede a medias.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/usuarios-con-roles">Volver</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post"
          action="<?= $editando
                      ? '/usuarios-con-roles/' . rawurlencode((string) $ficha['email']) . '/editar'
                      : '/usuarios-con-roles/nuevo' ?>">

      <div class="mb-3">
        <label class="form-label" for="email">Correo</label>
        <input class="form-control font-monospace" type="email" id="email" name="email"
               value="<?= htmlspecialchars((string) ($ficha['email'] ?? '')) ?>"
               <?= $editando ? 'disabled' : 'required' ?>>
        <?php if ($editando): ?>
          <div class="form-text">
            El correo es la llave: no se cambia desde esta pantalla.
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-4">
        <label class="form-label" for="contrasena">Contraseña</label>
        <input class="form-control" type="password" id="contrasena" name="contrasena"
               autocomplete="new-password" <?= $editando ? '' : 'required' ?>>
        <div class="form-text">
          <?= $editando
                ? 'Vacía = no se toca. Es la convención del procedimiento, y el repositorio la respeta.'
                : 'Mínimo 6 caracteres. La API la hashea con bcrypt costo 12.' ?>
        </div>
      </div>

      <fieldset class="mb-4">
        <legend class="form-label">Roles</legend>
        <?php /* Casillas y no un desplegable: son varios a la vez. Y vienen
                 marcadas con lo que ya tiene, porque guardar REEMPLAZA la
                 lista entera. */ ?>
        <?php foreach ($roles as $rol): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   id="rol<?= (int) $rol['id'] ?>" name="roles[]"
                   value="<?= (int) $rol['id'] ?>"
                   <?= in_array((int) $rol['id'], $marcados, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="rol<?= (int) $rol['id'] ?>">
              <?= htmlspecialchars((string) $rol['nombre']) ?>
              <span class="text-body-secondary small">· id <?= (int) $rol['id'] ?></span>
            </label>
          </div>
        <?php endforeach; ?>
        <div class="form-text">
          Lo que quede marcado es la lista COMPLETA: lo que se destilde se
          quita.
        </div>
      </fieldset>

      <?php if ($editando): ?>
        <button class="btn btn-primary" type="submit" name="verbo" value="completa">
          Guardar contraseña y roles
        </button>
        <button class="btn btn-outline-primary" type="submit" name="verbo" value="parcial">
          Guardar solo lo que cambié
        </button>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Crear con sus roles</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php endif; ?>
