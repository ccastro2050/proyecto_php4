<?php
/**
 * ServicioConsultas — la capa de negocio de las diez consultas.
 *
 * DIEZ METODOS QUE DELEGAN, y ni uno hace nada mas. Eso no es un descuido:
 * es lo que pasa cuando una operacion no tiene regla de negocio. Un reporte
 * se pide, se devuelve y no hay nada que validar —no entra un dato de
 * nadie—.
 *
 * LA PREGUNTA QUE HAY QUE SABER RESPONDER: si no agrega nada, para que esta.
 * Por dos razones concretas:
 *
 *   1. el controlador depende de `IServicioConsultas` y NO del repositorio;
 *      cambiar de motor —o de la forma de leer, manana— no lo toca;
 *
 *   2. cuando aparezca la primera regla, tiene sitio. Y aparecen: «el
 *      vendedor solo ve sus propias ventas» es una regla de negocio, no de
 *      SQL ni de HTTP, y su lugar es exactamente este archivo.
 *
 * Mientras no la haya, el metodo de una linea es la respuesta honesta.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioConsultas.php';
require_once __DIR__ . '/../repositorios/IRepositorioConsultas.php';

class ServicioConsultas implements IServicioConsultas
{
    public function __construct(
        private readonly IRepositorioConsultas $repositorio,
    ) {
    }

    /** 1. Que se vende y cuanto deja. Cruza producto, renglon, factura y cliente. */
    public function ventasPorProducto(): array
    {
        return $this->repositorio->ventasPorProducto();
    }

    /** 2. Quien compra y cuanto. Cruza cliente, persona, factura y renglon. */
    public function ventasPorCliente(): array
    {
        return $this->repositorio->ventasPorCliente();
    }

    /** 3. Quien vende y cuanto. Cruza vendedor, persona, factura y renglon. */
    public function ventasPorVendedor(): array
    {
        return $this->repositorio->ventasPorVendedor();
    }

    /** 4. Cuanto factura cada empresa. Cruza empresa, cliente, factura y renglon. */
    public function ventasPorEmpresa(): array
    {
        return $this->repositorio->ventasPorEmpresa();
    }

    /** 5. Cuanto vale en promedio una factura de cada vendedor. */
    public function ticketPorVendedor(): array
    {
        return $this->repositorio->ticketPorVendedor();
    }

    /** 6. Lo que NO se ha vendido: LEFT JOIN con HAVING COUNT = 0. */
    public function productosSinVender(): array
    {
        return $this->repositorio->productosSinVender();
    }

    /** 7. Quien anula y por cuanto. La cara B de ventas por cliente. */
    public function anulacionesPorCliente(): array
    {
        return $this->repositorio->anulacionesPorCliente();
    }

    /** 8. A cuantas interfaces llega cada usuario. Cinco tablas del acceso. */
    public function alcanceDeUsuarios(): array
    {
        return $this->repositorio->alcanceDeUsuarios();
    }

    /** 9. Rutas declaradas a las que ningun usuario alcanza. */
    public function interfacesSinUsuarios(): array
    {
        return $this->repositorio->interfacesSinUsuarios();
    }

    /** 10. Credito, consumido y disponible por cliente. */
    public function creditoContraConsumo(): array
    {
        return $this->repositorio->creditoContraConsumo();
    }
}
