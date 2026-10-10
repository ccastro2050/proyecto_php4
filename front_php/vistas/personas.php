<?php
/**
 * personas.php — LA PANTALLA COMPLETA de la persona, en un solo archivo.
 *
 * Las 2 secciones —lista, formulario— viven aqui, y `$seccion` dice cual se
 * pinta: la pone `index.php` en cada llamada a `pintar()`. Si faltara, el
 * valor por omision es `'lista'`, que es la pantalla con la que se entra.
 *
 * POR QUE UN SOLO ARCHIVO: las secciones hablan del MISMO recurso y comparten
 * el titulo, el boton de volver y los nombres de las columnas. Separadas,
 * cambiar una etiqueta obligaba a tocar dos sitios. Es lo que hacen los otros
 * fronts del curso: un `Personas.razor` en Blazor, una plantilla por
 * entidad en Flask.
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
     El listado de personas.
     
     Las columnas están escritas aquí, con sus nombres. No hay una vista
     genérica que recorra una descripción de la tabla: si la hubiera, esta
     pantalla no podría decir lo que solo vale para la persona.
   ====================================================== */ ?>
<?php
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Personas</h1>
    <p class="text-body-secondary mb-0">Los datos de contacto de alguien. Una persona no es todavía ni cliente ni vendedor: es el dato de quién es.</p>
  </div>
  <a class="btn btn-primary" href="/personas/nuevo">Agregar</a>
</div>

<?php if (!empty($filas)): ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Código</th>
            <th scope="col">Nombre</th>
            <th scope="col">Correo</th>
            <th scope="col">Teléfono</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td>
                <span class="badge text-bg-secondary font-monospace">
                  <?= htmlspecialchars((string) $f['codigo']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars((string) $f['nombre']) ?></td>
              <td><?= htmlspecialchars((string) $f['email']) ?></td>
              <td><?= htmlspecialchars((string) $f['telefono']) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary"
                   href="/personas/<?= rawurlencode((string) $f['codigo']) ?>/editar">Editar</a>

                <?php /* POST y no un enlace: un GET que borra lo puede
                         disparar el navegador solo al precargar la página. */ ?>
                <form class="d-inline" method="post"
                      action="/personas/<?= rawurlencode((string) $f['codigo']) ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar <?= htmlspecialchars((string) $f['codigo']) ?>?');">
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
      <p class="fs-5 mb-1">Todavía no hay personas</p>
      <p class="text-body-secondary">Use «Agregar» para crear el primero.</p>
      <a class="btn btn-primary" href="/personas/nuevo">Agregar</a>
    </div>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php if ($seccion === 'formulario'): ?>
<?php /* ======================================================
   EL FORMULARIO
     El formulario de una persona: sirve para agregar y para editar.
     
     La diferencia entre los dos usos está en $editando, y se ve en dos sitios:
     la llave y los botones de guardar.
   ====================================================== */ ?>
<?php
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $editando ? 'Editar' : 'Agregar' ?> la persona</h1>
    <p class="text-body-secondary mb-0">
      <?= $editando ? 'El código identifica la ficha y no se cambia.' : 'El código lo escribe usted y no se podrá cambiar después.' ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/personas">Volver al listado</a>
</div>

<div class="card shadow-sm" style="max-width: 40rem;">
  <div class="card-body p-4">
    <form method="post">

      <?php /* LA LLAVE. Al editar va de solo lectura: es la identidad de la
               ficha. Un campo que el usuario puede escribir pero el sistema
               no puede cambiar es una promesa falsa. */ ?>
      <div class="mb-3">
        <label class="form-label" for="codigo">Código</label>
        <input class="form-control font-monospace" type="text" id="codigo" name="codigo"
               maxlength="10"
               value="<?= htmlspecialchars((string) ($ficha['codigo'] ?? '')) ?>"
               <?= $editando ? 'readonly' : 'required autofocus' ?>>
        <?php if (!$editando): ?>
          <div class="form-text">Hasta 10 caracteres.</div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label" for="nombre">Nombre</label>
        <input class="form-control" type="text" id="nombre" name="nombre" maxlength="100"
               value="<?= htmlspecialchars((string) ($ficha['nombre'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="email">Correo</label>
        <input class="form-control" type="text" id="email" name="email" maxlength="100"
               value="<?= htmlspecialchars((string) ($ficha['email'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="telefono">Teléfono</label>
        <input class="form-control" type="text" id="telefono" name="telefono" maxlength="20"
               value="<?= htmlspecialchars((string) ($ficha['telefono'] ?? '')) ?>">
      </div>

      <hr class="my-4">

      <?php /* ==============================================================
           LOS DOS BOTONES, QUE NO HACEN LO MISMO

             · «Guardar la ficha completa» manda todo, así que un dato
               obligatorio en blanco se rechaza (nombre, correo, teléfono).
             · «Guardar solo lo que cambié» manda únicamente lo diligenciado,
               así que el mismo formulario a medio llenar sí se guarda.

           El mismo formulario, dos comportamientos, y la diferencia no la
           decide ningún `if` de negocio: la decide QUÉ SE ENVÍA.
           ============================================================== */ ?>
      <?php if ($editando): ?>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit" name="verbo" value="completa">
            Guardar la ficha completa
          </button>
          <button class="btn btn-outline-primary" type="submit" name="verbo" value="parcial">
            Guardar solo lo que cambié
          </button>
        </div>
        <div class="form-text mt-3">
          <strong>«La ficha completa»</strong> exige que estén diligenciados
          nombre, correo, teléfono. <strong>«Solo lo que cambié»</strong> guarda lo que
          usted escribió y deja lo demás como estaba.
        </div>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Agregar</button>
      <?php endif; ?>

    </form>
  </div>
</div>
<?php endif; ?>
