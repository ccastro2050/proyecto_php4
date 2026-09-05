<?php
/**
 * index.php — el FRONT CONTROLLER del front.
 *
 * Todas las peticiones entran aquí, este archivo mira el método y la ruta, y
 * decide qué pantalla pintar. Nada de SQL, nada de negocio, nada de HTML: eso
 * está en `vistas/`.
 *
 * ======================================================================
 * LAS PANTALLAS DE LA v2
 * ======================================================================
 *
 *   /                              el inicio, con el menú
 *   /productos                     listado · /nuevo · /{codigo}/editar · /eliminar
 *   /empresas                      lo mismo
 *   /personas                      lo mismo
 *   /clientes                      lo mismo (la llave es un número)
 *   /vendedores                    lo mismo
 *   /facturas                      listado · /nueva · /{numero} (ver)
 *                                  · /{numero}/editar · /anular · /eliminar
 *
 * **Cada pantalla tiene su dirección propia**, no una con el nombre de la
 * tabla como parámetro: se puede guardar como marcador, mandar por correo y
 * poner en un menú.
 *
 * ======================================================================
 * SÍ, LOS BLOQUES SE PARECEN MUCHO ENTRE SÍ
 * ======================================================================
 *
 * Cinco recursos con seis rutas cada uno hacen un archivo largo y repetitivo,
 * y conviene mirarlo de frente en vez de disimularlo.
 *
 * La alternativa era un enrutador con una tabla de configuración adentro:
 * cuarenta líneas en vez de trescientas. Se descartó por lo mismo que la API
 * no es genérica (Artículo 10 de la constitución) — y aquí hay una prueba a
 * la vista: **el bloque de facturas no se parece a los demás**. No tiene
 * «editar un campo», tiene una pantalla de ver el detalle y tiene «anular».
 * Una tabla de configuración habría tenido que crecer con excepciones hasta
 * volverse ilegible, o habría obligado a que las facturas se comportaran
 * como lo que no son.
 *
 * Trescientas líneas aburridas que se leen de arriba abajo, y en las que se
 * puede buscar «clientes» y encontrar exactamente lo que pasa con esa
 * pantalla. Ése es el trato.
 */

declare(strict_types=1);

require_once __DIR__ . '/cliente_api.php';

// ----------------------------------------------------------------------
// 1. CAPTURAR la petición
// ----------------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

// ----------------------------------------------------------------------
// 1.b LOS ARCHIVOS ESTÁTICOS (y una trampa que costó una pantalla fea)
// ----------------------------------------------------------------------
//
// El servidor embebido de PHP, cuando se le da un router —que es lo que
// hacemos con `php -S ... index.php`—, **lo ejecuta para TODAS las
// peticiones**. También para `/publico/estilos.css`. Y como este archivo no
// tiene una ruta que se llame así, la hoja de estilos caería en el 404 de
// abajo: el navegador recibiría una página HTML donde espera CSS, y la
// pantalla saldría sin un solo estilo.
//
// La solución es la que el propio PHP documenta: **devolver `false`** desde
// el router. Eso le dice «yo no me encargo de ésta, entrégala tal cual».
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . $ruta;
    if ($ruta !== '/' && is_file($archivo)) {
        return false;
    }
}

// Los avisos que una pantalla le deja a la siguiente. Se guardan en la sesión
// porque después de guardar se REDIRIGE, y una redirección pierde todo lo que
// hubiera en memoria.
session_start();

/** Deja un aviso para la pantalla siguiente y redirige. */
function redirigir_con(string $destino, string $tipo, $mensaje): void
{
    $_SESSION['aviso'] = ['tipo' => $tipo, 'mensajes' => (array) $mensaje];
    header("Location: $destino");
    exit;
}

/** Saca el aviso pendiente (y lo borra: se muestra una sola vez). */
function aviso_pendiente(): ?array
{
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $aviso;
}

