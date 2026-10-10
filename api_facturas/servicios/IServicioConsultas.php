<?php
/**
 * IServicioConsultas — el contrato que ve el controlador.
 *
 * Es gemelo del repositorio, y conviene decir por que existe si no agrega un
 * solo metodo: **para que el controlador no conozca al repositorio.** El dia
 * que una consulta necesite una regla —redondear, filtrar por rol de quien
 * pregunta, juntar dos consultas en una pantalla— esa regla tiene un sitio
 * donde entrar sin tocar ni el motor ni el HTTP.
 *
 * En los otros ocho recursos el servicio ya se gano el sueldo: valida,
 * hashea, decide el 404. Aqui todavia no tiene nada que hacer, y aun asi se
 * escribe. **La arquitectura no se arma el dia que hace falta.**
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IServicioConsultas
{
    /** 1. Que se vende y cuanto deja. Cruza producto, renglon, factura y cliente. */
    public function ventasPorProducto(): array;

    /** 2. Quien compra y cuanto. Cruza cliente, persona, factura y renglon. */
    public function ventasPorCliente(): array;

    /** 3. Quien vende y cuanto. Cruza vendedor, persona, factura y renglon. */
    public function ventasPorVendedor(): array;

    /** 4. Cuanto factura cada empresa. Cruza empresa, cliente, factura y renglon. */
    public function ventasPorEmpresa(): array;

    /** 5. Cuanto vale en promedio una factura de cada vendedor. */
    public function ticketPorVendedor(): array;

    /** 6. Lo que NO se ha vendido: LEFT JOIN con HAVING COUNT = 0. */
    public function productosSinVender(): array;

    /** 7. Quien anula y por cuanto. La cara B de ventas por cliente. */
    public function anulacionesPorCliente(): array;

    /** 8. A cuantas interfaces llega cada usuario. Cinco tablas del acceso. */
    public function alcanceDeUsuarios(): array;

    /** 9. Rutas declaradas a las que ningun usuario alcanza. */
    public function interfacesSinUsuarios(): array;

    /** 10. Credito, consumido y disponible por cliente. */
    public function creditoContraConsumo(): array;
}
