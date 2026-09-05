<?php
/**
 * La traducción de los errores de integridad de SQL SERVER.
 *
 * El tercero de la familia. Compare los tres archivos
 * `errores_de_integridad_*.php` de esta carpeta: hacen exactamente lo mismo y
 * los tres se escriben distinto.
 *
 * ======================================================================
 * LOS TRES MOTORES, LADO A LADO
 * ======================================================================
 *
 * |  | MariaDB | PostgreSQL | SQL Server |
 * |---|---|---|---|
 * | Dónde está el código útil | `errorInfo[1]` | `getCode()` | **`errorInfo[1]`**, como MariaDB |
 * | Llave duplicada | `1062` | `23505` | `2627` (y `2601` para un índice único) |
 * | Apuntar a algo que no existe | `1452` | `23503` | `547` |
 * | Borrar algo con dependientes | `1451` | `23503` — el mismo | **`547` — el mismo** |
 * | Cómo se distinguen esos dos | por el número | por el texto | **por el texto** |
 *
 * Tres motores, tres formas de decir lo mismo. Y fíjese en el patrón: **dos
 * de los tres usan un solo código para los dos sentidos de una llave
 * foránea.** MariaDB, que los separa, resultó ser la excepción — lo cual
 * habría sido imposible de saber con un solo motor.
 *
 * ======================================================================
 * EL DETALLE DE SQL SERVER QUE HAY QUE MIRAR
 * ======================================================================
 *
 * Los dos sentidos se distinguen por qué constraint menciona el mensaje:
 *
 *     …conflicted with the FOREIGN KEY constraint…   → al INSERTAR: apunta a
 *                                                      algo que no existe
 *     …conflicted with the REFERENCE constraint…     → al BORRAR: hay filas
 *                                                      que dependen de ésta
 *
 * Es la misma fragilidad que en PostgreSQL —depender del texto— y se acepta
 * por la misma razón, con la misma condición: **queda en un solo sitio**.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/conflictos.php';

/**
 * Convierte una PDOException de SQL Server en una excepción entendible.
 *
 * @param string $ficha Cómo se llama esto en el idioma del usuario ("el cliente").
 */
function traducirErrorSqlServer(PDOException $error, string $ficha): never
{
    // Igual que en MariaDB, el SQLSTATE es genérico ('23000' para todas las
    // violaciones de integridad) y el número útil está en errorInfo[1].
    $codigo = (int) ($error->errorInfo[1] ?? 0);

    // 2627 — violación de PRIMARY KEY · 2601 — de un índice único.
    if ($codigo === 2627 || $codigo === 2601) {
        conflictoLlaveDuplicada($ficha);
    }

    // 547 — violación de llave foránea, en CUALQUIERA de los dos sentidos.
    // Hay que mirar el mensaje para saber cuál de los dos fue.
    if ($codigo === 547) {
        if (str_contains($error->getMessage(), 'REFERENCE constraint')) {
            conflictoTieneDependientes($ficha);
        }
        conflictoReferenciaRota($ficha);
    }

    // No es un problema de integridad conocido: que suba y se vuelva 500.
    throw $error;
}
