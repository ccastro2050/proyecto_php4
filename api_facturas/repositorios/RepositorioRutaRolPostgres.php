<?php
/**
 * RepositorioRutaRolPostgres — la capa de DATOS del puente `rutarol`
 * contra PostgreSQL.
 *
 * `rutarol` dice QUE ROL ENTRA A QUE RUTA, y es la tabla que
 * `verificar_acceso_ruta` recorre para decidir cada 403 de la aplicacion.
 * Sin ella, los permisos habria que escribirlos usuario por usuario.
 *
 * DOS COSAS QUE ESTE ARCHIVO ENSENA Y LAS ENTIDADES NO
 * ----------------------------------------------------
 * **El JOIN del listado.** La tabla guarda numeros —(3, 1)—, y un numero no
 * le dice nada a quien lee la pantalla. El listado trae los nombres con un
 * JOIN. Es discutible y conviene saber por que se decidio asi: el GET plano
 * seria mas rapido, pero obligaria al front a pedir las otras dos tablas
 * aparte y a cruzarlas el mismo. **El cruce se hace donde estan los datos.**
 *
 * **El DELETE lleva las dos columnas en el WHERE.** No es una precaucion: es
 * la unica forma de borrar una pareja. Con una sola columna se borrarian
 * todas las del mismo lado.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioRutaRol.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';

class RepositorioRutaRolPostgres implements IRepositorioRutaRol
{
    private ?PDO $conexion = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $usuario,
        private readonly string $clave,
    ) {
    }

    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return $this->conexion;
    }

    // ------------------------------------------------------------------
    // Los 5 metodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        $sql = 'SELECT rr.fkidruta, rt.ruta, rr.fkidrol, r.nombre AS rol
                FROM rutarol rr
                JOIN ruta rt ON rt.id = rr.fkidruta
                JOIN rol r ON r.id = rr.fkidrol
                ORDER BY rt.ruta, r.nombre LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorLadoA(int $fkidruta): array
    {
        $sql = 'SELECT rr.fkidruta, rt.ruta, rr.fkidrol, r.nombre AS rol
                FROM rutarol rr
                JOIN ruta rt ON rt.id = rr.fkidruta
                JOIN rol r ON r.id = rr.fkidrol
                WHERE rr.fkidruta = :fkidruta ORDER BY rt.ruta, r.nombre';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkidruta' => $fkidruta]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorLadoB(int $fkidrol): array
    {
        $sql = 'SELECT rr.fkidruta, rt.ruta, rr.fkidrol, r.nombre AS rol
                FROM rutarol rr
                JOIN ruta rt ON rt.id = rr.fkidruta
                JOIN rol r ON r.id = rr.fkidrol
                WHERE rr.fkidrol = :fkidrol ORDER BY rt.ruta, r.nombre';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkidrol' => $fkidrol]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(int $fkidruta, int $fkidrol): bool
    {
        $sql = 'INSERT INTO rutarol (fkidruta, fkidrol)
                VALUES (:fkidruta, :fkidrol)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute(['fkidruta' => $fkidruta, 'fkidrol' => $fkidrol]);
        } catch (PDOException $error) {
            // DOS motivos posibles y el mismo codigo de salida: la pareja ya
            // existe (llave primaria) o uno de los dos lados no existe
            // (foranea). En ambos casos la peticion esta bien escrita y
            // choca con el estado de la base de datos → 409.
            traducirErrorPostgres($error, 'el permiso');
        }
        return $sentencia->rowCount() === 1;
    }

    public function eliminar(int $fkidruta, int $fkidrol): int
    {
        // LAS DOS columnas: con una sola se borrarian todas las parejas de
        // ese lado.
        $sql = 'DELETE FROM rutarol
                WHERE fkidruta = :fkidruta AND fkidrol = :fkidrol';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkidruta' => $fkidruta, 'fkidrol' => $fkidrol]);
        return $sentencia->rowCount();
    }
}
