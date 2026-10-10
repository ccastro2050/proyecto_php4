<?php
/**
 * ControladorRol — la capa HTTP de `rol`.
 *
 * Su unico trabajo: leer la peticion, VALIDAR la forma del body (→ 422),
 * delegar al servicio y responder JSON con el codigo correcto. Aqui NO hay
 * SQL ni reglas de negocio.
 *
 * La traduccion, igual en los once controladores (6_contracts.md §0):
 *   Body con errores de forma     → 422 (con la lista de errores)
 *   InvalidArgumentException      → 400 (regla de negocio)
 *   NoEncontradoExcepcion         → 404
 *   ConflictoDeIntegridadExcepcion→ 409 (choca con lo que ya existe)
 *   cualquier otra                → 500
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioRol.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorRol
{
    public function __construct(
        // El TIPO es la INTERFAZ: el controlador no sabe que servicio hay.
        private readonly IServicioRol $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/rol[?limite=N]  →  listar
    // ------------------------------------------------------------------
    public function listar(): void
    {
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        try {
            $filas = $this->servicio->listar($limite);

            if ($filas === []) {
                http_response_code(204);   // exito SIN contenido: tabla vacia
                return;
            }
            $datos = [];
            foreach ($filas as $fila) {
                $datos[] = $fila->toArray();
            }
            $this->responder(200, [
                'tabla'  => 'rol',
                'limite' => $limite,
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/rol/{id}  →  obtener uno
    // ------------------------------------------------------------------
    public function obtener(int $id): void
    {
        try {
            $this->responder(200, $this->servicio->obtener($id)->toArray());
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Rol no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/rol  →  crear
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        // VALIDAR PRIMERO: el POST exige TODOS los campos.
        $errores = $this->validarCampos($body, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }

        try {
            $resultado = $this->servicio->crear($this->filtrarColumnas($body));
            // El 201 devuelve el id que genero la base de datos: quien creo
            // el rol no tiene otra forma de saberlo.
            $this->responder(201, ['estado' => 201, 'mensaje' => 'Rol creado exitosamente.', 'id' => $resultado]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 y no 400: lo que llego esta bien escrito, y choca con lo
            // que ya hay en la base de datos.
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La operacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/rol/{id}  →  reemplazo COMPLETO
    // ------------------------------------------------------------------
    public function reemplazar(int $id, array $body): void
    {
        // PUT exige TODOS los campos: omitir uno es 422, no «dejalo como
        // estaba». Esa es la semantica del verbo.
        $errores = $this->validarCampos($body, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }
        $this->escribir($id, $this->filtrarColumnas($body), 'reemplazado');
    }

    // ------------------------------------------------------------------
    // PATCH /api/rol/{id}  →  actualizacion PARCIAL
    // ------------------------------------------------------------------
    public function actualizar(int $id, array $body): void
    {
        // PATCH valida SOLO lo que llego (false = nada obligatorio).
        $errores = $this->validarCampos($body, false);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422, 'mensaje' => 'Body invalido.', 'errores' => $errores]);
            return;
        }
        $this->escribir($id, $this->filtrarColumnas($body), 'actualizado');
    }

    // ------------------------------------------------------------------
    // DELETE /api/rol/{id}
    // ------------------------------------------------------------------
    public function eliminar(int $id): void
    {
        try {
            $filas = $this->servicio->eliminar($id);
            $this->responder(200, ['estado' => 200, 'mensaje' => 'Rol eliminado exitosamente.', 'filasEliminadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Rol no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'No se puede eliminar: otra tabla lo referencia.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Lo comun de PUT y PATCH, en un solo sitio
    // ------------------------------------------------------------------
    private function escribir(int $id, array $datos, string $verbo): void
    {
        try {
            $filas = $this->servicio->actualizar($id, $datos);
            $this->responder(200, ['estado' => 200, 'mensaje' => "Rol $verbo exitosamente.", 'filasAfectadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404, 'mensaje' => 'Rol no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            $this->responder(409, ['estado' => 409, 'mensaje' => 'La operacion choca con los datos que ya existen.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Validacion de FORMA (lo que produce los 422)
    // ------------------------------------------------------------------

    /**
     * Valida `nombre`. Con $obligatorios=true (POST/PUT) debe venir; con
     * false (PATCH) solo se valida si llego.
     *
     * Y fijese en que el `id` NO se valida nunca: no lo manda el cliente.
     * Mandarlo seria pedirle que adivine un numero que todavia no existe.
     */
    private function validarCampos(array $datos, bool $obligatorios): array
    {
        $errores = [];

        if (array_key_exists('nombre', $datos)) {
            if (!is_string($datos['nombre']) || trim($datos['nombre']) === ''
                || strlen($datos['nombre']) > 50) {
                $errores[] = 'El campo nombre debe ser un texto de 1 a 50 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo nombre es obligatorio.';
        }

        return $errores;
    }


    /**
     * Deja pasar SOLO las columnas conocidas (lista blanca): cualquier campo
     * extrano que mande el cliente se ignora y jamas llega a un SQL.
     */
    private function filtrarColumnas(array $body): array
    {
        $datos = [];
        if (array_key_exists('nombre', $body)) {
            $datos['nombre'] = $body['nombre'];
        }
        return $datos;
    }

    // ------------------------------------------------------------------
    // Respuesta: SIEMPRE se sale por aqui
    // ------------------------------------------------------------------

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
