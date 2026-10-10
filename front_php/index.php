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

    // Quien está dentro y qué alcanza, para la cabecera y el menú (v3).
    // Van SIEMPRE, con valores vacíos si no hay sesión: así la plantilla no
    // tiene que preguntar si existen —y la pantalla de entrar usa el mismo
    // marco que todas las demás—.
    $datos['sesion'] = [
        'email' => $_SESSION['email'] ?? null,
        'roles' => $_SESSION['roles'] ?? [],
    ];
    $datos['permisos'] = $_SESSION['permisos'] ?? [];

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

// ======================================================================
// LA GUARDIA DEL FRONT  (v3)
// ======================================================================
//
// Desde la v3 no se ve una sola pantalla sin identificarse. Y está escrito
// igual que en la API, por el mismo motivo: **que el olvido sea difícil.**
//
//   · una LISTA BLANCA con lo que se sirve sin sesión, que son dos cosas;
//   · UNA línea que manda a `/entrar` todo lo demás, aquí arriba, antes de
//     cualquier bloque de pantalla.
//
// Para dejar una pantalla abierta por accidente habría que agregarla a la
// lista blanca, y eso se ve en el `diff`.
//
// Y HAY QUE DECIR QUÉ PROTEGE ESTO Y QUÉ NO. Esta guardia evita que alguien
// sin sesión vea el armazón de una pantalla; **los datos no los protege
// ella**, los protege la API, que responde 401 sin token y 403 sin permiso
// aunque la petición venga de este front, de Postman o de `curl`. Si esta
// línea se borrara, el front enseñaría pantallas vacías con mensajes de
// error — fea, pero sin un dato de más.
$SIN_SESION = ['/entrar'];

if (!in_array($ruta, $SIN_SESION, true) && !hay_sesion()) {
    // Se recuerda a dónde quería ir, para llevarlo ahí después de entrar en
    // vez de dejarlo siempre en el inicio. Es comodidad, y es de las que se
    // notan: la dirección que alguien guardó en marcadores sigue sirviendo.
    $_SESSION['destino'] = $ruta;
    header('Location: /entrar');
    exit;
}

// ======================================================================
// ENTRAR Y SALIR  —  /entrar  ·  /salir
// ======================================================================
if ($ruta === '/entrar') {
    if ($metodo === 'GET') {
        // Si ya hay sesión, no se pinta el formulario otra vez: al inicio.
        if (hay_sesion()) {
            header('Location: /');
            exit;
        }
        pintar('entrar', ['email' => '']);
        exit;
    }

    if ($metodo === 'POST') {
        $email      = trim((string) ($_POST['email'] ?? ''));
        $contrasena = (string) ($_POST['contrasena'] ?? '');

        $r = entrar($email, $contrasena);

        if ($r['ok']) {
            // El destino guardado por la guardia, si había uno.
            $destino = $_SESSION['destino'] ?? '/';
            unset($_SESSION['destino']);
            redirigir_con($destino, 'exito',
                          'Hola, ' . ($r['datos']['email'] ?? $email) . '.');
        }

        // Se repinta el formulario CON el correo escrito y sin la contraseña.
        // Lo segundo es deliberado: una contraseña no vuelve a la pantalla ni
        // dentro de un campo oculto.
        pintar('entrar', ['email' => $email, 'errores' => $r['errores']]);
        exit;
    }

    pintar('no_encontrada', ['ruta' => $ruta]);
    exit;
}

if ($ruta === '/salir') {
    // Salir es un POST y no un enlace, y es a propósito: un GET que cambia
    // algo —aquí, cerrar la sesión— lo puede disparar cualquier cosa que
    // cargue una dirección, desde una imagen hasta el prefetch del navegador.
    if ($metodo === 'POST') {
        salir();
        session_start();                 // una sesión nueva, solo para el aviso
        redirigir_con('/entrar', 'exito', 'Sesión cerrada.');
    }
    pintar('no_encontrada', ['ruta' => $ruta]);
    exit;
}

// ======================================================================
// EL TABLERO  —  /tablero  ·  /tablero/{consulta}     (v4)
// ======================================================================
//
// La pantalla que estrena la v4. Dos rutas y ni una tercera:
//
//   /tablero                 las diez consultas, con su título
//   /tablero/{nombre}        una consulta, con sus filas en una tabla
//
// LA TABLA SE PINTA SIN SABER QUÉ COLUMNAS TRAE. Las diez devuelven columnas
// distintas —`ingreso`, `ticket_promedio`, `sus_roles`— y la vista recorre
// las claves de la primera fila para armar el encabezado.
//
// Eso es lo contrario de lo que hacen las seis pantallas del CRUD, que tienen
// sus columnas escritas una por una. Y la diferencia está razonada: una ficha
// de producto tiene campos que se escriben, se validan y se traducen al
// español; una fila de reporte es el resultado de una pregunta, y el nombre
// de su columna ES la respuesta. Inventarle un formulario a cada una serían
// diez pantallas que nadie mantiene.
if ($ruta === '/tablero') {
    $r = listar_consultas();
    pintar('tablero', ['seccion' => 'indice',
                       'consultas' => $r['datos'],
                       'errores' => $r['errores']]);
    exit;
}

