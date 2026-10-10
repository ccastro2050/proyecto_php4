<?php
/**
 * cliente_api.php — la capa de DATOS del front.
 *
 * Es al front lo que el repositorio es a la API: la ÚNICA pieza que sabe
 * dónde viven los datos —en la API, nunca en la base— y la única que habla
 * HTTP.
 *
 * ======================================================================
 * LO QUE ESTE ARCHIVO NO HACE, Y ES LO MÁS IMPORTANTE
 * ======================================================================
 *
 * No abre ninguna conexión a MariaDB. No hay `new PDO(...)` en todo el front,
 * y no lo va a haber: es el Artículo de la constitución que dice que el front
 * no toca la base.
 *
 * Y hay una tentación que en este proyecto es MAYOR que en otros, porque el
 * front y la API están **los dos en PHP**: bastaría un
 *
 *     require_once __DIR__ . '/../api_facturas/modelos/Cliente.php';
 *
 * para usar aquí las clases de la API. Funcionaría. Y estaría mal: los dos
 * dejarían de ser procesos independientes, y renombrar un método dentro de la
 * API rompería el front **sin que nadie tocara el contrato**. Lo único que
 * comparten es el JSON.
 *
 * Por eso este archivo trabaja con **arrays**, no con objetos de la API.
 *
 * ======================================================================
 * SEIS RECURSOS, TREINTA Y TANTAS FUNCIONES, TODAS CON SU NOMBRE
 * ======================================================================
 *
 * `listar_productos`, `crear_cliente`, `anular_factura`… no una
 * `listar($recurso)` genérica. Es el Artículo 10 de la constitución aplicado
 * del lado del front, y en la v2 se nota más que en la v1:
 *
 *   · las facturas **no tienen** `actualizar_parcial_factura`, porque el
 *     recurso no admite PATCH;
 *   · y tienen `anular_factura`, que ningún otro recurso tiene.
 *
 * Una función genérica habría ofrecido las seis operaciones para todo, y
 * habría mentido en los dos casos.
 *
 * ======================================================================
 * QUÉ DEVUELVE CADA FUNCIÓN
 * ======================================================================
 *
 * Siempre un array con la misma forma, para que las vistas no tengan que
 * saber qué es un 409:
 *
 *     ['ok' => bool, 'datos' => mixed, 'errores' => string[]]
 */

declare(strict_types=1);

// La dirección de la API. Dentro de Docker la manda el compose con el NOMBRE
// del servicio; fuera vale el valor de la derecha. Nunca 'localhost' dentro de
// un contenedor: ahí localhost es el contenedor mismo.
define('URL_API', getenv('URL_API') ?: 'http://localhost:8090');

const TIEMPO_MAXIMO = 10;   // segundos

/**
 * Hace la petición HTTP y unifica un solo caso: que la API no responda.
 *
 * Devuelve null cuando NO hubo respuesta —API caída, tiempo agotado—, que es
 * distinto de «respondió con un error». Un 404 es la API funcionando y
 * diciendo que esa ficha no existe; un null es que no hay con quién hablar.
 *
 * @return array{codigo:int, cuerpo:array}|null
 */
function llamar_api(string $metodo, string $ruta, ?array $cuerpo = null): ?array
{
    $curl = curl_init(URL_API . $ruta);

    // ------------------------------------------------------------------
    // EL TOKEN VA EN TODAS LAS PETICIONES (v3)
    // ------------------------------------------------------------------
    //
    // Desde la v3 la API esta cerrada: sin esta cabecera responde 401 a todo
    // menos al diagnostico y a la puerta de entrada. Se pone AQUI, en el
    // unico sitio que habla HTTP, y no en cada una de las cuarenta funciones
    // de abajo: si estuviera en cada una, la que se olvide es la que falla.
    //
    // Y se pone solo SI HAY token. Las dos rutas abiertas de la API —el
    // diagnostico y `POST /api/sesion`— se piden sin sesion, y mandarles una
    // cabecera vacia seria peor que no mandar nada.
    $cabeceras = ['Content-Type: application/json'];
    $token = $_SESSION['token'] ?? null;
    if (is_string($token) && $token !== '') {
        $cabeceras[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,          // devolver la respuesta, no imprimirla
        CURLOPT_CUSTOMREQUEST  => $metodo,       // GET, POST, PUT, PATCH, DELETE
        CURLOPT_TIMEOUT        => TIEMPO_MAXIMO,
        CURLOPT_HTTPHEADER     => $cabeceras,
    ]);

    if ($cuerpo !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    }

    $respuesta = curl_exec($curl);
    $codigo    = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $fallo     = curl_errno($curl);
    curl_close($curl);

    if ($fallo !== 0) {
        return null;                              // no hubo con quién hablar
    }

    // El 204 no trae cuerpo, y eso NO es un error: es «no hay filas».
    return ['codigo' => $codigo,
            'cuerpo' => json_decode((string) $respuesta, true) ?? []];
}

