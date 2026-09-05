<?php
/**
 * Ensamblador — el ÚNICO lugar del sistema que conoce clases concretas.
 *
 * ======================================================================
 * LA v4 NO CAMBIÓ LA FORMA DE ESTE ARCHIVO: LE AGREGÓ UNA RAMA
 * ======================================================================
 *
 * En la v3 este archivo se convirtió en fábrica y cada función escogía entre
 * dos clases. Ahora escoge entre tres, y **la estructura es exactamente la
 * misma**: un `match` sobre el motor activo.
 *
 * Que agregar el tercer motor no haya obligado a reorganizar nada es, en sí
 * mismo, el resultado de la v4. La v3 demostró que el sistema estaba abierto
 * a un segundo motor; ésta demuestra que **sigue abierto**, que es una cosa
 * distinta: muchas arquitecturas aguantan la primera extensión y se
 * deforman en la segunda.
 *
 * ======================================================================
 * DIECIOCHO CLASES ESCRITAS A MANO, Y SIGUE VALIENDO LA PENA
 * ======================================================================
 *
 * Seis recursos por tres motores. La tentación de resolverlo armando el
 * nombre de la clase con texto —`"Repositorio{$recurso}{$motor}"`— nunca fue
 * tan grande: dieciocho `new` contra cuatro líneas.
 *
 * Y sigue estando mal, por lo mismo que la API no es genérica (Artículo 10),
 * solo que ahora los números están de nuestro lado para explicarlo: son
 * **dieciocho combinaciones**, y nadie va a probarlas todas a mano. Con las
 * clases escritas, PHP verifica cada una al llamar su función. Con nombres
 * armados en texto, la que nadie probó falla **en producción**, y con tres
 * motores la probabilidad de que exista una así se triplicó.
 *
 * Dieciocho líneas aburridas y verificables antes que cuatro ingeniosas que
 * fallan tarde.
 *
 * ======================================================================
 * POR QUÉ `match` Y NO UN `if` ENCADENADO
 * ======================================================================
 *
 * Con dos motores, un ternario alcanzaba. Con tres, un `match` dice mejor lo
 * que pasa: es una tabla de correspondencia, no una cadena de preguntas. Y
 * `match` en PHP compara de forma estricta y **exige** que algún caso
 * coincida — el `default` de aquí abajo no es una red de seguridad, es la
 * decisión de que MariaDB es el motor por defecto.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/ServicioProducto.php';
require_once __DIR__ . '/ServicioEmpresa.php';
require_once __DIR__ . '/ServicioPersona.php';
require_once __DIR__ . '/ServicioCliente.php';
require_once __DIR__ . '/ServicioVendedor.php';
require_once __DIR__ . '/ServicioFactura.php';

// --- MariaDB ---
require_once __DIR__ . '/../repositorios/RepositorioProductoMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioEmpresaMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioPersonaMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioClienteMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioVendedorMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioFacturaMariaDB.php';

// --- PostgreSQL ---
require_once __DIR__ . '/../repositorios/RepositorioProductoPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioEmpresaPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioPersonaPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioClientePostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioVendedorPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioFacturaPostgres.php';

// --- SQL Server ---
require_once __DIR__ . '/../repositorios/RepositorioProductoSqlServer.php';
require_once __DIR__ . '/../repositorios/RepositorioEmpresaSqlServer.php';
require_once __DIR__ . '/../repositorios/RepositorioPersonaSqlServer.php';
require_once __DIR__ . '/../repositorios/RepositorioClienteSqlServer.php';
require_once __DIR__ . '/../repositorios/RepositorioVendedorSqlServer.php';
require_once __DIR__ . '/../repositorios/RepositorioFacturaSqlServer.php';

/**
 * Qué motor está activo: `'mariadb'`, `'postgres'` o `'sqlserver'`.
 *
 * Sale de la variable de entorno `MOTOR`, que pone el compose. Si no viene o
 * trae cualquier otra cosa, **se usa MariaDB** en vez de fallar: el motor por
 * defecto es una decisión de configuración, no una trampa para el que se
 * equivoque escribiendo.
 *
 * Fíjese en que los tres valores están escritos aquí. El texto de la variable
 * no se usa para armar nada — si alguien pone `MOTOR=oracle`, no pasa nada
 * raro: arranca en MariaDB.
 */
function motorActivo(): string
{
    return match (strtolower(trim((string) getenv('MOTOR')))) {
        'postgres', 'postgresql' => 'postgres',
        'sqlserver', 'mssql'     => 'sqlserver',
        default                  => 'mariadb',
    };
}

/**
 * Los tres datos de conexión del motor activo.
 *
 * Los tres juegos de credenciales llegan siempre por el entorno; esta función
 * escoge el que corresponde. Los valores por defecto apuntan a los puertos
 * PUBLICADOS de cada base, para poder correr la API sin Docker mientras las
 * bases sí están en Docker.
 *
 * **SQL Server no usa el mismo usuario que los otros dos**, y no es un
 * descuido: su cuenta administrativa se llama `sa` y su clave tiene que
 * cumplir una política de complejidad. Es la clase de diferencia que uno no
 * espera hasta que la encuentra.
 *
 * @return array{0: string, 1: string, 2: string}
 */
function datosDeConexion(): array
{
    $usuario = getenv('DB_USUARIO') ?: 'paradigmas';
    $clave   = getenv('DB_CLAVE')   ?: 'paradigmas123';

    return match (motorActivo()) {
        'postgres' => [
            getenv('DB_DSN_POSTGRES')
                ?: 'pgsql:host=localhost;port=15465;dbname=bdfacturas_postgres_local',
            $usuario, $clave,
        ],
        'sqlserver' => [
            getenv('DB_DSN_SQLSERVER')
                ?: 'sqlsrv:Server=localhost,11474;Database=bdfacturas_sqlserver_local;TrustServerCertificate=yes',
            getenv('DB_USUARIO_SQLSERVER') ?: 'sa',
            getenv('DB_CLAVE_SQLSERVER')   ?: 'Paradigmas123!',
        ],
        default => [
            getenv('DB_DSN_MARIADB')
                ?: 'mysql:host=localhost;port=13329;dbname=bdfacturas_mariadb_local',
            $usuario, $clave,
        ],
    };
}

// ======================================================================
// Una función por recurso. Fíjese en el tipo de retorno: siempre LA
// INTERFAZ, nunca la clase concreta — quien las llama no sabe, y no
// necesita saber, con qué motor quedó armado el servicio.
// ======================================================================

function crearServicioProducto(): IServicioProducto
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioProductoPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioProductoSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioProductoMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioProducto($repositorio);
}

function crearServicioEmpresa(): IServicioEmpresa
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioEmpresaPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioEmpresaSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioEmpresaMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioEmpresa($repositorio);
}

function crearServicioPersona(): IServicioPersona
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioPersonaPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioPersonaSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioPersonaMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioPersona($repositorio);
}

function crearServicioCliente(): IServicioCliente
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioClientePostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioClienteSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioClienteMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioCliente($repositorio);
}

function crearServicioVendedor(): IServicioVendedor
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioVendedorPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioVendedorSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioVendedorMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioVendedor($repositorio);
}

function crearServicioFactura(): IServicioFactura
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioFacturaPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioFacturaSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioFacturaMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioFactura($repositorio);
}
