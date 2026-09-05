<?php
/**
 * IRepositorioPersona — el CONTRATO de la capa de datos de `persona`.
 *
 * Una interfaz nativa de PHP: dice QUÉ operaciones existen, nunca CÓMO se
 * hacen. El servicio depende de esto, no de una clase concreta — y por eso
 * se puede probar con un repositorio falso, sin base de datos.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Persona.php';

interface IRepositorioPersona
{
    /**
     * Devuelve hasta $limite fichas ordenadas por codigo.
     * @return Persona[]
     */
    public function obtenerTodos(int $limite): array;

    /** La ficha con esa llave, o null si no existe. */
    public function obtenerPorClave(string $codigo): ?Persona;

    /** Inserta la ficha (llega como objeto del modelo). true = insertada. */
    public function crear(Persona $persona): bool;

    /**
     * Escribe los campos de $datos (los usan PUT y PATCH). Va como array
     * porque un PATCH puede traer SOLO algunos campos.
     * Devuelve las filas afectadas (0 = esa llave no existe).
     */
    public function actualizar(string $codigo, array $datos): int;

    /** Elimina la ficha. Devuelve filas eliminadas (0 = no existía). */
    public function eliminar(string $codigo): int;
}
