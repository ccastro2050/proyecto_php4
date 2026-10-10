<?php
/**
 * IServicioRolUsuario — el CONTRATO de negocio del puente `rol_usuario`.
 *
 * Cinco operaciones, las mismas cinco del repositorio. Y las reglas que el
 * servicio agrega son las que el motor no puede exigir: que los ids sean
 * positivos y que el usuario venga limpio.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IServicioRolUsuario
{
    /** @return array[] */
    public function listar(int $limite): array;

    /** @return array[] */
    public function listarPorLadoA(string $fkemail): array;

    /** @return array[] */
    public function listarPorLadoB(int $fkidrol): array;

    /** Asigna. ConflictoDeIntegridadExcepcion si ya existe o falta un lado. */
    public function crear(string $fkemail, int $fkidrol): void;

    /** Quita la pareja. NoEncontradoExcepcion si no existia. */
    public function eliminar(string $fkemail, int $fkidrol): int;
}
