<?php
/**
 * ControladorSesion — la puerta de la calle.
 *
 * `POST /api/sesion` es el ÚNICO endpoint de la API al que se entra sin
 * token. Y tiene que ser así, porque no puede exigir lo que todavía no
 * existe. Junto con el diagnóstico `GET /`, son los dos únicos abiertos.
 *
 * LA RESPUESTA AL FALLAR ES LA MISMA EN LOS DOS CASOS —correo inexistente y
 * contraseña equivocada—: 401, con el mismo texto. Un 404 para el primero le
 * confirmaría a un desconocido qué correos SÍ existen (ver `ServicioSesion`).
 *
 * Los otros endpoints SÍ exigen token, y hablan siempre de uno mismo: el
 * correo sale del token, nunca del body ni de la URL. Eso es lo que impide
 * usarlos para tocarle la sesión a otro.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioSesion.php';

class ControladorSesion
{
    public function __construct(
        private readonly IServicioSesion $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // POST /api/sesion  →  entrar (SIN token: el único)
    // ------------------------------------------------------------------
    public function entrar(array $body): void
    {
        // La contraseña pide mínimo 1 y no 6, y es deliberado: aquí no se
        // está creando nada, se está comprobando. Exigir seis caracteres
        // para INTENTAR entrar le diría a quien prueba que las contraseñas
        // cortas no existen en el sistema.
        $errores = [];
        $email = $body['email'] ?? null;
        $clave = $body['contrasena'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            $errores[] = 'El campo email es obligatorio.';
        }
        if (!is_string($clave) || $clave === '') {
            $errores[] = 'El campo contrasena es obligatorio.';
        }
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body inválido.', 'errores' => $errores]);
            return;
        }

        try {
            $sesion = $this->servicio->entrar((string) $email, (string) $clave);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
            return;
        }

        if ($sesion === null) {
            // UN SOLO mensaje para los dos casos malos, a propósito.
            $this->responder(401, [
                'estado'  => 401,
                'mensaje' => 'El correo o la contrasena no son correctos.',
            ]);
            return;
        }
        $this->responder(200, $sesion);
    }

    // ------------------------------------------------------------------
    // GET /api/sesion  →  quién soy, según el token
    // ------------------------------------------------------------------
    public function quienSoy(array $quien): void
    {
        // No consulta la base de datos: lee el token y ya. Sirve para que una
        // interfaz sepa a nombre de quién está trabajando sin guardar ese
        // dato por su cuenta.
        $this->responder(200, ['email' => $quien['email'], 'roles' => $quien['roles']]);
    }

    // ------------------------------------------------------------------
    // POST /api/sesion/renovar  →  token nuevo, con los roles de HOY
    // ------------------------------------------------------------------
    public function renovar(array $quien): void
    {
        try {
            $sesion = $this->servicio->renovar((string) $quien['email']);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
            return;
        }
        if ($sesion === null) {
            $this->responder(401, ['estado' => 401, 'mensaje' => 'No hay una sesion valida.']);
            return;
        }
        $this->responder(200, $sesion);
    }

    // ------------------------------------------------------------------
    // DELETE /api/sesion  →  salir
    // ------------------------------------------------------------------
    public function salir(array $quien): void
    {
        // Cierra la sesión… del lado del cliente. Y hay que decirlo sin
        // maquillaje: **este endpoint no invalida el token**. Un JWT es
        // autocontenido: mientras no venza sigue siendo válido, y el
        // servidor no guarda ninguna lista de tokens vivos.
        //
        // Para invalidar de verdad haría falta una lista negra en la base de
        // datos y una consulta por petición — justo lo que un JWT viene a
        // evitar. Esa es la contrapartida de no guardar estado, y conviene
        // conocerla antes de elegirlo. Mientras tanto, la defensa es que el
        // token dure poco (`JWT_MINUTOS`).
        $this->responder(200, [
            'estado'      => 200,
            'mensaje'     => 'Sesion cerrada. Borre el token en el cliente.',
            'email'       => $quien['email'],
            'advertencia' => 'El token sigue siendo valido hasta que venza.',
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/permisos/mios  →  las rutas de quien llama
    // ------------------------------------------------------------------
    public function misPermisos(array $quien): void
    {
        // Y la advertencia que acompaña a este endpoint siempre: **esta lista
        // no protege nada.** Esconder una entrada del menú no es control de
        // acceso: quien escriba la dirección a mano llega igual. La
        // protección es el 403 que responde la API en CADA petición.
        try {
            $rutas = $this->servicio->misRutas((string) $quien['email']);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
            return;
        }
        $this->responder(200, [
            'consulta' => 'mis rutas',
            'email'    => $quien['email'],
            'roles'    => $quien['roles'],
            'total'    => count($rutas),
            'datos'    => $rutas,
        ]);
    }

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