if (str_starts_with($ruta, '/tablero/')) {
    $nombre = substr($ruta, strlen('/tablero/'));

    // El índice se pide igual, porque la pantalla de una consulta también
    // muestra el menú de las diez: una llamada más, y el usuario no tiene que
    // volver atrás para ver otra.
    $indice = listar_consultas();
    $titulo = $nombre;
    foreach ($indice['datos'] as $c) {
        if (($c['nombre'] ?? '') === $nombre) {
            $titulo = $c['titulo'];
        }
    }

    $r = consulta($nombre);
    pintar('tablero', ['seccion' => 'consulta',
                       'consultas' => $indice['datos'],
                       'nombre' => $nombre,
                       'titulo' => $titulo,
                       'filas' => $r['datos'],
                       'errores' => $r['errores']]);
    exit;
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
    pintar('productos', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/productos/nuevo') {
    if ($metodo === 'GET') {
        pintar('productos', ['seccion' => 'formulario', 'ficha' => null, 'editando' => false]);
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
    pintar('productos', ['seccion' => 'formulario', 
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
        pintar('productos', ['seccion' => 'formulario', 'ficha' => $r['datos'], 'editando' => true]);
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

    pintar('productos', ['seccion' => 'formulario', 
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
    pintar('empresas', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/empresas/nuevo') {
    if ($metodo === 'GET') {
        pintar('empresas', ['seccion' => 'formulario', 'ficha' => null, 'editando' => false]);
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
    pintar('empresas', ['seccion' => 'formulario', 
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
        pintar('empresas', ['seccion' => 'formulario', 'ficha' => $r['datos'], 'editando' => true]);
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

    pintar('empresas', ['seccion' => 'formulario', 
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
    pintar('personas', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/personas/nuevo') {
    if ($metodo === 'GET') {
        pintar('personas', ['seccion' => 'formulario', 'ficha' => null, 'editando' => false]);
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
    pintar('personas', ['seccion' => 'formulario', 
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
        pintar('personas', ['seccion' => 'formulario', 'ficha' => $r['datos'], 'editando' => true]);
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

    pintar('personas', ['seccion' => 'formulario', 
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
    pintar('clientes', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/clientes/nuevo') {
    if ($metodo === 'GET') {
        pintar('clientes', ['seccion' => 'formulario', 'ficha' => null, 'editando' => false] + catalogos_de_cliente());
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
    pintar('clientes', ['seccion' => 'formulario', 
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
        pintar('clientes', ['seccion' => 'formulario', 'ficha' => $r['datos'], 'editando' => true] + catalogos_de_cliente());
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

    pintar('clientes', ['seccion' => 'formulario', 
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
    pintar('vendedores', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Agregar ----
if ($ruta === '/vendedores/nuevo') {
    if ($metodo === 'GET') {
        pintar('vendedores', ['seccion' => 'formulario', 'ficha' => null, 'editando' => false] + catalogos_de_vendedor());
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
    pintar('vendedores', ['seccion' => 'formulario', 
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
        pintar('vendedores', ['seccion' => 'formulario', 'ficha' => $r['datos'], 'editando' => true] + catalogos_de_vendedor());
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

    pintar('vendedores', ['seccion' => 'formulario', 
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
    pintar('facturas', ['seccion' => 'lista', 'filas' => $r['datos'], 'errores' => $r['errores']]);
    exit;
}

// ---- Emitir una factura ----
//
// LOS RENGLONES SE AGREGAN DE A UNO, y por eso esta pantalla necesita algo que
// las demás no: un BORRADOR que sobreviva entre peticiones. Cada botón es una
// petición distinta, y sin guardarlo en algún lado lo agregado se perdería.
// Vive en `$_SESSION` (ver `borrador_de_factura()` al final del archivo).
//
// Cuatro acciones y UNA sola ruta: lo que las distingue es el `name="accion"`
// del botón que se oprimió.
if ($ruta === '/facturas/nueva') {
    $b = borrador_de_factura();

    if ($metodo === 'POST') {
        // El maestro se guarda SIEMPRE, con cualquier botón: perder el cliente
        // elegido por agregar un renglón sería castigar a quien lo eligió.
        $b['cliente']  = trim((string) ($_POST['fkidcliente']  ?? $b['cliente']));
        $b['vendedor'] = trim((string) ($_POST['fkidvendedor'] ?? $b['vendedor']));
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'agregar') {
            $precios = precios_de_productos();
            $codigo   = trim((string) ($_POST['codigo'] ?? ''));
            $cantidad = trim((string) ($_POST['cantidad'] ?? ''));

            if (!isset($precios[$codigo])) {
                guardar_borrador($b);
                redirigir_con('/facturas/nueva', 'error', 'Elija un producto.');
            }
            if (!ctype_digit($cantidad) || (int) $cantidad < 1) {
                guardar_borrador($b);
                redirigir_con('/facturas/nueva', 'error', 'La cantidad tiene que ser 1 o más.');
            }

            // El mismo producto dos veces SUMA, no repite el renglón.
            $encontrado = false;
            foreach ($b['renglones'] as &$r) {
                if ($r['codigo'] === $codigo) {
                    $r['cantidad'] += (int) $cantidad;
                    $encontrado = true;
                    break;
                }
            }
            unset($r);   // se rompe la referencia del foreach, que si no muerde

            if (!$encontrado) {
                $b['renglones'][] = [
                    'codigo'   => $codigo,
                    'cantidad' => (int) $cantidad,
                    'nombre'   => $precios[$codigo]['nombre'],
                    'precio'   => $precios[$codigo]['valorunitario'],
                ];
            }
            guardar_borrador($b);
            header('Location: /facturas/nueva');
            exit;
        }

        if ($accion === 'quitar') {
            $fuera = (string) ($_POST['quitar'] ?? '');
            $b['renglones'] = array_values(array_filter(
                $b['renglones'],
                fn(array $r) => $r['codigo'] !== $fuera,
            ));
            guardar_borrador($b);
            header('Location: /facturas/nueva');
            exit;
        }

        if ($accion === 'limpiar') {
            unset($_SESSION['borrador_factura']);
            header('Location: /facturas/nueva');
            exit;
        }

        if ($accion === 'emitir') {
            if ($b['cliente'] === '' || $b['vendedor'] === '') {
                guardar_borrador($b);
                redirigir_con('/facturas/nueva', 'error', 'Elija el cliente y el vendedor.');
            }
            if (!$b['renglones']) {
                guardar_borrador($b);
                redirigir_con('/facturas/nueva', 'error', 'Agregue al menos un renglón.');
            }

            // VIAJAN el cliente, el vendedor y los renglones. NO viajan el
            // subtotal ni el total: los calcula el disparador de la base.
            $r = crear_factura([
                'fkidcliente'  => (int) $b['cliente'],
                'fkidvendedor' => (int) $b['vendedor'],
                'detalle'      => array_map(
                    fn(array $x) => ['codigo' => $x['codigo'], 'cantidad' => $x['cantidad']],
                    $b['renglones'],
                ),
            ]);

            if ($r['ok']) {
                unset($_SESSION['borrador_factura']);
                $numero = $r['datos']['factura']['numero'] ?? '';
                redirigir_con("/facturas/$numero", 'exito',
                    "Factura $numero emitida — los subtotales y el total los calculó la base de datos.");
            }
            guardar_borrador($b);
            redirigir_con('/facturas/nueva', 'error', $r['errores']);
        }
    }

    $total = 0.0;
    foreach ($b['renglones'] as $r) {
        $total += $r['cantidad'] * $r['precio'];
    }
    pintar('facturas', ['seccion' => 'formulario'] +
        ['borrador' => $b, 'total_estimado' => $total] + catalogos_de_factura());
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

// ---- Ver una factura con su detalle ----
// Esta pantalla no existe en ningún otro recurso, y es la que le da sentido
// a maestro-detalle: el encabezado y sus renglones, en una sola vista.
if (preg_match('#^/facturas/(\d+)$#', $ruta, $coincidencias) && $metodo === 'GET') {
    $r = obtener_factura((int) $coincidencias[1]);
    if (!$r['ok']) {
        redirigir_con('/facturas', 'error', $r['errores']);
    }
    pintar('facturas', ['seccion' => 'detalle', 'factura' => $r['datos']]);
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
/**
 * EL BORRADOR DE LA FACTURA, que vive en la sesión.
 *
 * Hace falta porque los renglones se agregan DE A UNO y cada botón es una
 * petición nueva: PHP no recuerda nada entre una y otra. En el front de Blazor
 * esto no existe —el borrador vive en el circuito— y ésa es toda la diferencia
 * entre los dos fronts.
 *
 * `cliente` y `vendedor` se guardan como texto porque así llegan del `<select>`
 * y así se comparan al volver a pintarlo; al emitir se convierten a entero.
 */
function borrador_de_factura(): array
{
    if (!isset($_SESSION['borrador_factura'])) {
        $_SESSION['borrador_factura'] = [
            'cliente' => '', 'vendedor' => '', 'renglones' => [],
        ];
    }
    return $_SESSION['borrador_factura'];
}

/** Devuelve el borrador a la sesión después de tocarlo. */
function guardar_borrador(array $b): void
{
    $_SESSION['borrador_factura'] = $b;
}

/**
 * Los productos indexados por su código, para armar el renglón sin volver a
 * preguntarle a la API el nombre y el precio de lo que ya se listó.
 *
 * El precio se guarda en el borrador SOLO para mostrar el total estimado. El
 * que queda en la base lo pone el disparador, y es el que manda — por eso este
 * número no viaja al emitir.
 */
function precios_de_productos(): array
{
    $porCodigo = [];
    foreach (listar_productos()['datos'] as $p) {
        $porCodigo[(string) $p['codigo']] = [
            'nombre'        => (string) ($p['nombre'] ?? $p['codigo']),
            'valorunitario' => (float) ($p['valorunitario'] ?? 0),
        ];
    }
    return $porCodigo;
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
