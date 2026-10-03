<?php
/**
 * El listado de facturas.
 *
 * Tres cosas que esta pantalla tiene y ninguna otra del sistema:
 *   · una columna con el número de renglones (el detalle existe, aunque no
 *     se vea entero aquí);
 *   · un estado que se pinta con color, porque «anulada» no es un detalle;
 *   · y tres acciones distintas — ver, anular y eliminar—, no las dos de
 *     siempre.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Facturas</h1>
    <p class="text-body-secondary mb-0">
      Cada factura es un encabezado y sus renglones. El total lo calcula la
      base de datos: aquí nadie lo escribe.
    </p>
  </div>
  <a class="btn btn-primary" href="/facturas/nueva">Nueva factura</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Número</th>
            <th scope="col">Fecha</th>
            <th scope="col">Cliente</th>
            <th scope="col">Vendedor</th>
            <th scope="col" class="text-end">Renglones</th>
            <th scope="col" class="text-end">Total</th>
            <th scope="col">Estado</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <?php $anulada = ($f['estado'] ?? '') === 'anulada'; ?>
            <tr class="<?= $anulada ? 'text-body-secondary' : '' ?>">
              <td>
                <a class="fw-semibold text-decoration-none"
                   href="/facturas/<?= (int) $f['numero'] ?>">
                  <?= (int) $f['numero'] ?>
                </a>
              </td>
              <td class="text-nowrap">
                <?= htmlspecialchars(str_replace('T', ' ', (string) ($f['fecha'] ?? ''))) ?>
              </td>
              <td><?= htmlspecialchars((string) ($f['nombreCliente'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($f['nombreVendedor'] ?? '—')) ?></td>
              <td class="text-end"><?= count($f['detalle'] ?? []) ?></td>
              <td class="text-end">
                $ <?= htmlspecialchars(number_format((float) ($f['total'] ?? 0), 2, ',', '.')) ?>
              </td>
              <td>
                <span class="badge <?= $anulada ? 'text-bg-warning' : 'text-bg-success' ?>">
                  <?= htmlspecialchars((string) ($f['estado'] ?? '')) ?>
                </span>
              </td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/facturas/<?= (int) $f['numero'] ?>">Ver</a>

                <?php /* Anular solo tiene sentido en una factura activa. Una
                         acción que no se puede hacer no se muestra apagada:
                         no se muestra. */ ?>
                <?php if (!$anulada): ?>
                  <form class="d-inline" method="post"
                        action="/facturas/<?= (int) $f['numero'] ?>/anular"
                        onsubmit="return confirm('¿Anular la factura <?= (int) $f['numero'] ?>? El stock volverá a los productos.');">
                    <button class="btn btn-sm btn-outline-warning" type="submit">Anular</button>
                  </form>
                <?php endif; ?>

                <form class="d-inline" method="post"
                      action="/facturas/<?= (int) $f['numero'] ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar la factura <?= (int) $f['numero'] ?>? Esto la borra de verdad; anular deja el rastro.');">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-body-secondary small mt-3 mb-0">
    <?= count($filas) ?> factura(s). <strong>Anular</strong> deja la factura
    con su número y devuelve el stock; <strong>eliminar</strong> la borra.
  </p>

<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-5 mb-1">Todavía no hay facturas</p>
      <p class="text-body-secondary">
        Para hacer una hacen falta un cliente, un vendedor y al menos un
        producto.
      </p>
      <a class="btn btn-primary" href="/facturas/nueva">Nueva factura</a>
    </div>
  </div>
<?php endif; ?>
