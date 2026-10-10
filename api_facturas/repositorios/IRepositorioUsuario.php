<?php
/**
 * IRepositorioUsuario — el CONTRATO de la capa de datos de `usuario`.
 *
 * Tiene SEIS métodos, uno más que el molde: `verificarContrasena`. Y está
 * aquí —y no en el servicio— por una razón de diseño que conviene tener
 * clara:
 *
 * **El hash es un detalle de CÓMO SE PERSISTE el secreto.** Quien decide
 * guardar bcrypt con costo 12 es la capa de datos; el negocio solo quiere
 * saber si la contraseña coincide. Si mañana el hash cambia a argon2, cambian
 * estos tres repositorios y nada más.
 *
 * Corolario: **el hash NUNCA sale de esta capa.** No hay un
 * `obtenerHash()` a propósito — devolverlo sería repartir el secreto por
 * toda la aplicación.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Usuario.php';

interface IRepositorioUsuario
{
    /**
     * Hasta $limite usuarios, ordenados por email. **Solo el email**: ningún
     * SELECT de esta interfaz proyecta la contraseña.
     * @return Usuario[]
     */
    public function obtenerTodos(int $limite): array;

    /** El Usuario con ese email (sin contraseña), o null si no existe. */
    public function obtenerPorClave(string $email): ?Usuario;

    /** Inserta. Recibe la contraseña EN CLARO y la hashea aquí. */
    public function crear(Usuario $usuario): bool;

    /**
     * Escribe los campos de $datos. Si viene `contrasena`, la rehashea.
     * Devuelve filas afectadas (0 = el email no existe).
     */
    public function actualizar(string $email, array $datos): int;

    /** Elimina. Devuelve filas eliminadas (0 = no existía). */
    public function eliminar(string $email): int;

    /**
     * ¿Coincide la contraseña?
     *
     * Devuelve TRES valores, no dos, y la diferencia importa:
     *   null  → el usuario no existe   (el servicio lo vuelve 404)
     *   false → existe y no coincide   (el servicio lo vuelve 401)
     *   true  → coincide
     *
     * «No coincide» y «no existe» son hechos distintos, y quien llama
     * necesita poder separarlos — aunque la puerta de entrada decida después
     * responder lo mismo en los dos casos.
     */
    public function verificarContrasena(string $email, string $contrasena): ?bool;
}
