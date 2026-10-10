<?php
/**
 * ControladorUsuarioConRoles — el usuario y sus roles, en un solo envio.
 *
 * POR QUE ESTE RECURSO EXISTE SI YA HAY /api/usuario Y /api/rol-usuario
 * ---------------------------------------------------------------------
 * Porque crear el usuario y asignarle sus roles con dos peticiones deja un
 * estado intermedio REAL: si la segunda falla, queda un usuario que no puede
 * hacer nada. Aqui las dos cosas ocurren DENTRO de un procedimiento, en una
 * transaccion del motor.
 *
 * Y la comparacion que vale una clase entera: `/api/rol-usuario` es la MISMA
 * relacion vista como tabla puente cruda —una pareja a la vez—; esto es la
 * relacion vista como un documento —el usuario con su juego de roles—. Las
 * dos son legitimas y sirven para cosas distintas: la pantalla de casillas
 * usa esta, una pantalla de administracion fina usa la otra.
 *
 * La traduccion de excepciones es la de siempre, con una nota sobre el 422:
 * la lista de roles vacia la rechaza la FORMA (422) y la lista con ceros la
 * rechaza la REGLA (400). Dos puertas para dos cosas distintas.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioUsuarioConRoles.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorUsuarioConRoles
{
    public function __construct(
        private readonly IServicioUsuarioConRoles $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/usuario-con-roles  →  todos, con sus roles
    // ------------------------------------------------------------------
    public function listar(): void
    {
        try {
            $datos = $this->servicio->listar();
            // UNA consulta, no N+1: el procedimiento ya trae cada usuario con
            // su lista de roles. Pedir los usuarios y luego un viaje por cada
            // uno daria el mismo resultado y ocho veces el trabajo.
            $this->responder(200, [
                'tabla' => 'usuario_con_roles',
                'total' => count($datos),
                'datos' => $datos,
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/usuario-con-roles/{email}
    // ------------------------------------------------------------------
    public function consultar(string $email): void
    {
        try {
            $this->responder(200, $this->servicio->consultar($email));
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/usuario-con-roles  →  usuario Y roles, en una transaccion
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = $this->validar($body, true, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $datos = $this->servicio->crear(
                (string) $body['email'], (string) $body['contrasena'],
                (array) $body['roles']);
            $this->responder(201, ['estado' => 201, 'mensaje' => 'Usuario creado exitosamente.', 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La operacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/usuario-con-roles/{email}  →  el juego COMPLETO de roles
    // ------------------------------------------------------------------
    public function reemplazar(string $email, array $body): void
    {
        // La contrasena es OPCIONAL aqui, y tiene su razon: la pantalla de
        // casillas sirve para cambiar roles, y obligar a reescribir la
        // contrasena para guardar un rol haria que alguien la teclee mal y
        // deje a otro sin poder entrar.
        $errores = $this->validar($body, false, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $datos = $this->servicio->actualizar($email,
                isset($body['contrasena']) ? (string) $body['contrasena'] : null,
                (array) $body['roles']);
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Usuario actualizado exitosamente.', 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La operacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PATCH /api/usuario-con-roles/{email}  →  solo lo que llegue
    // ------------------------------------------------------------------
    public function actualizar(string $email, array $body): void
    {
        // Con dos campos, el PATCH se gana su sitio: permite cambiar la
        // contrasena SIN mandar los roles —que es lo que uno quiere cuando
        // solo cambia la clave— y al reves.
        if ($body === []) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => 'No se envio ningun campo para actualizar.']);
            return;
        }
        $errores = $this->validar($body, false, false);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $datos = $this->servicio->actualizarParcial($email,
                isset($body['contrasena']) ? (string) $body['contrasena'] : null,
                isset($body['roles']) ? (array) $body['roles'] : null);
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Usuario actualizado exitosamente.', 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // ESTE `catch` FALTABA, y el fallo se midio: mandar un rol que no
            // existe respondia 500 «Error interno» en vez de 409, con el
            // detalle correcto adentro. Un `catch (Throwable)` al final no
            // avisa de lo que se le cuela: atrapa la excepcion de negocio
            // igual que un error de programacion, y las dos salen como 500.
            // En el PUT de aqui al lado si estaba — la misma clase, dos
            // metodos, una respuesta distinta.
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La operacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/usuario-con-roles/{email}  →  el usuario Y sus roles
    // ------------------------------------------------------------------
    public function eliminar(string $email): void
    {
        try {
            $datos = $this->servicio->eliminar($email);
            // 200 con el mensaje del procedimiento y no 204: aqui se borraron
            // filas de DOS tablas, y decirlo es informacion util. En
            // /api/usuario, que borra de una sola, el 200 simple basta.
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Usuario eliminado exitosamente.', 'datos' => $datos]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Validacion de FORMA
    // ------------------------------------------------------------------

    /**
     * @param bool $exigeEmail      el POST si; los otros lo traen en la URL
     * @param bool $exigeRoles      el POST y el PUT si; el PATCH no
     */
    private function validar(array $datos, bool $exigeEmail, bool $exigeRoles): array
    {
        $errores = [];

        if ($exigeEmail) {
            $email = $datos['email'] ?? null;
            if (!is_string($email) || trim($email) === '' || strlen($email) > 100) {
                $errores[] = 'El campo email es obligatorio: texto de 1 a 100 caracteres.';
            } elseif (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
                $errores[] = 'El campo email no tiene forma de correo electronico.';
            }
            $clave = $datos['contrasena'] ?? null;
            if (!is_string($clave) || strlen($clave) < 6 || strlen($clave) > 200) {
                $errores[] = 'El campo contrasena debe ser un texto de 6 a 200 caracteres.';
            }
        } elseif (array_key_exists('contrasena', $datos)) {
            $clave = $datos['contrasena'];
            if (!is_string($clave) || strlen($clave) < 6 || strlen($clave) > 200) {
                $errores[] = 'El campo contrasena debe ser un texto de 6 a 200 caracteres.';
            }
        }

        if (array_key_exists('roles', $datos)) {
            $roles = $datos['roles'];
            if (!is_array($roles) || $roles === []) {
                // La lista vacia es 422 —forma—; la lista con un cero la
                // rechaza el servicio con 400 —regla—.
                $errores[] = 'El campo roles debe ser una lista con al menos un id.';
            } else {
                foreach ($roles as $id) {
                    if (!is_int($id)) {
                        $errores[] = 'Cada rol de la lista debe ser un entero.';
                        break;
                    }
                }
            }
        } elseif ($exigeRoles) {
            $errores[] = 'El campo roles es obligatorio.';
        }

        return $errores;
    }

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
