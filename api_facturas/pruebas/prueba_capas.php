<?php
/**
 * prueba_capas.php — Criterio 9 de la v2: los servicios funcionan con
 * repositorios FALSOS en memoria — sin MariaDB corriendo.
 *
 * Si esto pasa, las capas quedaron bien cortadas (polimorfismo + inversión
 * de dependencias). Ejecutar:  php pruebas/prueba_capas.php
 *
 * ======================================================================
 * QUÉ PRUEBA LA v2 QUE LA v1 NO PODÍA
 * ======================================================================
 *
 * La v1 comprobaba una cosa: que el servicio de producto no supiera qué
 * motor hay detrás. Aquí se comprueban tres, y las dos nuevas son las que
 * importan:
 *
 *   1. lo mismo con `producto` — que no debe haberse roto;
 *   2. un servicio cuya llave la genera la base (`cliente`): el falso
 *      también genera identificadores, y el servicio no nota la diferencia;
 *   3. el de facturas, cuya interfaz **no tiene los cinco métodos de
 *      siempre**. Que el repositorio falso se pueda escribir sin forzar
 *      nada es la prueba de que esa interfaz describe lo que la factura
 *      HACE, y no lo que las demás entidades hacen.
 *
 * Y hay una comprobación que solo se puede hacer aquí: el falso **no tiene
 * triggers**, así que devuelve subtotales en cero. Si el servicio de
 * facturas calculara algo por su cuenta, ese cero no seguiría siendo cero.
 *
 * Fíjese en lo que ninguno de los tres necesita: base de datos, red, o
 * esperar a que un contenedor arranque. Corre en milisegundos.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

// Este require arrastra (por sus propios require_once) la interfaz del
// repositorio, el modelo Producto y las excepciones:
require_once __DIR__ . '/../servicios/ServicioProducto.php';
require_once __DIR__ . '/../servicios/ServicioCliente.php';
require_once __DIR__ . '/../servicios/ServicioFactura.php';

/**
 * El REPOSITORIO FALSO: cumple el mismo contrato que el de MariaDB, pero
 * guarda los Producto en un simple array en memoria — cero SQL, cero red.
 * Como el servicio depende de la INTERFAZ, no nota la diferencia.
 */
class RepositorioFalsoEnMemoria implements IRepositorioProducto
{
    /** El "almacén": un array con el código como llave y el Producto como valor. */
    private array $datos = [];

    public function obtenerTodos(int $limite): array
    {
        // array_values descarta las llaves (deja lista simple);
        // array_slice corta los primeros $limite elementos (como un LIMIT):
        return array_slice(array_values($this->datos), 0, $limite);
    }

    public function obtenerPorClave(string $codigo): ?Producto
    {
        // ?? null = si esa llave no existe, devolver null (el contrato):
        return $this->datos[$codigo] ?? null;
    }

    public function crear(Producto $producto): bool
    {
        // El objeto del modelo se guarda tal cual, con su código como llave:
        $this->datos[$producto->getCodigo()] = $producto;
        return true;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        // isset pregunta si la llave existe; 0 filas = "no existía":
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        // Se escriben SOLO los campos que llegaron (igual que el UPDATE
        // dinámico del repositorio real), usando los SETTERS del modelo:
        $producto = $this->datos[$codigo];
        if (array_key_exists('nombre', $datos)) {
            $producto->setNombre($datos['nombre']);
        }
        if (array_key_exists('stock', $datos)) {
            $producto->setStock($datos['stock']);
        }
        if (array_key_exists('valorunitario', $datos)) {
            $producto->setValorunitario((float) $datos['valorunitario']);
        }
        return 1;
    }

    public function eliminar(string $codigo): int
    {
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        // unset borra la llave del array:
        unset($this->datos[$codigo]);
        return 1;
    }
}

// ----------------------------------------------------------------------
// La prueba: el MISMO ServicioProducto, con otro repositorio (polimorfismo)
// ----------------------------------------------------------------------
$servicio = new ServicioProducto(new RepositorioFalsoEnMemoria());

