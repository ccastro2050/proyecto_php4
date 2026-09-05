<?php
/**
 * ControladorVendedor — la capa HTTP de `vendedor`.
 *
 * Lee la petición, VALIDA la forma del body (→ 422), delega al servicio y
 * responde JSON. Aquí no hay SQL ni reglas de negocio.
 *
 * LO QUE ESTA ENTIDAD AGREGA A LA v1: tiene **llaves foráneas** (`fkcodpersona` → `persona`).
 * Este controlador NO las comprueba, y es a propósito: valida la FORMA
 * —que el código sea un texto de la longitud correcta— y deja que la base
 * decida si ese código existe. Cuando la base dice que no, el repositorio
 * lo traduce y aquí sale un **409**, no un 500.
 *
 * La traducción de excepciones, igual en los cinco métodos:
 *   Body con errores de forma      → 422 (con la lista de errores)
 *   InvalidArgumentException       → 400 (regla de negocio)
 *   NoEncontradoExcepcion          → 404 (no existe)
 *   ConflictoDeIntegridadExcepcion → 409 (choca con los datos que hay)
 *   Cualquier otra                 → 500
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioVendedor.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorVendedor
{
    public function __construct(
        private readonly IServicioVendedor $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/vendedor[?limite=N]  →  listar
    // ------------------------------------------------------------------
    public function listar(): void
    {
        if (isset($_GET['limite'])) {
            $limite = (int) $_GET['limite'];
        } else {
            $limite = 1000;
        }

        try {
            $fichas = $this->servicio->listar($limite);

            if ($fichas === []) {
                http_response_code(204);   // 204 = éxito SIN contenido: tabla vacía
                return;
            }
            $datos = [];
            foreach ($fichas as $ficha) {
                $datos[] = $ficha->toArray();
            }
            $this->responder(200, [
                'tabla'  => 'vendedor',
                'limite' => $limite,
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/vendedor/{id}  →  obtener uno
    // ------------------------------------------------------------------
    public function obtener(int $id): void
    {
        try {
            $ficha = $this->servicio->obtener($id);
            $this->responder(200, $ficha->toArray());
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/vendedor  →  crear
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        // Fíjese en lo que NO se valida: el id. Lo genera la base de datos
        // (AUTO_INCREMENT), así que el cliente no lo manda — **lo recibe**.
        // Si viniera en el body, la lista blanca lo bota.
        $errores = $this->validarCampos($body, true);   // true = todos obligatorios
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $idNuevo = $this->servicio->crear($body);
            // Se devuelve la llave recién generada: sin esto, quien creó la
            // ficha no tendría cómo volver a pedirla.
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Vendedor creado exitosamente.',
                'id' => $idNuevo,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/vendedor/{id}  →  reemplazo COMPLETO
    // ------------------------------------------------------------------
    public function reemplazar(int $id, array $body): void
    {
        // PUT reemplaza el recurso entero: los campos obligatorios
        // (carnet, direccion, fkcodpersona) deben venir todos. Omitir uno es 422, no
        // "déjalo como estaba" — para eso está PATCH.
        $errores = $this->validarCampos($body, true);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $filas = $this->servicio->actualizar($id, $this->filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Vendedor reemplazado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PATCH /api/vendedor/{id}  →  actualización PARCIAL
    // ------------------------------------------------------------------
    public function actualizar(int $id, array $body): void
    {
        // El mismo body que PUT rechaza por incompleto, aquí pasa: PATCH
        // solo valida lo que llegó (false = nada es obligatorio).
        $errores = $this->validarCampos($body, false);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $filas = $this->servicio->actualizar($id, $this->filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Vendedor actualizado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/vendedor/{id}  →  eliminar
    // ------------------------------------------------------------------
    public function eliminar(int $id): void
    {
        try {
            $filas = $this->servicio->eliminar($id);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Vendedor eliminado exitosamente.',
                'filasEliminadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Vendedor no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (ConflictoDeIntegridadExcepcion $e) {
            // 409 — la petición estaba bien escrita, pero no cabe en los
            // datos que hay. Ver errores_de_integridad.php.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ==================================================================
    // LA VALIDACIÓN DEL BODY (los ifs de la frontera HTTP → 422)
    // ==================================================================

    /**
     * Valida los campos de la ficha.
     * Con $obligatorios=true (POST/PUT) los obligatorios deben venir;
     * con false (PATCH) solo se valida lo que llegue.
     */
    private function validarCampos(array $datos, bool $obligatorios): array
    {
        $errores = [];

        if (array_key_exists('carnet', $datos)) {
            if (!is_int($datos['carnet']) || $datos['carnet'] < 0) {
                $errores[] = 'El campo carnet debe ser un entero mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo carnet es obligatorio.';
        }

        if (array_key_exists('direccion', $datos)) {
            if (!is_string($datos['direccion']) || trim($datos['direccion']) === '' || strlen($datos['direccion']) > 100) {
                $errores[] = 'El campo direccion debe ser un texto de 1 a 100 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo direccion es obligatorio.';
        }

        if (array_key_exists('fkcodpersona', $datos)) {
            if (!is_string($datos['fkcodpersona']) || trim($datos['fkcodpersona']) === '' || strlen($datos['fkcodpersona']) > 10) {
                $errores[] = 'El campo fkcodpersona debe ser un texto de 1 a 10 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo fkcodpersona es obligatorio.';
        }

        return $errores;
    }

    /** Lista blanca: los campos desconocidos se ignoran y jamás llegan al SQL. */
    private function filtrarColumnas(array $body): array
    {
        $datos = [];
        if (array_key_exists('carnet', $body)) {
            $datos['carnet'] = $body['carnet'];
        }
        if (array_key_exists('direccion', $body)) {
            $datos['direccion'] = $body['direccion'];
        }
        if (array_key_exists('fkcodpersona', $body)) {
            $datos['fkcodpersona'] = $body['fkcodpersona'];
        }
        return $datos;
    }

    // ------------------------------------------------------------------
    // Respuesta: SIEMPRE se sale por aquí
    // ------------------------------------------------------------------

    /** Escribe el código de estado y el cuerpo JSON de la respuesta. */
    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