/**
 * Traduce a texto los errores que produce ESTA API.
 *
 * El sobre es plano y tiene dos formas:
 *   ['estado' => …, 'mensaje' => …, 'detalle' => …]     → 400, 404, 409, 500
 *   ['estado' => …, 'mensaje' => …, 'errores' => [...]] → cuando el cuerpo no cumple
 *
 * **Esta función es el único sitio del front que conoce ese formato.** Si
 * mañana la API cambia el sobre, se cambia aquí y en ninguna vista.
 *
 * Fíjese en lo que NO hace: no distingue un 409 de un 500. Para la persona
 * que está usando la pantalla, lo que importa es el texto —«no se puede
 * eliminar la persona porque hay otras fichas que dependen de ella»—, no el
 * número. El número le importa a quien programa, y está en el contrato.
 *
 * @return string[]
 */
function mensajes_de_error(array $cuerpo): array
{
    if (isset($cuerpo['errores']) && is_array($cuerpo['errores']) && $cuerpo['errores'] !== []) {
        return array_values(array_map('strval', $cuerpo['errores']));
    }

    $partes = array_filter([
        $cuerpo['mensaje'] ?? '',
        $cuerpo['detalle'] ?? '',
    ], static fn ($t) => trim((string) $t) !== '');

    return $partes === []
        ? ['No se pudo completar la operación.']
        : array_values(array_map('strval', $partes));
}

const NO_DISPONIBLE = ['El servicio no está disponible. ¿Está arriba la API?'];

/** Lo común a las lecturas de UNA ficha. */
function resultado_de_lectura(?array $r): array
{
    if ($r === null) {
        return ['ok' => false, 'datos' => null, 'errores' => NO_DISPONIBLE];
    }
    return $r['codigo'] === 200
        ? ['ok' => true, 'datos' => $r['cuerpo'], 'errores' => []]
        : ['ok' => false, 'datos' => null, 'errores' => mensajes_de_error($r['cuerpo'])];
}

/** Lo común a las operaciones que ESCRIBEN. */
function resultado_de(?array $r): array
{
    if ($r === null) {
        return ['ok' => false, 'datos' => null, 'errores' => NO_DISPONIBLE];
    }
    // El 201 se suma en la v3: los recursos nuevos —rol, ruta, usuario— sí
    // responden «creado» con su código propio, mientras los seis de la v2
    // responden 200. Dos códigos para la misma idea es una inconsistencia del
    // contrato, y está anotada en PENDIENTES; el front no la puede arreglar,
    // pero sí tiene que aceptar los dos o mostraría un error donde hubo éxito.
    return in_array($r['codigo'], [200, 201], true)
        ? ['ok' => true, 'datos' => $r['cuerpo'], 'errores' => []]
        : ['ok' => false, 'datos' => null, 'errores' => mensajes_de_error($r['cuerpo'])];
}

/** Lo común a los listados: el 204 es «no hay ninguno», y NO es un error. */
function resultado_de_listado(?array $r): array
{
    if ($r === null) {
        return ['ok' => false, 'datos' => [], 'errores' => NO_DISPONIBLE];
    }
    if ($r['codigo'] === 204) {
        return ['ok' => true, 'datos' => [], 'errores' => []];
    }
    if ($r['codigo'] === 200) {
        return ['ok' => true, 'datos' => $r['cuerpo']['datos'] ?? [], 'errores' => []];
    }
    return ['ok' => false, 'datos' => [], 'errores' => mensajes_de_error($r['cuerpo'])];
}

