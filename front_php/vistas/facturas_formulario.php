<?php
/**
 * facturas_formulario.php — EMITIR UNA FACTURA.
 *
 * La misma pantalla que en los otros tres cursos. Cuatro cosas que no son de
 * adorno:
 *
 *   1. LOS RENGLONES SE AGREGAN DE A UNO. No hay un número fijo de casillas,
 *      porque nadie sabe de antemano cuántas cosas va a vender.
 *
 *   2. EL TOTAL NO SE ENVÍA. Lo que se ve abajo es un cálculo para que la
 *      persona sepa cuánto va — pero lo que queda guardado lo pone el
 *      disparador. Si el front lo enviara habría dos fuentes de verdad, y el
 *      día que no coincidan gana la que nadie revisó.
 *
 *   3. UN SOLO ENVÍO con el maestro y el detalle juntos. Una factura con tres
 *      renglones no son cuatro peticiones: si la tercera fallara quedaría media
 *      factura en la base, y «media factura» no es un estado que el negocio
 *      reconozca.
 *
 *   4. AQUÍ CADA BOTÓN ES UNA PETICIÓN y el borrador vive en `$_SESSION`; en el
 *      front de Blazor vive en el circuito y no hay viaje. Ésa es la única
 *      diferencia entre los dos: lo que se ve y lo que se puede hacer es lo
 *      mismo.
 *
 * Las cuatro acciones del formulario se distinguen por el VALOR del botón que
 * se oprimió (`name="accion"`), no por rutas distintas: agregar, quitar,
 * limpiar y emitir.
 *
 * Variables: $clientes, $vendedores, $productos, $borrador, $total_estimado.
 */

declare(strict_types=1);
?>
<h1 class="h2 mb-3">Facturas</h1>

<form method="post">
  <section class="card mb-4">
    <div class="card-body">
      <h2 class="h5 card-title mb-3">Emitir una factura</h2>

      <?php /* EL MAESTRO: a quién y quién vende */ ?>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="fkidcliente">Cliente</label>
          <select class="form-select" id="fkidcliente" name="fkidcliente">
            <option value="">— elija un cliente —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int) $c['id'] ?>"
                <?= (string) $borrador['cliente'] === (string) $c['id'] ? 'selected' : '' ?>>
                Cliente <?= (int) $c['id'] ?> · persona <?= htmlspecialchars((string) $c['fkcodpersona']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="fkidvendedor">Vendedor</label>
          <select class="form-select" id="fkidvendedor" name="fkidvendedor">
            <option value="">— elija un vendedor —</option>
            <?php foreach ($vendedores as $v): ?>
              <option value="<?= (int) $v['id'] ?>"
                <?= (string) $borrador['vendedor'] === (string) $v['id'] ? 'selected' : '' ?>>
                Vendedor <?= (int) $v['id'] ?> · persona <?= htmlspecialchars((string) $v['fkcodpersona']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr>

      <?php /* EL DETALLE: los renglones, que se arman AQUÍ sin tocar la API */ ?>
      <h2 class="h5">Los renglones</h2>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-7">
          <label class="form-label" for="codigo">Producto</label>
          <select class="form-select" id="codigo" name="codigo">
            <option value="">— elija un producto —</option>
            <?php foreach ($productos as $p): ?>
              <option value="<?= htmlspecialchars((string) $p['codigo']) ?>">
                <?= htmlspecialchars((string) $p['nombre']) ?>
                — $ <?= htmlspecialchars(number_format((float) $p['valorunitario'], 2, ',', '.')) ?>
                (stock <?= (int) $p['stock'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="cantidad">Cantidad</label>
          <input class="form-control" type="number" min="1" value="1" id="cantidad" name="cantidad">
        </div>
        <div class="col-6 col-md-2">
          <?php /* Este botón NO habla con la API: agrega a la tabla de abajo. */ ?>
          <button class="btn btn-outline-primary w-100" name="accion" value="agregar">Agregar al detalle</button>
        </div>
      </div>

      <?php if ($borrador['renglones']): ?>
      <div class="table-responsive mt-3">
        <table class="table table-sm align-middle">
          <thead><tr>
            <th>Producto</th>
            <th class="text-end">Cantidad</th>
            <th class="text-end">Valor unitario</th>
            <th class="text-end">Subtotal</th>
            <th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($borrador['renglones'] as $r): ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['nombre']) ?></td>
              <td class="text-end font-monospace"><?= (int) $r['cantidad'] ?></td>
              <td class="text-end font-monospace">
                $ <?= htmlspecialchars(number_format((float) $r['precio'], 2, ',', '.')) ?>
              </td>
              <td class="text-end font-monospace">
                $ <?= htmlspecialchars(number_format($r['cantidad'] * $r['precio'], 2, ',', '.')) ?>
              </td>
              <td class="text-end">
                <?php /* Quitar un renglón de esta lista tampoco llama a la API:
                         todavía no se ha enviado nada. */ ?>
                <button class="btn btn-sm btn-danger" name="accion" value="quitar"
                        formnovalidate>Quitar</button>
                <input type="hidden" name="quitar" value="<?= htmlspecialchars((string) $r['codigo']) ?>">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end"><strong>Total estimado</strong></td>
              <td class="text-end font-monospace">
                <strong>$ <?= htmlspecialchars(number_format((float) $total_estimado, 2, ',', '.')) ?></strong>
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="small text-body-secondary">
        Este total es un cálculo para que usted sepa cuánto va.
        <strong>No se envía:</strong> el total que queda guardado lo calcula el
        disparador de la base de datos, y es el que manda.
      </p>
      <?php else: ?>
      <div class="alert alert-secondary mt-3 text-center">Agregue al menos un renglón.</div>
      <?php endif; ?>

      <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
        <?php /* UN SOLO ENVÍO con el maestro y el detalle juntos. */ ?>
        <button class="btn btn-primary" name="accion" value="emitir">Emitir la factura</button>
        <button class="btn btn-link" name="accion" value="limpiar"
                formnovalidate>Empezar de nuevo</button>
        <a class="btn btn-link" href="/facturas">Volver a las facturas</a>
      </div>

      <?php if (!$clientes || !$vendedores || !$productos): ?>
      <p class="small text-body-secondary mt-3 mb-0">
        Para emitir una factura hacen falta un <a href="/clientes">cliente</a>, un
        <a href="/vendedores">vendedor</a> y al menos un
        <a href="/productos">producto</a>.
      </p>
      <?php endif; ?>
    </div>
  </section>
</form>
