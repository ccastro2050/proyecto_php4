<?php
/**
 * RepositorioFacturaPostgres — la capa de DATOS de las facturas contra
 * PostgreSQL.
 *
 * ======================================================================
 * ÁBRALO AL LADO DE `RepositorioFacturaMariaDB.php`
 * ======================================================================
 *
 * Los dos llaman a los MISMOS seis procedimientos almacenados, con los mismos
 * nombres y los mismos parámetros. Y aun así, la forma de llamarlos no se
 * parece — que es exactamente lo que esta versión viene a enseñar.
 *
 * **Cómo devuelve un procedimiento su resultado:**
 *
 * | | MariaDB | PostgreSQL |
 * |---|---|---|
 * | El parámetro de salida | `OUT`, y PDO **no lo lee** | `INOUT` |
 * | Cómo se recoge | pasarle una variable de sesión `@resultado` y consultarla en OTRA sentencia | **viene como una fila del propio `CALL`** |
 * | Sentencias por llamada | **dos** | **una** |
 * | Hace falta `closeCursor()` | **sí**, o la segunda consulta falla | no |
 *
 * O sea que PostgreSQL lo resuelve de la forma directa: `CALL sp(?, NULL)`
 * devuelve una fila con una columna llamada `p_resultado`, y ahí está el
 * JSON. En MariaDB había que hacer el rodeo de la variable de sesión.
 *
 * **Cómo rechaza un procedimiento:**
 *
 * | | MariaDB | PostgreSQL |
 * |---|---|---|
 * | La instrucción | `SIGNAL SQLSTATE '45000'` | `RAISE EXCEPTION` |
 * | El SQLSTATE que llega | `45000` | `P0001` |
 * | El mensaje | viene con el número del motor delante | viene con un bloque `CONTEXT:` **detrás** |
 *
 * Los dos hay que limpiarlos, y de forma distinta. Ninguna de estas
 * diferencias es grande por separado; juntas explican por qué el detalle del
 * motor tiene que quedar encerrado en una clase por motor.
 *
 * ======================================================================
 * LO QUE NO CAMBIA, Y ES LO QUE IMPORTA
 * ======================================================================
 *
 * La interfaz. Esta clase cumple `IRepositorioFactura` igual que la otra, así
 * que el servicio, el controlador y la pantalla no se enteran de nada. Ése es
 * el examen de la v3, y se aprueba o se reprueba aquí.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioFactura.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';
require_once __DIR__ . '/../modelos/Factura.php';
require_once __DIR__ . '/../modelos/LineaFactura.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class RepositorioFacturaPostgres implements IRepositorioFactura
{
    private ?PDO $conexion = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $usuario,
        private readonly string $clave,
    ) {
    }

    // ------------------------------------------------------------------
    // Ayudantes privados
    // ------------------------------------------------------------------

    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return $this->conexion;
    }

    /**
     * Llama un procedimiento y devuelve el JSON de su parámetro de salida,
     * ya convertido a array.
     *
     * Compare este método con el de MariaDB: allá son tres pasos (CALL,
     * closeCursor, SELECT @resultado); aquí es uno, porque el `CALL` de
     * PostgreSQL **devuelve una fila** con los parámetros INOUT.
     *
     * @param string $nombre     El procedimiento, ej. 'sp_anular_factura'
     * @param array  $parametros Los parámetros de ENTRADA, en orden
     */
    private function llamarProcedimiento(string $nombre, array $parametros): array
    {
        // Un `?` por cada parámetro de entrada, y un NULL literal al final
        // para el INOUT. El NULL va escrito en el SQL y no como parámetro:
        // así PostgreSQL sabe que ese hueco es el de salida.
        $marcadores = array_fill(0, count($parametros), '?');
        $marcadores[] = 'NULL';
        $sql = "CALL $nombre(" . implode(', ', $marcadores) . ")";

        try {
            $sentencia = $this->obtenerConexion()->prepare($sql);
            $sentencia->execute($parametros);
        } catch (PDOException $error) {
            // P0001 = raise_exception: el procedimiento dijo que no, con su
            // motivo de negocio. No es un error nuestro.
            if ((string) $error->getCode() === 'P0001') {
                $this->interpretarRechazo($this->limpiarMensaje($error->getMessage()));
            }
            // Si no, puede ser un problema de integridad corriente
            // (un cliente que no existe) o algo peor.
            traducirErrorPostgres($error, 'la factura');
        }

        // La fila que devuelve el CALL trae una sola columna: `p_resultado`.
        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        $json = $fila === false ? '' : (string) reset($fila);

        return json_decode($json, true) ?? [];
    }

    /**
     * Decide qué clase de «no» dijo el procedimiento.
     *
     * Todos los rechazos llegan con el mismo SQLSTATE, así que el único dato
     * que los distingue es **el texto del mensaje**:
     *
     *   · «Factura 999 no existe»          → 404, la ficha no está;
     *   · «Factura 7 esta anulada…»        → 409, existe pero no se deja;
     *   · «La factura requiere minimo 1…»  → 409, la petición no cumple.
     *
     * Es la misma fragilidad que en el repositorio de MariaDB, y se acepta
     * por la misma razón: el arreglo limpio —que cada rechazo trajera su
     * propio código— exigiría cambiar los seis procedimientos **en los dos
     * motores**. Queda en un solo método por motor.
     */
    private function interpretarRechazo(string $mensaje): never
    {
        if (str_contains($mensaje, 'no existe')) {
            throw new NoEncontradoExcepcion($mensaje);
        }
        throw new ConflictoDeIntegridadExcepcion($mensaje);
    }

    /**
     * PostgreSQL envuelve el mensaje del RAISE así:
     *
     *   SQLSTATE[P0001]: Raise exception: 7 ERROR:  Factura 999 no existe
     *   CONTEXT:  PL/pgSQL function sp_anular_factura(integer,json) line 9…
     *
     * Lo que escribió el procedimiento es lo que va entre «ERROR:» y
     * «CONTEXT:». En MariaDB el ruido iba delante; aquí va detrás también.
     */
    private function limpiarMensaje(string $mensaje): string
    {
        if (preg_match('/ERROR:\s+(.+?)(?:\s*CONTEXT:|$)/s', $mensaje, $coincidencias)) {
            return trim($coincidencias[1]);
        }
        return $mensaje;
    }

    /** Arma el modelo a partir del JSON que devolvió un procedimiento. */
    private function armarFactura(array $crudo): Factura
    {
        $cabeza = $crudo['factura'] ?? $crudo;
        $renglones = $crudo['productos'] ?? [];

        $detalle = [];
        // json_agg de PostgreSQL devuelve NULL —no una lista vacía— cuando no
        // hay filas que agregar. Una factura sin renglones no debería existir,
        // pero el `?? []` evita que un dato raro tumbe la pantalla.
        foreach ($renglones ?? [] as $r) {
            $detalle[] = new LineaFactura(
                (string) ($r['codigo_producto'] ?? ''),
                (string) ($r['nombre_producto'] ?? ''),
                (int) ($r['cantidad'] ?? 0),
                (float) ($r['valorunitario'] ?? 0),
                (float) ($r['subtotal'] ?? 0),
            );
        }

        return new Factura(
            (int) $cabeza['numero'],
            // PostgreSQL devuelve la fecha con microsegundos
            // ("2025-12-03T12:57:19.27592"). Se recortan a los segundos para
            // que el contrato entregue el mismo formato con los dos motores:
            // quien consuma la API no tiene por qué notar cuál hay detrás.
            substr((string) ($cabeza['fecha'] ?? ''), 0, 19),
            (float) ($cabeza['total'] ?? 0),
            (string) ($cabeza['estado'] ?? 'activa'),
            (int) ($cabeza['fkidcliente'] ?? 0),
            (int) ($cabeza['fkidvendedor'] ?? 0),
            $detalle,
            $cabeza['nombre_cliente'] ?? null,
            $cabeza['nombre_vendedor'] ?? null,
        );
    }

    /**
     * Vuelve a leer una factura que ACABA de guardarse, para que el contrato
     * sea uniforme (el procedimiento de insertar no devuelve los nombres).
     */
    private function releer(int $numero): Factura
    {
        $factura = $this->obtenerPorNumero($numero);
        if ($factura === null) {
            throw new RuntimeException(
                "La factura $numero se guardó pero no se pudo volver a leer."
            );
        }
        return $factura;
    }

    // ------------------------------------------------------------------
    // Las operaciones del contrato
    // ------------------------------------------------------------------

    public function obtenerTodas(): array
    {
        $crudo = $this->llamarProcedimiento(
            'sp_listar_facturas_y_productosporfactura', []
        );

        $facturas = [];
        foreach ($crudo as $f) {
            $facturas[] = $this->armarFactura($f);
        }
        return $facturas;
    }

    public function obtenerPorNumero(int $numero): ?Factura
    {
        try {
            $crudo = $this->llamarProcedimiento(
                'sp_consultar_factura_y_productosporfactura', [$numero]
            );
        } catch (NoEncontradoExcepcion) {
            // Este método promete devolver null cuando no existe, así que la
            // excepción se traga aquí. En los demás métodos SÍ sube.
            return null;
        }
        return $this->armarFactura($crudo);
    }

    public function crear(int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $crudo = $this->llamarProcedimiento(
            'sp_insertar_factura_y_productosporfactura',
            [$idCliente, $idVendedor, json_encode($renglones), 1]
        );
        return $this->releer((int) $crudo['factura']['numero']);
    }

    public function reemplazar(int $numero, int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $crudo = $this->llamarProcedimiento(
            'sp_actualizar_factura_y_productosporfactura',
            [$numero, $idCliente, $idVendedor, json_encode($renglones), 1]
        );
        return $this->releer((int) $crudo['factura']['numero']);
    }

    public function anular(int $numero): array
    {
        return $this->llamarProcedimiento('sp_anular_factura', [$numero]);
    }

    public function eliminar(int $numero): array
    {
        return $this->llamarProcedimiento(
            'sp_borrar_factura_y_productosporfactura', [$numero]
        );
    }
}
