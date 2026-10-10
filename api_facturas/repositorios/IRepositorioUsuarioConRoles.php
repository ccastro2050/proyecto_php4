<?php
/**
 * IRepositorioUsuarioConRoles — CINCO operaciones, una por procedimiento.
 *
 * LO QUE EL PROCEDIMIENTO NO SABE, y por eso lo hace el repositorio: el
 * procedimiento guarda la contrasena TAL CUAL se le entrega. El hash es cosa
 * de la capa de datos de la API, igual que en el repositorio de usuario. Si
 * se le pasara la contrasena en claro, quedaria en claro en la tabla.
 *
 * Las lecturas devuelven arrays: lo que estos procedimientos entregan es un
 * JSON con el usuario y su lista de roles —`{email, roles: [...]}`—, y eso no
 * es una fila de ninguna tabla.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IRepositorioUsuarioConRoles
{
    /** `listar_usuarios_con_roles` — todos, con sus roles. @return array[] */
    public function listar(): array;

    /** `consultar_usuario_con_roles` — uno. Lanza NoEncontradoExcepcion. */
    public function consultar(string $email): array;

    /** `crear_usuario_con_roles` — el usuario y sus roles, en UNA transaccion. */
    public function crear(string $email, string $contrasena, array $idsRol): array;

    /** `actualizar_usuario_con_roles` — reemplaza roles y, si llega, la clave. */
    public function actualizar(string $email, ?string $contrasena, array $idsRol): array;

    /** `eliminar_usuario_con_roles` — el usuario Y sus asignaciones. */
    public function eliminar(string $email): array;
}
