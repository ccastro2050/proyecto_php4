<?php
/**
 * El token: cómo se firma y cómo se lee. **Escrito a mano, sin librería.**
 *
 * ======================================================================
 * UN JWT NO ESTÁ CIFRADO: ESTÁ FIRMADO
 * ======================================================================
 *
 * Cualquiera puede pegar el token en jwt.io y leer su contenido sin ninguna
 * clave — son tres pedazos de base64 separados por puntos. Lo que la clave
 * garantiza es otra cosa: que nadie pueda FABRICAR uno ni cambiarle una
 * letra sin que la firma deje de cuadrar.
 *
 * De ahí la regla: **en el token va lo que identifica, nunca lo que es
 * secreto.**
 *
 * ======================================================================
 * POR QUÉ ESCRITO A MANO Y NO CON `firebase/php-jwt`
 * ======================================================================
 *
 * Este proyecto **no usa Composer** —ninguna de las cuatro versiones lo
 * necesitó— y traerlo solo para esto costaría más de lo que resuelve: un
 * `vendor/` de cientos de archivos, un `composer.lock` que hay que explicar,
 * y una dependencia que el estudiante no puede leer.
 *
 * Y lo que hay que escribir son **cuarenta líneas**: dos `json_encode`, un
 * `hash_hmac` y un base64 con tres caracteres cambiados. Verlas es entender
 * qué es un JWT; instalar la librería es confiar en que lo hace bien.
 *
 * **En un sistema de producción se usa la librería**, y por una razón
 * concreta: ahí hay casos que esto no cubre —otros algoritmos, rotación de
 * claves, `kid`, validación de `aud`/`iss` múltiples— y cada uno es una
 * oportunidad de equivocarse. Lo de aquí es el mínimo correcto, no lo
 * completo.
 *
 * ======================================================================
 * LO QUE LLEVA ESTE TOKEN, Y LO QUE NO
 * ======================================================================
 *
 * Lleva el correo (`sub`), los NOMBRES de los roles y el vencimiento (`exp`).
 * No lleva la contraseña —ni en hash—, ni datos personales, ni los PERMISOS.
 *
 * Lo de los permisos es la decisión importante y no un olvido: **el permiso
 * NO va en el token.** Se consulta en cada petición con
 * `verificar_acceso_ruta`. Cuesta una consulta por operación, y es lo que
 * hace que quitarle un permiso a un rol surta efecto DE INMEDIATO, sin
 * esperar a que el token venza. Si el permiso viajara dentro, la persona
 * seguiría entrando con el permiso que ya no tiene.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

/**
 * La clave con la que se FIRMA, desde el entorno.
 *
 * El valor por omisión sirve para el aula y se nota que lo es. En un sistema
 * de verdad, sin `JWT_CLAVE` puesta, esto debería negarse a arrancar.
 */
function jwt_clave(): string
{
    $clave = (string) getenv('JWT_CLAVE');
    return $clave !== '' ? $clave : 'php4-clave-de-aula-cambieme-en-produccion';
}

/** Cuántos minutos dura un token. Corto no es incómodo: hay renovación. */
function jwt_minutos(): int
{
    $minutos = (int) getenv('JWT_MINUTOS');
    return $minutos > 0 ? $minutos : 60;
}

/**
 * Base64 **para URL**: el estándar del JWT (RFC 7515) cambia tres cosas del
 * base64 de siempre — `+` por `-`, `/` por `_`, y se quita el relleno `=`.
 *
 * No es un capricho: `+` y `/` tienen significado dentro de una URL, y el
 * token viaja en cabeceras y a veces en direcciones.
 */
function jwt_base64_codificar(string $texto): string
{
    return rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
}

/** El inverso: se devuelve el relleno que se había quitado. */
function jwt_base64_decodificar(string $texto): string
{
    $texto = strtr($texto, '-_', '+/');
    // El base64 necesita que el largo sea múltiplo de 4: se rellena con '='.
    return (string) base64_decode(str_pad($texto, strlen($texto) % 4 === 0
        ? strlen($texto)
        : strlen($texto) + 4 - strlen($texto) % 4, '='), true);
}

/**
 * Firma un token y devuelve `[token, cuándo expira]`.
 *
 * Las tres partes de un JWT, en orden:
 *   1. el ENCABEZADO: qué algoritmo lo firma;
 *   2. la CARGA: lo que el token afirma (aquí: quién, con qué roles, hasta cuándo);
 *   3. la FIRMA: HMAC-SHA256 de las dos primeras, con la clave.
 *
 * @param string[] $roles
 * @return array{0: string, 1: int}
 */
function jwt_firmar(string $email, array $roles): array
{
    $expira = time() + jwt_minutos() * 60;

    $encabezado = jwt_base64_codificar(json_encode(
        ['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_UNICODE));

    $carga = jwt_base64_codificar(json_encode([
        'sub'   => $email,     // el sujeto: de quién habla el token
        'roles' => $roles,     // los NOMBRES, para que el menú hable claro
        'iat'   => time(),     // cuándo se emitió
        'exp'   => $expira,    // hasta cuándo vale
    ], JSON_UNESCAPED_UNICODE));

    // hash_hmac con el cuarto parámetro en true devuelve los BYTES crudos,
    // no el texto hexadecimal: el JWT firma sobre los bytes.
    $firma = jwt_base64_codificar(
        hash_hmac('sha256', "$encabezado.$carga", jwt_clave(), true));

    return ["$encabezado.$carga.$firma", $expira];
}

/**
 * Comprueba la firma y el vencimiento, y devuelve la carga.
 *
 * Lanza `InvalidArgumentException` si el token no sirve —mal formado,
 * alterado, firmado con otra clave o vencido—. **Quien llama no necesita
 * distinguir el motivo**: los cuatro casos son el mismo 401, y decir cuál
 * fue le daría pistas a quien está probando.
 */
function jwt_leer(string $token): array
{
    $partes = explode('.', $token);
    if (count($partes) !== 3) {
        throw new InvalidArgumentException('El token no tiene tres partes.');
    }
    [$encabezado, $carga, $firma] = $partes;

    // La firma se vuelve a calcular y se compara.
    $esperada = jwt_base64_codificar(
        hash_hmac('sha256', "$encabezado.$carga", jwt_clave(), true));

    // hash_equals y NO `===`, y esto sí importa: la comparación normal se
    // detiene en el primer carácter distinto, así que su DURACIÓN delata
    // cuántos caracteres acertó quien lo intenta. `hash_equals` tarda lo
    // mismo siempre. Es un ataque de tiempo, y es real.
    if (!hash_equals($esperada, $firma)) {
        throw new InvalidArgumentException('La firma del token no cuadra.');
    }

    $datos = json_decode(jwt_base64_decodificar($carga), true);
    if (!is_array($datos)) {
        throw new InvalidArgumentException('La carga del token no es un JSON válido.');
    }

    // El vencimiento se comprueba AQUÍ, no en quien llama: si se dejara
    // afuera, el día que alguien olvide mirarlo los tokens no caducarían.
    if (!isset($datos['exp']) || (int) $datos['exp'] < time()) {
        throw new InvalidArgumentException('El token venció.');
    }

    return $datos;
}
