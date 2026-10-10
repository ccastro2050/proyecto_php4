<?php
/**
 * IRepositorioAcceso — las DOS preguntas del control de acceso.
 *
 * Es un repositorio aparte y no un metodo mas en el de usuario, porque la
 * pregunta «puede este usuario entrar aqui?» no es del CRUD de usuario: es
 * del acceso. Cruza tres tablas y la responde un procedimiento.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

interface IRepositorioAcceso
{
    /**
     * Tiene este correo acceso a esta ruta?
     *
     * Lo responde `verificar_acceso_ruta`, que YA EXISTE en la base de datos
     * y cruza usuario -> rol_usuario -> rutarol. La API no arma ese JOIN:
     * repetirlo en PHP dejaria la regla en dos sitios.
     */
    public function tieneAcceso(string $email, string $nombreRuta): bool;

    /**
     * Las rutas a las que este correo SI puede entrar.
     *
     * Solo sirve para que la interfaz arme su menu. **NO es el control de
     * acceso**: esconder una entrada del menu no protege nada, y quien
     * escriba la direccion a mano entra igual si el servidor no comprueba.
     *
     * @return string[]
     */
    public function rutasPermitidas(string $email): array;
}
