<?php
/**
 * RepositorioVendedorMariaDB — la capa de DATOS de `vendedor`.
 *
 * Cumple IRepositorioVendedor con `implements`. Igual que el repositorio de
 * producto de la v1: PDO, prepared statements, SQL a la vista.
 *
 * Lo que la v2 le agrega: **las escrituras van dentro de un try/catch**,
 * porque ahora esta tabla tiene llaves foráneas y el motor puede rechazar
 * con razón. Ese rechazo se traduce a un mensaje entendible en
 * `errores_de_integridad.php` — no se deja salir como un 500.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioVendedor.php';
require_once __DIR__ . '/errores_de_integridad_mariadb.php';
require_once __DIR__ . '/../modelos/Vendedor.php';

class RepositorioVendedorMariaDB implements IRepositorioVendedor
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
    private function armarVendedor(array $fila): Vendedor
    {
        return new Vendedor(
            (int) $fila['id'],
            (int) $fila['carnet'],
            $fila['direccion'],
            $fila['fkcodpersona'],
        );
    }

    // ------------------------------------------------------------------
    // Los 5 métodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        $sql = 'SELECT id, carnet, direccion, fkcodpersona FROM vendedor ORDER BY id LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarVendedor($fila), $filas);
    }

    public function obtenerPorClave(int $id): ?Vendedor
    {
        $sql = 'SELECT id, carnet, direccion, fkcodpersona FROM vendedor WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':id', $id, PDO::PARAM_INT);
        $sentencia->execute();

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarVendedor($fila);
    }

    /** Inserta y devuelve el id que generó la base. */
    public function crear(Vendedor $vendedor): int
    {
        $sql = 'INSERT INTO vendedor (carnet, direccion, fkcodpersona)
                VALUES (:carnet, :direccion, :fkcodpersona)';
        $conexion = $this->obtenerConexion();
        $sentencia = $conexion->prepare($sql);

        try {
            $sentencia->execute([
                'carnet' => $vendedor->getCarnet(),
                'direccion' => $vendedor->getDireccion(),
                'fkcodpersona' => $vendedor->getFkcodpersona(),
            ]);
        } catch (PDOException $error) {
            // La base rechazó por integridad: se traduce a un mensaje que
            // una persona pueda leer (ver errores_de_integridad_mariadb.php).
            traducirErrorMariaDB($error, 'el vendedor');
        }

        // lastInsertId devuelve la llave que ACABA de generar la base. Es la
        // única forma de saberla: no existía antes de este INSERT.
        return (int) $conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        // SET dinámico SOLO con las columnas que llegaron. Los NOMBRES salen
        // de la lista blanca del controlador, nunca del cliente; los VALORES
        // siempre van como parámetros.
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE vendedor SET ' . implode(', ', $asignaciones)
             . ' WHERE id = :id_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['id_clave' => $id]);
        } catch (PDOException $error) {
            traducirErrorMariaDB($error, 'el vendedor');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(int $id): int
    {
        $sql = 'DELETE FROM vendedor WHERE id = :id';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->bindValue(':id', $id, PDO::PARAM_INT);
            $sentencia->execute();
        } catch (PDOException $error) {
            // Aquí el rechazo típico es el contrario al de crear: no se puede
            // borrar porque OTRAS filas apuntan a ésta.
            traducirErrorMariaDB($error, 'el vendedor');
        }
        return $sentencia->rowCount();
    }
}
