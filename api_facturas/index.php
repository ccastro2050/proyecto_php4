<?php
/**
 * index.php — el FRONT CONTROLLER de la API Facturas v4.
 *
 * ESTE ARCHIVO NO CAMBIÓ AL AGREGAR EL TERCER MOTOR. Ni una línea: las seis
 * rutas, los seis controladores y los 405 son los mismos de la v2, y el campo
 * `motor` del diagnóstico es el de la v3 (que ahora puede decir un valor más).
 *
 * Lo único que se movió fue el número de versión.
 *
 * TODAS las peticiones entran por aquí (el servidor se arranca con
 * `php -S 0.0.0.0:8090 index.php`). Este archivo hace UNA cosa: mirar el
 * método (GET, POST…) y la ruta, y entregar la petición al método del
 * controlador que corresponde. Nada de SQL, nada de negocio.
 *
 * El recorrido completo de una petición, paso a paso, está explicado en
 * docs/FLUJO_DE_UNA_PETICION.md.
 *
 * ======================================================================
 * SEIS RECURSOS, SEIS BLOQUES DE RUTAS, ESCRITOS UNO POR UNO
 * ======================================================================
 *
 * `/api/producto`, `/api/empresa`, `/api/persona`, `/api/cliente`,
 * `/api/vendedor` y `/api/factura`. Cada uno con su nombre escrito.
 *
 * No hay ninguna ruta `/api/{tabla}` que sirva para todas, y el Artículo 10
 * de la constitución explica por qué con detalle. La versión corta: una ruta
 * genérica no puede decir qué campos lleva cada recurso, ni qué operaciones
 * admite. Y aquí eso importa más que nunca — mire el bloque de `/api/factura`:
 * **no tiene PATCH y sí tiene `/anular`**. Ninguna ruta genérica habría
 * podido expresar eso.
 *
 * Las rutas exactas, con sus formatos, están en el 6_contracts.md de la v2.
 */

// "Modo estricto de tipos": si una función espera int y llega el string "5",
// PHP lanza error en vez de convertirlo en silencio. DEBE ser la primera
// instrucción del archivo. Todos los archivos del proyecto lo llevan.
declare(strict_types=1);

// require_once = "carga este archivo aquí (una sola vez)". __DIR__ es la
// carpeta donde vive ESTE archivo. Esta lista es el inventario del proyecto:
require_once __DIR__ . '/servicios/ensamblador.php';
require_once __DIR__ . '/controladores/ControladorProducto.php';
require_once __DIR__ . '/controladores/ControladorEmpresa.php';
require_once __DIR__ . '/controladores/ControladorPersona.php';
require_once __DIR__ . '/controladores/ControladorCliente.php';
require_once __DIR__ . '/controladores/ControladorVendedor.php';
require_once __DIR__ . '/controladores/ControladorFactura.php';
require_once __DIR__ . '/controladores/ControladorRuta.php';
require_once __DIR__ . '/controladores/ControladorUsuario.php';
require_once __DIR__ . '/controladores/ControladorRutaRol.php';
require_once __DIR__ . '/controladores/ControladorRolUsuario.php';
require_once __DIR__ . '/controladores/ControladorSesion.php';
require_once __DIR__ . '/autorizacion/guardia.php';
require_once __DIR__ . '/controladores/ControladorRol.php';

// Toda respuesta de esta API es JSON — se avisa en el encabezado HTTP:
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------
// 1. CAPTURAR la petición: método, ruta y body
// ----------------------------------------------------------------------

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// El body solo se puede leer UNA vez, por eso se guarda aquí:
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ----------------------------------------------------------------------
// 2. ENRUTAR
// ----------------------------------------------------------------------

