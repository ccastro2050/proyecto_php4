<?php
/**
 * Las dos puertas: **401** y **403**.
 *
 * En .NET son dos atributos sobre el controlador; en FastAPI, dos
 * dependencias. En PHP sin framework son **dos funciones que el enrutador
 * llama antes de entregar la petición**, y la idea es la misma — el
 * mecanismo cambia con la herramienta, la decisión no.
 *
 *     exigirSesion()   →  401  «no sé quién es usted»
 *     exigirPermiso()  →  403  «sé quién es, y no puede»
 *
 * ======================================================================
 * TRES DECISIONES QUE VALE LA PENA LEER DOS VECES
 * ======================================================================
 *
 * 1. **El permiso se consulta en CADA petición**, no al entrar. Cuesta una
 *    consulta por operación, y es lo que hace que quitar un permiso surta
 *    efecto sin esperar a que el token venza.
 *
 * 2. **403, no 401.** El token es válido y se sabe perfectamente quién
 *    pregunta: lo que falta es el permiso. 401 significa «no sé quién es
 *    usted»; 403, «sé quién es, y no puede».
 *
 * 3. **La guardia corta la ejecución.** Las dos funciones responden y hacen
 *    `exit`: si solo devolvieran `false`, el día que alguien olvide mirar el
 *    resultado el endpoint queda abierto — y nadie lo nota, porque funciona.
 *    Aquí no hay forma de olvidarlo: después de la guardia, o hay sesión o
 *    la petición ya terminó.
 *
 * ======================================================================
 * Y LA LIMITACIÓN DE HACERLO ASÍ, DICHA SIN MAQUILLAJE
 * ======================================================================
 *
 * En .NET el filtro va sobre la CLASE del controlador y protege todos sus
 * métodos, incluido el que alguien escriba mañana. Aquí la guardia se llama
 * en el enrutador, **bloque por bloque**: un bloque nuevo sin la llamada
 * queda abierto.
 *
 * Por eso el enrutador tiene una **lista blanca** —`RUTAS_ABIERTAS`— y una
 * sola línea que exige sesión para todo lo demás: el olvido tendría que ser
 * agregar la ruta a la lista blanca, que es un acto deliberado y se ve en el
 * `diff`.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../servicios/ensamblador.php';

/**
 * Responde un error con el MISMO sobre que usan los controladores y termina.
 *
 * `exit` dentro de una función que responde no es elegante y es correcto: lo
 * que sigue a una guardia que falló no debe ejecutarse nunca.
 */
function responderYSalir(int $estado, string $mensaje, string $detalle = ''): never
{
    http_response_code($estado);
    if ($estado === 401) {
        // Lo que la norma pide en un 401: decirle al cliente CÓMO
        // identificarse, no solo que no lo hizo.
        header('WWW-Authenticate: Bearer');
    }
    $cuerpo = ['estado' => $estado, 'mensaje' => $mensaje];
    if ($detalle !== '') {
        $cuerpo['detalle'] = $detalle;
    }
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * El token que llegó en la cabecera, o null.
 *
 * **Ojo con `Authorization` en PHP:** el servidor embebido y Apache la
 * entregan en sitios distintos. `getallheaders()` la trae en los dos, y
 * `HTTP_AUTHORIZATION` queda como respaldo — hay montajes de Apache que no
 * la pasan a PHP a menos que se le diga, y eso produce un 401 desconcertante
 * «sin motivo».
 */
function tokenDeLaCabecera(): ?string
{
    $cabeceras = function_exists('getallheaders') ? getallheaders() : [];
    $valor = '';
    foreach ($cabeceras as $nombre => $contenido) {
        if (strtolower((string) $nombre) === 'authorization') {
            $valor = (string) $contenido;
            break;
        }
    }
    if ($valor === '') {
        $valor = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    }
    if (!str_starts_with(strtolower($valor), 'bearer ')) {
        return null;
    }
    return trim(substr($valor, 7));
}

/**
 * Exige un token válido y devuelve quién llama: `['email' => …, 'roles' => …]`.
 *
 * El correo sale SIEMPRE del token, nunca del body ni de la URL. Si se leyera
 * del body, cualquiera podría escribir el correo de otro y operar en su
 * nombre: el token es la única fuente que la API misma firmó.
 */
function exigirSesion(): array
{
    $token = tokenDeLaCabecera();
    if ($token === null) {
        responderYSalir(401, 'No hay una sesion valida. Inicie sesion.',
                        'Falta la cabecera Authorization: Bearer <token>.');
    }

    try {
        $datos = jwt_leer($token);
    } catch (InvalidArgumentException $e) {
        // Vencido, alterado, mal formado o firmado con otra clave: los
        // cuatro son el mismo 401.
        responderYSalir(401, 'No hay una sesion valida. Inicie sesion.',
                        $e->getMessage());
    }

    $email = (string) ($datos['sub'] ?? '');
    if ($email === '') {
        responderYSalir(401, 'No hay una sesion valida. Inicie sesion.',
                        'El token no dice de quien es.');
    }

    return ['email' => $email, 'roles' => $datos['roles'] ?? []];
}

/**
 * Exige el permiso de UNA ruta. Devuelve quién llama si lo tiene; si no,
 * responde 403 y termina.
 *
 * El nombre que se le pasa es el de la tabla `ruta` —`/producto`, `/factura`,
 * `/home`— letra por letra. Si no coincide, `verificar_acceso_ruta` no
 * encuentra la ruta y **nadie entra**: fallar cerrado, no abierto.
 */
function exigirPermiso(string $nombreRuta): array
{
    // Primero el 401: no se puede preguntar por los permisos de alguien que
    // no se ha identificado.
    $quien = exigirSesion();

    $acceso = crearRepositorioAcceso();
    if (!$acceso->tieneAcceso($quien['email'], $nombreRuta)) {
        responderYSalir(403, 'Su rol no tiene permiso para esta operacion.',
                        "El usuario {$quien['email']} no tiene acceso a $nombreRuta.");
    }

    return $quien;
}
