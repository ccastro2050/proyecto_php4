<?php
/**
 * IServicioUsuario — el CONTRATO de la capa de negocio de `usuario`.
 *
 * Seis operaciones. La última —`verificarContrasena`— convierte las TRES
 * respuestas del repositorio en dos caminos de negocio distintos, y eso es
 * la decisión que este contrato documenta:
 *
 *   el repositorio devuelve null  → aquí se lanza NoEncontradoExcepcion (404)
 *   el repositorio devuelve false → aquí se devuelve false (el controlador: 401)
 *
 * **Y ojo con la aparente contradicción**: la puerta de entrada
 * —`POST /api/sesion`— responde lo MISMO en los dos casos, a propósito, para
 * no confirmarle a un desconocido qué correos existen. Este endpoint sí los
 * distingue porque es para un administrador que ya entró. **El mismo hecho
 * se cuenta distinto según quién pregunte.**
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Usuario.php';

interface IServicioUsuario
{
    /**
     * Hasta $limite usuarios (solo el email).
     * @return Usuario[]
     */
    public function listar(int $limite): array;

    /** El Usuario con ese email. NoEncontradoExcepcion si no existe. */
    public function obtener(string $email): Usuario;

    /** Crea el usuario. El repositorio hashea la contraseña. */
    public function crear(array $datos): void;

    /**
     * Escribe lo que llegó (en esta tabla: la contraseña).
     * InvalidArgumentException si no llegó nada · NoEncontradoExcepcion si el
     * email no existe · devuelve filas afectadas.
     */
    public function actualizar(string $email, array $datos): int;

    /** Elimina. NoEncontradoExcepcion si no existe. */
    public function eliminar(string $email): int;

    /**
     * true si la contraseña coincide, false si no.
     * NoEncontradoExcepcion si el usuario no existe.
     */
    public function verificarContrasena(string $email, string $contrasena): bool;
}
