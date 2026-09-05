<?php
/**
 * El formulario de un vendedor: sirve para agregar y para editar.
 *
 * La diferencia entre los dos usos está en $editando, y se ve en dos sitios:
 * la llave y los botones de guardar.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= $editando ? 'Editar' : 'Agregar' ?> el vendedor</h1>
    <p class="text-body-secondary mb-0">
      <?= $editando ? 'El número lo asignó el sistema y no cambia.' : 'El número de la ficha lo asigna el sistema al guardar.' ?>
    </p>
  </div>
  <a class="btn btn-outline-secondary" href="/vendedores">Volver al listado</a>
</div>

<div class="card shadow-sm" style="max-width: 40rem;">
  <div class="card-body p-4">
    <form method="post">

      <?php /* La llave NO aparece como campo: la genera la base de datos. Al
               crear todavía no existe, y al editar no se puede cambiar —
               mostrarla como casilla sería ofrecer algo que no se puede
               hacer. Se muestra arriba, como dato, y ya. */ ?>
      <?php if ($editando): ?>
        <p class="text-body-secondary">
          Ficha número
          <span class="badge text-bg-secondary font-monospace"><?= htmlspecialchars((string) ($ficha['id'] ?? '')) ?></span>
        </p>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label" for="carnet">Carné</label>
        <input class="form-control" type="number" id="carnet" name="carnet" min="0"
               value="<?= htmlspecialchars((string) ($ficha['carnet'] ?? '')) ?>">
        <div class="form-text">El número de carné del vendedor.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="direccion">Dirección</label>
        <input class="form-control" type="text" id="direccion" name="direccion" maxlength="100"
               value="<?= htmlspecialchars((string) ($ficha['direccion'] ?? '')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="fkcodpersona">Persona</label>
        <?php /* Un desplegable, no una casilla de texto: nadie debería tener
                 que ir a otra pantalla a copiar un código.
                 Y ojo: esto NO valida nada. La lista pudo cargarse hace un
                 minuto y esa ficha pudo borrarse entretanto. Quien defiende
                 la integridad sigue siendo la base de datos. */ ?>
        <select class="form-select" id="fkcodpersona" name="fkcodpersona">
          <option value="">— escoja —</option>
          <?php foreach ($personas as $opcion): ?>
            <option value="<?= htmlspecialchars((string) $opcion['codigo']) ?>"
              <?= (string) ($ficha['fkcodpersona'] ?? '') === (string) $opcion['codigo'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $opcion['nombre']) ?>
              (<?= htmlspecialchars((string) $opcion['codigo']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">El código de la persona que es este vendedor.</div>
      </div>

      <hr class="my-4">

      <?php /* ==============================================================
           LOS DOS BOTONES, QUE NO HACEN LO MISMO

             · «Guardar la ficha completa» manda todo, así que un dato
               obligatorio en blanco se rechaza (carné, dirección, persona).
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
          carné, dirección, persona. <strong>«Solo lo que cambié»</strong> guarda lo que
          usted escribió y deja lo demás como estaba.
        </div>
      <?php else: ?>
        <button class="btn btn-primary" type="submit">Agregar</button>
      <?php endif; ?>

    </form>
  </div>
</div>
