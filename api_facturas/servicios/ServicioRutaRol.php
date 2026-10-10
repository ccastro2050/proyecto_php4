<?php
/**
 * ServicioRutaRol — las REGLAS del puente `rutarol`.
 *
 * Las reglas propias de una tabla puente, dichas de una en una:
 *
 *   · los ids tienen que ser positivos. El controlador valida la FORMA del
 *     body, pero los que vienen EN LA URL no pasan por ahi — y por eso se
 *     validan aqui;
 *   · una lista vacia NO es un error. Que una ruta no tenga roles
 *     asignados es un estado legal, no un 404: 404 seria «esa ruta no
 *     existe», que es otra cosa;
 *   · y no hay `actualizar`, porque en un puente «actualizar» es MOVER la
 *     fila: borrar una pareja e insertar otra. Eso ya se puede hacer con el
 *     DELETE y el POST que si estan.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioRutaRol.php';
require_once __DIR__ . '/../repositorios/IRepositorioRutaRol.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ServicioRutaRol implements IServicioRutaRol
{
    public function __construct(
        private readonly IRepositorioRutaRol $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones de negocio
    // ------------------------------------------------------------------

    /** El id del ruta: entero positivo. */
    private function validarLadoA(int $fkidruta): int
    {
        if ($fkidruta <= 0) {
            throw new InvalidArgumentException('El id del ruta debe ser un entero mayor que cero.');
        }
        return $fkidruta;
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

    public function listarPorLadoA(int $fkidruta): array
    {
        return $this->repositorio->obtenerPorLadoA($this->validarLadoA($fkidruta));
    }

    public function listarPorLadoB(int $fkidrol): array
    {
        return $this->repositorio->obtenerPorLadoB($this->validarLadoB($fkidrol));
    }

    public function crear(int $fkidruta, int $fkidrol): void
    {
        $this->repositorio->crear(
            $this->validarLadoA($fkidruta),
            $this->validarLadoB($fkidrol),
        );
    }

    public function eliminar(int $fkidruta, int $fkidrol): int
    {
        $filas = $this->repositorio->eliminar(
            $this->validarLadoA($fkidruta),
            $this->validarLadoB($fkidrol),
        );
        if ($filas === 0) {
            throw new NoEncontradoExcepcion(
                "No existe el permiso (fkidruta = $fkidruta, fkidrol = $fkidrol)");
        }
        return $filas;
    }
}