// ======================================================================
// EL DIAGNÓSTICO  —  GET /
//
// Nuevo en la v3, y por una razón concreta: la versión promete que el sistema
// se comporta igual contra los dos motores, y una promesa así hay que poder
// COMPROBARLA sin mirar el compose. La pantalla lo muestra en el pie.
//
// Fíjese en lo que el front NO hace con ese dato: **nada**. No cambia una
// consulta, no oculta un botón, no toma ninguna decisión. Solo lo enseña. El
// día que el front empiece a preguntar «¿estoy contra PostgreSQL?» para hacer
// algo distinto, la separación de capas se rompió.
// ======================================================================

/** Qué dice la API de sí misma: su versión y el motor que tiene detrás. */
function diagnostico(): array
{
    return resultado_de_lectura(llamar_api('GET', '/'));
}

// ======================================================================
// PRODUCTOS  —  /api/producto
// ======================================================================

/** Lista los productos. */
function listar_productos(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/producto?limite=$limite"));
}

/** Trae un producto por su llave. */
function obtener_producto(string $codigo): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/producto/' . rawurlencode((string) $codigo)));
}

/** Crea un producto. */
function crear_producto(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/producto', $datos));
}

/** Reemplaza la ficha completa: «guardar la ficha completa». */
function reemplazar_producto(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PUT', '/api/producto/' . rawurlencode((string) $codigo), $datos));
}

/** Actualiza solo lo enviado: «guardar solo lo que cambié». */
function actualizar_producto(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PATCH', '/api/producto/' . rawurlencode((string) $codigo), $datos));
}

/** Elimina el producto. */
function eliminar_producto(string $codigo): array
{
    return resultado_de(llamar_api('DELETE', '/api/producto/' . rawurlencode((string) $codigo)));
}

// ======================================================================
// EMPRESAS  —  /api/empresa
// ======================================================================

/** Lista las empresas. */
function listar_empresas(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/empresa?limite=$limite"));
}

/** Trae una empresa por su llave. */
function obtener_empresa(string $codigo): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/empresa/' . rawurlencode((string) $codigo)));
}

/** Crea una empresa. */
function crear_empresa(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/empresa', $datos));
}

/** Reemplaza la ficha completa: «guardar la ficha completa». */
function reemplazar_empresa(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PUT', '/api/empresa/' . rawurlencode((string) $codigo), $datos));
}

/** Actualiza solo lo enviado: «guardar solo lo que cambié». */
function actualizar_empresa(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PATCH', '/api/empresa/' . rawurlencode((string) $codigo), $datos));
}

/** Elimina la empresa. */
function eliminar_empresa(string $codigo): array
{
    return resultado_de(llamar_api('DELETE', '/api/empresa/' . rawurlencode((string) $codigo)));
}

// ======================================================================
// PERSONAS  —  /api/persona
// ======================================================================

/** Lista las personas. */
function listar_personas(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/persona?limite=$limite"));
}

/** Trae una persona por su llave. */
function obtener_persona(string $codigo): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/persona/' . rawurlencode((string) $codigo)));
}

/** Crea una persona. */
function crear_persona(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/persona', $datos));
}

/** Reemplaza la ficha completa: «guardar la ficha completa». */
function reemplazar_persona(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PUT', '/api/persona/' . rawurlencode((string) $codigo), $datos));
}

/** Actualiza solo lo enviado: «guardar solo lo que cambié». */
function actualizar_persona(string $codigo, array $datos): array
{
    return resultado_de(llamar_api('PATCH', '/api/persona/' . rawurlencode((string) $codigo), $datos));
}

/** Elimina la persona. */
function eliminar_persona(string $codigo): array
{
    return resultado_de(llamar_api('DELETE', '/api/persona/' . rawurlencode((string) $codigo)));
}

// ======================================================================
// CLIENTES  —  /api/cliente
// ======================================================================

/** Lista los clientes. */
function listar_clientes(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/cliente?limite=$limite"));
}

/** Trae un cliente por su llave. */
function obtener_cliente(int $id): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/cliente/' . rawurlencode((string) $id)));
}

/** Crea un cliente. */
function crear_cliente(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/cliente', $datos));
}

/** Reemplaza la ficha completa: «guardar la ficha completa». */
function reemplazar_cliente(int $id, array $datos): array
{
    return resultado_de(llamar_api('PUT', '/api/cliente/' . rawurlencode((string) $id), $datos));
}

/** Actualiza solo lo enviado: «guardar solo lo que cambié». */
function actualizar_cliente(int $id, array $datos): array
{
    return resultado_de(llamar_api('PATCH', '/api/cliente/' . rawurlencode((string) $id), $datos));
}

