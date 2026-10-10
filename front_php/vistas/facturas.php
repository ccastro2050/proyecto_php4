<?php
/**
 * facturas.php — LA PANTALLA COMPLETA de la factura, en un solo archivo.
 *
 * Las 3 secciones —lista, formulario, detalle— viven aqui, y `$seccion` dice cual se
 * pinta: la pone `index.php` en cada llamada a `pintar()`. Si faltara, el
 * valor por omision es `'lista'`, que es la pantalla con la que se entra.
 *
 * POR QUE UN SOLO ARCHIVO: las secciones hablan del MISMO recurso y comparten
 * el titulo, el boton de volver y los nombres de las columnas. Separadas,
 * cambiar una etiqueta obligaba a tocar dos sitios. Es lo que hacen los otros
 * fronts del curso: un `Facturas.razor` en Blazor, una plantilla por
 * entidad en Flask.
 * ES LA ENTIDAD QUE MAS SE GANA CON ESTO, porque la factura no es un CRUD:
 * se emite, se consulta y se anula. Las tres pantallas son tres caras de la
 * misma operacion, y antes vivian en tres archivos — para seguir un renglon
 * habia que abrir los tres.
 *
 * DOS DECISIONES DE LA UNION, Y LAS DOS SE APRENDIERON FALLANDO
 * -------------------------------------------------------------
 * **Las secciones son `if ... endif` INDEPENDIENTES, no un `if/elseif/else`.**
 * El HTML de la lista trae su propio `<?php else: ?>` —el «todavia no hay
 * nada»—, y PHP empareja por ORDEN, no por intencion: ese `else` interior
 * cerraba el `if` de la seccion y la siguiente quedaba huerfana. Con cada
 * bloque cerrado por su `endif`, lo de adentro no puede confundirse con lo de
 * afuera.
 *
 * **Se llama `$seccion` y no `$vista`.** `pintar(string $vista, array $datos)`
 * ya usa `$vista` para el nombre del archivo, y por dentro hace
 * `extract($datos)`: mandar una clave `vista` SOBREESCRIBIA el parametro y la
 * plantilla acababa pidiendo `vistas/lista.php`. Es el riesgo de `extract()`
 * —mete en el entorno variables cuyos nombres uno no controla— y falla de
 * golpe el dia que dos coinciden.
 */
declare(strict_types=1);

$seccion = $seccion ?? 'lista';
?>

<?php if ($seccion === 'lista'): ?>
<?php /* ======================================================
   EL LISTADO
     El listado de facturas.
     
     Tres cosas que esta pantalla tiene y ninguna otra del sistema:
     · una columna con el número de renglones (el detalle existe, aunque no
     se vea entero aquí);
     · un estado que se pinta con color, porque «anulada» no es un detalle;
     · y tres acciones distintas — ver, anular y eliminar—, no las dos de
     siempre.
   ====================================================== */ ?>
<?php
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
<?php endif; ?>

<?php if ($seccion === 'formulario'): ?>
<?php /* ======================================================
   EL FORMULARIO
     facturas_formulario.php — EMITIR UNA FACTURA.
     
     La misma pantalla que en los otros tres cursos. Cuatro cosas que no son de
     adorno:
     
     1. LOS RENGLONES SE AGREGAN DE A UNO. No hay un número fijo de casillas,
     porque nadie sabe de antemano cuántas cosas va a vender.
     
     2. EL TOTAL NO SE ENVÍA. Lo que se ve abajo es un cálculo para que la
     persona sepa cuánto va — pero lo que queda guardado lo pone el
     disparador. Si el front lo enviara habría dos fuentes de verdad, y el
     día que no coincidan gana la que nadie revisó.
     
     3. UN SOLO ENVÍO con el maestro y el detalle juntos. Una factura con tres
     renglones no son cuatro peticiones: si la tercera fallara quedaría media
     factura en la base, y «media factura» no es un estado que el negocio
     reconozca.
     
     4. AQUÍ CADA BOTÓN ES UNA PETICIÓN y el borrador vive en `$_SESSION`; en el
     front de Blazor vive en el circuito y no hay viaje. Ésa es la única
     diferencia entre los dos: lo que se ve y lo que se puede hacer es lo
     mismo.
     
     Las cuatro acciones del formulario se distinguen por el VALOR del botón que
     se oprimió (`name="accion"`), no por rutas distintas: agregar, quitar,
     limpiar y emitir.
     
     Variables: $clientes, $vendedores, $productos, $borrador, $total_estimado.
   ====================================================== */ ?>
<?php


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
<?php endif; ?>

<?php if ($seccion === 'detalle'): ?>
<?php /* ======================================================
   EL DETALLE
     Una factura con su detalle: la pantalla que le da sentido a maestro-detalle.
     
     Encabezado arriba, renglones abajo, total al pie. Es una sola petición a la
     API —`GET /api/factura/{numero}`— porque el contrato entrega el detalle
     anidado dentro del encabezado. Si la API lo devolviera aparte, esta
     pantalla tendría que hacer dos llamadas y coserlas, y sería peor.
   ====================================================== */ ?>
<?php
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
<?php endif; ?>
