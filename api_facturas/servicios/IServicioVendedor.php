<?php
/**
 * IServicioVendedor — el CONTRATO de la capa de negocio de `vendedor`.
 *
 * El controlador depende de esta interfaz, no de la clase concreta.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Vendedor.php';

interface IServicioVendedor
{
    /** @return Vendedor[] */
    public function listar(int $limite): array;

    /** La ficha, o NoEncontradoExcepcion si no existe. */
    public function obtener(int $id): Vendedor;

    /**
     * Crea la ficha y devuelve el id que generó la base.
     * El cliente no lo manda: lo recibe.
     */
    public function crear(array $datos): int;

    /** Escribe los campos indicados. Devuelve filas afectadas. */
    public function actualizar(int $id, array $datos): int;

    /** Elimina. Devuelve filas eliminadas. */
    public function eliminar(int $id): int;
}
