<?php
/**
 * RepositorioFacturaMariaDB — la capa de DATOS de las facturas.
 *
 * ======================================================================
 * ESTE REPOSITORIO NO ESCRIBE SQL: LLAMA PROCEDIMIENTOS ALMACENADOS
 * ======================================================================
 *
 * Los demás repositorios arman su `SELECT` y su `INSERT`. Éste no: la base
 * de datos ya trae escritas las operaciones de factura como
 * **procedimientos almacenados** (`sp_insertar_factura_y_productosporfactura`
 * y compañía), y este archivo los llama.
 *
 * No es un capricho, y vale la pena entender por qué:
 *
 *   1. **Crear una factura son varias escrituras que deben ir juntas**: la
 *      fila del encabezado, un renglón por producto, el total recalculado y
 *      el stock descontado. Si se cae a la mitad, no puede quedar media
 *      factura. Adentro del procedimiento eso es una sola operación.
 *   2. **La regla ya está escrita ahí.** El total lo calcula un trigger, el
 *      stock lo mueve otro, y el procedimiento se niega a crear una factura
 *      sin renglones. Repetir todo eso en PHP sería mantener el mismo
 *      negocio en dos idiomas.
 *   3. Y es contenido del curso: parte de la lógica **vive en la base**, y
 *      una API en capas tiene que saber convivir con eso.
 *
 * El precio, que se paga con gusto: este código queda atado al dialecto de
 * MariaDB. Por eso está en una clase que se llama `...MariaDB` — cuando
 * llegue otro motor será otra clase, y ni el servicio ni el controlador se
 * enterarán.
 *
 * ======================================================================
 * CÓMO SE LEE UN PARÁMETRO DE SALIDA CON PDO
 * ======================================================================
 *
 * Estos procedimientos devuelven su resultado en un parámetro `OUT` con un
 * JSON adentro. PDO no lee los OUT de MariaDB directamente: el truco —que es
 * el camino normal, no un rodeo— es pasarle una **variable de sesión** del
 * servidor (`@resultado`) y después consultarla:
 *
 *     CALL sp_lo_que_sea(?, ?, @resultado);
 *     SELECT @resultado;
 *
 * Eso es lo que hace `llamarProcedimiento()` aquí abajo, una sola vez.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioFactura.php';
require_once __DIR__ . '/errores_de_integridad_mariadb.php';
require_once __DIR__ . '/../modelos/Factura.php';
require_once __DIR__ . '/../modelos/LineaFactura.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class RepositorioFacturaMariaDB implements IRepositorioFactura
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
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        }
        return $this->conexion;
    }

    /**
     * Llama un procedimiento y devuelve el JSON de su parámetro de salida,
     * ya convertido a array.
     *
     * @param string $nombre     El procedimiento, ej. 'sp_anular_factura'
     * @param array  $parametros Los parámetros de ENTRADA, en orden
     */
    private function llamarProcedimiento(string $nombre, array $parametros): array
    {
        $conexion = $this->obtenerConexion();

        // Tantos ? como parámetros de entrada, y al final la variable de
        // sesión que recibirá el resultado.
        //
        // El caso de CERO parámetros hay que tratarlo aparte, y se aprendió
        // probando: `sp_listar_facturas_y_productosporfactura` no recibe
        // nada, y armar la lista sin mirar dejaba un `CALL sp(, @resultado)`
        // con una coma suelta que MariaDB rechaza por sintaxis.
        $marcadores = array_fill(0, count($parametros), '?');
        $marcadores[] = '@resultado';
        $sql = "CALL $nombre(" . implode(', ', $marcadores) . ")";

        try {
            $sentencia = $conexion->prepare($sql);
            $sentencia->execute($parametros);
            // closeCursor libera la conexión: MariaDB no deja lanzar otra
            // consulta mientras la anterior siga "abierta".
            $sentencia->closeCursor();
        } catch (PDOException $error) {
            // Los procedimientos rechazan con SIGNAL SQLSTATE '45000' —
            // «esto no cumple una regla del negocio». No es un error nuestro:
            // es la base explicando por qué dijo que no.
            if (($error->errorInfo[0] ?? '') === '45000') {
                $this->interpretarRechazo($this->limpiarMensaje($error->getMessage()));
            }
            // Si no, puede ser un problema de integridad corriente
            // (un cliente que no existe) o algo peor.
            traducirErrorMariaDB($error, 'la factura');
        }

        // Y ahora sí, se lee la variable de sesión:
        $json = $conexion->query('SELECT @resultado')->fetchColumn();

        return json_decode((string) $json, true) ?? [];
    }

    /**
     * Decide qué clase de «no» dijo el procedimiento.
     *
     * Todos los rechazos llegan con el mismo SQLSTATE '45000', así que el
     * único dato que los distingue es **el texto del mensaje**. Y hay que
     * distinguirlos, porque no significan lo mismo para quien llama:
     *
     *   · «Factura 999 no existe»           → 404, la ficha no está;
     *   · «Factura 7 esta anulada…»         → 409, existe pero no se deja;
     *   · «La factura requiere minimo 1…»   → 409, la petición no cumple.
     *
     * Mirar el texto de un mensaje es frágil, y conviene decirlo en voz alta
     * en vez de disimularlo: si mañana alguien reescribe el procedimiento en
     * otras palabras, esto deja de funcionar. Se acepta porque el arreglo
     * limpio —que cada rechazo trajera su propio código— exigiría cambiar
     * los seis procedimientos, y está fuera del alcance de esta versión.
     * Queda **en un solo sitio** justamente para que ese día se arregle aquí.
     */
    private function interpretarRechazo(string $mensaje): never
    {
        if (str_contains($mensaje, 'no existe')) {
            throw new NoEncontradoExcepcion($mensaje);
        }
        throw new ConflictoDeIntegridadExcepcion($mensaje);
    }

    /**
     * El mensaje del motor viene envuelto en su jerga:
     *   SQLSTATE[45000]: <<Unknown error>>: 1644 La factura requiere...
     * Esto deja solo la parte que escribió el procedimiento.
     */
    private function limpiarMensaje(string $mensaje): string
    {
        // La última parte después de "1644 " es el texto del SIGNAL.
        if (preg_match('/\b\d{4}\s(.+)$/s', $mensaje, $coincidencias)) {
            return trim($coincidencias[1]);
        }
        return $mensaje;
    }

    /** Arma el modelo a partir del JSON que devolvió un procedimiento. */
    private function armarFactura(array $crudo): Factura
    {
        // Los procedimientos devuelven {"factura": {...}, "productos": [...]}
        // o, en el listado, la factura con sus productos adentro.
        $cabeza = $crudo['factura'] ?? $crudo;
        $renglones = $crudo['productos'] ?? $crudo['detalle'] ?? [];

        $detalle = [];
        foreach ($renglones as $r) {
            $detalle[] = new LineaFactura(
                (string) ($r['codigo_producto'] ?? $r['codigoProducto'] ?? ''),
                (string) ($r['nombre_producto'] ?? $r['nombreProducto'] ?? ''),
                (int) ($r['cantidad'] ?? 0),
                (float) ($r['valorunitario'] ?? 0),
                (float) ($r['subtotal'] ?? 0),
            );
        }

        return new Factura(
            (int) $cabeza['numero'],
            (string) ($cabeza['fecha'] ?? ''),
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
     * Vuelve a leer una factura que ACABA de guardarse.
     *
     * `obtenerPorNumero` puede devolver null —el contrato lo permite, porque
     * el número podría no existir—, pero aquí sí existe: se acaba de crear.
     * Si aun así llegara null, algo se rompió de verdad y hay que decirlo,
     * no devolver un objeto a medias.
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

        // Este procedimiento devuelve una LISTA pelada —`[ {...}, {...} ]`—,
        // no un objeto con una llave adentro. Cada elemento ya trae sus
        // productos, así que armarFactura() sirve igual.
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
        } catch (NoEncontradoExcepcion $e) {
            // Este método promete devolver null cuando no existe —igual que
            // el de la v1—, así que la excepción se traga aquí. En los demás
            // métodos SÍ sube, y el controlador la vuelve 404.
            return null;
        }
        return $this->armarFactura($crudo);
    }

    public function crear(int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $crudo = $this->llamarProcedimiento(
            'sp_insertar_factura_y_productosporfactura',
            [
                $idCliente,
                $idVendedor,
                // El detalle viaja como JSON: el procedimiento lo recorre
                // adentro. Es la forma de mandarle una LISTA a un
                // procedimiento, que solo acepta parámetros sueltos.
                json_encode($renglones),
                1,   // mínimo de renglones: una factura vacía no existe
            ]
        );
        // Se vuelve a consultar, y vale la pena decir por qué: el
        // procedimiento de insertar devuelve la factura SIN los nombres del
        // cliente y del vendedor (no hace el JOIN hasta `persona`). Si se
        // devolviera tal cual, el mismo recurso llegaría a veces con nombres
        // y a veces sin ellos según cómo se hubiera pedido — y un contrato
        // que cambia de forma no es un contrato. Cuesta una consulta más.
        return $this->releer((int) $crudo['factura']['numero']);
    }

    public function reemplazar(int $numero, int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $crudo = $this->llamarProcedimiento(
            'sp_actualizar_factura_y_productosporfactura',
            [$numero, $idCliente, $idVendedor, json_encode($renglones), 1]
        );
        // Igual que en crear(): se relee para que el contrato sea uniforme.
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