/**
 * Pinta una vista dentro del marco común.
 *
 * Las tres variables que el MARCO siempre necesita se ponen aquí con un valor
 * por defecto, para que ninguna vista tenga que acordarse de mandarlas:
 * $aviso, $errores y $ruta (que el menú usa para marcar dónde está parado el
 * usuario). La de $ruta hace falta porque **esto es una función**: la $ruta
 * que se calculó arriba no entra aquí sola.
 */
function pintar(string $vista, array $datos = []): void
{
    $datos += ['errores' => [], 'ruta' => $GLOBALS['ruta'] ?? '/'];
    $datos['aviso'] = aviso_pendiente();

    // El motor activo, para el pie de página (ver `diagnostico()` en
    // cliente_api.php). Si la API no responde queda en null, y el pie
    // simplemente no lo muestra: que no se sepa el motor no es motivo para
    // que la pantalla deje de funcionar.
    $r = diagnostico();
    $datos['motor'] = $r['ok'] ? ($r['datos']['motor'] ?? null) : null;

    extract($datos);
    $contenido = __DIR__ . "/vistas/$vista.php";
    require __DIR__ . '/vistas/plantilla.php';
}

/**
 * El texto convertido a número si lo es; si no, el texto tal cual.
 *
 * Parece una validación en el front, y hay que ser preciso porque no lo es.
 * Un formulario HTML **solo produce texto**. El contrato pide un número, y
 * mandarlo entre comillas haría que la API lo rechazara **incluso siendo
 * correcto**.
 *
 * Así que esto ajusta la FORMA del dato, que es trabajo del front, y no juzga
 * su VALOR, que es trabajo de la API: si alguien escribió «doce», eso viaja
 * como «doce» y la API dice que no sirve.
 */
function a_numero(string $texto, string $tipo)
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }
    if ($tipo === 'entero') {
        return ctype_digit(ltrim($texto, '-')) ? (int) $texto : $texto;
    }
    return is_numeric($texto) ? (float) $texto : $texto;
}

// ----------------------------------------------------------------------
// 2. ENRUTAR
// ----------------------------------------------------------------------

// ---- El inicio ----
if ($ruta === '/' && $metodo === 'GET') {
    pintar('inicio');
    exit;
}

// ======================================================================
// PRODUCTOS
// ======================================================================

// ---- El listado ----
if ($ruta === '/productos' && $metodo === 'GET') {
    $r = listar_productos();
    // Aun con error se pinta la pantalla: el usuario ve el aviso DENTRO de la
    // aplicación, no una página de error de PHP.
    pintar('productos_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/productos/nuevo') {
    if ($metodo === 'GET') {
        pintar('productos_formulario', ['ficha' => null, 'editando' => false]);
        exit;
    }

    $cuerpo = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'stock' => a_numero($_POST['stock'] ?? '', 'entero'),
            'valorunitario' => a_numero($_POST['valorunitario'] ?? '', 'decimal'),
    ];

    $r = crear_producto($cuerpo);
    if ($r['ok']) {
        redirigir_con('/productos', 'exito', 'Se agregó la ficha.');
    }

    // Se devuelve el formulario CON lo que la persona había escrito: perder lo
    // digitado por un error de validación es castigarla dos veces.
    pintar('productos_formulario', [
        'ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores'],
    ]);
    exit;
}

