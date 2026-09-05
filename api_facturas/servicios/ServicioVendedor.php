<?php
/**
 * ServicioVendedor — la capa de NEGOCIO de `vendedor`.
 *
 * Recibe la INTERFAZ del repositorio por constructor: no sabe si detrás hay
 * MariaDB o un falso en memoria. No conoce HTTP — comunica los problemas con
 * excepciones que el controlador traduce.
 *
 * Fíjese en lo que este archivo NO tiene: ni una palabra sobre llaves
 * foráneas. La integridad la defiende la base de datos y la explica el
 * repositorio; el negocio no la repite (ver `errores_de_integridad.php`).
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioVendedor.php';
require_once __DIR__ . '/../repositorios/IRepositorioVendedor.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Vendedor.php';

class ServicioVendedor implements IServicioVendedor
{
    public function __construct(
        private readonly IRepositorioVendedor $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validación de negocio de la llave
    // ------------------------------------------------------------------

    private function validarClave(int $id): int
    {
        // Los identificadores que genera la base empiezan en 1: un 0 o un
        // negativo no es «no encontrado», es una petición mal hecha.
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El identificador el vendedor debe ser un entero mayor que cero.'
            );
        }
        return $id;
    }

    // ------------------------------------------------------------------
    // Operaciones de negocio
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        if ($limite <= 0) {
            throw new InvalidArgumentException('El límite debe ser un entero mayor que cero.');
        }
        return $this->repositorio->obtenerTodos($limite);
    }

    public function obtener(int $id): Vendedor
    {
        $id = $this->validarClave($id);
        $vendedor = $this->repositorio->obtenerPorClave($id);
        if ($vendedor === null) {
            throw new NoEncontradoExcepcion("No existe el vendedor con id = $id");
        }
        return $vendedor;
    }

    public function crear(array $datos): int
    {
        $vendedor = new Vendedor(
            null,   // el id todavía no existe: lo pone la base
            $datos['carnet'],
            $datos['direccion'],
            $datos['fkcodpersona'],
        );
        // El repositorio devuelve el id recién generado, y el servicio lo
        // pasa hacia arriba: el cliente necesita saber con qué llave quedó
        // guardada la ficha que acaba de crear.
        return $this->repositorio->crear($vendedor);
    }

    public function actualizar(int $id, array $datos): int
    {
        $id = $this->validarClave($id);
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ningún campo para actualizar.');
        }
        $filasAfectadas = $this->repositorio->actualizar($id, $datos);
        if ($filasAfectadas === 0) {
            throw new NoEncontradoExcepcion("No existe el vendedor con id = $id");
        }
        return $filasAfectadas;
    }

    public function eliminar(int $id): int
    {
        $id = $this->validarClave($id);
        $filasEliminadas = $this->repositorio->eliminar($id);
        if ($filasEliminadas === 0) {
            throw new NoEncontradoExcepcion("No existe el vendedor con id = $id");
        }
        return $filasEliminadas;
    }
}
