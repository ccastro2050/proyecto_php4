<?php
/**
 * ServicioPersona — la capa de NEGOCIO de `persona`.
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

require_once __DIR__ . '/IServicioPersona.php';
require_once __DIR__ . '/../repositorios/IRepositorioPersona.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Persona.php';

class ServicioPersona implements IServicioPersona
{
    public function __construct(
        private readonly IRepositorioPersona $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validación de negocio de la llave
    // ------------------------------------------------------------------

    private function validarClave(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException(
                'El código la persona no puede estar vacío.'
            );
        }
        return $codigo;
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

    public function obtener(string $codigo): Persona
    {
        $codigo = $this->validarClave($codigo);
        $persona = $this->repositorio->obtenerPorClave($codigo);
        if ($persona === null) {
            throw new NoEncontradoExcepcion("No existe la persona con codigo = $codigo");
        }
        return $persona;
    }

    public function crear(array $datos): void
    {
        $persona = new Persona(
            $datos['codigo'],
            $datos['nombre'],
            $datos['email'],
            $datos['telefono'],
        );
        $this->repositorio->crear($persona);
    }

    public function actualizar(string $codigo, array $datos): int
    {
        $codigo = $this->validarClave($codigo);
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ningún campo para actualizar.');
        }
        $filasAfectadas = $this->repositorio->actualizar($codigo, $datos);
        if ($filasAfectadas === 0) {
            throw new NoEncontradoExcepcion("No existe la persona con codigo = $codigo");
        }
        return $filasAfectadas;
    }

    public function eliminar(string $codigo): int
    {
        $codigo = $this->validarClave($codigo);
        $filasEliminadas = $this->repositorio->eliminar($codigo);
        if ($filasEliminadas === 0) {
            throw new NoEncontradoExcepcion("No existe la persona con codigo = $codigo");
        }
        return $filasEliminadas;
    }
}