// ---- Editar ----
if (preg_match('#^/productos/([^/]+)/editar$#', $ruta, $coincidencias)) {
    $clave = urldecode($coincidencias[1]);

    if ($metodo === 'GET') {
        $r = obtener_producto($clave);
        if (!$r['ok']) {
            redirigir_con('/productos', 'error', $r['errores']);
        }
        pintar('productos_formulario', ['ficha' => $r['datos'], 'editando' => true]);
        exit;
    }

    // ==================================================================
    // QUÉ BOTÓN SE OPRIMIÓ DECIDE QUÉ SE ENVÍA.
    // La diferencia NO está en un `if` de negocio: está en el CUERPO.
    // ==================================================================
    if (($_POST['verbo'] ?? '') === 'completa') {
        // Ficha completa: todos viajan aunque estén vacíos, y por eso un
        // campo obligatorio en blanco se rechaza. Es reemplazar.
        $cuerpo = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'stock' => a_numero($_POST['stock'] ?? '', 'entero'),
            'valorunitario' => a_numero($_POST['valorunitario'] ?? '', 'decimal'),
        ];
        $r = reemplazar_producto($clave, $cuerpo);
    } else {
        // Solo lo que cambió: viaja únicamente lo diligenciado.
        $cuerpo = [];
        $v = trim($_POST['nombre'] ?? '');
        if ($v !== '') { $cuerpo['nombre'] = $v; }
        $v = trim($_POST['stock'] ?? '');
        if ($v !== '') { $cuerpo['stock'] = a_numero($v, 'entero'); }
        $v = trim($_POST['valorunitario'] ?? '');
        if ($v !== '') { $cuerpo['valorunitario'] = a_numero($v, 'decimal'); }
        $r = actualizar_producto($clave, $cuerpo);
    }

    if ($r['ok']) {
        redirigir_con('/productos', 'exito', 'Se guardaron los cambios.');
    }

    pintar('productos_formulario', [
        'ficha'    => ['codigo' => $clave] + $_POST,
        'editando' => true,
        'errores'  => $r['errores'],
    ]);
    exit;
}

// ---- Eliminar ----
// Se exige POST a propósito: un enlace GET que borra lo puede disparar el
// navegador solo, al precargar la página.
if (preg_match('#^/productos/([^/]+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $clave = urldecode($coincidencias[1]);
    $r = eliminar_producto($clave);

    $r['ok']
        ? redirigir_con('/productos', 'exito', 'Se eliminó la ficha.')
        : redirigir_con('/productos', 'error', $r['errores']);
}

// ======================================================================
// EMPRESAS
// ======================================================================

// ---- El listado ----
if ($ruta === '/empresas' && $metodo === 'GET') {
    $r = listar_empresas();
    // Aun con error se pinta la pantalla: el usuario ve el aviso DENTRO de la
    // aplicación, no una página de error de PHP.
    pintar('empresas_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/empresas/nuevo') {
    if ($metodo === 'GET') {
        pintar('empresas_formulario', ['ficha' => null, 'editando' => false]);
        exit;
    }

    $cuerpo = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
    ];

    $r = crear_empresa($cuerpo);
    if ($r['ok']) {
        redirigir_con('/empresas', 'exito', 'Se agregó la ficha.');
    }

    // Se devuelve el formulario CON lo que la persona había escrito: perder lo
    // digitado por un error de validación es castigarla dos veces.
    pintar('empresas_formulario', [
        'ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores'],
    ]);
    exit;
}

// ---- Editar ----
if (preg_match('#^/empresas/([^/]+)/editar$#', $ruta, $coincidencias)) {
    $clave = urldecode($coincidencias[1]);

    if ($metodo === 'GET') {
        $r = obtener_empresa($clave);
        if (!$r['ok']) {
            redirigir_con('/empresas', 'error', $r['errores']);
        }
        pintar('empresas_formulario', ['ficha' => $r['datos'], 'editando' => true]);
        exit;
    }

    // ==================================================================
    // QUÉ BOTÓN SE OPRIMIÓ DECIDE QUÉ SE ENVÍA.
    // La diferencia NO está en un `if` de negocio: está en el CUERPO.
    // ==================================================================
    if (($_POST['verbo'] ?? '') === 'completa') {
        // Ficha completa: todos viajan aunque estén vacíos, y por eso un
        // campo obligatorio en blanco se rechaza. Es reemplazar.
        $cuerpo = [
            'nombre' => trim($_POST['nombre'] ?? ''),
        ];
        $r = reemplazar_empresa($clave, $cuerpo);
    } else {
        // Solo lo que cambió: viaja únicamente lo diligenciado.
        $cuerpo = [];
        $v = trim($_POST['nombre'] ?? '');
        if ($v !== '') { $cuerpo['nombre'] = $v; }
        $r = actualizar_empresa($clave, $cuerpo);
    }

    if ($r['ok']) {
        redirigir_con('/empresas', 'exito', 'Se guardaron los cambios.');
    }

    pintar('empresas_formulario', [
        'ficha'    => ['codigo' => $clave] + $_POST,
        'editando' => true,
        'errores'  => $r['errores'],
    ]);
    exit;
}

