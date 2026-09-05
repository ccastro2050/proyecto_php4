<?php
/**
 * RepositorioFacturaSqlServer — la capa de DATOS de las facturas contra
 * SQL Server.
 *
 * ======================================================================
 * ÁBRALO AL LADO DE LOS OTROS DOS
 * ======================================================================
 *
 * Los tres llaman a los MISMOS seis procedimientos, con los mismos nombres y
 * los mismos parámetros. Y los tres los llaman de una manera distinta:
 *
 * |  | MariaDB | PostgreSQL | SQL Server |
 * |---|---|---|---|
 * | El parámetro de salida | `OUT` | `INOUT` | `OUTPUT` |
 * | Cómo se recoge | variable de sesión `@resultado` + otra consulta | viene como fila del `CALL` | **se declara una variable en el propio SQL y se hace `SELECT` de ella** |
 * | Sentencias por llamada | dos | una | una (con tres instrucciones adentro) |
 * | `closeCursor()` | obligatorio | no | no |
 *
 * **Y aquí hay una historia que vale más que la tabla.** El camino «normal»
 * en SQL Server sería enlazar el parámetro de salida con PDO:
 *
 *     $sentencia->bindParam(2, $salida, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 8000);
 *
 * Eso **no funciona** con estos procedimientos: el driver responde
 * `SQLSTATE[HY104]: Invalid precision value`, porque el parámetro está
 * declarado `NVARCHAR(MAX)` y a un MAX no se le puede dar una longitud fija.
 *
 * La salida es escribir el bloque de T-SQL completo y pedirle el valor con un
 * `SELECT`, que es lo que hace `llamarProcedimiento()` aquí abajo. **Se
 * descubrió probando, no leyendo documentación**, y por eso está escrito: el
 * que llegue aquí con el mismo error va a saber en dos minutos lo que a
 * nosotros nos costó media hora.
 *
 * ======================================================================
 * `SET NOCOUNT ON` NO ES ADORNO
 * ======================================================================
 *
 * Sin él, cada `INSERT` de adentro del procedimiento manda un aviso de «N
 * filas afectadas», y PDO los entrega como resultados intermedios: el
 * `fetch()` devuelve cualquier cosa menos el JSON. Es de esos detalles que
 * hacen perder una tarde.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioFactura.php';
require_once __DIR__ . '/errores_de_integridad_sqlserver.php';
require_once __DIR__ . '/../modelos/Factura.php';
require_once __DIR__ . '/../modelos/LineaFactura.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class RepositorioFacturaSqlServer implements IRepositorioFactura
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
            ]);
        }
        return $this->conexion;
    }

    /**
     * Llama un procedimiento y devuelve el JSON de su parámetro de salida,
     * ya convertido a array.
     *
     * El bloque de T-SQL que se arma es siempre el mismo:
     *
     *     SET NOCOUNT ON;
     *     DECLARE @salida NVARCHAR(MAX);
     *     EXEC sp_lo_que_sea ?, ?, @salida OUTPUT;
     *     SELECT @salida AS p_resultado;
     *
     * O sea: la variable de salida se declara **dentro de la misma
     * sentencia** y después se pregunta por ella. Los `?` de en medio son los
     * parámetros de entrada.
     *
     * @param string $nombre     El procedimiento, ej. 'sp_anular_factura'
     * @param array  $parametros Los parámetros de ENTRADA, en orden
     */
    private function llamarProcedimiento(string $nombre, array $parametros): array
    {
        $marcadores = array_fill(0, count($parametros), '?');
        $marcadores[] = '@salida OUTPUT';
        $argumentos = implode(', ', $marcadores);

        $sql = "SET NOCOUNT ON;
                DECLARE @salida NVARCHAR(MAX);
                EXEC $nombre $argumentos;
                SELECT @salida AS p_resultado;";

        try {
            $sentencia = $this->obtenerConexion()->prepare($sql);
            $sentencia->execute($parametros);
        } catch (PDOException $error) {
            // Los procedimientos rechazan con THROW, y el número que se les
            // dio en el script está en el rango 50001–50010. Llega como
            // SQLSTATE 42000, que SQL Server usa para «error de sintaxis o
            // de acceso» — un cajón bastante amplio.
            $codigo = (int) ($error->errorInfo[1] ?? 0);
            if ($codigo >= 50000) {
                $this->interpretarRechazo($this->limpiarMensaje($error->getMessage()));
            }
            traducirErrorSqlServer($error, 'la factura');
        }

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        $json = $fila === false ? '' : (string) ($fila['p_resultado'] ?? '');

        return json_decode($json, true) ?? [];
    }

    /**
     * Decide qué clase de «no» dijo el procedimiento.
     *
     *   · «Factura 999 no existe»          → 404;
     *   · «Factura 7 esta anulada…»        → 409;
     *   · «La factura requiere minimo 1…»  → 409.
     *
     * La misma fragilidad de los otros dos motores —mirar el texto—, en el
     * mismo sitio único. Y una diferencia a favor de SQL Server: **su mensaje
     * llega limpio**. MariaDB le antepone el número del motor y PostgreSQL le
     * añade un bloque `CONTEXT:`; aquí solo hay que quitarle el prefijo del
     * driver ODBC.
     */
    private function interpretarRechazo(string $mensaje): never
    {
        if (str_contains($mensaje, 'no existe')) {
            throw new NoEncontradoExcepcion($mensaje);
        }
        throw new ConflictoDeIntegridadExcepcion($mensaje);
    }

    /**
     * El mensaje llega envuelto en la firma del driver:
     *
     *   SQLSTATE[42000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Factura 999 no existe
     *
     * Lo que escribió el procedimiento es lo que va después del último `]`.
     */
    private function limpiarMensaje(string $mensaje): string
    {
        $posicion = strrpos($mensaje, ']');
        return $posicion === false
            ? $mensaje
            : trim(substr($mensaje, $posicion + 1));
    }

    /** Arma el modelo a partir del JSON que devolvió un procedimiento. */
    private function armarFactura(array $crudo): Factura
    {
        $cabeza = $crudo['factura'] ?? $crudo;
        $renglones = $crudo['productos'] ?? [];

        $detalle = [];
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
            // SQL Server devuelve la fecha con SIETE decimales de segundo
            // ("2025-12-03T12:57:19.2759200"); PostgreSQL con seis; MariaDB
            // sin ninguno. Se recorta a los segundos para que el contrato
            // entregue el mismo formato con los tres.
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

    /** Vuelve a leer una factura recién guardada, para uniformar el contrato. */
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