// GET / — diagnóstico (sirve para saber si la API está viva)
if ($ruta === '/' && $metodo === 'GET') {
    echo json_encode([
        'mensaje'   => 'API Facturas funcionando',
        'version'   => 'v4',
        // El motor activo se publica en el diagnóstico, y vale la pena
        // explicar para qué: la ruta de versiones promete que el sistema se
        // comporta igual contra los TRES motores, y una promesa así hay que
        // poder COMPROBARLA. Sin este dato, la única forma de saber contra
        // cuál está corriendo la API sería mirar el compose.
        //
        // Es lo único que la API dice del motor. Ningún otro endpoint lo
        // menciona, y ninguna respuesta cambia de forma según cuál sea.
        'motor'     => motorActivo(),
        'recursos'  => [
            '/api/producto', '/api/empresa', '/api/persona',
            '/api/cliente', '/api/vendedor', '/api/factura',
            '/api/ruta', '/api/usuario',
            '/api/rol-usuario',
            '/api/rutarol',
            '/api/rol',
        ],
        'sesion'    => 'POST /api/sesion para entrar; todo lo demas exige '
                     . 'Authorization: Bearer <token>',
        'contratos' => 'docs/spec_kit/versiones/v4_aplicativo/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// ======================================================================
// 2.a  LA PUERTA DE LA CALLE Y LA GUARDIA  (v3)
// ======================================================================
//
// Desde la v3 esta API está CERRADA. Lo que sigue decide quién pasa, y está
// escrito con una idea: **que el olvido sea difícil**.
//
// En .NET la guardia es un atributo sobre la clase del controlador y protege
// todos sus métodos, incluido el que alguien escriba mañana. Aquí no hay
// framework que haga eso, así que en vez de llamar a la guardia bloque por
// bloque —donde olvidarse es un descuido de una línea— se hace al revés:
//
//   1. una LISTA BLANCA con las rutas abiertas, que son dos y tienen por qué;
//   2. UNA línea que exige sesión para todo lo demás, aquí arriba;
//   3. un MAPA de ruta → permiso para el 403 de cada recurso.
//
// Para dejar un endpoint abierto por accidente habría que agregarlo a la
// lista blanca, y eso se ve en el `diff`.

/**
 * Las DOS rutas abiertas, y la razón de cada una.
 *
 *   GET  /              el diagnóstico: un healthcheck que necesita
 *                       credenciales no sirve de healthcheck
 *   POST /api/sesion    la puerta de la calle: no puede exigir el token que
 *                       ella misma entrega
 */
const RUTAS_ABIERTAS = ['/', '/api/sesion'];

/**
 * Qué permiso exige cada recurso. El nombre es el de la tabla `ruta`, letra
 * por letra: si no coincide, `verificar_acceso_ruta` no lo encuentra y NADIE
 * entra (fallar cerrado).
 *
 * Fíjese en que tres recursos comparten `/usuario`: administrar usuarios es
 * UN permiso, aunque sean tres recursos. Si cada uno pidiera el suyo, dar de
 * alta a alguien exigiría tres permisos y nadie se acordaría de los tres.
 */
const PERMISO_POR_RECURSO = [
    'producto'    => '/producto',
    'empresa'     => '/empresa',
    'persona'     => '/persona',
    'cliente'     => '/cliente',
    'vendedor'    => '/vendedor',
    'factura'     => '/factura',
    'rol'         => '/rol',
    'ruta'        => '/ruta',
    'usuario'     => '/usuario',
    'rol-usuario' => '/usuario',
    'rutarol'     => '/permiso',
];

// ----------------------------------------------------------------------
// POST /api/sesion — entrar. VA ANTES DE LA GUARDIA, porque es la puerta.
// ----------------------------------------------------------------------
if ($ruta === '/api/sesion') {
    $controlador = new ControladorSesion(crearServicioSesion());
    if ($metodo === 'POST') {
        $controlador->entrar($body);
    } elseif ($metodo === 'GET') {
        // Los demás verbos de la sesión SÍ exigen token, y hablan de uno
        // mismo: el correo sale del token, no del body ni de la URL.
        $controlador->quienSoy(exigirSesion());
    } elseif ($metodo === 'DELETE') {
        $controlador->salir(exigirSesion());
    } else {
        responderNoPermitido();
    }
    return;
}

if ($ruta === '/api/sesion/renovar') {
    $controlador = new ControladorSesion(crearServicioSesion());
    if ($metodo === 'POST') {
        $controlador->renovar(exigirSesion());
    } else {
        responderNoPermitido();
    }
    return;
}

// ----------------------------------------------------------------------
// GET /api/permisos/mios — las rutas de quien llama, para el menú
// ----------------------------------------------------------------------
if ($ruta === '/api/permisos/mios') {
    $controlador = new ControladorSesion(crearServicioSesion());
    if ($metodo === 'GET') {
        $controlador->misPermisos(exigirSesion());
    } else {
        responderNoPermitido();
    }
    return;
}

// ----------------------------------------------------------------------
// LA GUARDIA, una sola vez, para TODO lo que no esté en la lista blanca
// ----------------------------------------------------------------------
if (!in_array($ruta, RUTAS_ABIERTAS, true)) {
    // El recurso es el segundo segmento: /api/producto/PR001 → 'producto'.
    $segmentos = explode('/', trim($ruta, '/'));
    $recurso = $segmentos[1] ?? '';

    if (isset(PERMISO_POR_RECURSO[$recurso])) {
        // 401 si no hay token; 403 si lo hay y le falta el permiso. Las dos
        // respuestas salen de aquí y cortan la ejecución: después de esta
        // línea, o hay sesión con permiso o la petición ya terminó.
        exigirPermiso(PERMISO_POR_RECURSO[$recurso]);
    } else {
        // Una ruta que no es de ningún recurso conocido: al menos se exige
        // sesión. Si no existe, el 404 del final la atiende — pero no sin
        // identificarse.
        exigirSesion();
    }
}

// ======================================================================
// PRODUCTO — igual que en la v1: la llave la escribe el cliente
// ======================================================================
if ($ruta === '/api/producto') {
    $controlador = new ControladorProducto(crearServicioProducto());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/producto/')) {
    $controlador = new ControladorProducto(crearServicioProducto());
    $codigo = urldecode(substr($ruta, strlen('/api/producto/')));

    if ($metodo === 'GET') {
        $controlador->obtener($codigo);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($codigo, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($codigo, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($codigo);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// EMPRESA — llave de texto, escrita por el cliente
// ======================================================================
if ($ruta === '/api/empresa') {
    $controlador = new ControladorEmpresa(crearServicioEmpresa());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/empresa/')) {
    $controlador = new ControladorEmpresa(crearServicioEmpresa());
    $codigo = urldecode(substr($ruta, strlen('/api/empresa/')));

    if ($metodo === 'GET') {
        $controlador->obtener($codigo);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($codigo, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($codigo, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($codigo);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// PERSONA — llave de texto, escrita por el cliente
// ======================================================================
if ($ruta === '/api/persona') {
    $controlador = new ControladorPersona(crearServicioPersona());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/persona/')) {
    $controlador = new ControladorPersona(crearServicioPersona());
    $codigo = urldecode(substr($ruta, strlen('/api/persona/')));

    if ($metodo === 'GET') {
        $controlador->obtener($codigo);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($codigo, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($codigo, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($codigo);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// CLIENTE — llave NUMÉRICA que genera la base
//
// Fíjese en el `(int)`: la ruta trae "/api/cliente/3" y de ahí sale el texto
// "3". El controlador espera un entero, así que la conversión se hace aquí,
// en la frontera, igual que con el ?limite. Un "/api/cliente/abc" se vuelve
// 0, y el servicio lo rechaza con un 400 — que es lo correcto: no es que no
// se haya encontrado, es que eso no es un identificador.
// ======================================================================
if ($ruta === '/api/cliente') {
    $controlador = new ControladorCliente(crearServicioCliente());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/cliente/')) {
    $controlador = new ControladorCliente(crearServicioCliente());
    $id = (int) substr($ruta, strlen('/api/cliente/'));

    if ($metodo === 'GET') {
        $controlador->obtener($id);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($id, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($id, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($id);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// VENDEDOR — llave numérica, igual que cliente
// ======================================================================
if ($ruta === '/api/vendedor') {
    $controlador = new ControladorVendedor(crearServicioVendedor());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/vendedor/')) {
    $controlador = new ControladorVendedor(crearServicioVendedor());
    $id = (int) substr($ruta, strlen('/api/vendedor/'));

    if ($metodo === 'GET') {
        $controlador->obtener($id);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($id, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($id, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($id);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// ROL — la llave la GENERA la base de datos: el POST no la lleva
// ======================================================================
if ($ruta === '/api/rol') {
    $controlador = new ControladorRol(crearServicioRol());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/rol/')) {
    $controlador = new ControladorRol(crearServicioRol());
    // La llave viaja en la URL como texto y aqui se convierte: la frontera
    // es el unico sitio donde se hace esa conversion.
    $llave = (int) urldecode(substr($ruta, strlen('/api/rol/')));

    if ($metodo === 'GET') {
        $controlador->obtener($llave);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($llave, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($llave, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($llave);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// RUTA — la llave la GENERA la base de datos: el POST no la lleva
// ======================================================================
if ($ruta === '/api/ruta') {
    $controlador = new ControladorRuta(crearServicioRuta());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/ruta/')) {
    $controlador = new ControladorRuta(crearServicioRuta());
    // La llave viaja en la URL como texto y aqui se convierte: la frontera
    // es el unico sitio donde se hace esa conversion.
    $llave = (int) urldecode(substr($ruta, strlen('/api/ruta/')));

    if ($metodo === 'GET') {
        $controlador->obtener($llave);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($llave, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($llave, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($llave);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// USUARIO — la llave es el EMAIL, un texto que el cliente conoce
// ======================================================================
//
// OJO AL ORDEN DE ESTOS TRES BLOQUES, porque es el tropiezo del recurso: el
// enrutador compara de arriba abajo, y `str_starts_with('/api/usuario/')`
// tambien casa con `/api/usuario/verificar-contrasena`. Si el bloque de la
// llave fuera primero, esa peticion acabaria buscando un usuario llamado
// «verificar-contrasena» y devolveria 404 — un 404 que cuesta encontrar,
// porque el endpoint existe.
//
// LA RUTA FIJA VA ANTES QUE LA RUTA CON PARAMETRO. Siempre.
if ($ruta === '/api/usuario/verificar-contrasena') {
    $controlador = new ControladorUsuario(crearServicioUsuario());
    if ($metodo === 'POST') {
        // Las credenciales van EN EL CUERPO, no en la URL: una contrasena en
        // la URL queda en el historial del navegador y en los registros de
        // cualquier proxy del camino.
        $controlador->verificarContrasena($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if ($ruta === '/api/usuario') {
    $controlador = new ControladorUsuario(crearServicioUsuario());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/usuario/')) {
    $controlador = new ControladorUsuario(crearServicioUsuario());
    // urldecode porque el email viaja con @ y puntos: el navegador los
    // escapa y aqui se devuelven a su forma.
    $email = urldecode(substr($ruta, strlen('/api/usuario/')));

    if ($metodo === 'GET') {
        $controlador->obtener($email);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($email, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($email, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($email);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// RUTAROL — tabla PUENTE: CINCO endpoints, y no son los cinco verbos
// ======================================================================
//
// Las dos columnas SON la llave, asi que una pareja existe o no existe. De
// ahi que no haya PUT ni PATCH: lo que seria «actualizar» es MOVER la fila
// —borrar una pareja e insertar otra—, y eso ya se hace con el DELETE y el
// POST de abajo.
//
// LOS DOS VERBOS APAGADOS, escritos para que se vea COMO serian:
//
//   } elseif ($metodo === 'PUT') {
//       // Mover la pareja: recibiria la nueva ENTERA en el body, borraria
//       // la vieja e insertaria la nueva — en UNA transaccion, porque si
//       // el INSERT falla el DELETE no puede quedarse hecho.
//       $controlador->reemplazar($a, $b, $body);
//   } elseif ($metodo === 'PATCH') {
//       // Mover UN lado y conservar el otro: {"fkidrol": 3} le pasaria
//       // esta fila a otro rol.
//       $controlador->actualizar($a, $b, $body);
//
// Estan apagados porque el contrato declara cinco endpoints y estos no son
// dos de ellos. Se dejan escritos porque hay dos cosas que aprender: como se
// programa el verbo, y que una API NO lleva todos los verbos en todos los
// recursos.
if ($ruta === '/api/rutarol') {
    $controlador = new ControladorRutaRol(crearServicioRutaRol());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

// Las DOS busquedas por lado. Van ANTES del bloque de la pareja porque
// `/api/rutarol/ruta/...` tambien casaria con el patron de dos
// segmentos, y el enrutador compara por orden.
if (str_starts_with($ruta, '/api/rutarol/ruta/')) {
    $controlador = new ControladorRutaRol(crearServicioRutaRol());
    $lado = (int) urldecode(substr($ruta, strlen('/api/rutarol/ruta/')));
    if ($metodo === 'GET') {
        $controlador->listarPorLadoA($lado);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/rutarol/rol/')) {
    $controlador = new ControladorRutaRol(crearServicioRutaRol());
    $lado = (int) urldecode(substr($ruta, strlen('/api/rutarol/rol/')));
    if ($metodo === 'GET') {
        $controlador->listarPorLadoB($lado);
    } else {
        responderNoPermitido();
    }
    return;
}

// La pareja: DOS segmentos, y los dos hacen falta para identificarla.
if (preg_match('#^/api/rutarol/([^/]+)/([^/]+)$#', $ruta, $partes)) {
    $controlador = new ControladorRutaRol(crearServicioRutaRol());
    $a = (int) urldecode($partes[1]);
    $b = (int) urldecode($partes[2]);

    if ($metodo === 'DELETE') {
        $controlador->eliminar($a, $b);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// ROL_USUARIO — tabla PUENTE: CINCO endpoints, y no son los cinco verbos
// ======================================================================
//
// Las dos columnas SON la llave, asi que una pareja existe o no existe. De
// ahi que no haya PUT ni PATCH: lo que seria «actualizar» es MOVER la fila
// —borrar una pareja e insertar otra—, y eso ya se hace con el DELETE y el
// POST de abajo.
//
// LOS DOS VERBOS APAGADOS, escritos para que se vea COMO serian:
//
//   } elseif ($metodo === 'PUT') {
//       // Mover la pareja: recibiria la nueva ENTERA en el body, borraria
//       // la vieja e insertaria la nueva — en UNA transaccion, porque si
//       // el INSERT falla el DELETE no puede quedarse hecho.
//       $controlador->reemplazar($a, $b, $body);
//   } elseif ($metodo === 'PATCH') {
//       // Mover UN lado y conservar el otro: {"fkidrol": 3} le pasaria
//       // esta fila a otro rol.
//       $controlador->actualizar($a, $b, $body);
//
// Estan apagados porque el contrato declara cinco endpoints y estos no son
// dos de ellos. Se dejan escritos porque hay dos cosas que aprender: como se
// programa el verbo, y que una API NO lleva todos los verbos en todos los
// recursos.
if ($ruta === '/api/rol-usuario') {
    $controlador = new ControladorRolUsuario(crearServicioRolUsuario());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

// Las DOS busquedas por lado. Van ANTES del bloque de la pareja porque
// `/api/rol-usuario/usuario/...` tambien casaria con el patron de dos
// segmentos, y el enrutador compara por orden.
if (str_starts_with($ruta, '/api/rol-usuario/usuario/')) {
    $controlador = new ControladorRolUsuario(crearServicioRolUsuario());
    $lado = urldecode(substr($ruta, strlen('/api/rol-usuario/usuario/')));
    if ($metodo === 'GET') {
        $controlador->listarPorLadoA($lado);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/rol-usuario/rol/')) {
    $controlador = new ControladorRolUsuario(crearServicioRolUsuario());
    $lado = (int) urldecode(substr($ruta, strlen('/api/rol-usuario/rol/')));
    if ($metodo === 'GET') {
        $controlador->listarPorLadoB($lado);
    } else {
        responderNoPermitido();
    }
    return;
}

// La pareja: DOS segmentos, y los dos hacen falta para identificarla.
if (preg_match('#^/api/rol-usuario/([^/]+)/([^/]+)$#', $ruta, $partes)) {
    $controlador = new ControladorRolUsuario(crearServicioRolUsuario());
    $a = urldecode($partes[1]);
    $b = (int) urldecode($partes[2]);

    if ($metodo === 'DELETE') {
        $controlador->eliminar($a, $b);
    } else {
        responderNoPermitido();
    }
    return;
}

// ======================================================================
// FACTURA — el recurso que NO es un CRUD
//
// Mire las diferencias con los bloques de arriba, porque son la lección:
//   · no hay PATCH — el detalle se reemplaza entero o no se toca;
//   · hay una ruta de más, `/anular`, que no existe en ningún otro recurso.
//
// Una ruta genérica `/api/{tabla}` no habría podido expresar ninguna de las
// dos cosas: habría dado PATCH donde no aplica y no habría dado `/anular`
// donde sí hace falta.
// ======================================================================
if ($ruta === '/api/factura') {
    $controlador = new ControladorFactura(crearServicioFactura());
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();
    }
    return;
}

// La ruta de anular va ANTES que la de "/api/factura/{numero}", porque
// "/api/factura/3/anular" también empieza por "/api/factura/": si se
// evaluara al revés, el número quedaría "3/anular" y nunca se llegaría aquí.
// El orden de los `if` de un enrutador es parte de su significado.
if (preg_match('#^/api/factura/(\d+)/anular$#', $ruta, $coincidencias)) {
    $controlador = new ControladorFactura(crearServicioFactura());
    if ($metodo === 'POST') {
        $controlador->anular((int) $coincidencias[1]);
    } else {
        responderNoPermitido();
    }
    return;
}

if (str_starts_with($ruta, '/api/factura/')) {
    $controlador = new ControladorFactura(crearServicioFactura());
    $numero = (int) substr($ruta, strlen('/api/factura/'));

    if ($metodo === 'GET') {
        $controlador->obtener($numero);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($numero, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($numero);
    } else {
        // PATCH cae aquí y responde 405. No es un descuido: es el
        // enrutador diciendo que ese verbo no aplica a este recurso.
        responderNoPermitido();
    }
    return;
}

// Ninguna ruta coincidió: 404 de RUTA
// (distinto del 404 de "esa ficha no existe", que decide el servicio).
http_response_code(404);
echo json_encode([
    'estado' => 404, 'mensaje' => 'Ruta no encontrada.', 'detalle' => "$metodo $ruta",
], JSON_UNESCAPED_UNICODE);

// ----------------------------------------------------------------------
// Función de apoyo del enrutador. ": void" declara que no devuelve nada.
function responderNoPermitido(): void
{
    // 405 = "la ruta existe, pero no con ese método"
    http_response_code(405);
    echo json_encode([
        'estado' => 405, 'mensaje' => 'Método no permitido para esta ruta.',
    ], JSON_UNESCAPED_UNICODE);
}
