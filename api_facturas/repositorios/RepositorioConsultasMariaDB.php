<?php
/**
 * RepositorioConsultasMariaDB — las DIEZ consultas de la v4.
 *
 * AQUI NO HAY PROCEDIMIENTOS ALMACENADOS, y es deliberado: una consulta de
 * reporte cambia cada vez que alguien quiere verla de otra forma, y tenerla
 * en un procedimiento obliga a tocar la base de datos para cambiar un ORDER
 * BY. Los procedimientos de este proyecto estan donde hay una REGLA que
 * proteger —la facturacion, el usuario con sus roles: transacciones—, no
 * donde solo hay un SELECT.
 *
 * CADA CONSULTA CRUZA CUATRO O MAS TABLAS. No es un capricho del enunciado:
 * una consulta de una tabla la responde el CRUD que ya existe. Lo que estas
 * diez agregan es la pregunta que NINGUN endpoint anterior podia responder.
 *
 * NI UNA SOLA RECIBE PARAMETROS, y por eso no hay `prepare` con marcadores:
 * son diez SELECT fijos. En el momento en que una acepte un filtro que venga
 * de la URL, vuelve el `prepare` con `?` —como en todo el resto del
 * proyecto—. **La consulta sin parametros no necesita defensa; la que los
 * recibe, si.**
 *
 * LO QUE CAMBIA EN MARIADB —y es solo una de las diez—:
 *
 *   alcanceDeUsuarios   MariaDB no tiene STRING_AGG. Su funcion es
 *                       GROUP_CONCAT, que lleva el separador con la palabra
 *                       SEPARATOR y acepta el ORDER BY adentro.
 *
 * Las otras nueve pasan palabra por palabra, incluida la division del ticket
 * promedio: MariaDB promueve el tipo igual que PostgreSQL.
 *
 * LO QUE EN PHP NO HACE FALTA, y es la nota de las tres tecnologias: el
 * gemelo .NET de este archivo lleva `CAST(COUNT(...) AS INT)` en las diez
 * consultas, porque COUNT() devuelve BIGINT y el modelo de C# pedia `int`.
 * Aqui no, y no porque PHP sea mejor: **no hay modelo que materializar**. La
 * fila llega como arreglo asociativo y el entero de PHP no tiene tamano
 * fijo. El tropiezo era del tipado estatico, no del motor.
 *
 * Y el precio de esa comodidad se paga en otro momento: si manana una
 * consulta devuelve una columna de mas, en C# se queja el compilador; aqui
 * pasa en silencio hasta la pantalla. Los dos cobran, en momentos distintos.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioConsultas.php';

class RepositorioConsultasMariaDB implements IRepositorioConsultas
{
    private ?PDO $conexion = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $usuario,
        private readonly string $clave,
    ) {
    }

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
     * Ejecuta y devuelve las filas como arreglos asociativos.
     *
     * `query` y no `prepare`+`execute`: no hay un solo dato de fuera en estas
     * diez sentencias. Donde si lo hay —todo el CRUD— va el `prepare` con
     * marcadores, y ese es el criterio: **el `prepare` no es un adorno de
     * estilo, es la defensa contra la inyeccion de SQL.**
     *
     * @return array<int, array<string, mixed>>
     */
    private function filas(string $sql): array
    {
        $sentencia = $this->obtenerConexion()->query($sql);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }


    // ------------------------------------------------------------------
    // 1. Ventas por producto
    // ------------------------------------------------------------------
    /**
     * Cuatro tablas: producto, el renglon del detalle, la factura y el
     * cliente. La pregunta «que se vende y cuanto deja» no la responde
     * ningun endpoint del CRUD, porque el CRUD mira una tabla a la vez.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorProducto(): array
    {
        return $this->filas(<<<SQL
            SELECT p.codigo, p.nombre,
                   SUM(pf.cantidad) AS unidades,
                   SUM(pf.subtotal) AS ingreso,
                   COUNT(DISTINCT f.numero) AS facturas,
                   COUNT(DISTINCT c.id) AS clientes
            FROM producto p
            INNER JOIN productosporfactura pf ON pf.fkcodproducto = p.codigo
            INNER JOIN factura f ON f.numero = pf.fknumfactura
            INNER JOIN cliente c ON c.id = f.fkidcliente
            WHERE f.estado = 'activa'
            GROUP BY p.codigo, p.nombre
            ORDER BY SUM(pf.subtotal) DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 2. Ventas por cliente
    // ------------------------------------------------------------------
    /**
     * El nombre del cliente NO esta en `cliente`: esta en `persona`. Esa
     * es la razon del JOIN, y es la misma razon por la que la tabla se
     * diseno asi — una persona puede ser cliente y vendedor a la vez.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorCliente(): array
    {
        return $this->filas(<<<SQL
            SELECT c.id, pe.nombre AS cliente, pe.email,
                   COUNT(DISTINCT f.numero) AS facturas,
                   SUM(pf.subtotal) AS comprado,
                   SUM(pf.cantidad) AS unidades
            FROM cliente c
            INNER JOIN persona pe ON pe.codigo = c.fkcodpersona
            INNER JOIN factura f ON f.fkidcliente = c.id
            INNER JOIN productosporfactura pf ON pf.fknumfactura = f.numero
            WHERE f.estado = 'activa'
            GROUP BY c.id, pe.nombre, pe.email
            ORDER BY SUM(pf.subtotal) DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 3. Ventas por vendedor
    // ------------------------------------------------------------------
    /**
     * La imagen en espejo de la anterior: el mismo recorrido por el otro
     * lado de la factura.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorVendedor(): array
    {
        return $this->filas(<<<SQL
            SELECT v.id, pe.nombre AS vendedor, v.carnet,
                   COUNT(DISTINCT f.numero) AS facturas,
                   SUM(pf.subtotal) AS vendido
            FROM vendedor v
            INNER JOIN persona pe ON pe.codigo = v.fkcodpersona
            INNER JOIN factura f ON f.fkidvendedor = v.id
            INNER JOIN productosporfactura pf ON pf.fknumfactura = f.numero
            WHERE f.estado = 'activa'
            GROUP BY v.id, pe.nombre, v.carnet
            ORDER BY SUM(pf.subtotal) DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 4. Ventas por empresa
    // ------------------------------------------------------------------
    /**
     * Cinco tablas contando el detalle. Aqui el INNER JOIN es correcto a
     * proposito: solo interesan las empresas que TIENEN clientes con
     * facturas. La consulta 10 hace lo contrario, y explica por que.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventasPorEmpresa(): array
    {
        return $this->filas(<<<SQL
            SELECT e.codigo, e.nombre AS empresa,
                   COUNT(DISTINCT c.id) AS clientes,
                   COUNT(DISTINCT f.numero) AS facturas,
                   SUM(pf.subtotal) AS facturado
            FROM empresa e
            INNER JOIN cliente c ON c.fkcodempresa = e.codigo
            INNER JOIN factura f ON f.fkidcliente = c.id
            INNER JOIN productosporfactura pf ON pf.fknumfactura = f.numero
            WHERE f.estado = 'activa'
            GROUP BY e.codigo, e.nombre
            ORDER BY SUM(pf.subtotal) DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 5. Ticket promedio por vendedor
    // ------------------------------------------------------------------
    /**
     * OJO: esta es la UNICA de las diez que se escribe distinto aqui,
     * y la razon que se arrastraba en los otros repos era falsa.
     *
     * `NULLIF` evita la division por cero —un vendedor sin facturas
     * activas aborta la consulta ENTERA sin el—. Eso si es verdad en los
     * tres motores.
     *
     * EL CAST, EN CAMBIO, NO ES LO QUE DECIAN. La version heredada
     * afirmaba que «en T-SQL dividir un DECIMAL entre un INT trunca».
     * Medido contra el motor, es al contrario:
     *
     *     CAST(3400000.00 AS DECIMAL(18,2)) / 3  ->  1133333.3333...
     *     3400000 / 3                            ->  1133333
     *
     * Lo que trunca es INT entre INT. Y como `subtotal` es DECIMAL(18,2),
     * la division ya promovia: la consulta con CAST y sin CAST devuelve
     * exactamente lo mismo —comprobado fila por fila—.
     *
     * Se queda escrito porque documenta la intencion y porque el dia que
     * la division sea entre dos enteros —dos COUNT, por ejemplo— el
     * truncado llega de verdad. Y se queda contado, porque el tropiezo
     * util no fue el CAST: fue descubrir que la explicacion se habia
     * copiado de repo en repo sin que nadie la ejecutara.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ticketPorVendedor(): array
    {
        return $this->filas(<<<SQL
            SELECT pe.nombre AS vendedor,
                   COUNT(DISTINCT f.numero) AS facturas,
                   SUM(pf.subtotal) AS total,
                   ROUND(SUM(pf.subtotal)
                         / NULLIF(COUNT(DISTINCT f.numero), 0), 2)
                       AS ticket_promedio
            FROM vendedor v
            INNER JOIN persona pe ON pe.codigo = v.fkcodpersona
            INNER JOIN factura f ON f.fkidvendedor = v.id
            INNER JOIN productosporfactura pf ON pf.fknumfactura = f.numero
            WHERE f.estado = 'activa'
            GROUP BY pe.nombre
            ORDER BY 4 DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 6. Productos que nunca se han vendido
    // ------------------------------------------------------------------
    /**
     * Y AQUI EL JOIN ES IZQUIERDO, que es toda la consulta: con INNER
     * JOIN estos productos no apareceran NUNCA —no tienen fila en el
     * detalle—, y la consulta devolveria vacio diciendo «todo se vende».
     * LEFT JOIN + HAVING COUNT = 0 es la forma de preguntar por lo que
     * NO esta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function productosSinVender(): array
    {
        return $this->filas(<<<SQL
            SELECT p.codigo, p.nombre, p.stock, p.valorunitario
            FROM producto p
            LEFT JOIN productosporfactura pf ON pf.fkcodproducto = p.codigo
            LEFT JOIN factura f
                   ON f.numero = pf.fknumfactura AND f.estado = 'activa'
            LEFT JOIN cliente c ON c.id = f.fkidcliente
            GROUP BY p.codigo, p.nombre, p.stock, p.valorunitario
            HAVING COUNT(c.id) = 0
            ORDER BY p.valorunitario DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 7. Anulaciones por cliente
    // ------------------------------------------------------------------
    /**
     * La cara B de la consulta 2: la misma, con `estado = 'anulada'`.
     *
     * Y eso ensena algo del diseno de la tabla: al anular, la factura NO
     * se borra, cambia de estado. Si se borrara, esta consulta no se
     * podria escribir.
     *
     * @return array<int, array<string, mixed>>
     */
    public function anulacionesPorCliente(): array
    {
        return $this->filas(<<<SQL
            SELECT pe.nombre AS cliente,
                   COUNT(DISTINCT f.numero) AS anuladas,
                   SUM(pf.subtotal) AS valor_anulado
            FROM factura f
            INNER JOIN cliente c ON c.id = f.fkidcliente
            INNER JOIN persona pe ON pe.codigo = c.fkcodpersona
            INNER JOIN productosporfactura pf ON pf.fknumfactura = f.numero
            WHERE f.estado = 'anulada'
            GROUP BY pe.nombre
            ORDER BY SUM(pf.subtotal) DESC
        SQL);
    }

    // ------------------------------------------------------------------
    // 8. Alcance de cada usuario
    // ------------------------------------------------------------------
    /**
     * OJO: esta tampoco se escribe igual en los tres motores.
     *
     * CINCO tablas: usuario, rol_usuario, rol, rutarol y ruta. Es el
     * camino completo del control de acceso de la v3, visto como
     * reporte: a cuantas interfaces llega cada quien, y con que roles.
     *
     * La funcion que pega los nombres en un solo texto se llama distinto
     * en cada motor —GROUP_CONCAT, STRING_AGG— y en SQL Server encima NO
     * acepta DISTINCT adentro: responde «Incorrect syntax near ','» y
     * hay que resolverlo con una subconsulta que traiga el DISTINCT
     * hecho.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alcanceDeUsuarios(): array
    {
        return $this->filas(<<<SQL
            SELECT u.email,
                   COUNT(DISTINCT r.id) AS roles,
                   COUNT(DISTINCT rt.id) AS interfaces,
                   GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', ') AS sus_roles
            FROM usuario u
            INNER JOIN rol_usuario ru ON ru.fkemail = u.email
            INNER JOIN rol r ON r.id = ru.fkidrol
            INNER JOIN rutarol rr ON rr.fkidrol = r.id
            INNER JOIN ruta rt ON rt.id = rr.fkidruta
            GROUP BY u.email
            ORDER BY COUNT(DISTINCT rt.id) DESC, u.email
        SQL);
    }

    // ------------------------------------------------------------------
    // 9. Interfaces a las que no llega nadie
    // ------------------------------------------------------------------
    /**
     * Un permiso declarado al que ningun usuario alcanza. No esta mal ni
     * bien, pero conviene saberlo: casi siempre es una ruta que alguien
     * creo y nadie conecto a un rol.
     *
     * @return array<int, array<string, mixed>>
     */
    public function interfacesSinUsuarios(): array
    {
        return $this->filas(<<<SQL
            SELECT rt.id, rt.ruta, rt.descripcion,
                   COUNT(DISTINCT r.id) AS roles_con_acceso,
                   COUNT(DISTINCT ru.fkemail) AS usuarios_con_acceso
            FROM ruta rt
            LEFT JOIN rutarol rr ON rr.fkidruta = rt.id
            LEFT JOIN rol r ON r.id = rr.fkidrol
            LEFT JOIN rol_usuario ru ON ru.fkidrol = r.id
            GROUP BY rt.id, rt.ruta, rt.descripcion
            HAVING COUNT(DISTINCT ru.fkemail) = 0
            ORDER BY rt.ruta
        SQL);
    }

    // ------------------------------------------------------------------
    // 10. Credito contra consumo
    // ------------------------------------------------------------------
    /**
     * El LEFT JOIN a `empresa` es el que importa: `fkcodempresa` es NULL
     * para el cliente que compra a nombre propio, y con INNER JOIN esos
     * clientes DESAPARECERIAN del reporte sin que nadie lo notara.
     *
     * El `COALESCE(SUM(f.total), 0)` hace lo mismo del lado del dinero:
     * un cliente sin facturas tiene consumido 0, no NULL — y asi su
     * credito disponible se puede restar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function creditoContraConsumo(): array
    {
        return $this->filas(<<<SQL
            SELECT pe.nombre AS cliente,
                   COALESCE(e.nombre, '(sin empresa)') AS empresa,
                   c.credito,
                   COALESCE(SUM(f.total), 0) AS consumido,
                   c.credito - COALESCE(SUM(f.total), 0) AS disponible
            FROM cliente c
            INNER JOIN persona pe ON pe.codigo = c.fkcodpersona
            LEFT JOIN empresa e ON e.codigo = c.fkcodempresa
            LEFT JOIN factura f
                   ON f.fkidcliente = c.id AND f.estado = 'activa'
            GROUP BY pe.nombre, e.nombre, c.credito
            ORDER BY 5
        SQL);
    }
}
