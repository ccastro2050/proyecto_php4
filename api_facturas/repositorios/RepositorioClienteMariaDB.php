<?php
/**
 * RepositorioClienteMariaDB — la capa de DATOS de `cliente`.
 *
 * Cumple IRepositorioCliente con `implements`. Igual que el repositorio de
 * producto de la v1: PDO, prepared statements, SQL a la vista.
 *
 * Lo que la v2 le agrega: **las escrituras van dentro de un try/catch**,
 * porque ahora esta tabla tiene llaves foráneas y el motor puede rechazar
 * con razón. Ese rechazo se traduce a un mensaje entendible en
 * `errores_de_integridad.php` — no se deja salir como un 500.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioCliente.php';
require_once __DIR__ . '/errores_de_integridad_mariadb.php';
require_once __DIR__ . '/../modelos/Cliente.php';

class RepositorioClienteMariaDB implements IRepositorioCliente
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
    private function armarCliente(array $fila): Cliente
    {
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

    /** Inserta y devuelve el id que generó la base. */
    public function crear(Cliente $cliente): int
    {
        $sql = 'INSERT INTO cliente (credito, fkcodpersona, fkcodempresa)
                VALUES (:credito, :fkcodpersona, :fkcodempresa)';
        $conexion = $this->obtenerConexion();
        $sentencia = $conexion->prepare($sql);

        try {
            $sentencia->execute([
                'credito' => $cliente->getCredito(),
                'fkcodpersona' => $cliente->getFkcodpersona(),
                'fkcodempresa' => $cliente->getFkcodempresa(),
            ]);
        } catch (PDOException $error) {
            // La base rechazó por integridad: se traduce a un mensaje que
            // una persona pueda leer (ver errores_de_integridad_mariadb.php).
            traducirErrorMariaDB($error, 'el cliente');
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
        $sql = 'UPDATE cliente SET ' . implode(', ', $asignaciones)
             . ' WHERE id = :id_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['id_clave' => $id]);
        } catch (PDOException $error) {
            traducirErrorMariaDB($error, 'el cliente');
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
            // Aquí el rechazo típico es el contrario al de crear: no se puede
            // borrar porque OTRAS filas apuntan a ésta.
            traducirErrorMariaDB($error, 'el cliente');
        }
        return $sentencia->rowCount();
    }
}
