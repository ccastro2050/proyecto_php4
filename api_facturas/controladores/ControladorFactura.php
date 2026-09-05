<?php
/**
 * ControladorFactura — la capa HTTP de las facturas.
 *
 * LA VALIDACIÓN AQUÍ ES DISTINTA A LA DE LAS DEMÁS ENTIDADES, y es lo que
 * hay que mirar: el body no es una ficha plana, trae una **lista adentro**.
 *
 *     {
 *       "fkidcliente": 1,
 *       "fkidvendedor": 2,
 *       "detalle": [
 *         { "codigo": "PR001", "cantidad": 2 },
 *         { "codigo": "PR003", "cantidad": 1 }
 *       ]
 *     }
 *
 * Validar eso es validar dos niveles: que `detalle` sea una lista y no venga
 * vacía, y que **cada renglón** traiga su código y su cantidad con el tipo
 * correcto. Un mensaje que dijera «el detalle es inválido» no sirve: hay que
 * decir CUÁL renglón y POR QUÉ.
 *
 * Lo que este controlador NO valida, y es igual de importante:
 *   · que el producto exista o que haya stock — lo dice la base;
 *   · el total y los subtotales — los calcula un trigger; si vinieran en el
 *     body, la lista blanca los bota.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioFactura.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

class ControladorFactura
{
    public function __construct(
        private readonly IServicioFactura $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/factura  →  listar (todas, con su detalle)
    // ------------------------------------------------------------------
    public function listar(): void
    {
        try {
            $facturas = $this->servicio->listar();

            if ($facturas === []) {
                http_response_code(204);
                return;
            }
            $datos = [];
            foreach ($facturas as $factura) {
                $datos[] = $factura->toArray();
            }
            $this->responder(200, [
                'tabla'  => 'factura',
                'limite' => null,   // este recurso no acepta ?limite (ver el servicio)
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/factura/{numero}  →  una factura con su detalle
    // ------------------------------------------------------------------
    public function obtener(int $numero): void
    {
        try {
            $this->responder(200, $this->servicio->obtener($numero)->toArray());
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/factura  →  crear (encabezado + detalle, de una sola vez)
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = $this->validarFactura($body);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $factura = $this->servicio->crear($body);
            // Se devuelve la factura COMPLETA, no solo su número: quien la
            // creó necesita ver el total que calculó el trigger, que es un
            // dato que no mandó y no podía saber.
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Factura creada exitosamente.',
                'factura' => $factura->toArray(),
            ]);
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/factura/{numero}  →  reemplazo COMPLETO
    // ------------------------------------------------------------------
    public function reemplazar(int $numero, array $body): void
    {
        // No hay PATCH de facturas, y no es un olvido: cambiar un renglón
        // cambia el total y el stock, así que el detalle se reemplaza entero
        // o no se toca. Un PATCH daría la impresión de que se puede editar
        // "solo un pedacito", y no se puede.
        $errores = $this->validarFactura($body);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $factura = $this->servicio->reemplazar($numero, $body);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Factura reemplazada exitosamente.',
                'factura' => $factura->toArray(),
            ]);
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/factura/{numero}/anular  →  anular
    // ------------------------------------------------------------------
    public function anular(int $numero): void
    {
        // Anular NO es eliminar: la factura se queda, con su número y su
        // fecha, marcada como anulada, y el stock vuelve a los productos.
        // Un negocio no borra facturas — las anula, y queda el rastro.
        //
        // Por eso tiene su propia ruta y su propio verbo: meterla dentro de
        // un PATCH de `estado` habría sido pretender que es un campo más.
        try {
            $resultado = $this->servicio->anular($numero);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Factura anulada exitosamente.',
                'resultado' => $resultado,
            ]);
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/factura/{numero}  →  borrar de verdad
    // ------------------------------------------------------------------
    public function eliminar(int $numero): void
    {
        try {
            $resultado = $this->servicio->eliminar($numero);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Factura eliminada exitosamente.',
                'resultado' => $resultado,
            ]);
        } catch (Throwable $e) {
            $this->responderError($e);
        }
    }

    // ==================================================================
    // LA VALIDACIÓN DEL BODY (→ 422)
    // ==================================================================

    /** Valida el encabezado y, uno por uno, los renglones del detalle. */
    private function validarFactura(array $body): array
    {
        $errores = [];

        foreach (['fkidcliente', 'fkidvendedor'] as $campo) {
            if (!array_key_exists($campo, $body)) {
                $errores[] = "El campo $campo es obligatorio.";
            } elseif (!is_int($body[$campo]) || $body[$campo] <= 0) {
                $errores[] = "El campo $campo debe ser un entero mayor que cero.";
            }
        }

        // --- El detalle: primero que sea una lista con algo adentro ---
        if (!array_key_exists('detalle', $body)) {
            $errores[] = 'El campo detalle es obligatorio: la lista de productos.';
            return $errores;   // sin detalle no hay nada más que revisar
        }
        if (!is_array($body['detalle']) || $body['detalle'] === []) {
            $errores[] = 'El detalle debe traer al menos un producto.';
            return $errores;
        }

        // --- Y ahora cada renglón, diciendo CUÁL falla ---
        foreach ($body['detalle'] as $i => $renglon) {
            // El número que ve la persona empieza en 1, no en 0:
            $n = (int) $i + 1;

            if (!is_array($renglon)) {
                $errores[] = "El renglón $n del detalle no es un producto válido.";
                continue;
            }
            $codigo = $renglon['codigo'] ?? null;
            if (!is_string($codigo) || trim($codigo) === '' || strlen($codigo) > 10) {
                $errores[] = "El renglón $n: el codigo debe ser un texto de 1 a 10 caracteres.";
            }
            $cantidad = $renglon['cantidad'] ?? null;
            if (!is_int($cantidad) || $cantidad <= 0) {
                $errores[] = "El renglón $n: la cantidad debe ser un entero mayor que cero.";
            }
        }

        return $errores;
    }

    // ------------------------------------------------------------------
    // Respuesta
    // ------------------------------------------------------------------

    /**
     * La traducción de excepciones, en un solo sitio.
     *
     * Los demás controladores repiten los cuatro `catch` en cada método
     * porque así se ven de una. Aquí son seis operaciones y la repetición
     * dejaba de ayudar: se lee mejor una vez, bien explicada.
     */
    private function responderError(Throwable $e): void
    {
        if ($e instanceof InvalidArgumentException) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
            return;
        }
        if ($e instanceof NoEncontradoExcepcion) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Factura no encontrada.', 'detalle' => $e->getMessage(),
            ]);
            return;
        }
        if ($e instanceof ConflictoDeIntegridadExcepcion) {
            // Aquí llegan los rechazos de los procedimientos: «la factura
            // requiere mínimo 1 producto», «ya está anulada», «no hay stock
            // suficiente». Son reglas del negocio, no fallas del sistema.
            $this->responder(409, [
                'estado' => 409, 'mensaje' => 'Conflicto con los datos existentes.',
                'detalle' => $e->getMessage(),
            ]);
            return;
        }
        $this->responder(500, [
            'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
        ]);
    }

    /** Escribe el código de estado y el cuerpo JSON de la respuesta. */
    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
