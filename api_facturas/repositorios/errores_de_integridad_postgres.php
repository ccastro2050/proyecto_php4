<?php
/**
 * La traducción de los errores de integridad de POSTGRESQL.
 *
 * Es el gemelo de `errores_de_integridad_mariadb.php`, y compararlos vale
 * más que cualquier explicación: **hacen lo mismo y no se parecen**.
 *
 * ======================================================================
 * LO QUE CAMBIA, Y ES MÁS DE LO QUE UNO ESPERARÍA
 * ======================================================================
 *
 * |  | MariaDB | PostgreSQL |
 * |---|---|---|
 * | Dónde está el código útil | `errorInfo[1]` (el SQLSTATE no sirve: todo es `23000`) | **`getCode()`**, que sí distingue |
 * | Llave duplicada | `1062` | `23505` |
 * | Apuntar a algo que no existe | `1452` | `23503` |
 * | Borrar algo de lo que otros dependen | `1451` | **`23503` — el MISMO** |
 *
 * Fíjese en la última fila, porque es la que obliga a escribir código
 * distinto: **PostgreSQL usa un solo código para los dos sentidos de una
 * llave foránea.** MariaDB los separa; PostgreSQL no. Lo único que los
 * distingue es el TEXTO del mensaje:
 *
 *     …is not present in table…      → se apuntó a algo que no existe
 *     …is still referenced from…     → hay filas que dependen de ésta
 *
 * Y mirar el texto de un mensaje es frágil: si mañana PostgreSQL lo redacta
 * distinto, o si el servidor está en otro idioma, esto deja de funcionar.
 *
 * **Se acepta, y se dice en voz alta en vez de disimularlo.** La alternativa
 * —hacer una consulta extra para averiguar si la fila tenía dependientes—
 * costaría un viaje a la base en cada error y volvería a abrir la carrera que
 * este diseño evita. Queda aquí, en un solo sitio, y con su fragilidad
 * escrita para que quien la encuentre rota sepa dónde mirar.
 *
 * ======================================================================
 * LA LECCIÓN DE LA v3, EN UNA FRASE
 * ======================================================================
 *
 * Dos motores que hacen lo mismo NO exponen la misma información de la misma
 * manera. Por eso el detalle del motor tiene que quedar encerrado en una
 * clase por motor: no porque quede bonito, sino porque **no hay forma de
 * escribir un solo código que sirva para los dos sin mentir en alguno**.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/conflictos.php';

/**
 * Convierte una PDOException de PostgreSQL en una excepción entendible.
 *
 * @param string $ficha Cómo se llama esto en el idioma del usuario ("el cliente").
 */
function traducirErrorPostgres(PDOException $error, string $ficha): never
{
    // En PostgreSQL el SQLSTATE sí es específico, y llega en getCode().
    $codigo = (string) $error->getCode();

    // 23505 — unique_violation: ya hay una fila con esa llave.
    if ($codigo === '23505') {
        conflictoLlaveDuplicada($ficha);
    }

    // 23503 — foreign_key_violation, en CUALQUIERA de los dos sentidos.
    // Hay que mirar el mensaje para saber cuál de los dos fue (ver arriba).
    if ($codigo === '23503') {
        if (str_contains($error->getMessage(), 'still referenced')) {
            conflictoTieneDependientes($ficha);
        }
        conflictoReferenciaRota($ficha);
    }

    // No es un problema de integridad conocido: que suba y se vuelva 500.
    throw $error;
}