/** Elimina el cliente. */
function eliminar_cliente(int $id): array
{
    return resultado_de(llamar_api('DELETE', '/api/cliente/' . rawurlencode((string) $id)));
}

// ======================================================================
// VENDEDORES  —  /api/vendedor
// ======================================================================

/** Lista los vendedores. */
function listar_vendedores(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/vendedor?limite=$limite"));
}

/** Trae un vendedor por su llave. */
function obtener_vendedor(int $id): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/vendedor/' . rawurlencode((string) $id)));
}

/** Crea un vendedor. */
function crear_vendedor(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/vendedor', $datos));
}

/** Reemplaza la ficha completa: «guardar la ficha completa». */
function reemplazar_vendedor(int $id, array $datos): array
{
    return resultado_de(llamar_api('PUT', '/api/vendedor/' . rawurlencode((string) $id), $datos));
}

/** Actualiza solo lo enviado: «guardar solo lo que cambié». */
function actualizar_vendedor(int $id, array $datos): array
{
    return resultado_de(llamar_api('PATCH', '/api/vendedor/' . rawurlencode((string) $id), $datos));
}

/** Elimina el vendedor. */
function eliminar_vendedor(int $id): array
{
    return resultado_de(llamar_api('DELETE', '/api/vendedor/' . rawurlencode((string) $id)));
}

// ======================================================================
// FACTURAS  —  /api/factura
//
// Cinco funciones, no seis, y una de ellas no existe en ningún otro recurso.
// La lista de funciones de este bloque ES el contrato de las facturas:
//
//   · NO hay `actualizar_factura` (PATCH): el detalle se reemplaza entero.
//     Ofrecer la función y que la API respondiera 405 sería peor que no
//     tenerla — el front prometería algo que no puede cumplir.
//   · SÍ hay `anular_factura`, que no es eliminar: la factura se queda con
//     su número y su fecha, marcada como anulada, y el stock vuelve.
// ======================================================================

/** Lista las facturas, cada una con su detalle adentro. */
function listar_facturas(): array
{
    // Sin ?limite: este recurso no lo acepta (el procedimiento almacenado que
    // lo resuelve no lo recibe). Mandarlo de todos modos sería ruido.
    return resultado_de_listado(llamar_api('GET', '/api/factura'));
}

/** Trae una factura con su detalle. */
function obtener_factura(int $numero): array
{
    return resultado_de_lectura(llamar_api('GET', "/api/factura/$numero"));
}

/**
 * Crea la factura y sus renglones de una sola vez.
 *
 * $datos = ['fkidcliente' => 1, 'fkidvendedor' => 2,
 *           'detalle' => [['codigo' => 'PR001', 'cantidad' => 2], ...]]
 */
function crear_factura(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/factura', $datos));
}

/** Reemplaza cliente, vendedor y TODO el detalle. */
function reemplazar_factura(int $numero, array $datos): array
{
    return resultado_de(llamar_api('PUT', "/api/factura/$numero", $datos));
}

/** Anula: la factura queda marcada y el stock vuelve. NO la borra. */
function anular_factura(int $numero): array
{
    return resultado_de(llamar_api('POST', "/api/factura/$numero/anular"));
}

/** Borra la factura y su detalle, de verdad. */
function eliminar_factura(int $numero): array
{
    return resultado_de(llamar_api('DELETE', "/api/factura/$numero"));
}

// ======================================================================
// LA SESION  —  /api/sesion   (v3)
// ======================================================================
//
// DONDE SE GUARDA EL TOKEN, y es la decision que hay que poder defender: en
// `$_SESSION`, que vive EN EL SERVIDOR. Al navegador solo le llega el
// identificador de sesion de PHP, en una cookie que el propio PHP marca como
// HttpOnly —JavaScript no la puede leer—.
//
// La alternativa, guardarlo en `localStorage` como hace un front de JS, deja
// el token al alcance de cualquier script que entre en la pagina. Aqui ni
// siquiera es una tentacion: **este front no tiene JavaScript que pida
// datos.** El token nunca sale del servidor.
//
// Y HAY UNA COSA QUE EL FRONT NO GUARDA: el permiso. Guarda la LISTA para
// pintar el menu, y nada mas. Cada peticion se vuelve a comprobar en la API
// —que consulta `verificar_acceso_ruta` en la base de datos—, asi que quitarle
// un rol a alguien tiene efecto en la peticion siguiente, no cuando caduque
// su token.
// ======================================================================

