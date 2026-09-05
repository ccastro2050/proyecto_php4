<?php
/**
 * La pantalla de inicio: dice qué es esto y a dónde se puede entrar.
 *
 * Las tarjetas están agrupadas como está el negocio, no como está la base de
 * datos: primero lo que se hace todos los días (facturar), después el
 * catálogo, y al final las fichas de las que todo lo demás depende. Un menú
 * ordenado por orden alfabético o por orden de creación de las tablas es un
 * menú ordenado para quien lo programó.
 */
?>
<div class="p-4 p-md-5 mb-4 bg-white border rounded-3 shadow-sm">
  <h1 class="display-6 fw-semibold">Sistema de facturas</h1>
  <p class="fs-5 text-body-secondary mb-0">
    Seis recursos, y una factura que es dos tablas a la vez: el encabezado y
    sus renglones.
  </p>
</div>

<h2 class="h5 text-body-secondary text-uppercase mb-3">Lo que se hace a diario</h2>
<div class="row g-3 mb-4">
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
    </div>
  </div>

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
    </div>
  </div>
</div>

<h2 class="h5 text-body-secondary text-uppercase mb-3">Quiénes participan</h2>
<div class="row g-3 mb-4">
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
    </div>
  </div>

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
    </div>
  </div>
</div>

<h2 class="h5 text-body-secondary text-uppercase mb-3">
  Las fichas de las que dependen las demás
</h2>
<div class="row g-3">
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
    </div>
  </div>

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
    </div>
  </div>
</div>
