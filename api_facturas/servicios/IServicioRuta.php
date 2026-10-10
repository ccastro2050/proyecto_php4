<?php
/**
 * IServicioRuta — el CONTRATO de la capa de negocio de `ruta`.
 *
 * El controlador depende de esta interfaz: no sabe —ni debe saber— que hay
 * detras. Los problemas se comunican con excepciones de NEGOCIO que el
 * controlador traduce a codigos HTTP:
 *   InvalidArgumentException → 400 · NoEncontradoExcepcion → 404 ·
 *   ConflictoDeIntegridadExcepcion → 409 · lo demas → 500.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Ruta.php';

interface IServicioRuta
{
    /**
     * Hasta $limite filas. InvalidArgumentException si limite <= 0.
     * @return Ruta[]
     */
    public function listar(int $limite): array;

    /** El Ruta con esa llave. NoEncontradoExcepcion si no existe. */
    public function obtener(int $id): Ruta;

    /**
     * Crea. Recibe el array YA validado en forma por el controlador; el
     * negocio construye con el la entidad. Devuelve el id generado.
     */
    public function crear(array $datos): int;

    /**
     * Escribe los campos enviados (PUT manda todos, PATCH un subconjunto).
     * InvalidArgumentException si no llego ningun campo ·
     * NoEncontradoExcepcion si la llave no existe · devuelve filas afectadas.
     */
    public function actualizar(int $id, array $datos): int;

    /** Elimina. NoEncontradoExcepcion si no existe · devuelve filas. */
    public function eliminar(int $id): int;
}