/**
 * Entra: cambia correo y contrasena por un token.
 *
 * Es la UNICA funcion del front que manda una contrasena, y viaja en el
 * cuerpo de un POST —nunca en la URL—: una contrasena en la direccion queda
 * en el historial del navegador, en los registros del servidor y en el
 * «Referer» de la peticion siguiente.
 */
function entrar(string $email, string $contrasena): array
{
    $r = llamar_api('POST', '/api/sesion',
                    ['email' => $email, 'contrasena' => $contrasena]);

    if ($r === null) {
        return ['ok' => false, 'datos' => null, 'errores' => NO_DISPONIBLE];
    }
    if ($r['codigo'] !== 200) {
        // El 401 de aqui dice «el correo o la contrasena no son correctos», sin
        // decir CUAL de los dos falla. No es un descuido del mensaje: decir
        // «ese correo no existe» le confirma a quien prueba al azar que los
        // demas si existen.
        return ['ok' => false, 'datos' => null,
                'errores' => mensajes_de_error($r['cuerpo'])];
    }

    // La sesion se REGENERA al entrar: el identificador de sesion que trajo
    // el navegador antes de identificarse se tira. Sin esto, alguien que
    // hubiera conseguido fijar ese identificador seguiria dentro de la sesion
    // ya autenticada —se llama «fijacion de sesion», y la defensa es esta
    // linea—.
    session_regenerate_id(true);

    $_SESSION['token']   = $r['cuerpo']['token'];
    $_SESSION['email']   = $r['cuerpo']['email'];
    $_SESSION['roles']   = $r['cuerpo']['roles'] ?? [];
    $_SESSION['expira']  = $r['cuerpo']['expira'] ?? null;
    $_SESSION['permisos'] = permisos_desde_la_api();

    return ['ok' => true, 'datos' => $r['cuerpo'], 'errores' => []];
}

/** Las rutas a las que llega quien esta dentro. Para el MENU, nada mas. */
function permisos_desde_la_api(): array
{
    $r = llamar_api('GET', '/api/permisos/mios');
    if ($r === null || $r['codigo'] !== 200) {
        return [];
    }
    return $r['cuerpo']['datos'] ?? [];
}

/** Sale: le dice a la API y borra la sesion de PHP. */
function salir(): void
{
    // Se avisa a la API —que con un token firmado no puede hacer gran cosa,
    // y por eso el aviso es cortesia y no seguridad: un JWT no se puede
    // «apagar» desde el servidor sin una lista negra—. Lo que de verdad
    // cierra la sesion es borrar el token de aqui.
    llamar_api('DELETE', '/api/sesion');

    $_SESSION = [];
    session_destroy();
}

/** Quien esta dentro, segun la propia API (no segun lo que guardamos). */
function quien_soy(): array
{
    return resultado_de_lectura(llamar_api('GET', '/api/sesion'));
}

/** True si hay sesion abierta en este front. */
function hay_sesion(): bool
{
    return is_string($_SESSION['token'] ?? null) && $_SESSION['token'] !== '';
}

/**
 * True si quien esta dentro alcanza esa ruta.
 *
 * **ESTO NO ES CONTROL DE ACCESO.** Es comodidad: sirve para no pintar un
 * boton que va a responder 403. El control de acceso esta en la API, que
 * consulta la base de datos en cada peticion — escriba la direccion a mano y
 * lo comprueba.
 */
function puede(string $ruta): bool
{
    return in_array($ruta, $_SESSION['permisos'] ?? [], true);
}

// ======================================================================
// LAS DIEZ CONSULTAS  —  /api/consultas   (v4)
// ======================================================================
//
// Dos funciones para diez consultas, y aqui SI es correcto que sean
// genericas: el nombre de la consulta no es un recurso, es el nombre de un
// REPORTE, y la respuesta tiene la misma forma en las diez —`{consulta,
// total, datos}` con columnas distintas—. No hay cinco operaciones que
// ofrecer por cada una: hay una, leer.
//
// Compare con `listar_productos` / `crear_producto` / ...: ahi lo generico
// habria ofrecido operaciones que algunos recursos no tienen. La diferencia
// no es el gusto, es si la forma del contrato es la misma o no.

/** El indice de las diez, para el menu del tablero. */
function listar_consultas(): array
{
    return resultado_de_listado(llamar_api('GET', '/api/consultas'));
}

