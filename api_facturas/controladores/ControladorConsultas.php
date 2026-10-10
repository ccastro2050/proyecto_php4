<?php
/**
 * ControladorConsultas — los diez endpoints de reporte de la v4.
 *
 * TODOS SON GET, y aqui si que no hay discusion: una consulta no cambia
 * nada. El verbo lo dice antes de leer el codigo, y un proxy o un navegador
 * pueden cachear la respuesta sin romper nada. **Un reporte detras de un
 * POST seria mentir con el protocolo** —y es un error comun cuando alguien
 * quiere mandar filtros «largos» en el body—.
 *
 * NO HAY PUT, PATCH NI DELETE, y es la UNICA familia de rutas del proyecto
 * donde faltan a proposito. En los ocho recursos del CRUD estan los cinco
 * verbos porque hay una fila que crear, reemplazar, corregir y borrar. Aqui
 * no hay fila: hay una PREGUNTA. `DELETE /api/consultas/ventas-por-producto`
 * no significa nada — ¿borrar que, las ventas? Esa pregunta ya la responden
 * `/api/factura` y `/api/producto`.
 *
 * EL SOBRE ES DISTINTO AL DEL CRUD, y es deliberado:
 *
 *     CRUD       {"tabla": "producto", "limite": 50, "total": 8, "datos": [...]}
 *     consultas  {"consulta": "ventas-por-producto", "total": 5, "datos": [...]}
 *
 * `tabla` seria falso —ninguna consulta sale de una sola— y `limite` no
 * aplica: un reporte se devuelve entero o no sirve para sumar. Lo que se
 * conserva es `total` y `datos`, que es lo que el front necesita para
 * pintar cualquiera de las dos cosas con el mismo codigo.
 *
 * Y EL SOBRE SE ARMA EN UN SOLO SITIO —el metodo `responder`—. Diez
 * endpoints armando el arreglo a mano serian diez sitios donde escribir
 * `datos` con una letra distinta.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioConsultas.php';

class ControladorConsultas
{
    public function __construct(
        private readonly IServicioConsultas $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/ventas-por-producto
    // ------------------------------------------------------------------
    /** 1. Que se vende y cuanto deja. Cruza producto, renglon, factura y cliente. */
    public function ventasPorProducto(): void
    {
        $this->responder('ventas-por-producto',
                         fn () => $this->servicio->ventasPorProducto());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/ventas-por-cliente
    // ------------------------------------------------------------------
    /** 2. Quien compra y cuanto. Cruza cliente, persona, factura y renglon. */
    public function ventasPorCliente(): void
    {
        $this->responder('ventas-por-cliente',
                         fn () => $this->servicio->ventasPorCliente());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/ventas-por-vendedor
    // ------------------------------------------------------------------
    /** 3. Quien vende y cuanto. Cruza vendedor, persona, factura y renglon. */
    public function ventasPorVendedor(): void
    {
        $this->responder('ventas-por-vendedor',
                         fn () => $this->servicio->ventasPorVendedor());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/ventas-por-empresa
    // ------------------------------------------------------------------
    /** 4. Cuanto factura cada empresa. Cruza empresa, cliente, factura y renglon. */
    public function ventasPorEmpresa(): void
    {
        $this->responder('ventas-por-empresa',
                         fn () => $this->servicio->ventasPorEmpresa());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/ticket-por-vendedor
    // ------------------------------------------------------------------
    /** 5. Cuanto vale en promedio una factura de cada vendedor. */
    public function ticketPorVendedor(): void
    {
        $this->responder('ticket-por-vendedor',
                         fn () => $this->servicio->ticketPorVendedor());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/productos-sin-vender
    // ------------------------------------------------------------------
    /** 6. Lo que NO se ha vendido: LEFT JOIN con HAVING COUNT = 0. */
    public function productosSinVender(): void
    {
        $this->responder('productos-sin-vender',
                         fn () => $this->servicio->productosSinVender());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/anulaciones-por-cliente
    // ------------------------------------------------------------------
    /** 7. Quien anula y por cuanto. La cara B de ventas por cliente. */
    public function anulacionesPorCliente(): void
    {
        $this->responder('anulaciones-por-cliente',
                         fn () => $this->servicio->anulacionesPorCliente());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/alcance-de-usuarios
    // ------------------------------------------------------------------
    /** 8. A cuantas interfaces llega cada usuario. Cinco tablas del acceso. */
    public function alcanceDeUsuarios(): void
    {
        $this->responder('alcance-de-usuarios',
                         fn () => $this->servicio->alcanceDeUsuarios());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/interfaces-sin-usuarios
    // ------------------------------------------------------------------
    /** 9. Rutas declaradas a las que ningun usuario alcanza. */
    public function interfacesSinUsuarios(): void
    {
        $this->responder('interfaces-sin-usuarios',
                         fn () => $this->servicio->interfacesSinUsuarios());
    }

    // ------------------------------------------------------------------
    // GET /api/consultas/credito-contra-consumo
    // ------------------------------------------------------------------
    /** 10. Credito, consumido y disponible por cliente. */
    public function creditoContraConsumo(): void
    {
        $this->responder('credito-contra-consumo',
                         fn () => $this->servicio->creditoContraConsumo());
    }

    /**
     * El sobre de las diez, y su manejo de error, en un solo metodo.
     *
     * Recibe una FUNCION y no los datos ya pedidos: asi el `try` envuelve de
     * verdad la ejecucion de la consulta. Si recibiera el arreglo, el error
     * habria ocurrido antes de entrar aqui —y este try no atraparia nada—.
     *
     * @param callable(): array<int, array<string, mixed>> $pedir
     */
    private function responder(string $nombre, callable $pedir): void
    {
        try {
            $datos = $pedir();
            http_response_code(200);
            echo json_encode([
                'consulta' => $nombre,
                'total'    => count($datos),
                'datos'    => $datos,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            // Un fallo aqui es del motor o del SQL, no del que pregunta: no
            // hay nada que el cliente pueda corregir en su peticion, asi que
            // es 500 y no 400. El detalle va adentro porque este proyecto es
            // de aula y el mensaje del motor es justo lo que hay que leer.
            http_response_code(500);
            echo json_encode([
                'estado'  => 500,
                'mensaje' => 'Error interno.',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
