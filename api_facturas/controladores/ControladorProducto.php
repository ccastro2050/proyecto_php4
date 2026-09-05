<?php
/**
 * ControladorProducto — la capa HTTP de la v1.
 *
 * Su único trabajo: leer la petición, VALIDAR la forma del body (→ 422),
 * delegar al servicio, y responder JSON con el código correcto.
 * Aquí NO hay SQL ni reglas de negocio.
 *
 * Cada método público es un verbo de la API (index.php lo llama según el
 * método HTTP que llegó) y lleva su propio try/catch, siempre con la misma
 * traducción (el contrato de 6_contracts.md §0):
 *   Body con errores de forma    → 422 (con la lista de errores)
 *   InvalidArgumentException     → 400 (regla de negocio, la lanza el servicio)
 *   NoEncontradoExcepcion        → 404 (no existe, la lanza el servicio)
 *   PDOException y cualquier otra→ 500 (mensaje del motor en `detalle`)
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioProducto.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ControladorProducto
{
    // El constructor recibe y guarda el servicio (PHP 8: declarar el
    // parámetro con "private readonly" lo vuelve atributo del objeto).
    // Ojo al TIPO: es la INTERFAZ IServicioProducto — el controlador no
    // sabe ni le importa qué servicio concreto hay detrás.
    public function __construct(
        private readonly IServicioProducto $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/producto[?limite=N]  →  listar
    // ------------------------------------------------------------------
    public function listar(): void
    {
        // $_GET trae el query string (?limite=5 → $_GET['limite']), SIEMPRE
        // como texto: "(int)" lo convierte a entero aquí, en la frontera.
        // Si no vino, queda el valor por defecto 1000.
        if (isset($_GET['limite'])) {
            $limite = (int) $_GET['limite'];
        } else {
            $limite = 1000;
        }

        try {
            $productos = $this->servicio->listar($limite);

            if ($productos === []) {
                http_response_code(204);   // 204 = éxito SIN contenido: tabla vacía
                return;
            }
            // $productos es una lista de objetos Producto. Como sus
            // propiedades son privadas, cada uno se convierte a array con
            // su toArray() antes de responder:
            $datos = [];
            foreach ($productos as $producto) {
                $datos[] = $producto->toArray();
            }
            // La "envoltura" de las lecturas: metadatos + datos.
            $this->responder(200, [
                'tabla'  => 'producto',
                'limite' => $limite,
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (InvalidArgumentException $e) {
            // El servicio rechazó una regla de negocio (ej. límite <= 0):
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            // Throwable atrapa TODO lo demás (ej. la BD no responde) → 500:
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/producto/{codigo}  →  obtener uno
    // ------------------------------------------------------------------
    public function obtener(string $codigo): void
    {
        try {
            // Si existe, el servicio devuelve el objeto Producto (se
            // responde su toArray). Si no existe, el servicio LANZA la
            // excepción y este método salta directo al catch del 404.
            $producto = $this->servicio->obtener($codigo);
            $this->responder(200, $producto->toArray());
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/producto  →  crear (body completo, con código)
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        // VALIDAR PRIMERO. POST exige TODO: el código y los 3 campos.
        // array_merge une las dos listas de errores en una sola.
        $errores = array_merge(
            $this->validarCodigo($body),
            $this->validarCampos($body, true),   // true = todos obligatorios
        );
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;   // con errores de forma no se sigue: nada llegó a la BD
        }

        try {
            // Se arma el registro: el código + SOLO las columnas conocidas:
            $datos = $this->filtrarColumnas($body);
            $datos['codigo'] = $body['codigo'];

            $this->servicio->crear($datos);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto creado exitosamente.',
            ]);
        } catch (Throwable $e) {
            // Ej.: código duplicado — la BD rechaza por llave primaria:
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/producto/{codigo}  →  reemplazo COMPLETO
    // ------------------------------------------------------------------
    public function reemplazar(string $codigo, array $body): void
    {
        // PUT exige TODOS los campos (el código va en la URL): un PUT con
        // body parcial muere aquí con 422 — esa es la semántica de PUT.
        $errores = $this->validarCampos($body, true);   // true = todos obligatorios
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            $filas = $this->servicio->actualizar($codigo, $this->filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto reemplazado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PATCH /api/producto/{codigo}  →  actualización PARCIAL
    // ------------------------------------------------------------------
    public function actualizar(string $codigo, array $body): void
    {
        // PATCH valida SOLO lo que llegó (false = nada es obligatorio).
        // El MISMO body {"stock": 99} que en PUT da 422, aquí pasa —
        // la diferencia entre PUT y PATCH queda escrita en código.
        $errores = $this->validarCampos($body, false);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        try {
            // El body vacío NO es 422: es una regla de negocio (400) que
            // decide el servicio — forma vs negocio, cada cosa en su capa.
            $filas = $this->servicio->actualizar($codigo, $this->filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto actualizado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/producto/{codigo}  →  eliminar
    // ------------------------------------------------------------------
    public function eliminar(string $codigo): void
    {
        try {
            $filas = $this->servicio->eliminar($codigo);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto eliminado exitosamente.',
                'filasEliminadas' => $filas,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    // ==================================================================
    // LA VALIDACIÓN DEL BODY (los ifs de la frontera HTTP → 422).
    // Métodos privados: validar la FORMA de la petición es trabajo de la
    // capa HTTP. Devuelven LISTA de errores (vacía = todo bien) para
    // reportarle al cliente todos los problemas de una vez.
    // ==================================================================

    /** El código: obligatorio, texto de 1 a 10 caracteres (VARCHAR(10)). */
    private function validarCodigo(array $datos): array
    {
        // ?? = "si la llave no existe (o es null), usa null":
        $codigo = $datos['codigo'] ?? null;
        // Tres condiciones unidas con || (basta que UNA falle):
        if (!is_string($codigo) || $codigo === '' || strlen($codigo) > 10) {
            return ['El campo codigo es obligatorio: texto de 1 a 10 caracteres.'];
        }
        return [];   // lista vacía = sin errores
    }

    /**
     * Valida nombre, stock y valorunitario.
     * Con $obligatorios=true (POST/PUT) los tres deben venir;
     * con false (PATCH) solo se valida lo que llegue.
     */
    private function validarCampos(array $datos, bool $obligatorios): array
    {
        // Se acumulan TODOS los errores (no se corta en el primero):
        $errores = [];

        // array_key_exists pregunta "¿vino este campo en el body?".
        // El patrón de cada campo es el mismo:
        //   si vino    → validar su contenido
        //   si no vino → es error SOLO cuando el verbo lo exige (POST/PUT)
        if (array_key_exists('nombre', $datos)) {
            // trim quita espacios a los lados: "   " no cuenta como nombre.
            if (!is_string($datos['nombre']) || trim($datos['nombre']) === '') {
                $errores[] = 'El campo nombre debe ser un texto no vacío.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo nombre es obligatorio.';
        }

        if (array_key_exists('stock', $datos)) {
            // is_int rechaza "7" (texto) y 7.5 (decimal): el TIPO también es
            // parte de la regla.
            if (!is_int($datos['stock']) || $datos['stock'] < 0) {
                $errores[] = 'El campo stock debe ser un entero mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo stock es obligatorio.';
        }

        if (array_key_exists('valorunitario', $datos)) {
            $valor = $datos['valorunitario'];
            // El precio acepta entero O decimal (120000 y 120000.50 valen):
            if ((!is_int($valor) && !is_float($valor)) || $valor < 0) {
                $errores[] = 'El campo valorunitario debe ser un número mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo valorunitario es obligatorio.';
        }

        return $errores;
    }

    /**
     * Deja pasar SOLO las columnas conocidas (lista blanca): cualquier
     * campo extraño que mande el cliente se ignora y jamás llega a un SQL.
     */
    private function filtrarColumnas(array $body): array
    {
        $datos = [];
        if (array_key_exists('nombre', $body)) {
            $datos['nombre'] = $body['nombre'];
        }
        if (array_key_exists('stock', $body)) {
            $datos['stock'] = $body['stock'];
        }
        if (array_key_exists('valorunitario', $body)) {
            $datos['valorunitario'] = $body['valorunitario'];
        }
        return $datos;
    }

    // ------------------------------------------------------------------
    // Respuesta: SIEMPRE se sale por aquí
    // ------------------------------------------------------------------

    /** Escribe el código de estado y el cuerpo JSON de la respuesta. */
    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);                        // el código HTTP (200, 404…)
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);  // el body como JSON
    }
}
