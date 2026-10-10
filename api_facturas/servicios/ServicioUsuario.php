<?php
/**
 * ServicioUsuario — la capa de NEGOCIO de `usuario`.
 *
 * Lo que este archivo NO sabe, a propósito:
 *
 *   · **No sabe que existe bcrypt.** Recibe la contraseña en claro y la pasa;
 *     quién la hashea y con qué costo es decisión de la capa de datos. Si
 *     mañana se cambia a argon2, aquí no se toca una línea.
 *   · **No sabe que existe HTTP.** No devuelve 404: lanza
 *     `NoEncontradoExcepcion`. Traducir eso a un código es del controlador.
 *
 * Y la regla propia del recurso: **crear un usuario que ya existe es 409, no
 * 500.** Se comprueba ANTES de llamar al repositorio, y eso ahorra algo que
 * no es obvio — el hash cuesta unos 250 ms con costo 12, y hashear para que
 * la base de datos rechace el INSERT es trabajo tirado a la basura.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioUsuario.php';
require_once __DIR__ . '/../repositorios/IRepositorioUsuario.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../excepciones/ConflictoDeIntegridadExcepcion.php';
require_once __DIR__ . '/../modelos/Usuario.php';

class ServicioUsuario implements IServicioUsuario
{
    public function __construct(
        private readonly IRepositorioUsuario $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones de negocio
    // ------------------------------------------------------------------

    /**
     * El email, limpio y en minúsculas.
     *
     * Lo de las minúsculas no es cosmética: la columna es un VARCHAR y la
     * base de datos distingue mayúsculas, así que `Admin@correo.com` y
     * `admin@correo.com` serían DOS usuarios. Quien escribe su correo no
     * piensa en eso.
     */
    private function validarClave(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new InvalidArgumentException('El email no puede estar vacío.');
        }
        return $email;
    }

    // ------------------------------------------------------------------
    // Operaciones
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        if ($limite <= 0) {
            throw new InvalidArgumentException('El límite debe ser un entero mayor que cero.');
        }
        return $this->repositorio->obtenerTodos($limite);
    }

    public function obtener(string $email): Usuario
    {
        $email = $this->validarClave($email);
        $usuario = $this->repositorio->obtenerPorClave($email);
        if ($usuario === null) {
            throw new NoEncontradoExcepcion("No existe un usuario con email = $email");
        }
        return $usuario;
    }

    public function crear(array $datos): void
    {
        $email = $this->validarClave((string) $datos['email']);

        // Se pregunta ANTES de hashear: un email repetido es la llave
        // primaria chocando, y eso es 409. Dejar que lo descubra la base de
        // datos costaría los 250 ms del hash para nada.
        if ($this->repositorio->obtenerPorClave($email) !== null) {
            throw new ConflictoDeIntegridadExcepcion(
                "Ya existe un usuario con email = $email");
        }

        // La contraseña viaja EN CLARO hasta el repositorio, que la hashea.
        // Suena incómodo y es lo correcto: el negocio no decide el algoritmo.
        $this->repositorio->crear(new Usuario($email, (string) $datos['contrasena']));
    }

    public function actualizar(string $email, array $datos): int
    {
        $email = $this->validarClave($email);
        // En esta tabla no hay nada más que cambiar: un PATCH sin contraseña
        // no tiene sentido de negocio.
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ninguna contraseña para actualizar.');
        }
        $filas = $this->repositorio->actualizar($email, $datos);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe un usuario con email = $email");
        }
        return $filas;
    }

    public function eliminar(string $email): int
    {
        $email = $this->validarClave($email);
        $filas = $this->repositorio->eliminar($email);
        if ($filas === 0) {
            throw new NoEncontradoExcepcion("No existe un usuario con email = $email");
        }
        return $filas;
    }

    public function verificarContrasena(string $email, string $contrasena): bool
    {
        $email = $this->validarClave($email);
        $resultado = $this->repositorio->verificarContrasena($email, $contrasena);

        // Las TRES respuestas del repositorio se vuelven dos caminos:
        if ($resultado === null) {
            throw new NoEncontradoExcepcion("No existe un usuario con email = $email");
        }
        return $resultado;
    }
}
