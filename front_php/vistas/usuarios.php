<?php
/**
 * usuarios.php — las cuentas, y la pantalla donde se ve que el front no guarda contrasenas.
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
 * TRES COSAS QUE ESTA PANTALLA DEMUESTRA, y las tres se pueden comprobar
 * mirando el HTML con «ver codigo fuente»:
 *
 *   1. LA LISTA NO TRAE CONTRASENAS. No es que la pantalla las esconda: la
 *      API no las devuelve. Pida `GET /api/usuario` en Postman y vera solo
 *      el correo. La columna existe en la tabla y no sale de ahi.
 *
 *   2. LA CONTRASENA NO VUELVE A LA PANTALLA. Si el intento falla, el correo
 *      se repinta —escribirlo otra vez es molesto y no es secreto— y la
 *      contrasena NO: ni en el campo, ni en un `hidden`, ni en la sesion.
 *
 *   3. EL HASH NO SE HACE AQUI. Viaja en claro por el cuerpo del POST hasta
 *      la API, que la hashea con bcrypt costo 12 antes de guardarla. Hacer
 *      el hash en el front seria peor: el hash pasaria a SER la contrasena
 *      —quien lo copie entra— y la API no podria imponer su costo.
 *
 * Y LA CUARTA, que es de diseno: aqui se crea la cuenta y nada mas. Los
 * roles se dan en <a href="/usuarios-roles">Asignar roles</a>, o las dos
 * cosas de un golpe en <a href="/usuarios-con-roles">Usuarios con sus
 * roles</a>, que es el recurso de la v4.
 */
?>

<?php if ($seccion === 'lista'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Usuarios</h1>
    <p class="text-body-secondary mb-0">
      Las cuentas que pueden entrar. La lista no trae contraseñas porque la
      API no las devuelve.
    </p>
  </div>
  <a class="btn btn-primary" href="/usuarios/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Correo</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td class="font-monospace"><?= htmlspecialchars((string) $f['email']) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/usuarios/<?= rawurlencode((string) $f['email']) ?>/editar">Editar</a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="/usuarios-con-roles/<?= rawurlencode((string) $f['email']) ?>/editar">Roles</a>
                <form class="d-inline" method="post"
                      action="/usuarios/<?= rawurlencode((string) $f['email']) ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar <?= htmlspecialchars((string) $f['email']) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> cuenta(s).</p>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay usuarios</p>
      <a class="btn btn-primary" href="/usuarios/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>

<?php endif; ?>


<?php if ($seccion === 'formulario'): ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">
      <?= $editando ? 'Editar usuario' : 'Agregar usuario' ?>
    </h1>
    <p class="text-body-secondary mb-0">
      La API hashea la contraseña con bcrypt antes de guardarla.
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/usuarios">Volver</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post"
          action="<?= $editando
                      ? '/usuarios/' . rawurlencode((string) $ficha['email']) . '/editar'
                      : '/usuarios/nuevo' ?>">

      <div class="mb-3">
        <label class="form-label" for="email">Correo</label>
        <input class="form-control font-monospace" type="email" id="email" name="email"
               value="<?= htmlspecialchars((string) ($ficha['email'] ?? '')) ?>"
               <?= $editando ? '' : 'required' ?>>
        <?php if ($editando): ?>
          <div class="form-text">
            Al «guardar la ficha completa» este correo reemplaza al actual.
            Con «guardar solo lo que cambié» no se toca.
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-4">
        <label class="form-label" for="contrasena">Contraseña</label>
        <?php /* Sin `value`, siempre: la contraseña no se repinta nunca. */ ?>
        <input class="form-control" type="password" id="contrasena" name="contrasena"
               autocomplete="new-password" <?= $editando ? '' : 'required' ?>>
        <div class="form-text">
          Mínimo 6 caracteres. <?= $editando
            ? 'Déjela vacía y use «solo lo que cambié» para no tocarla.'
            : '' ?>
        </div>
      </div>

      <?php if ($editando): ?>
        <?php /* ============================================================
             AQUI EL PUT Y EL PATCH SE DIFERENCIAN DE VERDAD, y es el mejor
             ejemplo del proyecto: el PUT reemplaza la ficha —correo Y
             contrasena— y el PATCH cambia SOLO la contrasena, que es lo que
             uno quiere casi siempre.
             ============================================================ */ ?>
        <button class="btn btn-primary" type="submit" name="verbo" value="completa">
          Guardar la ficha completa
        </button>
        <button class="btn btn-outline-primary" type="submit" name="verbo" value="parcial">
          Cambiar solo la contraseña
        </button>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Agregar</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php endif; ?>
