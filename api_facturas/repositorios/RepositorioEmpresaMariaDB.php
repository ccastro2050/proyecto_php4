<?php
/**
 * RepositorioEmpresaMariaDB — la capa de DATOS de `empresa`.
 *
 * Cumple IRepositorioEmpresa con `implements`. Igual que el repositorio de
 * producto de la v1: PDO, prepared statements, SQL a la vista.
 *
 * Lo que la v2 le agrega: **las escrituras van dentro de un try/catch**,
 * porque ahora esta tabla tiene llaves foráneas y el motor puede rechazar
 * con razón. Ese rechazo se traduce a un mensaje entendible en
 * `errores_de_integridad.php` — no se deja salir como un 500.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioEmpresa.php';
require_once __DIR__ . '/errores_de_integridad_mariadb.php';
require_once __DIR__ . '/../modelos/Empresa.php';

class RepositorioEmpresaMariaDB implements IRepositorioEmpresa
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
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        }
        return $this->conexion;
    }

    /** Una fila cruda convertida en objeto del modelo, con sus tipos. */
    private function armarEmpresa(array $fila): Empresa
    {
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
            // La base rechazó por integridad: se traduce a un mensaje que
            // una persona pueda leer (ver errores_de_integridad_mariadb.php).
            traducirErrorMariaDB($error, 'la empresa');
        }

        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        // SET dinámico SOLO con las columnas que llegaron. Los NOMBRES salen
        // de la lista blanca del controlador, nunca del cliente; los VALORES
        // siempre van como parámetros.
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
            traducirErrorMariaDB($error, 'la empresa');
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
            // Aquí el rechazo típico es el contrario al de crear: no se puede
            // borrar porque OTRAS filas apuntan a ésta.
            traducirErrorMariaDB($error, 'la empresa');
        }
        return $sentencia->rowCount();
    }
}
