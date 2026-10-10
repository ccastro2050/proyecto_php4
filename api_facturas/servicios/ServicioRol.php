<?php
/**
 * ServicioRol — la capa de NEGOCIO de `rol`.
 *
 * Recibe POR CONSTRUCTOR la interfaz del repositorio (inversion de
 * dependencias): no sabe si detras hay MariaDB, PostgreSQL o SQL Server.
 *
 * No conoce HTTP: comunica los problemas con excepciones de negocio que el
 * controlador traduce a codigos.
 *
 * La regla propia de este recurso es minima —el nombre no puede ser
 * espacios— y aun asi vale la pena verla: Pydantic no existe en PHP, asi que
 * la FORMA la valida el controlador y el CONTENIDO el servicio. Un nombre de
 * «   » pasa la forma (es un texto) y no pasa el negocio (no es un nombre).
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioRol.php';
require_once __DIR__ . '/../repositorios/IRepositorioRol.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Rol.php';

class ServicioRol implements IServicioRol
{
    public function __construct(
        // Se guarda LA INTERFAZ, no una clase concreta: polimorfismo.
        private readonly IRepositorioRol $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones de negocio
    // ------------------------------------------------------------------

    /** La llave es un entero positivo: 0 o negativo no puede existir. */
    private function validarClave(int $id): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('El id debe ser un entero mayor que cero.');
        }
        return $id;
    }


    /** El nombre no puede ser espacios en blanco. */
    private function validarNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del rol no puede estar vacio.');
        }
        return $nombre;
    }

    // ------------------------------------------------------------------
    // Operaciones
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        // El contrato dice 400 (no 422) para limites invalidos: es una REGLA
        // DE NEGOCIO, no un problema de forma del body.
        if ($limite <= 0) {
            throw new InvalidArgumentException('El limite debe ser un entero mayor que cero.');
        }
        return $this->repositorio->obtenerTodos($limite);
    }

    public function obtener(int $id): Rol
    {
        $id = $this->validarClave($id);
        $rol = $this->repositorio->obtenerPorClave($id);
        // El repositorio devuelve null cuando no hay fila; el NEGOCIO decide
        // que eso es un error y lo dice con SU excepcion (el controlador la
        // vuelve 404):
        if ($rol === null) {
            throw new NoEncontradoExcepcion("No existe el rol con id = $id");
        }
        return $rol;
    }

    public function crear(array $datos): int
    {
        $datos['nombre'] = $this->validarNombre((string) $datos['nombre']);
        // Desde aqui el dato deja de ser un array y viaja TIPADO:
        $rol = new Rol(
            0,   // el id lo pone la base de datos: aqui va
                 // un cero de relleno que nadie usa
            $datos['nombre'],
        );
        return $this->repositorio->crear($rol);
    }

    public function actualizar(int $id, array $datos): int
    {
        $id = $this->validarClave($id);
        // Un PATCH con body {} paso la validacion de forma… pero no tiene
        // sentido de negocio: no hay nada que actualizar → 400.
        if ($datos === []) {
            throw new InvalidArgumentException('No se envio ningun campo para actualizar.');
        }
        if (array_key_exists('nombre', $datos)) {
            $datos['nombre'] = $this->validarNombre((string) $datos['nombre']);
        }
        $filas = $this->repositorio->actualizar($id, $datos);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe el rol con id = $id");
        }
        return $filas;
    }

    public function eliminar(int $id): int
    {
        $id = $this->validarClave($id);
        $filas = $this->repositorio->eliminar($id);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe el rol con id = $id");
        }
        return $filas;
    }
}
