<?php
/**
 * IRepositorioFactura — el CONTRATO de la capa de datos de las facturas.
 *
 * FÍJESE EN QUE NO SON LOS MISMOS CINCO MÉTODOS DE LAS DEMÁS ENTIDADES, Y
 * ÉSA ES LA LECCIÓN.
 *
 * Empresa, persona, cliente y vendedor son fichas: se listan, se leen, se
 * crean, se corrigen y se borran. Una factura no se comporta así:
 *
 *   · no se «actualiza un campo»: se reemplaza con su detalle completo,
 *     porque cambiar un renglón cambia el total y el stock;
 *   · se **anula**, que no es lo mismo que borrarla — la factura anulada
 *     sigue existiendo, con su número y su historia;
 *   · y sí se puede borrar, pero es otra operación, con otro sentido.
 *
 * Copiar el CRUD de las demás entidades aquí habría sido más cómodo y
 * habría descrito mal el negocio. La interfaz dice lo que la factura HACE.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Factura.php';

interface IRepositorioFactura
{
    /**
     * Todas las facturas con su detalle.
     * @return Factura[]
     */
    public function obtenerTodas(): array;

    /** La factura con su detalle, o null si ese número no existe. */
    public function obtenerPorNumero(int $numero): ?Factura;

    /**
     * Crea la factura y sus renglones, y devuelve la factura ya guardada
     * —con su número, su total calculado y su detalle—.
     *
     * @param array $renglones Lista de ['codigo' => 'PR001', 'cantidad' => 2]
     */
    public function crear(int $idCliente, int $idVendedor, array $renglones): Factura;

    /**
     * Reemplaza el contenido de la factura: cliente, vendedor y **todo** el
     * detalle. No es un PATCH — no existe «cambiar solo un renglón».
     */
    public function reemplazar(int $numero, int $idCliente, int $idVendedor, array $renglones): Factura;

    /**
     * Anula la factura: la deja en estado 'anulada' y **devuelve el stock**
     * a los productos. La fila no se borra.
     */
    public function anular(int $numero): array;

    /** Borra la factura y su detalle. Devuelve lo que se borró. */
    public function eliminar(int $numero): array;
}
