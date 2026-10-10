<?php
/**
 * IRepositorioConsultas — el contrato de las DIEZ consultas de la v4.
 *
 * Diez metodos, ningun parametro, y los tres motores lo cumplen entero. Lo
 * que la interfaz consigue aqui es lo mismo que en los otros recursos: el
 * servicio pide «ventas por producto» y NO sabe si debajo hay GROUP_CONCAT o
 * STRING_AGG. Dos de las diez se escriben distinto segun el motor —y una de
 * esas dos, mal escrita, miente sin fallar—, y nada de eso sube de esta
 * capa.
 *
 * NO DEVUELVEN OBJETOS DEL DOMINIO, y es la diferencia con los otros ocho
 * repositorios: una consulta de reporte no es una ficha de `Producto`, es
 * una fila ancha armada con columnas de cuatro o cinco tablas. Forzarla a
 * una clase obligaria a inventar una clase por consulta —diez clases que
 * nadie reutiliza—. Aqui la fila viaja como arreglo asociativo, igual que
 * llega del motor.
 *
 * Eso tiene un costo honesto: **el nombre de cada columna pasa a ser parte
 * del contrato sin que ningun tipo lo vigile.** Si una consulta cambia
 * `ingreso` por `total`, el front deja de mostrar la columna y nadie se
 * enterara hasta que alguien mire la pantalla. En el gemelo .NET el
 * compilador avisa; aqui avisa la prueba.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IRepositorioConsultas
{
    /**
     * 1. Que se vende y cuanto deja. Cruza producto, renglon, factura y cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorProducto(): array;

    /**
     * 2. Quien compra y cuanto. Cruza cliente, persona, factura y renglon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorCliente(): array;

    /**
     * 3. Quien vende y cuanto. Cruza vendedor, persona, factura y renglon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorVendedor(): array;

    /**
     * 4. Cuanto factura cada empresa. Cruza empresa, cliente, factura y renglon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorEmpresa(): array;

    /**
     * 5. Cuanto vale en promedio una factura de cada vendedor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ticketPorVendedor(): array;

    /**
     * 6. Lo que NO se ha vendido: LEFT JOIN con HAVING COUNT = 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public function productosSinVender(): array;

    /**
     * 7. Quien anula y por cuanto. La cara B de ventas por cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function anulacionesPorCliente(): array;

    /**
     * 8. A cuantas interfaces llega cada usuario. Cinco tablas del acceso.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alcanceDeUsuarios(): array;

    /**
     * 9. Rutas declaradas a las que ningun usuario alcanza.
     *
     * @return array<int, array<string, mixed>>
     */
    public function interfacesSinUsuarios(): array;

    /**
     * 10. Credito, consumido y disponible por cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function creditoContraConsumo(): array;
}
