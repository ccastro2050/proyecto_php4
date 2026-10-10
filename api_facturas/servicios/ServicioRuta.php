<?php
/**
 * ServicioRuta — la capa de NEGOCIO de `ruta`.
 *
 * Recibe POR CONSTRUCTOR la interfaz del repositorio (inversion de
 * dependencias): no sabe si detras hay MariaDB, PostgreSQL o SQL Server.
 *
 * No conoce HTTP: comunica los problemas con excepciones de negocio que el
 * controlador traduce a codigos.
 *
 * La regla propia de este recurso es la que la base de datos NO puede
 * exigir: una ruta se escribe `/algo`. Para el motor `ruta` es un VARCHAR
 * cualquiera, asi que si no empieza por barra lo acepta sin chistar — y
 * entonces `verificar_acceso_ruta` nunca encontraria la ruta que el front
 * pide. Lo que el motor no puede defender, lo defiende el negocio.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioRuta.php';
require_once __DIR__ . '/../repositorios/IRepositorioRuta.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Ruta.php';

class ServicioRuta implements IServicioRuta
{
    public function __construct(
        // Se guarda LA INTERFAZ, no una clase concreta: polimorfismo.
        private readonly IRepositorioRuta $repositorio,
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


    /** La ruta no puede ser espacios, y empieza por barra. */
    private function validarRuta(string $ruta): string
    {
        $ruta = trim($ruta);
        if ($ruta === '') {
            throw new InvalidArgumentException('La ruta no puede estar vacia.');
        }
        if (!str_starts_with($ruta, '/')) {
            throw new InvalidArgumentException("La ruta debe empezar por '/': llego '$ruta'.");
        }
        return $ruta;
    }

    /** La descripcion tampoco puede ser espacios. */
    private function validarDescripcion(string $descripcion): string
    {
        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            throw new InvalidArgumentException('La descripcion no puede estar vacia.');
        }
        return $descripcion;
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

    public function obtener(int $id): Ruta
    {
        $id = $this->validarClave($id);
        $rutaObjeto = $this->repositorio->obtenerPorClave($id);
        // El repositorio devuelve null cuando no hay fila; el NEGOCIO decide
        // que eso es un error y lo dice con SU excepcion (el controlador la
        // vuelve 404):
        if ($rutaObjeto === null) {
            throw new NoEncontradoExcepcion("No existe la ruta con id = $id");
        }
        return $rutaObjeto;
    }

    public function crear(array $datos): int
    {
        $datos['ruta'] = $this->validarRuta((string) $datos['ruta']);
        $datos['descripcion'] = $this->validarDescripcion((string) $datos['descripcion']);
        // Desde aqui el dato deja de ser un array y viaja TIPADO:
        $rutaObjeto = new Ruta(
            0,   // el id lo pone la base de datos: aqui va
                 // un cero de relleno que nadie usa
            $datos['ruta'],
            $datos['descripcion'],
        );
        return $this->repositorio->crear($rutaObjeto);
    }

    public function actualizar(int $id, array $datos): int
    {
        $id = $this->validarClave($id);
        // Un PATCH con body {} paso la validacion de forma… pero no tiene
        // sentido de negocio: no hay nada que actualizar → 400.
        if ($datos === []) {
            throw new InvalidArgumentException('No se envio ningun campo para actualizar.');
        }
        if (array_key_exists('ruta', $datos)) {
            $datos['ruta'] = $this->validarRuta((string) $datos['ruta']);
        }
        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->validarDescripcion((string) $datos['descripcion']);
        }
        $filas = $this->repositorio->actualizar($id, $datos);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe la ruta con id = $id");
        }
        return $filas;
    }

    public function eliminar(int $id): int
    {
        $id = $this->validarClave($id);
        $filas = $this->repositorio->eliminar($id);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe la ruta con id = $id");
        }
        return $filas;
    }
}
