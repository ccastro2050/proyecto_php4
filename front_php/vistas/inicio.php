<?php
/**
 * La pantalla de inicio: dice qué es esto y a dónde se puede entrar.
 *
 * Las tarjetas están agrupadas como está el negocio, no como está la base de
 * datos: primero lo que se hace todos los días (facturar), después el
 * catálogo, y al final las fichas de las que todo lo demás depende. Un menú
 * ordenado por orden alfabético o por orden de creación de las tablas es un
 * menú ordenado para quien lo programó.
 *
 * ======================================================================
 * CADA TARJETA SE PINTA SI LA API DIJO QUE ESA PERSONA ALCANZA LA RUTA
 * ======================================================================
 *
 * Con `puede('/producto')`, igual que el menú de la barra. Y eso llegó
 * tarde: en la primera versión de la v3 la barra ya se recortaba y **esta
 * pantalla seguía ofreciendo las seis tarjetas**. El enlace llevaba a una
 * pantalla que responde «su rol no tiene permiso»: ningún dato se escapó
 * —la API niega—, pero una interfaz que ofrece lo que después rechaza está
 * mal hecha.
 *
 * La lección, que es la del control de acceso entero: **el permiso no se
 * comprueba en UN sitio del front, se comprueba en TODOS los que ofrecen
 * algo.** Son tres aquí: la barra, estas tarjetas y los botones de cada
 * pantalla. Olvidar uno es fácil — y por eso la defensa de verdad no está en
 * ninguno de los tres, sino en la API, que no depende de que una plantilla
 * se acuerde.
 */
?>
<div class="p-4 p-md-5 mb-4 bg-white border rounded-3 shadow-sm">
  <h1 class="display-6 fw-semibold">Sistema de facturas</h1>
  <p class="fs-5 text-body-secondary mb-0">
    Once recursos, diez consultas y un control de acceso que se comprueba en
    cada petición. La factura sigue siendo dos tablas a la vez: el encabezado
    y sus renglones.
  </p>
  <p class="text-body-secondary small mb-0 mt-2">
    Está dentro como
    <strong><?= htmlspecialchars((string) ($sesion['email'] ?? '')) ?></strong>
    <?php if (!empty($sesion['roles'])): ?>
      · <?= htmlspecialchars(implode(' · ', $sesion['roles'])) ?>
    <?php endif; ?>
    · alcanza <?= count($permisos) ?>
    <?= count($permisos) === 1 ? 'ruta' : 'rutas' ?> de las 15 declaradas.
  </p>
</div>

<?php /* ==================================================================
     EL TABLERO, LA PANTALLA QUE ESTRENA LA v4

     Va de primera y sola, porque es la unica que no sirve para escribir:
     responde preguntas. Y pide `/home`, el permiso de entrar al aplicativo,
     que hoy tienen los cinco roles — asi que esta tarjeta la ve todo el que
     entre.
     ================================================================== */ ?>
<?php if (puede('/home')): ?>
<h2 class="h5 text-body-secondary text-uppercase mb-3">Lo que la versión 4 agrega</h2>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card h-100 shadow-sm border-success-subtle">
      <div class="card-body">
        <h3 class="card-title h5">Tablero · diez consultas</h3>
        <p class="card-text text-body-secondary mb-0">
          Qué se vende y cuánto deja, quién compra, quién vende, el ticket
          promedio, lo que nunca se vendió, las anulaciones, a cuántas
          interfaces llega cada usuario y cuánto crédito queda. Cada una cruza
          cuatro o más tablas: son las preguntas que ningún endpoint del CRUD
          puede responder.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-success" href="/tablero">Ver el tablero</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<h2 class="h5 text-body-secondary text-uppercase mb-3">Lo que se hace a diario</h2>
<div class="row g-3 mb-4">
  <?php if (puede('/factura')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-primary-subtle">
      <div class="card-body">
        <h3 class="card-title h5">Facturas</h3>
        <p class="card-text text-body-secondary">
          El encabezado y su detalle, en una sola pantalla. Al guardar, la
          base de datos calcula el total y descuenta el stock; anular una
          factura devuelve las unidades y deja el rastro.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-primary" href="/facturas">Ver las facturas</a>
      </div>
  <?php endif; ?>
    </div>
  </div>

  <?php if (puede('/producto')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body">
        <h3 class="card-title h5">Productos</h3>
        <p class="card-text text-body-secondary">
          El catálogo del que se surten las facturas: código, nombre, stock y
          valor unitario.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-outline-primary" href="/productos">Ver el catálogo</a>
      </div>
  <?php endif; ?>
    </div>
  </div>
</div>

<h2 class="h5 text-body-secondary text-uppercase mb-3">Quiénes participan</h2>
<div class="row g-3 mb-4">
  <?php if (puede('/cliente')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body">
        <h3 class="card-title h5">Clientes</h3>
        <p class="card-text text-body-secondary">
          Quién compra, con su cupo de crédito. Cada cliente es una persona, y
          puede pertenecer a una empresa.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-outline-primary" href="/clientes">Ver los clientes</a>
      </div>
  <?php endif; ?>
    </div>
  </div>

  <?php if (puede('/vendedor')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body">
        <h3 class="card-title h5">Vendedores</h3>
        <p class="card-text text-body-secondary">
          Quién factura, con su carné y su dirección. También es una persona,
          vista desde otro papel.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-outline-primary" href="/vendedores">Ver los vendedores</a>
      </div>
  <?php endif; ?>
    </div>
  </div>
</div>

<h2 class="h5 text-body-secondary text-uppercase mb-3">
  Las fichas de las que dependen las demás
</h2>
<div class="row g-3">
  <?php if (puede('/persona')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body">
        <h3 class="card-title h5">Personas</h3>
        <p class="card-text text-body-secondary">
          Los datos de contacto. Una persona no es todavía ni cliente ni
          vendedor: es el dato de quién es. Por eso no se puede eliminar
          mientras alguna de las dos cosas la esté usando.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-outline-primary" href="/personas">Ver las personas</a>
      </div>
  <?php endif; ?>
    </div>
  </div>

  <?php if (puede('/empresa')): ?>
  <div class="col-md-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body">
        <h3 class="card-title h5">Empresas</h3>
        <p class="card-text text-body-secondary">
          Las compañías a las que puede pertenecer un cliente. Es opcional:
          un cliente puede comprar a título propio.
        </p>
      </div>
      <div class="card-footer bg-transparent border-0 pb-3">
        <a class="btn btn-outline-primary" href="/empresas">Ver las empresas</a>
      </div>
  <?php endif; ?>
    </div>
  </div>
</div>
