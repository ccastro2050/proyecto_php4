<?php
/**
 * ServicioSesion — comprueba las credenciales y arma el token.
 *
 * Dos decisiones viven aquí, y las dos son del dominio:
 *
 * **1 · UN SOLO ERROR PARA LOS DOS CASOS.** Si el correo no existe y si la
 * contraseña está mal, la respuesta es la misma. Decir «ese correo no existe»
 * le confirma a un desconocido CUÁLES SÍ existen — y con una lista de correos
 * válidos, probar contraseñas empieza a valer la pena.
 *
 * Ojo con la aparente contradicción: `POST /api/usuario/verificar-contrasena`
 * SÍ distingue 404 de 401. Y está bien que lo haga, porque ese endpoint es
 * para un administrador que ya entró, no para la puerta de la calle. **El
 * mismo hecho se cuenta distinto según quién pregunte.**
 *
 * **2 · EL TOKEN LLEVA EL CORREO Y LOS ROLES, Y NADA MÁS.** No lleva permisos
 * —se consultan al usar— ni datos personales: el contenido de un JWT se lee
 * sin ninguna clave (ver `autorizacion/jwt.php`).
 *
 * Y una cosa que este servicio NO hace: no sabe nada de HTTP. Devuelve la
 * sesión o `null`; convertir ese `null` en 401 es trabajo del controlador.
 *
 * TRES REPOSITORIOS, y es el primero de la API que necesita más de uno: el de
 * usuario para la contraseña, el del puente para los roles y el de rol para
 * los nombres. Se ve que la fábrica no sufre — son tres llamadas al mismo
 * ensamblador.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioSesion.php';
require_once __DIR__ . '/../repositorios/IRepositorioUsuario.php';
require_once __DIR__ . '/../repositorios/IRepositorioRolUsuario.php';
require_once __DIR__ . '/../repositorios/IRepositorioAcceso.php';
require_once __DIR__ . '/../autorizacion/jwt.php';

class ServicioSesion implements IServicioSesion
{
    public function __construct(
        private readonly IRepositorioUsuario $usuarios,
        private readonly IRepositorioRolUsuario $rolesDeUsuario,
        private readonly IRepositorioAcceso $acceso,
    ) {
    }

    public function entrar(string $email, string $contrasena): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $contrasena === '') {
            return null;
        }

        // El repositorio compara el HASH, nunca la cadena. Devuelve:
        //   null  -> el correo no existe
        //   false -> existe y la contraseña no coincide
        //   true  -> es
        $resultado = $this->usuarios->verificarContrasena($email, $contrasena);

        // LOS DOS CASOS MALOS SE COLAPSAN EN UNO. Quien llama no puede
        // distinguirlos, y eso es lo que se quiere.
        if ($resultado !== true) {
            return null;
        }

        return $this->armar($email);
    }

    public function renovar(string $email): ?array
    {
        // AQUÍ NO SE PIDE CONTRASEÑA, y no es un descuido: quien llama ya
        // trajo un token válido, y validarlo es exactamente comprobar que en
        // su momento dio la contraseña correcta. Pedirla otra vez sería no
        // creerle al token que la API misma firmó.
        //
        // Lo que SÍ se vuelve a leer son LOS ROLES: si a alguien le quitaron
        // uno, el token nuevo sale sin él. Por eso renovar no es solo correr
        // la fecha.
        $email = strtolower(trim($email));
        return $email === '' ? null : $this->armar($email);
    }

    public function misRutas(string $email): array
    {
        return $this->acceso->rutasPermitidas(strtolower(trim($email)));
    }

    /** La sesión completa: token, correo, roles y vencimiento. */
    private function armar(string $email): array
    {
        $roles = $this->nombresDeRoles($email);
        [$token, $expira] = jwt_firmar($email, $roles);

        return [
            'token'  => $token,
            'email'  => $email,
            'roles'  => $roles,
            // En ISO 8601 y no el número de `time()`: una fecha que una
            // persona pueda leer en la respuesta.
            'expira' => date('c', $expira),
        ];
    }

    /**
     * Los NOMBRES de los roles, no sus ids.
     *
     * El menú de la interfaz le habla a una persona, y «3» no le dice nada a
     * nadie. El puente ya trae el nombre con su JOIN, así que no hace falta
     * una consulta más.
     *
     * @return string[]
     */
    private function nombresDeRoles(string $email): array
    {
        $nombres = [];
        foreach ($this->rolesDeUsuario->obtenerPorLadoA($email) as $fila) {
            if (isset($fila['rol']) && $fila['rol'] !== '') {
                $nombres[] = (string) $fila['rol'];
            }
        }
        return $nombres;
    }
}
