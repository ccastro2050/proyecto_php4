<?php
/**
 * RepositorioUsuarioConRolesPostgres — la API como TRADUCTORA.
 *
 * No hay un solo SELECT escrito aqui: cinco llamadas a cinco procedimientos.
 * El trabajo de este archivo es otro, y son tres cosas:
 *
 * **1 · HASHEAR LA CONTRASENA ANTES DE ENTREGARLA.** El procedimiento la
 * guarda tal cual se la den. Si se le pasara en claro, en claro quedaria en
 * la tabla — y seria exactamente el error que esta version vino a corregir.
 *
 * **2 · TRADUCIR LA LISTA DE ROLES A JSON.** Los procedimientos reciben
 * `[{"fkidrol": 1}, {"fkidrol": 3}]` y lo abren por dentro. UN viaje a la
 * base de datos en vez de uno por rol.
 *
 * **3 · TRADUCIR EL RECHAZO DEL MOTOR A UNA EXCEPCION DE NEGOCIO.** El
 * procedimiento levanta «Usuario % no existe»: eso es un 404, no un 500 —la
 * base de datos no fallo, contesto—.
 *
 * Aqui el INOUT vuelve como fila del CALL: una sola sentencia.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioUsuarioConRoles.php';
require_once __DIR__ . '/errores_de_integridad_postgres.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class RepositorioUsuarioConRolesPostgres implements IRepositorioUsuarioConRoles
{
    private const COSTO_BCRYPT = 12;

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

    /** El hash, con bcrypt y costo 12 — igual que en RepositorioUsuario. */
    private function hashear(string $contrasena): string
    {
        return password_hash($contrasena, PASSWORD_BCRYPT,
                             ['cost' => self::COSTO_BCRYPT]);
    }

    /**
     * La lista de enteros, en la forma que esperan los procedimientos.
     *
     * @param int[] $idsRol
     */
    private function rolesJson(array $idsRol): string
    {
        $pares = [];
        foreach ($idsRol as $id) {
            $pares[] = ['fkidrol' => (int) $id];
        }
        return (string) json_encode($pares);
    }

    /**
     * Si el motor rechazo con el mensaje del procedimiento, se traduce a una
     * excepcion de NEGOCIO. Nadie por encima de esta capa conoce PDOException.
     */
    private function traducirRechazo(PDOException $error): void
    {
        $mensaje = $error->getMessage();
        if (str_contains($mensaje, 'no existe')) {
            // Se recorta el ruido del driver y se deja la frase del
            // procedimiento, que es la que una persona puede leer.
            $limpio = preg_replace('/^.*?(Usuario .*?no existe).*$/s', '$1', $mensaje);
            throw new NoEncontradoExcepcion((string) $limpio);
        }
    }

    /**
     * En PostgreSQL el INOUT **vuelve como una fila del propio CALL**: una
     * sola sentencia, sin variables de sesion. El NULL va escrito en el SQL
     * para marcar el hueco de salida.
     */
    private function llamar(string $nombre, array $parametros): array
    {
        $marcadores = array_fill(0, count($parametros), '?');
        $marcadores[] = 'NULL';
        $sql = "CALL $nombre(" . implode(', ', $marcadores) . ")";

        try {
            $sentencia = $this->obtenerConexion()->prepare($sql);
            $sentencia->execute($parametros);
        } catch (PDOException $error) {
            $this->traducirRechazo($error);
            traducirErrorPostgres($error, 'el usuario');
        }

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        $json = $fila === false ? '' : (string) reset($fila);
        return json_decode($json, true) ?? [];
    }


    // ------------------------------------------------------------------
    // Las 5 operaciones del contrato
    // ------------------------------------------------------------------

    public function listar(): array
    {
        $datos = $this->llamar('listar_usuarios_con_roles', []);
        // El procedimiento devuelve un arreglo JSON; si viniera vacio, se
        // responde una lista vacia y no un null.
        return is_array($datos) ? $datos : [];
    }

    public function consultar(string $email): array
    {
        return $this->llamar('consultar_usuario_con_roles', [$email]);
    }

    public function crear(string $email, string $contrasena, array $idsRol): array
    {
        // El hash, ANTES de entregarselo al procedimiento (ver el encabezado).
        return $this->llamar('crear_usuario_con_roles',
                             [$email, $this->hashear($contrasena),
                              $this->rolesJson($idsRol)]);
    }

    public function actualizar(string $email, ?string $contrasena, array $idsRol): array
    {
        // Cadena vacia = «no cambie la contrasena». Es la convencion del
        // procedimiento —`IF p_contrasena IS NOT NULL AND p_contrasena != ''`—
        // y el repositorio la respeta en vez de inventar otra.
        $hash = ($contrasena === null || $contrasena === '')
            ? ''
            : $this->hashear($contrasena);

        return $this->llamar('actualizar_usuario_con_roles',
                             [$email, $hash, $this->rolesJson($idsRol)]);
    }

    public function eliminar(string $email): array
    {
        return $this->llamar('eliminar_usuario_con_roles', [$email]);
    }
}
