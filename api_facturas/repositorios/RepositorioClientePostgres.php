<?php
/**
 * RepositorioClientePostgres — la capa de DATOS de `cliente` contra PostgreSQL.
 *
 * Es el gemelo de `RepositorioClienteMariaDB`: **cumple la misma interfaz** y
 * hace lo mismo. Ábralos lado a lado, porque la comparación es la lección de
 * esta versión.
 *
 * Lo que ninguna otra capa se entera de que cambió:
 *   · el DSN empieza por `pgsql:` en vez de `mysql:`;
 *   · no hace falta `MYSQL_ATTR_FOUND_ROWS` — en PostgreSQL `rowCount()` ya
 *     cuenta las filas que el WHERE encontró;
 *   · los errores del motor se traducen con otro archivo;
 *   · y las llaves generadas se leen con `RETURNING`, no preguntando después.
 *
 * El SQL de las consultas, en cambio, resultó ser **idéntico**. No siempre
 * pasa —esta base usa SQL estándar— y por eso conviene decirlo: la clase
 * separada no existe porque el SQL fuera a cambiar, sino porque **todo lo de
 * alrededor sí cambia**.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioCliente.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';
require_once __DIR__ . '/../modelos/Cliente.php';

class RepositorioClientePostgres implements IRepositorioCliente
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
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Y aquí NO va MYSQL_ATTR_FOUND_ROWS: no existe fuera de
                // MySQL/MariaDB, y aquí no hace falta — el rowCount() de un
                // UPDATE ya cuenta las filas que encontró el WHERE.
            ]);
        }
        return $this->conexion;
    }

    /** Una fila cruda convertida en objeto del modelo, con sus tipos. */
    private function armarCliente(array $fila): Cliente
    {
        // Los casts se quedan aunque PostgreSQL devuelva los enteros ya como
        // enteros: los NUMERIC siguen llegando como texto, y escribir la
        // conversión completa en los dos repositorios evita que alguien tenga
        // que acordarse de en cuál sí y en cuál no.
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
        $sql = 'SELECT id, credito, fkcodpersona, fkcodempresa FROM cliente ORDER BY id LIMIT :limite';
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
     * **Aquí no hay `lastInsertId()`.** PostgreSQL lo resuelve mejor: se
     * le pide al propio INSERT que devuelva la llave con `RETURNING`, y
     * llega en la misma respuesta. En MariaDB había que preguntar después
     * —dos viajes en vez de uno— porque su `INSERT` no devuelve nada.
     */
    public function crear(Cliente $cliente): int
    {
        $sql = 'INSERT INTO cliente (credito, fkcodpersona, fkcodempresa)
                VALUES (:credito, :fkcodpersona, :fkcodempresa) RETURNING id';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'credito' => $cliente->getCredito(),
                'fkcodpersona' => $cliente->getFkcodpersona(),
                'fkcodempresa' => $cliente->getFkcodempresa(),
            ]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'el cliente');
        }

        // fetchColumn lee la primera columna de la fila que devolvió el
        // RETURNING: la llave recién generada.
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
            traducirErrorPostgres($error, 'el cliente');
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
            traducirErrorPostgres($error, 'el cliente');
        }
        return $sentencia->rowCount();
    }
}
