<?php
/**
 * RepositorioUsuarioPostgres — la capa de DATOS de `usuario` contra PostgreSQL.
 *
 * AQUI, Y SOLO AQUI, VIVE EL HASH. Dos reglas que no se negocian:
 *
 *   1. Se guarda con `password_hash` y bcrypt costo 12. Jamas texto plano.
 *   2. Ningun SELECT proyecta la columna `contrasena` hacia afuera. El unico
 *      que la lee es `verificarContrasena`, y la compara adentro: el hash no
 *      sale de este archivo.
 *
 * Y lo que hay que notar comparando los tres repositorios: **el hash es
 * identico en los tres**. `password_hash` y `password_verify` son de PHP, no
 * del motor — cambiar de base de datos no cambia como se guarda un secreto.
 * Lo que cambia es todo lo de alrededor: las opciones de PDO, el tope de
 * filas y el numero con el que el motor avisa que la llave ya existe.
 *
 * El mismo email repetido, otro codigo: PostgreSQL lo senala con el SQLSTATE
 * 23505. Lo reconoce `errores_de_integridad_postgres.php`.
 *
 * UNA ADVERTENCIA SOBRE LOS DATOS SEMBRADOS: en esta base de datos hubo dos
 * usuarios con la contrasena EN TEXTO PLANO. `password_verify` no la
 * reconoce y devuelve `false` sin lanzar nada —no se cae, simplemente no
 * coincide—, que es exactamente el comportamiento que se quiere: un dato malo
 * en la base de datos no puede tumbar la API.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioUsuario.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';
require_once __DIR__ . '/../modelos/Usuario.php';

class RepositorioUsuarioPostgres implements IRepositorioUsuario
{
    /**
     * El costo del hash. Cada punto DUPLICA el tiempo de calculo: 12 es el
     * equilibrio habitual entre «molesta al atacante» y «no molesta al
     * usuario». Y es para lo que se diseno bcrypt — encarecerlo cuando las
     * maquinas sean mas rapidas, sin cambiar de funcion.
     */
    private const COSTO_BCRYPT = 12;

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

    /** El hash de una contrasena, con bcrypt y el costo de arriba. */
    private function hashear(string $contrasena): string
    {
        return password_hash($contrasena, PASSWORD_BCRYPT,
                             ['cost' => self::COSTO_BCRYPT]);
    }

    // ------------------------------------------------------------------
    // Los 6 metodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        // SOLO email: la contrasena no sale ni en hash.
        $sql = 'SELECT email FROM usuario ORDER BY email LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        // El objeto se arma SIN contrasena: el segundo parametro queda vacio.
        return array_map(fn(array $fila) => new Usuario($fila['email']), $filas);
    }

    public function obtenerPorClave(string $email): ?Usuario
    {
        $sql = 'SELECT email FROM usuario WHERE email = :email';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['email' => $email]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : new Usuario($fila['email']);
    }

    public function crear(Usuario $usuario): bool
    {
        // El hash se calcula AQUI, justo antes de persistir.
        $sql = 'INSERT INTO usuario (email, contrasena) VALUES (:email, :hash)';
        $sentencia = $this->obtenerConexion()->prepare($sql);

        try {
            $sentencia->execute([
                'email' => $usuario->getEmail(),
                'hash'  => $this->hashear($usuario->getContrasenaParaGuardar()),
            ]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'el usuario');
        }
        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $email, array $datos): int
    {
        // Si viene la contrasena, se REHASHEA: nunca se escribe lo que llego.
        if (array_key_exists('contrasena', $datos)) {
            $datos['contrasena'] = $this->hashear((string) $datos['contrasena']);
        }

        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE usuario SET ' . implode(', ', $asignaciones)
             . ' WHERE email = :email_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute($datos + ['email_clave' => $email]);
        } catch (PDOException $error) {
            traducirErrorPostgres($error, 'el usuario');
        }
        return $sentencia->rowCount();
    }

    public function eliminar(string $email): int
    {
        $sql = 'DELETE FROM usuario WHERE email = :email';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        try {
            $sentencia->execute(['email' => $email]);
        } catch (PDOException $error) {
            // Si el usuario todavia tiene roles, la foranea de rol_usuario
            // rechaza el borrado: el traductor lo vuelve un 409 con el
            // nombre del estorbo. Para borrar usuario Y roles de una vez
            // esta /api/usuario-con-roles.
            traducirErrorPostgres($error, 'el usuario');
        }
        return $sentencia->rowCount();
    }

    public function verificarContrasena(string $email, string $contrasena): ?bool
    {
        // El hash SE LEE pero no sale: se compara aqui mismo.
        $sql = 'SELECT contrasena FROM usuario WHERE email = :email';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['email' => $email]);

        $hash = $sentencia->fetchColumn();
        if ($hash === false) {
            return null;            // el usuario no existe
        }

        // password_verify devuelve false ante un hash malformado —las filas
        // sembradas en texto plano— sin lanzar nada. Eso es lo correcto.
        return password_verify($contrasena, (string) $hash);
    }
}
