<?php
/**
 * usuarios_roles.php — que rol tiene cada usuario. La tabla puente `rol_usuario`.
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
 * EL OTRO PUENTE, con la misma forma que `permisos.php` y por la misma
 * razon: una fila es la pareja (usuario, rol) y no tiene datos propios, asi
 * que se da o se quita. Tres acciones, no cinco.
 *
 * Y AQUI SE VE EL CAMINO COMPLETO DEL CONTROL DE ACCESO, que son dos saltos:
 *
 *     usuario --(rol_usuario)--> rol --(rutarol)--> ruta
 *
 * Esta pantalla hace el primero; <a href="/permisos">Permisos por rol</a>
 * hace el segundo. Quien quiera verlos juntos tiene la consulta «alcance de
 * usuarios» en el tablero, que recorre las cinco tablas de un tiron.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="mb-4">
  <h1 class="h3 mb-1">Asignar roles</h1>
  <p class="text-body-secondary mb-0">
    Qué rol tiene cada usuario. Es el primero de los dos saltos:
    <span class="font-monospace">usuario → rol → ruta</span>.
  </p>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="h6 text-uppercase text-body-secondary mb-3">Dar un rol</h2>
    <form method="post" action="/usuarios-roles/nuevo" class="row g-2 align-items-end">
      <div class="col-12 col-md-5">
        <label class="form-label" for="fkemail">Usuario</label>
        <select class="form-select" id="fkemail" name="fkemail" required>
          <option value="">— escoja un usuario —</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= htmlspecialchars((string) $u['email']) ?>">
              <?= htmlspecialchars((string) $u['email']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <label class="form-label" for="fkidrol">Rol</label>
        <select class="form-select" id="fkidrol" name="fkidrol" required>
          <option value="">— escoja un rol —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>">
              <?= htmlspecialchars((string) $r['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100" type="submit">Dar</button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Usuario</th>
            <th scope="col">Rol</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td class="font-monospace"><?= htmlspecialchars((string) $f['fkemail']) ?></td>
              <td><?= htmlspecialchars((string) ($f['rol'] ?? $f['fkidrol'])) ?></td>
              <td class="text-end text-nowrap">
                <form class="d-inline" method="post"
                      action="/usuarios-roles/<?= rawurlencode((string) $f['fkemail']) ?>/<?= (int) $f['fkidrol'] ?>/eliminar"
                      onsubmit="return confirm('¿Quitarle el rol a <?= htmlspecialchars((string) $f['fkemail']) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> asignación(es).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Ningún usuario tiene rol</p>
      <p class="text-body-secondary mb-0">
        Un usuario sin rol entra —la contraseña es correcta— y no alcanza
        ninguna ruta: 401 no, 403 en todo.
      </p>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>
