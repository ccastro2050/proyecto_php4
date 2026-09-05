<?php
/**
 * IServicioEmpresa — el CONTRATO de la capa de negocio de `empresa`.
 *
 * El controlador depende de esta interfaz, no de la clase concreta.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Empresa.php';

interface IServicioEmpresa
{
    /** @return Empresa[] */
    public function listar(int $limite): array;

    /** La ficha, o NoEncontradoExcepcion si no existe. */
    public function obtener(string $codigo): Empresa;

    /** Crea la ficha. */
    public function crear(array $datos): void;

    /** Escribe los campos indicados. Devuelve filas afectadas. */
    public function actualizar(string $codigo, array $datos): int;

    /** Elimina. Devuelve filas eliminadas. */
    public function eliminar(string $codigo): int;
}
