<?php
/**
 * El formulario de una persona: sirve para agregar y para editar.
 *
 * La diferencia entre los dos usos está en $editando, y se ve en dos sitios:
 * la llave y los botones de guardar.
 */
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
