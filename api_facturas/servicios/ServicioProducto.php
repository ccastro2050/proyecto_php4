<?php
/**
 * ServicioProducto — la capa de NEGOCIO de la v1.
 *
 * Recibe POR CONSTRUCTOR la interfaz del repositorio (inversión de
 * dependencias): no sabe si detrás hay MariaDB o un falso en memoria para
 * pruebas — y así debe ser.
 *
 * No conoce HTTP: comunica los problemas con excepciones de negocio que el
 * controlador traduce a códigos (InvalidArgumentException → 400 ·
 * NoEncontradoExcepcion → 404).
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioProducto.php';
require_once __DIR__ . '/../repositorios/IRepositorioProducto.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Producto.php';

// "implements IServicioProducto" = esta clase FIRMA el contrato de la
// interfaz: PHP verifica que tenga TODOS los métodos prometidos.
class ServicioProducto implements IServicioProducto
{
    // Promoción de propiedades (PHP 8): el parámetro se vuelve atributo
    // del objeto automáticamente ($this->repositorio).
    //   private  → solo esta clase lo usa
    //   readonly → se asigna una vez, nadie lo cambia después
    public function __construct(
        // Se guarda LA INTERFAZ, no una clase concreta: cualquier clase que
        // la implemente sirve — eso es el polimorfismo trabajando.
        private readonly IRepositorioProducto $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones pequeñas y compartidas
    // ------------------------------------------------------------------

    private function validarCodigo(string $codigo): string
    {
        // trim quita espacios a los lados; "  " queda como "".
        $codigo = trim($codigo);
        if ($codigo === '') {
            // throw corta la ejecución AQUÍ y lanza la excepción hacia
            // arriba; el controlador la atrapará y responderá 400.
            throw new InvalidArgumentException('El código del producto no puede estar vacío.');
        }
        // Se devuelve el código ya limpio para que el resto lo use:
        return $codigo;
    }

    // ------------------------------------------------------------------
    // Operaciones de negocio
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        // El contrato dice 400 (no 422) para límites inválidos:
        // es una REGLA DE NEGOCIO, no un problema de forma del body.
        if ($limite <= 0) {
            throw new InvalidArgumentException('El límite debe ser un entero mayor que cero.');
        }
        // Delegar a la capa de datos — a través de la interfaz:
        return $this->repositorio->obtenerTodos($limite);
    }

    public function obtener(string $codigo): Producto
    {
        $codigo = $this->validarCodigo($codigo);
        $producto = $this->repositorio->obtenerPorClave($codigo);
        // El repositorio devuelve null cuando no hay fila; el NEGOCIO decide
        // que eso es un error y lo dice con SU excepción (el repositorio no
        // opina, el controlador la vuelve 404):
        if ($producto === null) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $producto;
    }

    public function crear(array $datos): void
    {
        // Los datos ya pasaron por la validación del controlador (tipos y
        // rangos). Aquí el NEGOCIO construye el objeto del MODELO: desde
        // este punto el producto deja de ser un array y viaja tipado.
        // (float) porque el JSON pudo traer 120000 —entero— y la propiedad
        // del modelo es float:
        $producto = new Producto(
            $datos['codigo'],
            $datos['nombre'],
            $datos['stock'],
            (float) $datos['valorunitario'],
        );
        // Si la BD rechaza (código duplicado → viola la PK), la PDOException
        // sube tal cual y el controlador la convierte en 500.
        $this->repositorio->crear($producto);
    }

    public function actualizar(string $codigo, array $datos): int
    {
        $codigo = $this->validarCodigo($codigo);
        // Un PATCH con body {} pasó la validación del controlador (nada inválido)… pero no
        // tiene sentido de negocio: no hay nada que actualizar → 400.
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ningún campo para actualizar.');
        }
        $filasAfectadas = $this->repositorio->actualizar($codigo, $datos);
        // 0 filas afectadas = ese código no existe en la tabla:
        if ($filasAfectadas === 0) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $filasAfectadas;
    }

    public function eliminar(string $codigo): int
    {
        $codigo = $this->validarCodigo($codigo);
        $filasEliminadas = $this->repositorio->eliminar($codigo);
        if ($filasEliminadas === 0) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $filasEliminadas;
    }
}
