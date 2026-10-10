<?php
/**
 * permisos.php — que rol alcanza que ruta. La tabla puente `rutarol`.
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
 * TRES ACCIONES Y NO CINCO, Y ESO ES EL TEMA DE LA PANTALLA.
 *
 * Una fila de `rutarol` es una PAREJA de llaves foraneas: (ruta, rol). No
 * tiene ningun dato propio. Y por eso «editar un permiso» no significa nada:
 * cambiarle el rol es QUITAR ese permiso y DAR otro, que son dos operaciones
 * distintas con dos efectos distintos.
 *
 * En la API el PUT y el PATCH de este recurso estan escritos y COMENTADOS,
 * con esta misma razon al lado. La pantalla dice lo mismo con su forma: ver,
 * dar, quitar.
 *
 * EL FORMULARIO VA ARRIBA DE LA LISTA y no en otra pagina, porque dar un
 * permiso es una sola linea: dos desplegables y un boton. Mandar a otra
 * pantalla para escoger dos cosas de dos listas seria un paso de mas.
 *
 * Y LOS DOS DESPLEGABLES SE LLENAN DESDE LA API —`listar_rutas()` y
 * `listar_roles()`—, no con una lista escrita aqui: un rol nuevo aparece en
 * el desplegable sin tocar el front.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="mb-4">
  <h1 class="h3 mb-1">Permisos por rol</h1>
  <p class="text-body-secondary mb-0">
    Qué rol alcanza qué ruta. Es la tabla que la API consulta en cada
    petición: quitar una fila de aquí deja a alguien fuera en la petición
    siguiente, sin esperar a que caduque su token.
  </p>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="h6 text-uppercase text-body-secondary mb-3">Dar un permiso</h2>
    <form method="post" action="/permisos/nuevo" class="row g-2 align-items-end">
      <div class="col-12 col-md-5">
        <label class="form-label" for="fkidruta">Ruta</label>
        <select class="form-select" id="fkidruta" name="fkidruta" required>
          <option value="">— escoja una ruta —</option>
          <?php foreach ($rutas as $r): ?>
            <option value="<?= (int) $r['id'] ?>">
              <?= htmlspecialchars((string) $r['ruta']) ?>
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
    <p class="form-text mb-0 mt-2">
      La pareja repetida responde 409: el permiso ya existe, y darlo dos veces
      no significa nada.
    </p>
  </div>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Ruta</th>
            <th scope="col">Rol</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td class="font-monospace">
                <?= htmlspecialchars((string) ($f['ruta'] ?? $f['fkidruta'])) ?>
              </td>
              <td><?= htmlspecialchars((string) ($f['rol'] ?? $f['fkidrol'])) ?></td>
              <td class="text-end text-nowrap">
                <?php /* La pareja ENTERA va en la direccion: /permisos/4/1.
                         Es la llave de la fila, y una llave compuesta se ve
                         en la URL igual que una simple. */ ?>
                <form class="d-inline" method="post"
                      action="/permisos/<?= (int) $f['fkidruta'] ?>/<?= (int) $f['fkidrol'] ?>/eliminar"
                      onsubmit="return confirm('¿Quitarle <?= htmlspecialchars((string) ($f['ruta'] ?? '')) ?> a <?= htmlspecialchars((string) ($f['rol'] ?? '')) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> permiso(s).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">No hay un solo permiso dado</p>
      <p class="text-body-secondary mb-0">
        Con esta tabla vacía nadie entra a ninguna parte: la API falla
        cerrado.
      </p>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>
