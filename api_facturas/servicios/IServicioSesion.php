<?php
/**
 * IServicioSesion — el CONTRATO de la sesión.
 *
 * Tres operaciones, y las dos primeras devuelven `array|null`: la sesión o
 * nada. Que ese «nada» sea un 401 lo decide el controlador; aquí no se sabe
 * que existe HTTP.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

interface IServicioSesion
{
    /**
     * La sesión `['token', 'email', 'roles', 'expira']`, o null si las
     * credenciales no sirven —y no se dice cuál de las dos falló—.
     */
    public function entrar(string $email, string $contrasena): ?array;

    /** Un token nuevo, con los roles de HOY. No pide contraseña. */
    public function renovar(string $email): ?array;

    /**
     * Las rutas a las que este correo puede entrar, para el menú.
     * @return string[]
     */
    public function misRutas(string $email): array;
}
