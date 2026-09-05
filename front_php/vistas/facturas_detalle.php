<?php
/**
 * Una factura con su detalle: la pantalla que le da sentido a maestro-detalle.
 *
 * Encabezado arriba, renglones abajo, total al pie. Es una sola petición a la
 * API —`GET /api/factura/{numero}`— porque el contrato entrega el detalle
 * anidado dentro del encabezado. Si la API lo devolviera aparte, esta
 * pantalla tendría que hacer dos llamadas y coserlas, y sería peor.
 */
$anulada = ($factura['estado'] ?? '') === 'anulada';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">
      Factura <?= (int) $factura['numero'] ?>
      <span class="badge align-middle <?= $anulada ? 'text-bg-warning' : 'text-bg-success' ?>">
        <?= htmlspecialchars((string) ($factura['estado'] ?? '')) ?>
      </span>
    </h1>
    <p class="text-body-secondary mb-0">
      <?= htmlspecialchars(str_replace('T', ' ', (string) ($factura['fecha'] ?? ''))) ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <?php if (!$anulada): ?>
      <a class="btn btn-outline-primary" href="/facturas/<?= (int) $factura['numero'] ?>/editar">Editar</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="/facturas">Volver al listado</a>
  </div>
</div>

<?php if ($anulada): ?>
  <div class="alert alert-warning">
    Esta factura está <strong>anulada</strong>: el stock ya volvió a los
    productos y su contenido no se puede modificar. Se conserva porque una
    factura anulada sigue siendo parte de la historia del negocio.
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <p class="text-body-secondary small text-uppercase mb-1">Cliente</p>
        <p class="fs-5 mb-0"><?= htmlspecialchars((string) ($factura['nombreCliente'] ?? '—')) ?></p>
        <p class="text-body-secondary small mb-0">
          ficha <?= (int) ($factura['fkidcliente'] ?? 0) ?>
        </p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <p class="text-body-secondary small text-uppercase mb-1">Vendedor</p>
        <p class="fs-5 mb-0"><?= htmlspecialchars((string) ($factura['nombreVendedor'] ?? '—')) ?></p>
        <p class="text-body-secondary small mb-0">
          ficha <?= (int) ($factura['fkidvendedor'] ?? 0) ?>
        </p>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-transparent">
    <strong>El detalle</strong>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col">Producto</th>
          <th scope="col" class="text-end">Cantidad</th>
          <th scope="col" class="text-end">Valor unitario</th>
          <th scope="col" class="text-end">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($factura['detalle'] ?? [] as $linea): ?>
          <tr>
            <td>
              <?= htmlspecialchars((string) $linea['nombreProducto']) ?>
              <span class="badge text-bg-secondary font-monospace ms-1">
                <?= htmlspecialchars((string) $linea['codigoProducto']) ?>
              </span>
            </td>
            <td class="text-end"><?= (int) $linea['cantidad'] ?></td>
            <td class="text-end">
              $ <?= htmlspecialchars(number_format((float) $linea['valorunitario'], 2, ',', '.')) ?>
            </td>
            <td class="text-end">
              $ <?= htmlspecialchars(number_format((float) $linea['subtotal'], 2, ',', '.')) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="3" class="text-end">Total</th>
          <th class="text-end">
            $ <?= htmlspecialchars(number_format((float) ($factura['total'] ?? 0), 2, ',', '.')) ?>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php /* Vale la pena decirlo en la pantalla, no solo en el código: estos
         números no los escribió nadie. */ ?>
<p class="text-body-secondary small mt-3">
  Los subtotales y el total los calculó la base de datos al guardar los
  renglones, y el stock de cada producto se descontó en ese mismo momento.
  Ni el formulario ni la API los escriben.
</p>
