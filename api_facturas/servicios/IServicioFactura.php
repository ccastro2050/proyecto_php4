<?php
/**
 * IServicioFactura — el CONTRATO de la capa de negocio de las facturas.
 *
 * Igual que la interfaz del repositorio, no son los cinco métodos de las
 * demás entidades: una factura se crea, se reemplaza entera, se anula o se
 * borra. No se le «cambia un campo».
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Factura.php';

interface IServicioFactura
{
    /** @return Factura[] */
    public function listar(): array;

    /** La factura con su detalle, o NoEncontradoExcepcion si no existe. */
    public function obtener(int $numero): Factura;

    /** Crea la factura con sus renglones y devuelve la factura guardada. */
    public function crear(array $datos): Factura;

    /** Reemplaza cliente, vendedor y TODO el detalle. */
    public function reemplazar(int $numero, array $datos): Factura;

    /** Anula: la factura queda 'anulada' y el stock vuelve. */
    public function anular(int $numero): array;

    /** Borra la factura y su detalle. */
    public function eliminar(int $numero): array;
}