/** Una consulta por su nombre. Devuelve las filas tal como llegan. */
function consulta(string $nombre): array
{
    $r = llamar_api('GET', '/api/consultas/' . rawurlencode($nombre));
    if ($r === null) {
        return ['ok' => false, 'datos' => [], 'errores' => NO_DISPONIBLE];
    }
    if ($r['codigo'] === 200) {
        return ['ok' => true, 'datos' => $r['cuerpo']['datos'] ?? [],
                'errores' => []];
    }
    return ['ok' => false, 'datos' => [],
            'errores' => mensajes_de_error($r['cuerpo'])];
}

// ======================================================================
// ROL  —  /api/rol        (v3)
// ======================================================================

/** Lista los roles. */
function listar_roles(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/rol?limite=$limite"));
}

/** Trae un rol por su id. */
function obtener_rol(int $id): array
{
    return resultado_de_lectura(llamar_api('GET', "/api/rol/$id"));
}

/** Crea un rol. */
function crear_rol(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/rol', $datos));
}

/** Reemplaza la ficha completa. */
function reemplazar_rol(int $id, array $datos): array
{
    return resultado_de(llamar_api('PUT', "/api/rol/$id", $datos));
}

/** Actualiza solo lo enviado. */
function actualizar_rol(int $id, array $datos): array
{
    return resultado_de(llamar_api('PATCH', "/api/rol/$id", $datos));
}

/** Elimina el rol. */
function eliminar_rol(int $id): array
{
    return resultado_de(llamar_api('DELETE', "/api/rol/$id"));
}

// ======================================================================
// RUTA  —  /api/ruta      (v3)
//
// «Ruta» aqui es una INTERFAZ del sistema a la que se da permiso, no una
// direccion del front: `/factura` es el permiso, `/facturas` es la pantalla.
// El singular y el plural distinguen las dos cosas en todo el proyecto.
// ======================================================================

/** Lista las rutas declaradas. */
function listar_rutas(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/ruta?limite=$limite"));
}

/** Trae una ruta por su id. */
function obtener_ruta(int $id): array
{
    return resultado_de_lectura(llamar_api('GET', "/api/ruta/$id"));
}

/** Crea una ruta. */
function crear_ruta(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/ruta', $datos));
}

/** Reemplaza la ficha completa. */
function reemplazar_ruta(int $id, array $datos): array
{
    return resultado_de(llamar_api('PUT', "/api/ruta/$id", $datos));
}

/** Actualiza solo lo enviado. */
function actualizar_ruta(int $id, array $datos): array
{
    return resultado_de(llamar_api('PATCH', "/api/ruta/$id", $datos));
}

/** Elimina la ruta. */
function eliminar_ruta(int $id): array
{
    return resultado_de(llamar_api('DELETE', "/api/ruta/$id"));
}

// ======================================================================
// USUARIO  —  /api/usuario    (v3)
//
// La llave es el CORREO, y va en la URL: por eso `rawurlencode`, que es lo
// que convierte la arroba y el punto en algo que un servidor no interprete.
// Sin el, un correo con un `+` —validisimo— rompe la direccion.
// ======================================================================

/** Lista los usuarios. Nunca trae la contrasena: la API no la devuelve. */
function listar_usuarios(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/usuario?limite=$limite"));
}

/** Trae un usuario por su correo. */
function obtener_usuario(string $email): array
{
    return resultado_de_lectura(
        llamar_api('GET', '/api/usuario/' . rawurlencode($email)));
}

/** Crea un usuario. La API hashea la contrasena; el front nunca la guarda. */
function crear_usuario(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/usuario', $datos));
}

/** Reemplaza la ficha completa (correo y contrasena). */
function reemplazar_usuario(string $email, array $datos): array
{
    return resultado_de(
        llamar_api('PUT', '/api/usuario/' . rawurlencode($email), $datos));
}

/** Actualiza solo lo enviado —en la practica, la contrasena—. */
function actualizar_usuario(string $email, array $datos): array
{
    return resultado_de(
        llamar_api('PATCH', '/api/usuario/' . rawurlencode($email), $datos));
}

/** Elimina el usuario. */
function eliminar_usuario(string $email): array
{
    return resultado_de(
        llamar_api('DELETE', '/api/usuario/' . rawurlencode($email)));
}

