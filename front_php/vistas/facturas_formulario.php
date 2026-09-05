<?php
/**
 * El formulario de una factura: encabezado y detalle, en un solo envío.
 *
 * ======================================================================
 * POR QUÉ EL DETALLE SON CINCO RENGLONES FIJOS
 * ======================================================================
 *
 * Un formulario que deje agregar renglones sin límite necesita JavaScript, y
 * esta versión no lo usa: lo que se ve es PHP y HTML, no magia de un
 * framework (Artículo 2 de la constitución).
 *
 * Así que la pantalla ofrece cinco casillas y **las que queden vacías se
 * ignoran** al armar el cuerpo (`cuerpo_de_factura()` en index.php).
 *
 * Es una limitación de verdad, y está dicha aquí y en la especificación en
 * vez de escondida: una factura de seis productos no cabe en esta pantalla.
 * Se decidió así porque el contenido de la versión es maestro-detalle, no
 * interfaces dinámicas — y porque cinco renglones alcanzan para verlo
 * funcionar. La API sí acepta los que sean: la limitación es de la pantalla.
 *
 * ======================================================================
 * Y POR QUÉ NO HAY «GUARDAR SOLO LO QUE CAMBIÉ»
 * ======================================================================
 *
 * Las demás pantallas tienen dos botones. Ésta tiene uno, porque el recurso
 * no admite PATCH: cambiar un renglón cambia el total y el stock, así que el
 * detalle se reemplaza entero. Un segundo botón habría prometido algo que la
 * API responde con un 405.
 */

// Los renglones que ya tiene la factura (al editar), o ninguno (al crear).
$renglones = $ficha['detalle'] ?? [];
const RENGLONES_FACTURA = 5;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">
      <?= $editando ? 'Editar la factura ' . (int) ($ficha['numero'] ?? 0) : 'Nueva factura' ?>
    </h1>
    <p class="text-body-secondary mb-0">
      <?php if ($editando): ?>
        Se reemplaza el contenido completo: cliente, vendedor y todos los
        renglones. El stock se ajusta solo.
      <?php else: ?>
        El número, el total y los subtotales los pone la base de datos.
      <?php endif; ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary"
     href="<?= $editando ? '/facturas/' . (int) ($ficha['numero'] ?? 0) : '/facturas' ?>">
    Cancelar
  </a>
</div>

<form method="post">

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-transparent"><strong>El encabezado</strong></div>
    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label" for="fkidcliente">Cliente</label>
          <select class="form-select" id="fkidcliente" name="fkidcliente">
            <option value="">— escoja —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>"
                <?= (int) ($ficha['fkidcliente'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                Cliente <?= (int) $c['id'] ?> · persona <?= htmlspecialchars((string) $c['fkcodpersona']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="fkidvendedor">Vendedor</label>
          <select class="form-select" id="fkidvendedor" name="fkidvendedor">
            <option value="">— escoja —</option>
            <?php foreach ($vendedores as $v): ?>
              <option value="<?= (int) $v['id'] ?>"
                <?= (int) ($ficha['fkidvendedor'] ?? 0) === (int) $v['id'] ? 'selected' : '' ?>>
                Vendedor <?= (int) $v['id'] ?> · carné <?= (int) $v['carnet'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <strong>El detalle</strong>
      <span class="text-body-secondary small">
        Deje en blanco los renglones que no use
      </span>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col" style="width: 3rem;">#</th>
            <th scope="col">Producto</th>
            <th scope="col" style="width: 12rem;">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($i = 0; $i < RENGLONES_FACTURA; $i++): ?>
            <?php
            // Lo que ya tenía la factura en ese renglón, si es que lo tenía.
            // Al volver de un error el array trae 'codigo'/'cantidad'; al
            // editar viene de la API con 'codigoProducto'.
            $r = $renglones[$i] ?? [];
            $codigoActual = (string) ($r['codigoProducto'] ?? $r['codigo'] ?? '');
            $cantidadActual = (string) ($r['cantidad'] ?? '');
            ?>
            <tr>
              <td class="text-body-secondary"><?= $i + 1 ?></td>
              <td>
                <select class="form-select" name="detalle_codigo[]">
                  <option value="">— sin producto —</option>
                  <?php foreach ($productos as $p): ?>
                    <option value="<?= htmlspecialchars((string) $p['codigo']) ?>"
                      <?= $codigoActual === (string) $p['codigo'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string) $p['nombre']) ?>
                      — $ <?= htmlspecialchars(number_format((float) $p['valorunitario'], 2, ',', '.')) ?>
                      (quedan <?= (int) $p['stock'] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input class="form-control" type="number" min="1"
                       name="detalle_cantidad[]"
                       value="<?= htmlspecialchars($cantidadActual) ?>">
              </td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php /* UN solo botón. Ver el comentario de arriba: este recurso no
           admite «guardar solo lo que cambié». */ ?>
  <button class="btn btn-primary" type="submit">
    <?= $editando ? 'Reemplazar el contenido de la factura' : 'Crear la factura' ?>
  </button>

  <div class="form-text mt-3">
    Al guardar, la base de datos descuenta el stock de cada producto y
    calcula los subtotales y el total. Si algún producto no tiene unidades
    suficientes, la factura no se guarda y aquí aparece el motivo.
  </div>

</form>
