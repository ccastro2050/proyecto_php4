<?php
/**
 * ControladorRolUsuario — la capa HTTP del puente `rol_usuario`.
 *
 * CINCO ENDPOINTS, Y NO SON LOS CINCO VERBOS:
 *
 *     GET    /api/rol-usuario                     las parejas, con los nombres
 *     GET    /api/rol-usuario/usuario/{...}      que roles tiene este usuario
 *     GET    /api/rol-usuario/rol/{id}       que usuarios tienen este rol
 *     POST   /api/rol-usuario                     asignar
 *     DELETE /api/rol-usuario/{a}/{b}             quitar esa pareja exacta
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

require_once __DIR__ . '/../servicios/IServicioRolUsuario.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorRolUsuario
{
    public function __construct(
        private readonly IServicioRolUsuario $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/rol-usuario  →  las parejas, con los nombres
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
                'tabla'  => 'rol_usuario',
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
    // GET /api/rol-usuario/usuario/{fkemail}
    // ------------------------------------------------------------------
    public function listarPorLadoA(string $fkemail): void
    {
        try {
            $filas = $this->servicio->listarPorLadoA($fkemail);
            // Lista vacia NO es 404: el usuario puede existir y no tener
            // roles todavia. 404 seria «ese usuario no existe».
            $this->responder(200, [
                'consulta' => 'roles del usuario',
                'fkemail' => $fkemail,
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
    // GET /api/rol-usuario/rol/{fkidrol}
    // ------------------------------------------------------------------
    public function listarPorLadoB(int $fkidrol): void
    {
        try {
            $filas = $this->servicio->listarPorLadoB($fkidrol);
            $this->responder(200, [
                'consulta' => 'usuarios del rol',
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
    // POST /api/rol-usuario  →  asignar
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = $this->validarPareja($body);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $this->servicio->crear($body['fkemail'], $body['fkidrol']);
            $this->responder(201, [
                'estado'  => 201,
                'mensaje' => 'Asignacion creada exitosamente.',
                'fkemail' => $body['fkemail'],
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
    // DELETE /api/rol-usuario/{fkemail}/{fkidrol}  →  quitar ESA pareja
    // ------------------------------------------------------------------
    public function eliminar(string $fkemail, int $fkidrol): void
    {
        try {
            $filas = $this->servicio->eliminar($fkemail, $fkidrol);
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

        $fkemail = $datos['fkemail'] ?? null;
        if (!is_string($fkemail) || trim($fkemail) === '') {
            $errores[] = 'El campo fkemail es obligatorio: el email del usuario.';
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
