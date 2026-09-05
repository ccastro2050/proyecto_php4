<?php
/**
 * ConflictoDeIntegridadExcepcion — «la base dijo que no, y tenía razón».
 *
 * La lanza un repositorio cuando el motor rechaza una escritura por una
 * regla de INTEGRIDAD: una llave repetida, una referencia a algo que no
 * existe, o el intento de borrar algo de lo que otros dependen.
 *
 * El controlador la traduce a **409 Conflicto**, que es distinto de:
 *   · 422 — el body venía mal formado (eso lo ve el controlador, antes);
 *   · 404 — la ficha que se pidió no existe (eso lo decide el servicio);
 *   · 500 — algo se rompió de verdad (la base caída, un SQL mal escrito).
 *
 * Un 500 significa «tenemos un problema»; un 409 significa «su petición no
 * cabe en los datos que hay». Confundirlos hace que el usuario vea «error
 * interno» cuando lo único que pasó es que escribió un código que no existe.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class ConflictoDeIntegridadExcepcion extends RuntimeException
{
}
