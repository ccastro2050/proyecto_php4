<?php
/**
 * IRepositorioRolUsuario — el CONTRATO de datos del puente `rol_usuario`.
 *
 * Frente al molde de una entidad (5 metodos) cambian tres cosas:
 *
 *   1. Hay DOS busquedas por lado —por usuario y por rol—, porque las dos
 *      preguntas son el motivo de la tabla.
 *   2. NO hay `actualizar`: las dos columnas SON la llave, asi que no queda
 *      ningun campo suelto que modificar.
 *   3. El `eliminar` exige LAS DOS columnas. Borrar por una sola borraria de
 *      mas — y nadie se daria cuenta hasta que faltaran permisos.
 *
 * Las lecturas devuelven ARRAYS y no objetos del modelo, y es deliberado: el
 * listado trae los NOMBRES con un JOIN —no solo los ids— y eso no es una fila
 * de la tabla, es el resultado de una pregunta.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IRepositorioRolUsuario
{
    /** Las parejas con los nombres, hasta $limite. @return array[] */
    public function obtenerTodos(int $limite): array;

    /** Que roles tiene este usuario. @return array[] */
    public function obtenerPorLadoA(string $fkemail): array;

    /** Que usuarios tienen este rol. @return array[] */
    public function obtenerPorLadoB(int $fkidrol): array;

    /** Inserta la pareja. Choca si ya existe (llave primaria compuesta). */
    public function crear(string $fkemail, int $fkidrol): bool;

    /** Borra UNA pareja exacta. Devuelve filas eliminadas. */
    public function eliminar(string $fkemail, int $fkidrol): int;
}
