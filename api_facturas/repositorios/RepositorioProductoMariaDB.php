<?php
/**
 * RepositorioProductoMariaDB — la capa de DATOS de la v1.
 *
 * Única clase del sistema que habla SQL y que conoce la conexión. Cumple el
 * contrato IRepositorioProducto con `implements` (interface nativa de PHP).
 *
 * Reglas de la constitución que se cumplen aquí:
 * - SQL SIEMPRE en prepared statements de PDO (nunca concatenar valores).
 * - El SQL queda visible (PDO como ejecutor, sin ORM).
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioProducto.php';
require_once __DIR__ . '/../modelos/Producto.php';

class RepositorioProductoMariaDB implements IRepositorioProducto
{
    // La conexión viva. "?PDO" = un objeto PDO o null; arranca en null
    // porque la conexión NO se abre al construir (es perezosa).
    private ?PDO $conexion = null;

    // Promoción de propiedades: los 3 datos de conexión quedan guardados
    // como atributos privados e inmutables del objeto.
    public function __construct(
        private readonly string $dsn,      // "cadena de conexión" de PDO
        private readonly string $usuario,
        private readonly string $clave,
    ) {
        // Nada más: este archivo no sabe de variables de entorno.
        // El DSN llega de afuera (lo arma el ensamblador).
    }

    // ------------------------------------------------------------------
    // Ayudantes privados
    // ------------------------------------------------------------------

    /** Abre la conexión PDO la primera vez y la reutiliza (perezosa). */
    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            // new PDO(dsn, usuario, clave, opciones) ABRE la conexión.
            // Las opciones cambian el comportamiento del driver:
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                // Errores como EXCEPCIONES (sin esto PDO falla en silencio);
                // el controlador las traduce a 500:
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Prepared statements REALES del servidor, no emulados por
                // el driver. Detalle MariaDB: con esto, :limite DEBE
                // enlazarse como entero (ver bindValue abajo).
                PDO::ATTR_EMULATE_PREPARES => false,
                // Detalle MariaDB #2: por defecto, rowCount() de un UPDATE
                // cuenta filas CAMBIADAS (si el valor nuevo es igual al viejo
                // reporta 0, y parecería que el código "no existe"). Con
                // FOUND_ROWS cuenta filas ENCONTRADAS, como los demás motores:
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        }
        return $this->conexion;
    }

    /**
     * Convierte una fila cruda de la BD en un objeto del MODELO.
     * El driver mysql entrega lo numérico "flojo" (DECIMAL llega como
     * string): los casts (int)/(float) dejan el tipo correcto AQUÍ, en un
     * solo lugar, y el resto del sistema recibe el objeto ya limpio.
     */
    private function armarProducto(array $fila): Producto
    {
        return new Producto(
            $fila['codigo'],
            $fila['nombre'],
            (int) $fila['stock'],
            (float) $fila['valorunitario'],
        );
    }

    // ------------------------------------------------------------------
    // Los 5 métodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        // El SQL con MARCADORES (:limite): el valor viaja por aparte y el
        // motor jamás lo confunde con SQL — eso evita la inyección.
        $sql = 'SELECT codigo, nombre, stock, valorunitario
                FROM producto ORDER BY codigo LIMIT :limite';
        // prepare = "compila" la consulta con sus marcadores:
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // bindValue enlaza el valor a su marcador. PARAM_INT es obligatorio
        // aquí: un LIMIT con string es error de sintaxis en MariaDB.
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        // execute = ejecutar la consulta ya preparada:
        $sentencia->execute();

        // fetchAll trae TODAS las filas; FETCH_ASSOC = como arrays
        // asociativos (columna => valor):
        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        // array_map aplica una función a CADA fila. "fn(...) => ..." es una
        // arrow function: función de una sola expresión. Cada fila cruda se
        // convierte en un objeto del MODELO:
        return array_map(fn(array $fila) => $this->armarProducto($fila), $filas);
    }

    public function obtenerPorClave(string $codigo): ?Producto
    {
        $sql = 'SELECT codigo, nombre, stock, valorunitario
                FROM producto WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // execute también acepta los valores como array (todos como texto,
        // suficiente para un WHERE de texto):
        $sentencia->execute(['codigo' => $codigo]);

        // fetch trae UNA fila, o false si no hubo resultado:
        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        // Operador ternario: false → null (el contrato); fila → el modelo.
        return $fila === false ? null : $this->armarProducto($fila);
    }

    public function crear(Producto $producto): bool
    {
        $sql = 'INSERT INTO producto (codigo, nombre, stock, valorunitario)
                VALUES (:codigo, :nombre, :stock, :valorunitario)';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // Los valores salen del OBJETO del modelo, a través de sus getters:
        $sentencia->execute([
            'codigo'        => $producto->getCodigo(),
            'nombre'        => $producto->getNombre(),
            'stock'         => $producto->getStock(),
            'valorunitario' => $producto->getValorunitario(),
        ]);
        // rowCount = cuántas filas tocó la última sentencia (1 si insertó):
        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        // SET dinámico SOLO con las columnas que llegaron (PUT manda las 3,
        // PATCH un subconjunto). Los NOMBRES de columna salen de la lista
        // blanca del modelo (filtrarColumnas) — nunca del cliente — por eso
        // es seguro interpolarlos; los VALORES sí van siempre como parámetros.
        $asignaciones = [];
        // array_keys devuelve las llaves del array (los nombres de columna):
        foreach (array_keys($datos) as $columna) {
            // Cada columna genera su pareja "columna = :columna":
            $asignaciones[] = "$columna = :$columna";
        }
        // implode une la lista con comas: "nombre = :nombre, stock = :stock".
        // El marcador de la clave se llama distinto (:codigo_clave) para no
        // chocar si el PATCH también trajera un campo "codigo":
        $sql = 'UPDATE producto SET ' . implode(', ', $asignaciones)
             . ' WHERE codigo = :codigo_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        // "+" une los dos arrays: los campos a escribir + la clave del WHERE:
        $sentencia->execute($datos + ['codigo_clave' => $codigo]);
        // Filas afectadas (gracias a FOUND_ROWS: encontradas, no cambiadas):
        return $sentencia->rowCount();
    }

    public function eliminar(string $codigo): int
    {
        $sql = 'DELETE FROM producto WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);
        return $sentencia->rowCount();
    }
}