// ======================================================================
// PERMISOS POR ROL  —  /api/rutarol       (v3, tabla puente)
//
// DOS OPERACIONES Y NO CINCO, y es lo que hay que poder explicar: una fila
// de esta tabla es una PAREJA de llaves foraneas y nada mas. Cambiarle el
// rol no es «editarla»: es borrar un permiso y dar otro. En la API el PUT y
// el PATCH estan escritos y COMENTADOS, con esa razon al lado.
// ======================================================================

/** Lista los permisos (ruta + rol), con los nombres ya resueltos. */
function listar_rutarol(int $limite = 1000): array
{
    return resultado_de_listado(llamar_api('GET', "/api/rutarol?limite=$limite"));
}

/** Los roles que alcanzan una ruta. */
function rutarol_por_ruta(int $idRuta): array
{
    return resultado_de_listado(llamar_api('GET', "/api/rutarol/ruta/$idRuta"));
}

/** Las rutas que alcanza un rol. */
function rutarol_por_rol(int $idRol): array
{
    return resultado_de_listado(llamar_api('GET', "/api/rutarol/rol/$idRol"));
}

/** Da un permiso: la pareja (ruta, rol). */
function crear_rutarol(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/rutarol', $datos));
}

/** Quita un permiso. La pareja ENTERA va en la direccion. */
function eliminar_rutarol(int $idRuta, int $idRol): array
{
    return resultado_de(llamar_api('DELETE', "/api/rutarol/$idRuta/$idRol"));
}

// ======================================================================
// ROLES DE CADA USUARIO  —  /api/rol-usuario    (v3, tabla puente)
// ======================================================================

/** Lista las parejas (usuario, rol). */
function listar_rol_usuario(int $limite = 1000): array
{
    return resultado_de_listado(
        llamar_api('GET', "/api/rol-usuario?limite=$limite"));
}

/** Los roles de un usuario. */
function roles_de_usuario(string $email): array
{
    return resultado_de_listado(
        llamar_api('GET', '/api/rol-usuario/usuario/' . rawurlencode($email)));
}

/** Los usuarios que tienen un rol. */
function usuarios_de_rol(int $idRol): array
{
    return resultado_de_listado(llamar_api('GET', "/api/rol-usuario/rol/$idRol"));
}

/** Le da un rol a un usuario. */
function crear_rol_usuario(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/rol-usuario', $datos));
}

/** Le quita un rol a un usuario. */
function eliminar_rol_usuario(string $email, int $idRol): array
{
    return resultado_de(
        llamar_api('DELETE', '/api/rol-usuario/' . rawurlencode($email) . "/$idRol"));
}

// ======================================================================
// USUARIO CON SUS ROLES  —  /api/usuario-con-roles      (v4)
//
// El recurso que hace en UNA peticion lo que de otro modo son varias: crear
// el usuario y darle sus roles, o cambiarle los roles de golpe. Detras hay
// un procedimiento con transaccion, y por eso importa: si el segundo rol
// falla, el usuario no se queda creado a medias.
// ======================================================================

/** Lista los usuarios con su lista de roles. UNA consulta, no N+1. */
function listar_usuarios_con_roles(int $limite = 1000): array
{
    return resultado_de_listado(
        llamar_api('GET', "/api/usuario-con-roles?limite=$limite"));
}

/** Trae un usuario con sus roles. */
function obtener_usuario_con_roles(string $email): array
{
    return resultado_de_lectura(
        llamar_api('GET', '/api/usuario-con-roles/' . rawurlencode($email)));
}

/** Crea el usuario y le da sus roles, de una vez. */
function crear_usuario_con_roles(array $datos): array
{
    return resultado_de(llamar_api('POST', '/api/usuario-con-roles', $datos));
}

/** Reemplaza contrasena y roles. */
function reemplazar_usuario_con_roles(string $email, array $datos): array
{
    return resultado_de(llamar_api('PUT',
        '/api/usuario-con-roles/' . rawurlencode($email), $datos));
}

/** Cambia solo lo enviado: los roles, o la contrasena, o las dos. */
function actualizar_usuario_con_roles(string $email, array $datos): array
{
    return resultado_de(llamar_api('PATCH',
        '/api/usuario-con-roles/' . rawurlencode($email), $datos));
}

/** Borra el usuario Y sus roles. */
function eliminar_usuario_con_roles(string $email): array
{
    return resultado_de(llamar_api('DELETE',
        '/api/usuario-con-roles/' . rawurlencode($email)));
}
