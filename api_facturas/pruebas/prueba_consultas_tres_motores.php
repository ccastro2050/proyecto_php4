<?php
/**
 * prueba_consultas_tres_motores.php — las DIEZ consultas de la v4, contra el
 * motor que diga la variable `MOTOR`.
 *
 * Ejecutar (dentro del contenedor de la API):
 *
 *     php pruebas/prueba_consultas_tres_motores.php
 *     MOTOR=postgres  php pruebas/prueba_consultas_tres_motores.php
 *     MOTOR=sqlserver php pruebas/prueba_consultas_tres_motores.php
 *
 * ======================================================================
 * POR QUE ESTA PRUEBA EXISTE, Y POR QUE ES DISTINTA A LA DE CAPAS
 * ======================================================================
 *
 * `prueba_capas.php` corre SIN base de datos: comprueba que los servicios no
 * sepan que motor hay debajo. Esta hace lo contrario y por eso no la
 * reemplaza: **toca los tres motores de verdad**, porque lo que se quiere
 * comprobar aqui es justamente lo que cambia entre ellos.
 *
 * Dos de las diez consultas estan escritas distinto en cada motor:
 *
 *   alcance-de-usuarios   GROUP_CONCAT / STRING_AGG / subconsulta
 *   ticket-por-vendedor   el CAST a DECIMAL que en T-SQL es obligatorio
 *
 * Y el segundo es el que justifica la prueba entera: **si falta el CAST, la
 * consulta NO falla.** Responde 200 con un ticket promedio sin centavos. Un
 * error que revienta se arregla el mismo dia; uno que miente se queda meses.
 * Esta prueba lo caza porque no mira el codigo de respuesta: mira el VALOR, y
 * exige que tenga decimales distintos de cero en al menos una fila donde la
 * division no sea exacta.
 *
 * Lo que comprueba, consulta por consulta:
 *   · que corra —un error de dialecto revienta aqui y no en la pantalla—;
 *   · que devuelva las columnas del contrato, con ESE nombre;
 *   · y en las dos marcadas, que el valor sea el correcto y no solo un valor.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/ensamblador.php';

$motor = motorActivo();
echo "\n  Motor activo: $motor\n";
echo "  " . str_repeat("-", 72) . "\n";

$servicio = crearServicioConsultas();

/**
 * Las diez, con las columnas que CADA UNA tiene que devolver.
 *
 * La lista de columnas es el contrato. Sin ella la prueba solo diria «no
 * revento», que es la mitad de lo que hace falta saber: una consulta puede
 * correr y devolver otra cosa —un alias cambiado deja la pantalla en blanco
 * sin un solo error—.
 */
$esperado = [
    'ventasPorProducto'     => ['codigo', 'nombre', 'unidades', 'ingreso', 'facturas', 'clientes'],
    'ventasPorCliente'      => ['id', 'cliente', 'email', 'facturas', 'comprado', 'unidades'],
    'ventasPorVendedor'     => ['id', 'vendedor', 'carnet', 'facturas', 'vendido'],
    'ventasPorEmpresa'      => ['codigo', 'empresa', 'clientes', 'facturas', 'facturado'],
    'ticketPorVendedor'     => ['vendedor', 'facturas', 'total', 'ticket_promedio'],
    'productosSinVender'    => ['codigo', 'nombre', 'stock', 'valorunitario'],
    'anulacionesPorCliente' => ['cliente', 'anuladas', 'valor_anulado'],
    'alcanceDeUsuarios'     => ['email', 'roles', 'interfaces', 'sus_roles'],
    'interfacesSinUsuarios' => ['id', 'ruta', 'descripcion', 'roles_con_acceso', 'usuarios_con_acceso'],
    'creditoContraConsumo'  => ['cliente', 'empresa', 'credito', 'consumido', 'disponible'],
];

$fallos = 0;
$vacias = [];

foreach ($esperado as $metodo => $columnas) {
    try {
        $filas = $servicio->$metodo();
    } catch (Throwable $e) {
        printf("  %-24s REVENTO  %s\n", $metodo, $e->getMessage());
        $fallos++;
        continue;
    }

    // Cero filas NO es un fallo: tres de las diez preguntan por lo que NO
    // existe —productos sin vender, facturas anuladas, rutas sin usuarios— y
    // con los datos sembrados pueden estar vacias con razon. Pero se anota:
    // una consulta vacia no prueba sus columnas.
    if ($filas === []) {
        printf("  %-24s %4d filas  (vacia: no se pudieron revisar columnas)\n",
               $metodo, 0);
        $vacias[] = $metodo;
        continue;
    }

    $faltan = array_diff($columnas, array_keys($filas[0]));
    if ($faltan !== []) {
        printf("  %-24s %4d filas  FALTAN COLUMNAS: %s\n",
               $metodo, count($filas), implode(', ', $faltan));
        $fallos++;
        continue;
    }

    printf("  %-24s %4d filas  %d columnas, todas las del contrato\n",
           $metodo, count($filas), count($filas[0]));
}

// ======================================================================
// LA COMPROBACION QUE NO SE PUEDE HACER MIRANDO EL CODIGO DE RESPUESTA
// ======================================================================
echo "  " . str_repeat("-", 72) . "\n";

$ticket = $servicio->ticketPorVendedor();
if ($ticket === []) {
    echo "  ticket_promedio: no hay facturas activas, no se pudo comprobar\n";
} else {
    // Se busca una fila donde el promedio NO sea exacto. Si el motor truncara
    // —T-SQL sin el CAST—, ese valor llegaria sin centavos y la resta con su
    // propio redondeo daria cero en TODAS las filas.
    $hayCentavos = false;
    foreach ($ticket as $fila) {
        $promedio = (float) $fila['ticket_promedio'];
        $exacto   = (float) $fila['total'] / max(1, (int) $fila['facturas']);
        if (abs($promedio - round($promedio)) > 0.0001) {
            $hayCentavos = true;
        }
        // Y que el promedio sea el que es: total / facturas, con 2 decimales.
        if (abs($promedio - round($exacto, 2)) > 0.011) {
            printf("  ticket_promedio MAL en %s: dice %s y deberia ser %.2f\n",
                   $fila['vendedor'], $fila['ticket_promedio'], $exacto);
            $fallos++;
        }
    }
    printf("  ticket_promedio: %d vendedores, el promedio coincide con total/facturas%s\n",
           count($ticket),
           $hayCentavos ? " (y hay centavos: no truncó)" : " (todos exactos: no prueba el truncado)");
}

// Y el agregado de texto, que es la otra que cambia de motor.
$alcance = $servicio->alcanceDeUsuarios();
if ($alcance !== []) {
    $conRoles = 0;
    foreach ($alcance as $fila) {
        if (is_string($fila['sus_roles']) && $fila['sus_roles'] !== '') {
            $conRoles++;
        }
    }
    printf("  sus_roles: %d de %d filas traen el texto pegado por el motor\n",
           $conRoles, count($alcance));
    if ($conRoles !== count($alcance)) {
        $fallos++;
    }
}

echo "  " . str_repeat("-", 72) . "\n";
if ($fallos === 0) {
    printf("  Las diez consultas pasan contra %s%s\n\n", $motor,
           $vacias === [] ? "" : " (" . count($vacias) . " vacías con los datos de hoy)");
    exit(0);
}
printf("  %d FALLOS contra %s\n\n", $fallos, $motor);
exit(1);
