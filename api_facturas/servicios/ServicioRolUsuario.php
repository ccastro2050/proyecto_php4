<?php
/**
 * ServicioRolUsuario — las REGLAS del puente `rol_usuario`.
 *
 * Las reglas propias de una tabla puente, dichas de una en una:
 *
 *   · los ids tienen que ser positivos. El controlador valida la FORMA del
 *     body, pero los que vienen EN LA URL no pasan por ahi — y por eso se
 *     validan aqui;
 *   · una lista vacia NO es un error. Que una usuario no tenga roles
 *     asignados es un estado legal, no un 404: 404 seria «esa usuario no
 *     existe», que es otra cosa;
 *   · y no hay `actualizar`, porque en un puente «actualizar» es MOVER la
 *     fila: borrar una pareja e insertar otra. Eso ya se puede hacer con el
 *     DELETE y el POST que si estan.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioRolUsuario.php';
require_once __DIR__ . '/../repositorios/IRepositorioRolUsuario.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ServicioRolUsuario implements IServicioRolUsuario
{
    public function __construct(
        private readonly IRepositorioRolUsuario $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones de negocio
    // ------------------------------------------------------------------

    /** El email, limpio y en minusculas (ver ServicioUsuario). */
    private function validarLadoA(string $fkemail): string
    {
        $fkemail = strtolower(trim($fkemail));
        if ($fkemail === '') {
            throw new InvalidArgumentException('El email no puede estar vacio.');
        }
        return $fkemail;
    }

    /** El id del rol: entero positivo. */
    private function validarLadoB(int $fkidrol): int
    {
        if ($fkidrol <= 0) {
            throw new InvalidArgumentException('El id del rol debe ser un entero mayor que cero.');
        }
        return $fkidrol;
    }

    // ------------------------------------------------------------------
    // Operaciones
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        if ($limite <= 0) {
            throw new InvalidArgumentException('El limite debe ser un entero mayor que cero.');
        }
        return $this->repositorio->obtenerTodos($limite);
    }

    public function listarPorLadoA(string $fkemail): array
    {
        return $this->repositorio->obtenerPorLadoA($this->validarLadoA($fkemail));
    }

    public function listarPorLadoB(int $fkidrol): array
    {
        return $this->repositorio->obtenerPorLadoB($this->validarLadoB($fkidrol));
    }

    public function crear(string $fkemail, int $fkidrol): void
    {
        $this->repositorio->crear(
            $this->validarLadoA($fkemail),
            $this->validarLadoB($fkidrol),
        );
    }

    public function eliminar(string $fkemail, int $fkidrol): int
    {
        $filas = $this->repositorio->eliminar(
            $this->validarLadoA($fkemail),
            $this->validarLadoB($fkidrol),
        );
        if ($filas === 0) {
            throw new NoEncontradoExcepcion(
                "No existe la asignacion (fkemail = $fkemail, fkidrol = $fkidrol)");
        }
        return $filas;
    }
}
