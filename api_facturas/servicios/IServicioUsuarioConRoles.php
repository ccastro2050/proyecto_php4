<?php
/**
 * IServicioUsuarioConRoles — la capa de NEGOCIO.
 *
 * Cinco operaciones, las mismas cinco del repositorio. Y conviene explicar
 * por que existe esta capa si casi no hace mas que pasar la pelota: porque
 * **aqui vive la regla de que un usuario no se crea sin rol**. Esa regla no
 * es del motor —el procedimiento acepta una lista vacia sin chistar— ni de
 * HTTP. Es del dominio, y este es su sitio.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IServicioUsuarioConRoles
{
    /** @return array[] */
    public function listar(): array;

    /** NoEncontradoExcepcion si el usuario no existe. */
    public function consultar(string $email): array;

    /** InvalidArgumentException si no llega ningun rol. */
    public function crear(string $email, string $contrasena, array $idsRol): array;

    /** Reemplaza los roles; la contrasena solo si llega. */
    public function actualizar(string $email, ?string $contrasena, array $idsRol): array;

    /**
     * Lo del PATCH: si $idsRol es null, CONSERVA los roles de hoy.
     */
    public function actualizarParcial(string $email, ?string $contrasena, ?array $idsRol): array;

    /** NoEncontradoExcepcion si el usuario no existe. */
    public function eliminar(string $email): array;
}
