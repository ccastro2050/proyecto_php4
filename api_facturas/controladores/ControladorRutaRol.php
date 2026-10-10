<?php
/**
 * ControladorRutaRol — la capa HTTP del puente `rutarol`.
 *
 * CINCO ENDPOINTS, Y NO SON LOS CINCO VERBOS:
 *
 *     GET    /api/rutarol                     las parejas, con los nombres
 *     GET    /api/rutarol/ruta/{...}      que roles tiene este ruta
 *     GET    /api/rutarol/rol/{id}       que rutas tienen este rol
 *     POST   /api/rutarol                     asignar
 *     DELETE /api/rutarol/{a}/{b}             quitar esa pareja exacta
 *
 * **No hay PUT ni PATCH, y no es un olvido.** En una tabla puente las dos
 * columnas SON la llave: no hay un campo suelto que modificar, y una pareja
 * existe o no existe. Lo que seria «actualizar» —mover la pareja— se hace
 * con el DELETE y el POST que si estan. En el enrutador los dos verbos estan
 * escritos y apagados, con la explicacion al lado.
 *
 * La traduccion de excepciones es la de siempre (6_contracts.md §0), con una
 * nota sobre el 409: aqui cubre DOS cosas —la pareja repetida y el lado que
 * no existe— y el mensaje del motor dice cual de las dos fue.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioRutaRol.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorRutaRol
{
    public function __construct(
        private readonly IServicioRutaRol $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/rutarol  →  las parejas, con los nombres
    // ------------------------------------------------------------------
    public function listar(): void
    {
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        try {
            $filas = $this->servicio->listar($limite);
            if ($filas === []) {
                http_response_code(204);
                return;
            }
            $this->responder(200, [
                'tabla'  => 'rutarol',
                'limite' => $limite,
                'total'  => count($filas),
                'datos'  => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/rutarol/ruta/{fkidruta}  →  que roles entran a esta ruta
    // ------------------------------------------------------------------
    public function listarPorLadoA(int $fkidruta): void
    {
        try {
            $filas = $this->servicio->listarPorLadoA($fkidruta);
            // Lista vacia NO es 404: el ruta puede existir y no tener
            // roles todavia. 404 seria «ese ruta no existe».
            $this->responder(200, [
                'consulta' => 'roles de la ruta',
                'fkidruta' => $fkidruta,
                'total'    => count($filas),
                'datos'    => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/rutarol/rol/{fkidrol}
    // ------------------------------------------------------------------
    public function listarPorLadoB(int $fkidrol): void
    {
        try {
            $filas = $this->servicio->listarPorLadoB($fkidrol);
            $this->responder(200, [
                'consulta' => 'rutas del rol',
                'fkidrol' => $fkidrol,
                'total'    => count($filas),
                'datos'    => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/rutarol  →  asignar
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = $this->validarPareja($body);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $this->servicio->crear($body['fkidruta'], $body['fkidrol']);
            $this->responder(201, [
                'estado'  => 201,
                'mensaje' => 'Asignacion creada exitosamente.',
                'fkidruta' => $body['fkidruta'],
                'fkidrol' => $body['fkidrol'],
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La asignacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/rutarol/{fkidruta}/{fkidrol}  →  quitar ESA pareja
    // ------------------------------------------------------------------
    public function eliminar(int $fkidruta, int $fkidrol): void
    {
        try {
            $filas = $this->servicio->eliminar($fkidruta, $fkidrol);
            // 200 con cuerpo y no 204: en un puente es facil creer que se
            // borro una pareja y haber borrado otra — decir CUANTAS filas se
            // fueron es informacion util.
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Asignacion eliminada exitosamente.', 'filasEliminadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Asignacion no encontrada.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Validacion de FORMA
    // ------------------------------------------------------------------

    /** Las dos columnas son obligatorias: una pareja a medias no existe. */
    private function validarPareja(array $datos): array
    {
        $errores = [];

        $fkidruta = $datos['fkidruta'] ?? null;
        if (!is_int($fkidruta) || $fkidruta <= 0) {
            $errores[] = 'El campo fkidruta debe ser un entero mayor que cero.';
        }

        $fkidrol = $datos['fkidrol'] ?? null;
        if (!is_int($fkidrol) || $fkidrol <= 0) {
            $errores[] = 'El campo fkidrol debe ser un entero mayor que cero.';
        }

        return $errores;
    }

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
