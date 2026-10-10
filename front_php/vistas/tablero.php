<?php
/**
 * tablero.php — las DIEZ consultas de la v4, en pantalla.
 *
 * UN SOLO ARCHIVO PARA LAS DOS SECCIONES, igual que las pantallas de las
 * entidades: `$seccion` dice si se pinta el índice de las diez o una
 * consulta con sus filas.
 *
 * ======================================================================
 * LA TABLA SE PINTA SIN SABER QUÉ COLUMNAS TRAE, Y HAY QUE EXPLICARLO
 * ======================================================================
 *
 * Las seis pantallas del CRUD tienen sus columnas escritas una por una, en
 * español y en el orden que decidió quien hizo la pantalla. Esta no: recorre
 * las claves de la primera fila y arma el encabezado con ellas.
 *
 * ¿No es eso lo «genérico» que el proyecto rechaza en la API? No, y la
 * diferencia es exacta:
 *
 *   · una ficha de producto tiene CAMPOS: se escriben, se validan, se
 *     traducen y cada uno necesita su etiqueta y su tipo de control;
 *   · una fila de reporte es el RESULTADO DE UNA PREGUNTA. El nombre de su
 *     columna es parte de la respuesta —`ticket_promedio`, `sus_roles`— y no
 *     hay nada que escribir en ella.
 *
 * Escribir a mano diez encabezados que nadie va a editar sería diez sitios
 * donde equivocarse cuando una consulta cambie de columna. Y el costo de
 * esta decisión también se dice: **si el SQL renombra una columna, aquí
 * cambia el título de la tabla sin que nadie se entere.** Por eso existe
 * `prueba_consultas_tres_motores.php`, que comprueba los nombres contra el
 * contrato en los tres motores.
 *
 * LOS NÚMEROS SE ALINEAN A LA DERECHA Y SE FORMATEAN, que es lo único que
 * esta vista interpreta: un total de siete cifras sin separadores de miles
 * no se lee. Y se formatea AQUÍ y no en la API, porque el punto y la coma
 * son cosa del idioma de quien mira, no del dato.
 */
?>

<?php if ($seccion === 'indice'): ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
      <h1 class="h3 mb-1">Tablero</h1>
      <p class="text-body-secondary mb-0">
        Las diez consultas de la versión 4. Cada una cruza cuatro o más
        tablas: son las preguntas que ningún endpoint del CRUD puede
        responder.
      </p>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($consultas as $c): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <a class="card h-100 text-decoration-none shadow-sm"
           href="/tablero/<?= htmlspecialchars((string) $c['nombre']) ?>">
          <div class="card-body">
            <h2 class="h6 mb-1"><?= htmlspecialchars((string) $c['titulo']) ?></h2>
            <p class="small text-body-secondary font-monospace mb-0">
              <?= htmlspecialchars((string) $c['nombre']) ?>
            </p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($consultas === []): ?>
    <p class="text-body-secondary">No se pudo leer el índice de consultas.</p>
  <?php endif; ?>

<?php endif; ?>


<?php if ($seccion === 'consulta'): ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1"><?= htmlspecialchars((string) $titulo) ?></h1>
      <p class="text-body-secondary small font-monospace mb-0">
        GET /api/consultas/<?= htmlspecialchars((string) $nombre) ?>
      </p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/tablero">Las diez</a>
  </div>

  <?php /* El menú de las diez, también aquí: cambiar de consulta sin volver
           atrás. Marca la que se está viendo. */ ?>
  <div class="mb-3 d-flex flex-wrap gap-1">
    <?php foreach ($consultas as $c): ?>
      <a class="btn btn-sm <?= ($c['nombre'] ?? '') === $nombre ? 'btn-primary' : 'btn-outline-primary' ?>"
         href="/tablero/<?= htmlspecialchars((string) $c['nombre']) ?>">
        <?= htmlspecialchars((string) $c['titulo']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($filas === []): ?>

    <?php /* ============================================================
         CERO FILAS NO ES UN ERROR, y decirlo es parte de la pantalla.

         Tres de las diez preguntan por lo que NO existe —productos sin
         vender, facturas anuladas, rutas sin usuarios—, y con los datos
         sembrados pueden estar vacías con toda la razón. Una pantalla que
         dijera «error» ahí estaría mintiendo.
         ============================================================ */ ?>
    <div class="alert alert-secondary">
      <p class="mb-1 fw-semibold">La consulta corrió y no devolvió filas.</p>
      <p class="mb-0 small">
        No es un error. Tres de las diez preguntan por lo que <em>no</em>
        existe —productos que nunca se vendieron, facturas anuladas, rutas a
        las que no llega nadie—: con los datos sembrados pueden estar vacías.
        Cree una factura y anúlela, y
        <code>anulaciones-por-cliente</code> responderá con una fila.
      </p>
    </div>

  <?php else: ?>

    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <?php foreach (array_keys($filas[0]) as $columna): ?>
                <?php
                // El guion bajo del SQL pasa a espacio, y la primera letra en
                // mayúscula: `ticket_promedio` -> «Ticket promedio». Es lo
                // único que la vista le hace al nombre de la columna.
                $titulo_col = ucfirst(str_replace('_', ' ', (string) $columna));
                ?>
                <th class="text-nowrap"><?= htmlspecialchars($titulo_col) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filas as $fila): ?>
              <tr>
                <?php foreach ($fila as $valor): ?>
                  <?php
                  // PDO devuelve los DECIMAL como TEXTO —«7500000.00»—, no
                  // como número: es una diferencia real de PHP que conviene
                  // conocer. `is_numeric` acepta las dos formas, así que la
                  // alineación y el formato funcionan igual.
                  $es_numero = is_numeric($valor);
                  $texto = $valor === null
                      ? '—'
                      : ($es_numero
                          ? number_format((float) $valor, (fmod((float) $valor, 1.0) === 0.0 ? 0 : 2), ',', '.')
                          : (string) $valor);
                  ?>
                  <td class="<?= $es_numero ? 'text-end font-monospace text-nowrap' : '' ?>">
                    <?= htmlspecialchars($texto) ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white small text-body-secondary">
        <?= count($filas) ?> <?= count($filas) === 1 ? 'fila' : 'filas' ?> ·
        <?= count($filas[0]) ?> columnas, tal como las devolvió la consulta
      </div>
    </div>

  <?php endif; ?>

<?php endif; ?>
