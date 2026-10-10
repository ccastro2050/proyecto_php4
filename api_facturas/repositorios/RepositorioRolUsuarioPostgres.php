<?php
/**
 * RepositorioRolUsuarioPostgres — la capa de DATOS del puente `rol_usuario`
 * contra PostgreSQL.
 *
 * `rol_usuario` dice QUE ROLES TIENE CADA USUARIO, y es la hermana de
 * `rutarol`. La diferencia vale una clase: aqui uno de los dos lados es
 * TEXTO —el email, que es la llave primaria de usuario— y el otro un entero.
 * Una tabla puente no exige que sus dos lados sean numeros; exige que las dos
 * columnas juntas identifiquen la fila.
 *
 * Las dos juntas cierran el circulo del control de acceso:
 *
 *     usuario --(rol_usuario)--> rol --(rutarol)--> ruta
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

require_once __DIR__ . '/IRepositorioRolUsuario.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';

class RepositorioRolUsuarioPostgres implements IRepositorioRolUsuario
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
        $sql = 'SELECT ru.fkemail, ru.fkidrol, r.nombre AS rol
                FROM rol_usuario ru
                JOIN rol r ON r.id = ru.fkidrol
                ORDER BY ru.fkemail, r.nombre LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorLadoA(string $fkemail): array
    {
        $sql = 'SELECT ru.fkemail, ru.fkidrol, r.nombre AS rol
                FROM rol_usuario ru
                JOIN rol r ON r.id = ru.fkidrol
                WHERE ru.fkemail = :fkemail ORDER BY ru.fkemail, r.nombre';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkemail' => $fkemail]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorLadoB(int $fkidrol): array
    {
        $sql = 'SELECT ru.fkemail, ru.fkidrol, r.nombre AS rol
                FROM rol_usuario ru
                JOIN rol r ON r.id = ru.fkidrol
                WHERE ru.fkidrol = :fkidrol ORDER BY ru.fkemail, r.nombre';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkidrol' => $fkidrol]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $fkemail, int $fkidrol): bool
    {
        $sql = 'INSERT INTO rol_usuario (fkemail, fkidrol)
                VALUES (:fkemail, :fkidrol)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute(['fkemail' => $fkemail, 'fkidrol' => $fkidrol]);
        } catch (PDOException $error) {
            // DOS motivos posibles y el mismo codigo de salida: la pareja ya
            // existe (llave primaria) o uno de los dos lados no existe
            // (foranea). En ambos casos la peticion esta bien escrita y
            // choca con el estado de la base de datos → 409.
            traducirErrorPostgres($error, 'la asignacion');
        }
        return $sentencia->rowCount() === 1;
    }

    public function eliminar(string $fkemail, int $fkidrol): int
    {
        // LAS DOS columnas: con una sola se borrarian todas las parejas de
        // ese lado.
        $sql = 'DELETE FROM rol_usuario
                WHERE fkemail = :fkemail AND fkidrol = :fkidrol';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['fkemail' => $fkemail, 'fkidrol' => $fkidrol]);
        return $sentencia->rowCount();
    }
}