// ---- Eliminar ----
// Se exige POST a propósito: un enlace GET que borra lo puede disparar el
// navegador solo, al precargar la página.
if (preg_match('#^/empresas/([^/]+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $clave = urldecode($coincidencias[1]);
    $r = eliminar_empresa($clave);

    $r['ok']
        ? redirigir_con('/empresas', 'exito', 'Se eliminó la ficha.')
        : redirigir_con('/empresas', 'error', $r['errores']);
}

// ======================================================================
// PERSONAS
// ======================================================================

// ---- El listado ----
if ($ruta === '/personas' && $metodo === 'GET') {
    $r = listar_personas();
    // Aun con error se pinta la pantalla: el usuario ve el aviso DENTRO de la
    // aplicación, no una página de error de PHP.
    pintar('personas_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/personas/nuevo') {
    if ($metodo === 'GET') {
        pintar('personas_formulario', ['ficha' => null, 'editando' => false]);
        exit;
    }

    $cuerpo = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
    ];

    $r = crear_persona($cuerpo);
    if ($r['ok']) {
        redirigir_con('/personas', 'exito', 'Se agregó la ficha.');
    }

    // Se devuelve el formulario CON lo que la persona había escrito: perder lo
    // digitado por un error de validación es castigarla dos veces.
    pintar('personas_formulario', [
        'ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores'],
    ]);
    exit;
}

// ---- Editar ----
if (preg_match('#^/personas/([^/]+)/editar$#', $ruta, $coincidencias)) {
    $clave = urldecode($coincidencias[1]);

    if ($metodo === 'GET') {
        $r = obtener_persona($clave);
        if (!$r['ok']) {
            redirigir_con('/personas', 'error', $r['errores']);
        }
        pintar('personas_formulario', ['ficha' => $r['datos'], 'editando' => true]);
        exit;
    }

    // ==================================================================
    // QUÉ BOTÓN SE OPRIMIÓ DECIDE QUÉ SE ENVÍA.
    // La diferencia NO está en un `if` de negocio: está en el CUERPO.
    // ==================================================================
    if (($_POST['verbo'] ?? '') === 'completa') {
        // Ficha completa: todos viajan aunque estén vacíos, y por eso un
        // campo obligatorio en blanco se rechaza. Es reemplazar.
        $cuerpo = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
        ];
        $r = reemplazar_persona($clave, $cuerpo);
    } else {
        // Solo lo que cambió: viaja únicamente lo diligenciado.
        $cuerpo = [];
        $v = trim($_POST['nombre'] ?? '');
        if ($v !== '') { $cuerpo['nombre'] = $v; }
        $v = trim($_POST['email'] ?? '');
        if ($v !== '') { $cuerpo['email'] = $v; }
        $v = trim($_POST['telefono'] ?? '');
        if ($v !== '') { $cuerpo['telefono'] = $v; }
        $r = actualizar_persona($clave, $cuerpo);
    }

    if ($r['ok']) {
        redirigir_con('/personas', 'exito', 'Se guardaron los cambios.');
    }

    pintar('personas_formulario', [
        'ficha'    => ['codigo' => $clave] + $_POST,
        'editando' => true,
        'errores'  => $r['errores'],
    ]);
    exit;
}

