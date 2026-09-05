<?php
/**
 * IRepositorioVendedor — el CONTRATO de la capa de datos de `vendedor`.
 *
 * Una interfaz nativa de PHP: dice QUÉ operaciones existen, nunca CÓMO se
 * hacen. El servicio depende de esto, no de una clase concreta — y por eso
 * se puede probar con un repositorio falso, sin base de datos.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Vendedor.php';

interface IRepositorioVendedor
{
    /**
     * Devuelve hasta $limite fichas ordenadas por id.
     * @return Vendedor[]
     */
    public function obtenerTodos(int $limite): array;

    /** La ficha con esa llave, o null si no existe. */
    public function obtenerPorClave(int $id): ?Vendedor;

    /** Inserta y devuelve el **id que generó la base**. Ese id no
     * existía antes de llamar a este método: por eso se devuelve, y
     * no se le pregunta al objeto. */
    public function crear(Vendedor $vendedor): int;

    /**
     * Escribe los campos de $datos (los usan PUT y PATCH). Va como array
     * porque un PATCH puede traer SOLO algunos campos.
     * Devuelve las filas afectadas (0 = esa llave no existe).
     */
    public function actualizar(int $id, array $datos): int;

    /** Elimina la ficha. Devuelve filas eliminadas (0 = no existía). */
    public function eliminar(int $id): int;
}