/** Mini-verificador: si la condición es falsa, reporta y sale con error. */
function verificar(bool $condicion, string $descripcion): void
{
    if (!$condicion) {
        // STDERR es la salida de errores; exit(1) = terminar "mal"
        // (los scripts que salen con 0 pasaron, con != 0 fallaron):
        fwrite(STDERR, "FALLÓ: $descripcion\n");
        exit(1);
    }
}

// El ciclo completo contra el repositorio falso. Note que las lecturas
// devuelven OBJETOS Producto: se pregunta con los getters del modelo
// (getCodigo()), no con llaves de array (['codigo']).
$servicio->crear(['codigo' => 'T1', 'nombre' => 'Test', 'stock' => 5, 'valorunitario' => 100.0]);
verificar($servicio->listar(10)[0]->getCodigo() === 'T1',     'crear + listar');
verificar($servicio->obtener('T1')->getNombre() === 'Test',   'obtener por código');
verificar($servicio->actualizar('T1', ['stock' => 9]) === 1,  'actualizar');
verificar($servicio->obtener('T1')->getStock() === 9,         'el stock quedó en 9');
verificar($servicio->eliminar('T1') === 1,                    'eliminar');

// Las excepciones de negocio también funcionan sin BD:
try { $servicio->obtener('NOEXISTE'); verificar(false, 'debió lanzar NoEncontradoExcepcion'); }
catch (NoEncontradoExcepcion) { /* esperado */ }