// ---- Eliminar ----
// Se exige POST a propósito: un enlace GET que borra lo puede disparar el
// navegador solo, al precargar la página.
if (preg_match('#^/personas/([^/]+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $clave = urldecode($coincidencias[1]);
    $r = eliminar_persona($clave);

    $r['ok']
        ? redirigir_con('/personas', 'exito', 'Se eliminó la ficha.')
        : redirigir_con('/personas', 'error', $r['errores']);
}

// ======================================================================
// CLIENTES
// ======================================================================

// ---- El listado ----
if ($ruta === '/clientes' && $metodo === 'GET') {
    $r = listar_clientes();
    // Aun con error se pinta la pantalla: el usuario ve el aviso DENTRO de la
    // aplicación, no una página de error de PHP.
    pintar('clientes_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/clientes/nuevo') {
    if ($metodo === 'GET') {
        pintar('clientes_formulario', ['ficha' => null, 'editando' => false] + catalogos_de_cliente());
        exit;
    }

    $cuerpo = [
            'credito' => a_numero($_POST['credito'] ?? '', 'decimal'),
            'fkcodpersona' => trim($_POST['fkcodpersona'] ?? ''),
            'fkcodempresa' => trim($_POST['fkcodempresa'] ?? ''),
    ];
        // Los campos opcionales viajan como null cuando quedan en blanco:
        // «vacío» y «no aplica» son la misma cosa para estos.
        if ($cuerpo['fkcodempresa'] === '') { $cuerpo['fkcodempresa'] = null; }

    $r = crear_cliente($cuerpo);
    if ($r['ok']) {
        redirigir_con('/clientes', 'exito', 'Se agregó la ficha.');
    }

    // Se devuelve el formulario CON lo que la persona había escrito: perder lo
    // digitado por un error de validación es castigarla dos veces.
    pintar('clientes_formulario', [
        'ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores'],
    ] + catalogos_de_cliente());
    exit;
}

// ---- Editar ----
if (preg_match('#^/clientes/([^/]+)/editar$#', $ruta, $coincidencias)) {
        // La llave de este recurso es un número: la URL trae texto y aquí
        // se convierte, igual que hace la API en su propio enrutador.
    $clave = (int) $coincidencias[1];

    if ($metodo === 'GET') {
        $r = obtener_cliente($clave);
        if (!$r['ok']) {
            redirigir_con('/clientes', 'error', $r['errores']);
        }
        pintar('clientes_formulario', ['ficha' => $r['datos'], 'editando' => true] + catalogos_de_cliente());
        exit;
    }

    // ==================================================================
    // QUÉ BOTÓN SE OPRIMIÓ DECIDE QUÉ SE ENVÍA.
    // La diferencia NO está en un `if` de negocio: está en el CUERPO.
    // ==================================================================
    if (($_POST['verbo'] ?? '') === 'completa') {
        // Ficha completa: todos viajan aunque estén vacíos, y por eso un
        // campo obligatorio en blanco se rechaza. Es reemplazar.
        $cuerpo = [
            'credito' => a_numero($_POST['credito'] ?? '', 'decimal'),
            'fkcodpersona' => trim($_POST['fkcodpersona'] ?? ''),
            'fkcodempresa' => trim($_POST['fkcodempresa'] ?? ''),
        ];
        // Los campos opcionales viajan como null cuando quedan en blanco:
        // «vacío» y «no aplica» son la misma cosa para estos.
        if ($cuerpo['fkcodempresa'] === '') { $cuerpo['fkcodempresa'] = null; }
        $r = reemplazar_cliente($clave, $cuerpo);
    } else {
        // Solo lo que cambió: viaja únicamente lo diligenciado.
        $cuerpo = [];
        $v = trim($_POST['credito'] ?? '');
        if ($v !== '') { $cuerpo['credito'] = a_numero($v, 'decimal'); }
        $v = trim($_POST['fkcodpersona'] ?? '');
        if ($v !== '') { $cuerpo['fkcodpersona'] = $v; }
        $v = trim($_POST['fkcodempresa'] ?? '');
        if ($v !== '') { $cuerpo['fkcodempresa'] = $v; }
        $r = actualizar_cliente($clave, $cuerpo);
    }

    if ($r['ok']) {
        redirigir_con('/clientes', 'exito', 'Se guardaron los cambios.');
    }

    pintar('clientes_formulario', [
        'ficha'    => ['id' => $clave] + $_POST,
        'editando' => true,
        'errores'  => $r['errores'],
    ] + catalogos_de_cliente());
    exit;
}

// ---- Eliminar ----
// Se exige POST a propósito: un enlace GET que borra lo puede disparar el
// navegador solo, al precargar la página.
if (preg_match('#^/clientes/([^/]+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $clave = (int) $coincidencias[1];
    $r = eliminar_cliente($clave);

    $r['ok']
        ? redirigir_con('/clientes', 'exito', 'Se eliminó la ficha.')
        : redirigir_con('/clientes', 'error', $r['errores']);
}

// ======================================================================
// VENDEDORS
// ======================================================================

// ---- El listado ----
if ($ruta === '/vendedores' && $metodo === 'GET') {
    $r = listar_vendedores();
    // Aun con error se pinta la pantalla: el usuario ve el aviso DENTRO de la
    // aplicación, no una página de error de PHP.
    pintar('vendedores_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/vendedores/nuevo') {
    if ($metodo === 'GET') {
        pintar('vendedores_formulario', ['ficha' => null, 'editando' => false] + catalogos_de_vendedor());
        exit;
    }

    $cuerpo = [
            'carnet' => a_numero($_POST['carnet'] ?? '', 'entero'),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'fkcodpersona' => trim($_POST['fkcodpersona'] ?? ''),
    ];

    $r = crear_vendedor($cuerpo);
    if ($r['ok']) {
        redirigir_con('/vendedores', 'exito', 'Se agregó la ficha.');
    }

    // Se devuelve el formulario CON lo que la persona había escrito: perder lo
    // digitado por un error de validación es castigarla dos veces.
    pintar('vendedores_formulario', [
        'ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores'],
    ] + catalogos_de_vendedor());
    exit;
}

// ---- Editar ----
if (preg_match('#^/vendedores/([^/]+)/editar$#', $ruta, $coincidencias)) {
        // La llave de este recurso es un número: la URL trae texto y aquí
        // se convierte, igual que hace la API en su propio enrutador.
    $clave = (int) $coincidencias[1];

    if ($metodo === 'GET') {
        $r = obtener_vendedor($clave);
        if (!$r['ok']) {
            redirigir_con('/vendedores', 'error', $r['errores']);
        }
        pintar('vendedores_formulario', ['ficha' => $r['datos'], 'editando' => true] + catalogos_de_vendedor());
        exit;
    }

    // ==================================================================
    // QUÉ BOTÓN SE OPRIMIÓ DECIDE QUÉ SE ENVÍA.
    // La diferencia NO está en un `if` de negocio: está en el CUERPO.
    // ==================================================================
    if (($_POST['verbo'] ?? '') === 'completa') {
        // Ficha completa: todos viajan aunque estén vacíos, y por eso un
        // campo obligatorio en blanco se rechaza. Es reemplazar.
        $cuerpo = [
            'carnet' => a_numero($_POST['carnet'] ?? '', 'entero'),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'fkcodpersona' => trim($_POST['fkcodpersona'] ?? ''),
        ];
        $r = reemplazar_vendedor($clave, $cuerpo);
    } else {
        // Solo lo que cambió: viaja únicamente lo diligenciado.
        $cuerpo = [];
        $v = trim($_POST['carnet'] ?? '');
        if ($v !== '') { $cuerpo['carnet'] = a_numero($v, 'entero'); }
        $v = trim($_POST['direccion'] ?? '');
        if ($v !== '') { $cuerpo['direccion'] = $v; }
        $v = trim($_POST['fkcodpersona'] ?? '');
        if ($v !== '') { $cuerpo['fkcodpersona'] = $v; }
        $r = actualizar_vendedor($clave, $cuerpo);
    }

    if ($r['ok']) {
        redirigir_con('/vendedores', 'exito', 'Se guardaron los cambios.');
    }

    pintar('vendedores_formulario', [
        'ficha'    => ['id' => $clave] + $_POST,
        'editando' => true,
        'errores'  => $r['errores'],
    ] + catalogos_de_vendedor());
    exit;
}

// ---- Eliminar ----
// Se exige POST a propósito: un enlace GET que borra lo puede disparar el
// navegador solo, al precargar la página.
if (preg_match('#^/vendedores/([^/]+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $clave = (int) $coincidencias[1];
    $r = eliminar_vendedor($clave);

    $r['ok']
        ? redirigir_con('/vendedores', 'exito', 'Se eliminó la ficha.')
        : redirigir_con('/vendedores', 'error', $r['errores']);
}

// ======================================================================
// FACTURAS — el recurso que no se parece a los demás
//
// Tiene una pantalla que ningún otro tiene (ver el detalle), no tiene
// «guardar solo lo que cambié», y tiene «anular».
// ======================================================================

// ---- El listado ----
if ($ruta === '/facturas' && $metodo === 'GET') {
    $r = listar_facturas();
    pintar('facturas_lista', ['filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Nueva ----
if ($ruta === '/facturas/nueva') {
    if ($metodo === 'GET') {
        pintar('facturas_formulario', ['ficha' => null, 'editando' => false]
            + catalogos_de_factura());
        exit;
    }

    $cuerpo = cuerpo_de_factura();
    $r = crear_factura($cuerpo);
    if ($r['ok']) {
        $numero = $r['datos']['factura']['numero'] ?? null;
        redirigir_con("/facturas/$numero", 'exito', "Se creó la factura $numero.");
    }

    pintar('facturas_formulario',
        ['ficha' => $cuerpo, 'editando' => false, 'errores' => $r['errores']]
        + catalogos_de_factura());
    exit;
}

// ---- Editar (reemplazo completo) ----
// Va ANTES que la pantalla de ver, porque "/facturas/3/editar" también
// empieza por "/facturas/". El orden de los `if` es parte del significado.
if (preg_match('#^/facturas/(\d+)/editar$#', $ruta, $coincidencias)) {
    $numero = (int) $coincidencias[1];

    if ($metodo === 'GET') {
        $r = obtener_factura($numero);
        if (!$r['ok']) {
            redirigir_con('/facturas', 'error', $r['errores']);
        }
        pintar('facturas_formulario',
            ['ficha' => $r['datos'], 'editando' => true] + catalogos_de_factura());
        exit;
    }

    $cuerpo = cuerpo_de_factura();
    $r = reemplazar_factura($numero, $cuerpo);
    if ($r['ok']) {
        redirigir_con("/facturas/$numero", 'exito', "Se guardó la factura $numero.");
    }

    pintar('facturas_formulario',
        ['ficha' => $cuerpo + ['numero' => $numero], 'editando' => true,
         'errores' => $r['errores']] + catalogos_de_factura());
    exit;
}

// ---- Anular ----
if (preg_match('#^/facturas/(\d+)/anular$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $numero = (int) $coincidencias[1];
    $r = anular_factura($numero);

    $r['ok']
        ? redirigir_con('/facturas', 'exito',
            "Se anuló la factura $numero. El stock volvió a los productos.")
        : redirigir_con('/facturas', 'error', $r['errores']);
}

// ---- Eliminar ----
if (preg_match('#^/facturas/(\d+)/eliminar$#', $ruta, $coincidencias) && $metodo === 'POST') {
    $numero = (int) $coincidencias[1];
    $r = eliminar_factura($numero);

    $r['ok']
        ? redirigir_con('/facturas', 'exito', "Se eliminó la factura $numero.")
        : redirigir_con('/facturas', 'error', $r['errores']);
}

// ---- Ver una factura con su detalle ----
// Esta pantalla no existe en ningún otro recurso, y es la que le da sentido
// a maestro-detalle: el encabezado y sus renglones, en una sola vista.
if (preg_match('#^/facturas/(\d+)$#', $ruta, $coincidencias) && $metodo === 'GET') {
    $r = obtener_factura((int) $coincidencias[1]);
    if (!$r['ok']) {
        redirigir_con('/facturas', 'error', $r['errores']);
    }
    pintar('facturas_detalle', ['factura' => $r['datos']]);
    exit;
}

// ---- Cualquier otra cosa ----
http_response_code(404);
pintar('no_encontrada', ['ruta' => $ruta]);

// ======================================================================
// Dos ayudantes que solo usan las facturas
// ======================================================================

/**
 * Las listas para los desplegables del formulario de factura.
 *
 * El formulario no le pide a la persona que escriba «1» en un campo llamado
 * fkidcliente: le muestra los clientes por su nombre. Eso cuesta tres
 * peticiones más a la API, y las vale — teclear un identificador que hay que
 * ir a buscar a otra pantalla es de las cosas que hacen odiar un sistema.
 */
function catalogos_de_factura(): array
{
    return [
        'clientes'  => listar_clientes()['datos'],
        'vendedores' => listar_vendedores()['datos'],
        'productos' => listar_productos()['datos'],
    ];
}

/**
 * Arma el cuerpo de la factura a partir del formulario.
 *
 * EL DETALLE LLEGA COMO RENGLONES FIJOS, y conviene explicar por qué.
 *
 * Un formulario que deje agregar renglones sin límite necesita JavaScript, y
 * esta versión no lo usa (la constitución pide que lo que se vea sea PHP, no
 * magia de un framework). Así que la pantalla ofrece RENGLONES_FACTURA
 * casillas y **las que queden vacías se ignoran aquí**.
 *
 * Es una limitación real y está dicha en la spec, no escondida: para más
 * renglones de los que caben, la versión siguiente traerá otra solución.
 */
function cuerpo_de_factura(): array
{
    $detalle = [];
    $codigos    = $_POST['detalle_codigo']   ?? [];
    $cantidades = $_POST['detalle_cantidad'] ?? [];

    foreach ($codigos as $i => $codigo) {
        $codigo   = trim((string) $codigo);
        $cantidad = trim((string) ($cantidades[$i] ?? ''));

        // Un renglón sin producto NO es un error: es una casilla que la
        // persona dejó vacía porque no la necesitaba.
        if ($codigo === '') {
            continue;
        }
        $detalle[] = [
            'codigo'   => $codigo,
            'cantidad' => a_numero($cantidad, 'entero'),
        ];
    }

    return [
        'fkidcliente'  => a_numero(trim($_POST['fkidcliente'] ?? ''), 'entero'),
        'fkidvendedor' => a_numero(trim($_POST['fkidvendedor'] ?? ''), 'entero'),
        'detalle'      => $detalle,
    ];
}

/**
 * Las listas para los desplegables del formulario de cliente.
 *
 * `cliente` apunta a empresas y a personas con una llave foránea, así que la pantalla
 * ofrece esos códigos en un desplegable en vez de pedir que se escriban.
 *
 * Y ojo con lo que esto NO significa: **el desplegable no valida nada.** La
 * lista pudo cargarse hace un minuto y alguien pudo borrar esa ficha
 * entretanto; además, cualquiera puede mandar un POST sin pasar por la
 * pantalla. Quien defiende la integridad sigue siendo la base de datos —
 * esto solo hace el formulario cómodo.
 */
function catalogos_de_cliente(): array
{
    return [
        'empresas' => listar_empresas()['datos'],
        'personas' => listar_personas()['datos'],
    ];
}

/**
 * Las listas para los desplegables del formulario de vendedor.
 *
 * `vendedor` apunta a personas con una llave foránea, así que la pantalla
 * ofrece esos códigos en un desplegable en vez de pedir que se escriban.
 *
 * Y ojo con lo que esto NO significa: **el desplegable no valida nada.** La
 * lista pudo cargarse hace un minuto y alguien pudo borrar esa ficha
 * entretanto; además, cualquiera puede mandar un POST sin pasar por la
 * pantalla. Quien defiende la integridad sigue siendo la base de datos —
 * esto solo hace el formulario cómodo.
 */
function catalogos_de_vendedor(): array
{
    return [
        'personas' => listar_personas()['datos'],
    ];
}
