<?php
/**
 * IServicioProducto — el CONTRATO de la capa de negocio.
 *
 * El controlador depende de esta interfaz: no sabe (ni debe saber) qué hay
 * detrás. Los métodos comunican problemas con excepciones de NEGOCIO que el
 * controlador traduce a códigos HTTP:
 *   InvalidArgumentException → 400 · NoEncontradoExcepcion → 404 ·
 *   PDOException y demás → 500.
 *
 * Las lecturas devuelven objetos del MODELO (Producto) — el dato tipado.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Producto.php';

interface IServicioProducto
{
    /**
     * Hasta $limite productos. InvalidArgumentException si limite <= 0.
     * @return Producto[] (lista de objetos del modelo)
     */
    public function listar(int $limite): array;

    /** El Producto con ese código. NoEncontradoExcepcion si no existe. */
    public function obtener(string $codigo): Producto;

    /**
     * Crea el producto. Recibe el array YA validado por el controlador
     * (Producto::validarCrear); el servicio construye con él la entidad.
     */
    public function crear(array $datos): void;

    /**
     * Escribe los campos enviados (PUT manda todos, PATCH un subconjunto).
     * InvalidArgumentException si no llegó ningún campo ·
     * NoEncontradoExcepcion si el código no existe · devuelve filas afectadas.
     */
    public function actualizar(string $codigo, array $datos): int;

    /** Elimina. NoEncontradoExcepcion si no existe · devuelve filas eliminadas. */
    public function eliminar(string $codigo): int;
}
