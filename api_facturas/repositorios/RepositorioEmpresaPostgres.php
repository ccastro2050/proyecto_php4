<?php
/**
 * RepositorioEmpresaPostgres — la capa de DATOS de `empresa` contra PostgreSQL.
 *
 * Es el gemelo de `RepositorioEmpresaMariaDB`: **cumple la misma interfaz** y
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

require_once __DIR__ . '/IRepositorioEmpresa.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';
require_once __DIR__ . '/../modelos/Empresa.php';

class RepositorioEmpresaPostgres implements IRepositorioEmpresa
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
    private function armarEmpresa(array $fila): Empresa
    {
        // Los casts se quedan aunque PostgreSQL devuelva los enteros ya como
        // enteros: los NUMERIC siguen llegando como texto, y escribir la
        // conversión completa en los dos repositorios evita que alguien tenga
        // que acordarse de en cuál sí y en cuál no.
        return new Empresa(
            $fila['codigo'],
            $fila['nombre'],
        );
    }

    // ------------------------------------------------------------------
    // Los 5 métodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        $sql = 'SELECT codigo, nombre FROM empresa ORDER BY codigo LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarEmpresa($fila), $filas);
    }

    public function obtenerPorClave(string $codigo): ?Empresa
    {
        $sql = 'SELECT codigo, nombre FROM empresa WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarEmpresa($fila);
    }

    /** Inserta la ficha. true = insertada. */
    public function crear(Empresa $empresa): bool
    {
        $sql = 'INSERT INTO empresa (codigo, nombre)
                VALUES (:codigo, :nombre)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'codigo' => $empresa->getCodigo(),
                'nombre' => $empresa->getNombre(),
            ]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'la empresa');
        }

        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE empresa SET ' . implode(', ', $asignaciones)
             . ' WHERE codigo = :codigo_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['codigo_clave' => $codigo]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'la empresa');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(string $codigo): int
    {
        $sql = 'DELETE FROM empresa WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute(['codigo' => $codigo]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'la empresa');
        }
        return $sentencia->rowCount();
    }
}
