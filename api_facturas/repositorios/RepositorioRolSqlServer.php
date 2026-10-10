<?php
/**
 * RepositorioRolSqlServer — la capa de DATOS de `rol` contra SQL Server.
 *
 * Unica clase que habla SQL de este motor y que conoce la conexion. Cumple
 * IRepositorioRol con `implements`.
 *
 * Reglas de la constitucion que se cumplen aqui:
 * - SQL SIEMPRE en prepared statements de PDO (nunca concatenar valores).
 * - El SQL queda visible (PDO como ejecutor, sin ORM).
 *
 * Dos cosas cambian aqui y ninguna es el SQL de las consultas: el tope de
 * filas se escribe con `OFFSET 0 ROWS FETCH NEXT` —`LIMIT` no existe en
 * T-SQL— y la llave generada se lee con `OUTPUT INSERTED.id`.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioRol.php';
require_once __DIR__ . '/errores_de_integridad_sqlserver.php';
require_once __DIR__ . '/../modelos/Rol.php';

class RepositorioRolSqlServer implements IRepositorioRol
{
    // La conexion viva. Arranca en null: NO se abre al construir (perezosa).
    private ?PDO $conexion = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $usuario,
        private readonly string $clave,
    ) {
        // Este archivo no sabe de variables de entorno: el DSN llega armado
        // desde el ensamblador.
    }

    // ------------------------------------------------------------------
    // Ayudantes privados
    // ------------------------------------------------------------------

    /** Abre la conexion PDO la primera vez y la reutiliza. */
    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Fijese en lo que NO esta: ATTR_EMULATE_PREPARES. El driver de
                // Microsoft no lo admite —siempre usa prepared statements
                // reales— y ponerlo hace fallar la conexion.
            ]);
        }
        return $this->conexion;
    }

    /** Convierte una fila cruda en un objeto del MODELO, ya tipado. */
    private function armarRol(array $fila): Rol
    {
        return new Rol(
            (int) $fila['id'],
            $fila['nombre'],
        );
    }

    // ------------------------------------------------------------------
    // Los 5 metodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        // `LIMIT` no existe en T-SQL: el equivalente estandar es
        // OFFSET/FETCH, y EXIGE un ORDER BY delante.
        $sql = 'SELECT id, nombre
                FROM rol ORDER BY id
                OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // El tope tambien va parametrizado, y como ENTERO:
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarRol($fila), $filas);
    }

    public function obtenerPorClave(int $id): ?Rol
    {
        $sql = 'SELECT id, nombre FROM rol WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['id' => $id]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarRol($fila);
    }

    public function crear(Rol $rol): int
    {
        $sql = 'INSERT INTO rol (nombre)
                VALUES (:nombre)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'nombre' => $rol->getNombre(),
            ]);
        } catch (PDOException $error) {
            // La base de datos rechazo por integridad: se traduce a un
            // mensaje legible (ver errores_de_integridad_sqlserver.php).
            traducirErrorSqlServer($error, 'el rol');
        }

        // OUTPUT INSERTED.id devuelve la llave generada en la misma
        // sentencia: el equivalente del RETURNING de PostgreSQL.
        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(int $id, array $datos): int
    {
        // SET dinamico SOLO con las columnas que llegaron. Los NOMBRES salen
        // de la lista blanca del controlador —nunca del cliente—, por eso es
        // seguro interpolarlos; los VALORES siempre van como parametros.
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE rol SET ' . implode(', ', $asignaciones)
             . ' WHERE id = :id_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['id_clave' => $id]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'el rol');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(int $id): int
    {
        $sql = 'DELETE FROM rol WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute(['id' => $id]);
        } catch (PDOException $error) {
            // Borrar algo que otra tabla referencia: la foranea lo rechaza y
            // el mensaje dice cual es el estorbo.
            traducirErrorSqlServer($error, 'el rol');
        }
        return $sentencia->rowCount();
    }
}
