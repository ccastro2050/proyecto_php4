<?php
/**
 * RepositorioAccesoSqlServer — llama al procedimiento que ya estaba.
 *
 * `verificar_acceso_ruta` esta en la base de datos desde el primer dia, sin
 * que nadie lo llamara. Eso es lo que cambia en la v3: no se agrega una
 * tabla, **se empieza a preguntar**.
 *
 * EL PROCEDIMIENTO RECIBE EL ID DE LA RUTA, no su nombre. Asi que hay un paso
 * antes: traducir `/producto` al id que le corresponde. Se hace aqui y no en
 * la guardia, porque es una consulta a la base de datos.
 *
 * Y hay una decision de seguridad en ese paso: **si la ruta NO esta en la
 * tabla, la respuesta es `false`** —nadie entra—. Una ruta que no se declaro
 * no se concedio. Fallar cerrado, no abierto: al contrario, bastaria escribir
 * mal el nombre de una ruta en el codigo para dejar un endpoint sin
 * proteccion, y nadie lo notaria porque todo «funcionaria».
 *
 * ======================================================================
 * EL TIPO DE `tiene_acceso` NO ES EL MISMO EN LOS TRES MOTORES
 * ======================================================================
 *
 * El procedimiento es «el mismo» y su JSON no lo es:
 *
 *     PostgreSQL   {"tiene_acceso": false}     booleano
 *     SQL Server   {"tiene_acceso": 0}         numero
 *     MariaDB      {"tiene_acceso": "0"}       CADENA
 *
 * Y en PHP, `(bool) "0"` es **false** —PHP trata la cadena "0" como falsa, al
 * contrario de casi todos los demas lenguajes—, pero `(bool) "false"` seria
 * **true**. Confiar en esa tabla de conversiones es confiar en que nadie
 * cambie el procedimiento.
 *
 * Por eso la conversion es EXPLICITA y lista los valores que acepta: lo que
 * no reconozca es `false`. Esta en los tres repositorios, incluido el de
 * PostgreSQL donde no haria falta, para que nadie lo «simplifique» aqui y
 * copie el atajo al que si rompe.
 *
 * Aqui el campo llega como NUMERO. Con un `(bool)` funcionaria por
 * casualidad, y esa clase de casualidad es la que hace creer que el codigo
 * esta bien hasta que se cambia de motor.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioAcceso.php';

class RepositorioAccesoSqlServer implements IRepositorioAcceso
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
            ]);
        }
        return $this->conexion;
    }

    /**
     * Convierte a booleano lo que el motor pone en `tiene_acceso`.
     *
     * NUNCA un `(bool)` a secas: la tabla de conversiones de PHP es larga y
     * tiene sorpresas —`"0"` es falso, `"false"` es verdadero, `"0.0"` es
     * verdadero—. Aqui se listan los valores que cuentan como SI, y todo lo
     * demas es NO: fallar cerrado, no abierto.
     */
    private function aBooleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }
        if (is_string($valor)) {
            return in_array(strtolower(trim($valor)), ['1', 'true', 't', 'yes'], true);
        }
        return false;
    }

    public function tieneAcceso(string $email, string $nombreRuta): bool
    {
        $conexion = $this->obtenerConexion();

        // Paso 1: el nombre de la ruta a su id.
        $sentencia = $conexion->prepare(
            'SELECT id FROM ruta WHERE ruta = :nombre');
        $sentencia->execute(['nombre' => $nombreRuta]);
        $idRuta = $sentencia->fetchColumn();
        if ($idRuta === false) {
            return false;        // ruta no declarada -> nadie entra
        }

        // Paso 2: el procedimiento. SQL Server devuelve el OUTPUT en un LOTE:
        // se DECLARA la variable, se llama con `@salida OUTPUT` y se hace
        // SELECT de ella. `SET NOCOUNT ON` al frente para que el unico
        // resultado del lote sea ese SELECT.
        $sql = 'SET NOCOUNT ON;
                DECLARE @salida NVARCHAR(MAX);
                EXEC verificar_acceso_ruta ?, ?, @salida OUTPUT;
                SELECT @salida AS p_resultado;';
        $llamada = $conexion->prepare($sql);
        $llamada->execute([$email, (int) $idRuta]);

        $fila = $llamada->fetch(PDO::FETCH_ASSOC);
        $json = $fila === false ? '' : (string) ($fila['p_resultado'] ?? '');
        $datos = json_decode($json, true) ?? [];

        return $this->aBooleano($datos['tiene_acceso'] ?? null);
    }

    public function rutasPermitidas(string $email): array
    {
        // Este SI es un JOIN escrito en PHP, y conviene decir por que no
        // contradice la regla de arriba: **no decide NADA**. Es una lista
        // para dibujar un menu. La DECISION —si una operacion entra o no— la
        // toma `verificar_acceso_ruta`, y solo el.
        $sql = 'SELECT DISTINCT r.ruta
                FROM usuario u
                INNER JOIN rol_usuario ru ON u.email = ru.fkemail
                INNER JOIN rutarol rr ON ru.fkidrol = rr.fkidrol
                INNER JOIN ruta r ON r.id = rr.fkidruta
                WHERE u.email = :email
                ORDER BY r.ruta';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['email' => $email]);

        // FETCH_COLUMN entrega una lista plana de valores, no de filas: es
        // justo lo que hace falta cuando la consulta trae una sola columna.
        return $sentencia->fetchAll(PDO::FETCH_COLUMN);
    }
}