try { $servicio->actualizar('T1', []); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

try { $servicio->listar(0); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

// ======================================================================
// 2. UN SERVICIO CUYA LLAVE LA GENERA LA BASE
// ======================================================================
//
// El falso hace de cuenta que es AUTO_INCREMENT: lleva su propio contador.
// El servicio no nota la diferencia — y ése es justamente el punto.
class RepositorioClienteFalso implements IRepositorioCliente
{
    private array $datos = [];
    private int $siguienteId = 1;

    public function obtenerTodos(int $limite): array
    {
        return array_slice(array_values($this->datos), 0, $limite);
    }

    public function obtenerPorClave(int $id): ?Cliente
    {
        return $this->datos[$id] ?? null;
    }

    public function crear(Cliente $cliente): int
    {
        // Aquí se ve lo que hace la base: la ficha llega SIN id y sale CON
        // uno. Por eso el modelo lo declara `?int`.
        $id = $this->siguienteId++;
        $this->datos[$id] = new Cliente(
            $id,
            $cliente->getCredito(),
            $cliente->getFkcodpersona(),
            $cliente->getFkcodempresa(),
        );
        return $id;
    }

    public function actualizar(int $id, array $datos): int
    {
        if (!isset($this->datos[$id])) {
            return 0;
        }
        $viejo = $this->datos[$id];
        $this->datos[$id] = new Cliente(
            $id,
            (float) ($datos['credito'] ?? $viejo->getCredito()),
            $datos['fkcodpersona'] ?? $viejo->getFkcodpersona(),
            array_key_exists('fkcodempresa', $datos)
                ? $datos['fkcodempresa'] : $viejo->getFkcodempresa(),
        );
        return 1;
    }

    public function eliminar(int $id): int
    {
        if (!isset($this->datos[$id])) {
            return 0;
        }
        unset($this->datos[$id]);
        return 1;
    }
}

$servicioCliente = new ServicioCliente(new RepositorioClienteFalso());

$id = $servicioCliente->crear([
    'credito' => 1000.0, 'fkcodpersona' => 'P001', 'fkcodempresa' => null,
]);
verificar($id === 1,                                       'crear devuelve el id generado');
verificar($servicioCliente->obtener($id)->getId() === 1,   'la ficha quedó con ese id');
verificar($servicioCliente->obtener($id)->getFkcodempresa() === null,
                                                           'la empresa opcional quedó en null');
verificar($servicioCliente->actualizar($id, ['credito' => 2000.0]) === 1,
                                                           'actualizar cliente');
verificar($servicioCliente->obtener($id)->getCredito() === 2000.0, 'el crédito cambió');

// Un identificador que no es un identificador: es 400, no 404.
try { $servicioCliente->obtener(0); verificar(false, 'debió rechazar el id 0'); }
catch (InvalidArgumentException) { /* esperado */ }

// ======================================================================
// 3. EL SERVICIO DE FACTURAS: el que NO tiene los cinco métodos de siempre
// ======================================================================
//
// Mire la lista de métodos que hay que implementar aquí abajo: no son los
// mismos de los dos falsos anteriores. `anular` no existe en ningún otro
// repositorio del sistema, y `actualizar` no existe en éste.
class RepositorioFacturaFalso implements IRepositorioFactura
{
    private array $datos = [];
    private int $siguienteNumero = 1;

    public function obtenerTodas(): array
    {
        return array_values($this->datos);
    }

    public function obtenerPorNumero(int $numero): ?Factura
    {
        return $this->datos[$numero] ?? null;
    }

    public function crear(int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $numero = $this->siguienteNumero++;
        $this->datos[$numero] = new Factura(
            $numero, '2026-01-01T00:00:00', 0.0, 'activa',
            $idCliente, $idVendedor, $this->armarDetalle($renglones),
        );
        return $this->datos[$numero];
    }

    public function reemplazar(int $numero, int $idCliente, int $idVendedor, array $renglones): Factura
    {
        $this->datos[$numero] = new Factura(
            $numero, '2026-01-01T00:00:00', 0.0, 'activa',
            $idCliente, $idVendedor, $this->armarDetalle($renglones),
        );
        return $this->datos[$numero];
    }

    public function anular(int $numero): array
    {
        $vieja = $this->datos[$numero];
        $this->datos[$numero] = new Factura(
            $numero, $vieja->getFecha(), $vieja->getTotal(), 'anulada',
            $vieja->getFkidcliente(), $vieja->getFkidvendedor(), $vieja->getDetalle(),
        );
        return ['estado' => 'anulada'];
    }

    public function eliminar(int $numero): array
    {
        unset($this->datos[$numero]);
        return ['numero_eliminado' => (string) $numero];
    }

    /**
     * Los subtotales quedan en CERO a propósito: aquí no hay trigger que los
     * calcule, y el falso no se inventa la cuenta. Es lo que permite
     * comprobar, más abajo, que el servicio tampoco la hace.
     */
    private function armarDetalle(array $renglones): array
    {
        $detalle = [];
        foreach ($renglones as $r) {
            $detalle[] = new LineaFactura($r['codigo'], $r['codigo'], $r['cantidad'], 0.0, 0.0);
        }
        return $detalle;
    }
}

$servicioFactura = new ServicioFactura(new RepositorioFacturaFalso());

$factura = $servicioFactura->crear([
    'fkidcliente' => 1, 'fkidvendedor' => 2,
    // Se manda un 'subtotal' de más, para comprobar que el servicio lo bota:
    'detalle' => [['codigo' => 'PR001', 'cantidad' => 2, 'subtotal' => 999999]],
]);
verificar($factura->getNumero() === 1,             'crear factura devuelve la factura');
verificar(count($factura->getDetalle()) === 1,     'con su detalle');
verificar($factura->getDetalle()[0]->getSubtotal() === 0.0,
          'el subtotal NO lo puso el servicio: lo pone la capa de datos');
verificar($servicioFactura->obtener(1)->getEstado() === 'activa', 'nace activa');

$servicioFactura->anular(1);
verificar($servicioFactura->obtener(1)->getEstado() === 'anulada', 'anular la deja anulada');
verificar($servicioFactura->obtener(1) !== null,   'y la factura SIGUE existiendo');

try { $servicioFactura->obtener(99); verificar(false, 'debió lanzar NoEncontradoExcepcion'); }
catch (NoEncontradoExcepcion) { /* esperado */ }

echo "CRITERIO 9 OK: los tres servicios funcionan con repositorios falsos, sin MariaDB\n";
