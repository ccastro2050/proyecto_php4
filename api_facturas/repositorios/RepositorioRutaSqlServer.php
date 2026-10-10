<?php
/**
 * RepositorioRutaSqlServer — la capa de DATOS de `ruta` contra SQL Server.
 *
 * Unica clase que habla SQL de este motor y que conoce la conexion. Cumple
 * IRepositorioRuta con `implements`.
 *
 * Reglas de la constitucion que se cumplen aqui:
 * - SQL SIEMPRE en prepared statements de PDO (nunca concatenar valores).
 * - El SQL queda visible (PDO como ejecutor, sin ORM).
 *
 * El mismo UNIQUE, un tercer codigo: SQL Server usa el error 2627. Tres
 * motores, tres numeros, UNA frontera que los normaliza.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioRuta.php';
require_once __DIR__ . '/errores_de_integridad_sqlserver.php';
require_once __DIR__ . '/../modelos/Ruta.php';

class RepositorioRutaSqlServer implements IRepositorioRuta
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
    private function armarRuta(array $fila): Ruta
    {
        return new Ruta(
            (int) $fila['id'],
            $fila['ruta'],
            $fila['descripcion'],
        );
    }

    // ------------------------------------------------------------------
    // Los 5 metodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        // `LIMIT` no existe en T-SQL: el equivalente estandar es
        // OFFSET/FETCH, y EXIGE un ORDER BY delante.
        $sql = 'SELECT id, ruta, descripcion
                FROM ruta ORDER BY id
                OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // El tope tambien va parametrizado, y como ENTERO:
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarRuta($fila), $filas);
    }

    public function obtenerPorClave(int $id): ?Ruta
    {
        $sql = 'SELECT id, ruta, descripcion FROM ruta WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['id' => $id]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarRuta($fila);
    }

    public function crear(Ruta $rutaObjeto): int
    {
        $sql = 'INSERT INTO ruta (ruta, descripcion)
                VALUES (:ruta, :descripcion)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'ruta' => $rutaObjeto->getRuta(),
                'descripcion' => $rutaObjeto->getDescripcion(),
            ]);
        } catch (PDOException $error) {
            // La base de datos rechazo por integridad: se traduce a un
            // mensaje legible (ver errores_de_integridad_sqlserver.php).
            traducirErrorSqlServer($error, 'la ruta');
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
        $sql = 'UPDATE ruta SET ' . implode(', ', $asignaciones)
             . ' WHERE id = :id_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['id_clave' => $id]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'la ruta');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(int $id): int
    {
        $sql = 'DELETE FROM ruta WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute(['id' => $id]);
        } catch (PDOException $error) {
            // Borrar algo que otra tabla referencia: la foranea lo rechaza y
            // el mensaje dice cual es el estorbo.
            traducirErrorSqlServer($error, 'la ruta');
        }
        return $sentencia->rowCount();
    }
}
