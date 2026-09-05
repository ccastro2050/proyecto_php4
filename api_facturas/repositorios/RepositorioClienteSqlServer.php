<?php
/**
 * RepositorioClienteSqlServer — la capa de DATOS de `cliente` contra SQL Server.
 *
 * El tercero de la familia. Cumple `IRepositorioCliente` igual que los otros dos,
 * y como ellos, no se parece a ninguno.
 *
 * ======================================================================
 * AQUÍ SÍ CAMBIÓ EL SQL, Y ES LA NOVEDAD DE LA v4
 * ======================================================================
 *
 * Entre MariaDB y PostgreSQL las consultas se copiaron **tal cual**: los dos
 * aceptan el mismo SQL estándar para lo que este sistema hace. Con SQL Server
 * eso se acabó:
 *
 *   · **`LIMIT` no existe.** Se escribe
 *     `ORDER BY … OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY`, y **exige** un
 *     `ORDER BY` — que aquí ya estaba, pero podría no haber estado.
 *   · **La llave generada se lee con `OUTPUT INSERTED.id`**, una cláusula que
 *     va en medio del INSERT.
 *
 * Es la comprobación más dura de la ruta de versiones: hasta ahora se podía
 * sospechar que las clases separadas eran ceremonia, porque el SQL coincidía.
 * Con el tercer motor deja de coincidir, y la separación se paga sola.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioCliente.php';
require_once __DIR__ . '/errores_de_integridad_sqlserver.php';
require_once __DIR__ . '/../modelos/Cliente.php';

class RepositorioClienteSqlServer implements IRepositorioCliente
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

    /** Abre la conexión PDO la primera vez y la reutiliza (perezosa). */
    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            // Fíjese en lo que NO está: `ATTR_EMULATE_PREPARES`. El driver de
            // Microsoft no lo admite —siempre usa prepared statements reales—
            // y ponerlo hace fallar la conexión. Otra cosa que cambia y que
            // no tiene nada que ver con el SQL.
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return $this->conexion;
    }

    /** Una fila cruda convertida en objeto del modelo, con sus tipos. */
    private function armarCliente(array $fila): Cliente
    {
        // SQL Server devuelve TODO como texto —incluidos los enteros—, así
        // que aquí los casts no son opcionales. (PostgreSQL sí entrega los
        // enteros como enteros; MariaDB, como texto. Tres motores, tres
        // comportamientos.)
        return new Cliente(
            (int) $fila['id'],
            (float) $fila['credito'],
            $fila['fkcodpersona'],
            $fila['fkcodempresa'],   // puede venir NULL, y así se queda
        );
    }

    // ------------------------------------------------------------------
    // Los 5 métodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        // `LIMIT` no existe en T-SQL. El equivalente estándar es OFFSET/FETCH,
        // que además EXIGE un ORDER BY: SQL Server se niega a paginar sin un
        // orden definido, y tiene razón — sin orden, «los primeros N» no
        // significa nada.
        $sql = 'SELECT id, credito, fkcodpersona, fkcodempresa FROM cliente
                ORDER BY id OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarCliente($fila), $filas);
    }

    public function obtenerPorClave(int $id): ?Cliente
    {
        $sql = 'SELECT id, credito, fkcodpersona, fkcodempresa FROM cliente WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':id', $id, PDO::PARAM_INT);
        $sentencia->execute();

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarCliente($fila);
    }

    /**
     * Inserta y devuelve el id que generó la base.
     *
     * Cada motor lo resuelve a su manera, y las tres se ven juntas si se
     * abren los tres repositorios de esta entidad:
     *   · MariaDB    → `lastInsertId()`, en otra consulta
     *   · PostgreSQL → `INSERT … RETURNING id`
     *   · SQL Server → **`OUTPUT INSERTED.id`**, que va en medio del
     *     INSERT, entre las columnas y los VALUES
     *
     * Las tres hacen lo mismo. Ninguna se parece.
     */
    public function crear(Cliente $cliente): int
    {
        $sql = 'INSERT INTO cliente (credito, fkcodpersona, fkcodempresa)
                OUTPUT INSERTED.id
                VALUES (:credito, :fkcodpersona, :fkcodempresa)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'credito' => $cliente->getCredito(),
                'fkcodpersona' => $cliente->getFkcodpersona(),
                'fkcodempresa' => $cliente->getFkcodempresa(),
            ]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'el cliente');
        }

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(int $id, array $datos): int
    {
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE cliente SET ' . implode(', ', $asignaciones)
             . ' WHERE id = :id_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['id_clave' => $id]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'el cliente');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(int $id): int
    {
        $sql = 'DELETE FROM cliente WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->bindValue(':id', $id, PDO::PARAM_INT);
            $sentencia->execute();
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'el cliente');
        }
        return $sentencia->rowCount();
    }
}
