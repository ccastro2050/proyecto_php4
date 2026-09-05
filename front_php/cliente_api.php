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

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,          // devolver la respuesta, no imprimirla
        CURLOPT_CUSTOMREQUEST  => $metodo,       // GET, POST, PUT, PATCH, DELETE
        CURLOPT_TIMEOUT        => TIEMPO_MAXIMO,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
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
    return $r['codigo'] === 200
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
