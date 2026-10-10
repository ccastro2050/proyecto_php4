<?php
/**
 * IRepositorioRuta — el CONTRATO de la capa de datos de `ruta`.
 *
 * Una interface nativa de PHP dice QUE operaciones existen, sin decir COMO ni
 * CONTRA QUE motor. Cualquier clase con `implements IRepositorioRuta`
 * ocupa este lugar: los tres repositorios reales, o un falso en memoria para
 * las pruebas (polimorfismo).
 *
 * El servicio depende de ESTA interfaz, nunca de una clase concreta
 * —inversion de dependencias, la D de SOLID—.
 *
 * Las lecturas devuelven objetos del MODELO, no arrays: la capa de datos
 * entrega el dato ya tipado.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Ruta.php';

interface IRepositorioRuta
{
    /**
     * Hasta $limite filas, ordenadas por id.
     * @return Ruta[]
     */
    public function obtenerTodos(int $limite): array;

    /** El Ruta con esa llave, o null si no existe. */
    public function obtenerPorClave(int $id): ?Ruta;

    /**
     * Inserta. Devuelve el id que genero la base de datos.
     */
    public function crear(Ruta $rutaObjeto): int;

    /**
     * Escribe los campos de $datos (los usan PUT y PATCH). Va como array
     * porque un PATCH puede traer SOLO algunos campos.
     * Devuelve filas afectadas (0 = la llave no existe).
     */
    public function actualizar(int $id, array $datos): int;

    /** Elimina. Devuelve filas eliminadas (0 = no existia). */
    public function eliminar(int $id): int;
}
