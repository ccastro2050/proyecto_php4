<?php
/**
 * RepositorioPersonaSqlServer — la capa de DATOS de `persona` contra SQL Server.
 *
 * El tercero de la familia. Cumple `IRepositorioPersona` igual que los otros dos,
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
 *   · **La llave generada se lee con `OUTPUT INSERTED.codigo`**, una cláusula que
 *     va en medio del INSERT.
 *
 * Es la comprobación más dura de la ruta de versiones: hasta ahora se podía
 * sospechar que las clases separadas eran ceremonia, porque el SQL coincidía.
 * Con el tercer motor deja de coincidir, y la separación se paga sola.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioPersona.php';
require_once __DIR__ . '/errores_de_integridad_sqlserver.php';
require_once __DIR__ . '/../modelos/Persona.php';

class RepositorioPersonaSqlServer implements IRepositorioPersona
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
    private function armarPersona(array $fila): Persona
    {
        // SQL Server devuelve TODO como texto —incluidos los enteros—, así
        // que aquí los casts no son opcionales. (PostgreSQL sí entrega los
        // enteros como enteros; MariaDB, como texto. Tres motores, tres
        // comportamientos.)
        return new Persona(
            $fila['codigo'],
            $fila['nombre'],
            $fila['email'],
            $fila['telefono'],
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
        $sql = 'SELECT codigo, nombre, email, telefono FROM persona
                ORDER BY codigo OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->armarPersona($fila), $filas);
    }

    public function obtenerPorClave(string $codigo): ?Persona
    {
        $sql = 'SELECT codigo, nombre, email, telefono FROM persona WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->armarPersona($fila);
    }

    /** Inserta la ficha. true = insertada. */
    public function crear(Persona $persona): bool
    {
        $sql = 'INSERT INTO persona (codigo, nombre, email, telefono)
                VALUES (:codigo, :nombre, :email, :telefono)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'codigo' => $persona->getCodigo(),
                'nombre' => $persona->getNombre(),
                'email' => $persona->getEmail(),
                'telefono' => $persona->getTelefono(),
            ]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'la persona');
        }

        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE persona SET ' . implode(', ', $asignaciones)
             . ' WHERE codigo = :codigo_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['codigo_clave' => $codigo]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'la persona');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(string $codigo): int
    {
        $sql = 'DELETE FROM persona WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute(['codigo' => $codigo]);
        } catch (PDOException $error) {
            traducirErrorSqlServer($error, 'la persona');
        }
        return $sentencia->rowCount();
    }
}
