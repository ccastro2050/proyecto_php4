<?php
/**
 * conflictos.php — CÓMO SE LE EXPLICA AL USUARIO que la base dijo que no.
 *
 * ======================================================================
 * ESTE ARCHIVO NACIÓ EN LA v3, Y NACIÓ PARTIENDO OTRO EN TRES
 * ======================================================================
 *
 * En la v2 había un solo archivo, `errores_de_integridad.php`, que hacía dos
 * cosas a la vez: **reconocer** el problema (mirando los códigos 1062, 1452 y
 * 1451 de MariaDB) y **redactarlo** en español.
 *
 * Mientras hubo un motor, eso estaba bien. Al llegar el segundo se vio que
 * eran dos trabajos distintos, porque uno cambia con el motor y el otro no:
 *
 * | | ¿Cambia con el motor? |
 * |---|---|
 * | Reconocer que fue una llave duplicada | **Sí**: MariaDB dice `1062`, PostgreSQL dice `23505` |
 * | Explicárselo al usuario | **No**: «Ya existe … con esa llave» es la misma frase |
 *
 * Así que ahora son tres archivos:
 *
 *   · **este** — las tres frases, una sola vez;
 *   · `errores_de_integridad_mariadb.php` — los números de MariaDB;
 *   · `errores_de_integridad_postgres.php` — los de PostgreSQL.
 *
 * Y la razón por la que importa: si cada motor redactara sus propios
 * mensajes, la MISMA petición contra dos motores le diría cosas distintas al
 * usuario. El contrato de la API promete un texto, no un código — y un
 * contrato que cambia según lo que haya detrás no es un contrato.
 *
 * **Ésta es la clase de costura que solo se ve cuando llega el segundo
 * motor.** No es que la v2 estuviera mal: es que con un solo motor no había
 * forma de saber dónde estaba la frontera.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';

/**
 * Ya existe una fila con esa llave primaria.
 *
 * @param string $ficha Cómo se llama esto en el idioma del usuario ("el cliente").
 */
function conflictoLlaveDuplicada(string $ficha): never
{
    throw new ConflictoDeIntegridadExcepcion(
        "Ya existe $ficha con esa llave. Las llaves no se repiten."
    );
}

/** Se apuntó a un código que no existe (crear o actualizar). */
function conflictoReferenciaRota(string $ficha): never
{
    throw new ConflictoDeIntegridadExcepcion(
        "Alguno de los códigos a los que apunta $ficha no existe. "
        . "Revise que la persona o la empresa estén creadas."
    );
}

/** Hay otras filas que dependen de ésta (eliminar). */
function conflictoTieneDependientes(string $ficha): never
{
    throw new ConflictoDeIntegridadExcepcion(
        "No se puede eliminar $ficha porque hay otras fichas que dependen "
        . "de ella. Elimine primero las que la usan."
    );
}
