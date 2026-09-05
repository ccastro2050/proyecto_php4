<?php
/**
 * El listado de vendedores.
 *
 * Las columnas están escritas aquí, con sus nombres. No hay una vista
 * genérica que recorra una descripción de la tabla: si la hubiera, esta
 * pantalla no podría decir lo que solo vale para el vendedor.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Vendedores</h1>
    <p class="text-body-secondary mb-0">Quien factura. También es una persona, vista desde otro papel.</p>
  </div>
  <a class="btn btn-primary" href="/vendedores/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Id</th>
            <th scope="col" class="text-end">Carné</th>
            <th scope="col">Dirección</th>
            <th scope="col">Persona</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td>
                <span class="badge text-bg-secondary font-monospace">
                  <?= htmlspecialchars((string) $f['id']) ?>
                </span>
              </td>
              <td class="text-end"><?= htmlspecialchars((string) $f['carnet']) ?></td>
              <td><?= htmlspecialchars((string) $f['direccion']) ?></td>
              <td><?= htmlspecialchars((string) $f['fkcodpersona']) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/vendedores/<?= rawurlencode((string) $f['id']) ?>/editar">Editar</a>

                <?php /* POST y no un enlace: un GET que borra lo puede
                         disparar el navegador solo al precargar la página. */ ?>
                <form class="d-inline" method="post"
                      action="/vendedores/<?= rawurlencode((string) $f['id']) ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar <?= htmlspecialchars((string) $f['id']) ?>?');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-body-secondary small mt-3 mb-0"><?= count($filas) ?> ficha(s).</p>

<?php else: ?>
  <?php /* Vacío NO es un error, y la pantalla lo distingue: si la API está
           caída, arriba hay además un aviso rojo. */ ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay vendedores</p>
      <p class="text-body-secondary">Use «Agregar» para crear el primero.</p>
      <a class="btn btn-primary" href="/vendedores/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>
