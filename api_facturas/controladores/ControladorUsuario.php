<?php
/**
 * ControladorUsuario — la capa HTTP de `usuario`.
 *
 * La traducción, igual que en los demás (6_contracts.md §0):
 *   Body con errores de forma     → 422 (con la lista de errores)
 *   InvalidArgumentException      → 400 (regla de negocio)
 *   NoEncontradoExcepcion         → 404
 *   ConflictoDeIntegridadExcepcion→ 409 (el email ya existe)
 *   cualquier otra                → 500
 *
 * Y UNA MÁS, que solo tiene este recurso: **401**, en
 * `verificar-contrasena`, cuando el usuario existe y la contraseña no
 * coincide. Es el único 401 de la API que no viene del token.
 *
 * NINGUNA RESPUESTA DE ESTE CONTROLADOR DEVUELVE LA CONTRASEÑA, ni en hash.
 * No hace falta cuidarlo endpoint por endpoint: el modelo no la expone en su
 * `toArray()`, así que no hay por dónde escaparse.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioUsuario.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorUsuario
{
    public function __construct(
        private readonly IServicioUsuario $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/usuario[?limite=N]  →  listar (solo emails)
    // ------------------------------------------------------------------
    public function listar(): void
    {
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        try {
            $usuarios = $this->servicio->listar($limite);

            if ($usuarios === []) {
                http_response_code(204);
                return;
            }
            $datos = [];
            foreach ($usuarios as $usuario) {
                $datos[] = $usuario->toArray();
            }
            $this->responder(200, [
                'tabla'  => 'usuario',
                'limite' => $limite,
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/usuario/{email}  →  obtener uno
    // ------------------------------------------------------------------
    public function obtener(string $email): void
    {
        try {
            $this->responder(200, $this->servicio->obtener($email)->toArray());
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/usuario  →  crear (el email SÍ lo manda el cliente)
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = array_merge(
            $this->validarEmail($body),
            $this->validarContrasena($body, true),
        );
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body inválido.', 'errores' => $errores]);
            return;
        }

        try {
            $this->servicio->crear([
                'email'      => $body['email'],
                'contrasena' => $body['contrasena'],
            ]);
            // La respuesta devuelve el email y NADA más.
            $this->responder(201, [
                'estado'  => 201,
                'mensaje' => 'Usuario creado exitosamente.',
                'email'   => strtolower(trim((string) $body['email'])),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'El usuario ya existe.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/usuario/{email}  →  reemplazo COMPLETO (la contraseña)
    // ------------------------------------------------------------------
    public function reemplazar(string $email, array $body): void
    {
        // En esta tabla «completo» es una sola columna: la contraseña. El
        // email no se reemplaza porque el email ES la llave — cambiarlo
        // sería borrar un usuario y crear otro, y arrastrar sus roles.
        $errores = $this->validarContrasena($body, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body inválido.', 'errores' => $errores]);
            return;
        }
        $this->escribir($email, ['contrasena' => $body['contrasena']], 'reemplazada');
    }

    // ------------------------------------------------------------------
    // PATCH /api/usuario/{email}  →  actualización PARCIAL
    // ------------------------------------------------------------------
    public function actualizar(string $email, array $body): void
    {
        // Con una sola columna actualizable, PATCH y PUT hacen lo mismo.
        // Están los dos a propósito: el contrato de la API no cambia porque
        // esta tabla sea angosta hoy, y el día que gane una columna —último
        // ingreso, estado— el PATCH ya está donde debe estar.
        $errores = $this->validarContrasena($body, false);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body inválido.', 'errores' => $errores]);
            return;
        }
        $datos = array_key_exists('contrasena', $body)
            ? ['contrasena' => $body['contrasena']]
            : [];
        $this->escribir($email, $datos, 'actualizada');
    }

    // ------------------------------------------------------------------
    // DELETE /api/usuario/{email}
    // ------------------------------------------------------------------
    public function eliminar(string $email): void
    {
        try {
            $filas = $this->servicio->eliminar($email);
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Usuario eliminado exitosamente.', 'filasEliminadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // El usuario todavía tiene roles: la foránea de rol_usuario lo
            // rechaza. Primero se le quitan, o se usa /api/usuario-con-roles.
            $this->responder(409, ['estado' => 409, 'mensaje' => 'No se puede eliminar: el usuario todavía tiene roles.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/usuario/verificar-contrasena  →  comprobar sin entrar
    // ------------------------------------------------------------------
    public function verificarContrasena(array $body): void
    {
        $errores = array_merge(
            $this->validarEmail($body),
            $this->validarContrasena($body, true, 1),   // 1 = mínimo para comprobar
        );
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body inválido.', 'errores' => $errores]);
            return;
        }

        try {
            $coincide = $this->servicio->verificarContrasena(
                (string) $body['email'], (string) $body['contrasena']);

            if (!$coincide) {
                // 401: se sabe quién dice ser, y la prueba no cuadra.
                $this->responder(401, ['estado' => 401, 'mensaje' => 'Credenciales inválidas.', 'detalle' => 'La contraseña no coincide.']);
                return;
            }
            $this->responder(200, ['estado' => 200, 'mensaje' => 'La contraseña coincide.', 'email' => strtolower(trim((string) $body['email']))]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            // 404 y no 401, y es deliberado: este endpoint es para un
            // administrador que ya entró, no para la puerta de la calle.
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Lo común de PUT y PATCH
    // ------------------------------------------------------------------
    private function escribir(string $email, array $datos, string $verbo): void
    {
        try {
            $filas = $this->servicio->actualizar($email, $datos);
            $this->responder(200, ['estado' => 200, 'mensaje' => "Contraseña $verbo exitosamente.", 'filasAfectadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Usuario no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Validación de FORMA (lo que produce los 422)
    // ------------------------------------------------------------------

    /**
     * El email: texto, no vacío, hasta 100, y con forma de correo.
     *
     * La forma se comprueba con `filter_var`, que viene en PHP y hace esto
     * mucho mejor que una expresión regular escrita a mano. Lo que NO
     * comprueba —ni puede— es que el buzón exista.
     */
    private function validarEmail(array $datos): array
    {
        $email = $datos['email'] ?? null;
        if (!is_string($email) || trim($email) === '' || strlen($email) > 100) {
            return ['El campo email es obligatorio: texto de 1 a 100 caracteres.'];
        }
        if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            return ['El campo email no tiene forma de correo electrónico.'];
        }
        return [];
    }

    /**
     * La contraseña: texto de $minimo a 200 caracteres.
     *
     * El mínimo por defecto es 6 al crear o cambiar. Al COMPROBAR una
     * contraseña el mínimo baja a 1, y no es un descuido: exigir seis
     * caracteres para *intentar* le diría a quien prueba que las contraseñas
     * cortas no existen en el sistema.
     */
    private function validarContrasena(array $datos, bool $obligatoria, int $minimo = 6): array
    {
        if (array_key_exists('contrasena', $datos)) {
            $clave = $datos['contrasena'];
            if (!is_string($clave) || strlen($clave) < $minimo || strlen($clave) > 200) {
                return ["El campo contrasena debe ser un texto de $minimo a 200 caracteres."];
            }
            return [];
        }
        return $obligatoria ? ['El campo contrasena es obligatorio.'] : [];
    }

    // ------------------------------------------------------------------
    // Respuesta
    // ------------------------------------------------------------------

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
