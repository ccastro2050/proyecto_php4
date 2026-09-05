<?php
/**
 * ServicioCliente — la capa de NEGOCIO de `cliente`.
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

require_once __DIR__ . '/IServicioCliente.php';
require_once __DIR__ . '/../repositorios/IRepositorioCliente.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Cliente.php';

class ServicioCliente implements IServicioCliente
{
    public function __construct(
        private readonly IRepositorioCliente $repositorio,
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
                'El identificador el cliente debe ser un entero mayor que cero.'
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

    public function obtener(int $id): Cliente
    {
        $id = $this->validarClave($id);
        $cliente = $this->repositorio->obtenerPorClave($id);
        if ($cliente === null) {
            throw new NoEncontradoExcepcion("No existe el cliente con id = $id");
        }
        return $cliente;
    }

    public function crear(array $datos): int
    {
        $cliente = new Cliente(
            null,   // el id todavía no existe: lo pone la base
            (float) $datos['credito'],
            $datos['fkcodpersona'],
            $datos['fkcodempresa'] ?? null,   // opcional
        );
        // El repositorio devuelve el id recién generado, y el servicio lo
        // pasa hacia arriba: el cliente necesita saber con qué llave quedó
        // guardada la ficha que acaba de crear.
        return $this->repositorio->crear($cliente);
    }

    public function actualizar(int $id, array $datos): int
    {
        $id = $this->validarClave($id);
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ningún campo para actualizar.');
        }
        $filasAfectadas = $this->repositorio->actualizar($id, $datos);
        if ($filasAfectadas === 0) {
            throw new NoEncontradoExcepcion("No existe el cliente con id = $id");
        }
        return $filasAfectadas;
    }

    public function eliminar(int $id): int
    {
        $id = $this->validarClave($id);
        $filasEliminadas = $this->repositorio->eliminar($id);
        if ($filasEliminadas === 0) {
            throw new NoEncontradoExcepcion("No existe el cliente con id = $id");
        }
        return $filasEliminadas;
    }
}
