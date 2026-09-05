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
        ],
        'contratos' => 'docs/spec_kit/versiones/v4_sqlserver/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    return;
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
