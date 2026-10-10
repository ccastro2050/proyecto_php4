<?php
/**
 * ServicioUsuarioConRoles — donde viven las reglas que el motor no tiene.
 *
 * Tres, y ninguna la impone la base de datos:
 *
 * **1 · Un usuario no se crea sin rol.** El procedimiento acepta una lista
 * vacia; el negocio no, porque un usuario sin rol no puede hacer nada y solo
 * deja basura en la tabla. Es 400 y no 422: la peticion esta bien escrita, lo
 * que pide es lo que no vale.
 *
 * **2 · La lista de roles no lleva repetidos.** Dos casillas marcadas con el
 * mismo rol harian chocar la llave primaria DENTRO del procedimiento, con un
 * 500 que no es culpa de nadie. Se limpia aqui, conservando el orden.
 *
 * **3 · El PATCH conserva lo que no llega, y eso cuesta una consulta.** El
 * procedimiento `actualizar_usuario_con_roles` SIEMPRE reemplaza los roles
 * —tiene semantica de PUT—, asi que un PATCH que solo trae la contrasena
 * tiene que leer los roles de ahora y volverlos a mandar. No es un rodeo: es
 * la diferencia entre la operacion que la base de datos ofrece —reemplazar—
 * y la que la API necesita —modificar—, y se paga aqui, no retocando el
 * procedimiento.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioUsuarioConRoles.php';
require_once __DIR__ . '/../repositorios/IRepositorioUsuarioConRoles.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ServicioUsuarioConRoles implements IServicioUsuarioConRoles
{
    public function __construct(
        private readonly IRepositorioUsuarioConRoles $repositorio,
    ) {
    }

    /** El email, limpio y en minusculas (ver ServicioUsuario). */
    private function validarEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new InvalidArgumentException('El email no puede estar vacio.');
        }
        return $email;
    }

    /**
     * Al menos un rol, sin repetidos, todos positivos.
     *
     * @param int[] $idsRol
     * @return int[]
     */
    private function validarRoles(array $idsRol): array
    {
        $limpios = [];
        foreach ($idsRol as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'El id de un rol debe ser un entero mayor que cero.');
            }
            if (!in_array($id, $limpios, true)) {
                $limpios[] = $id;
            }
        }
        if ($limpios === []) {
            throw new InvalidArgumentException(
                'El usuario necesita al menos un rol: sin rol no puede hacer nada.');
        }
        return $limpios;
    }

    public function listar(): array
    {
        return $this->repositorio->listar();
    }

    public function consultar(string $email): array
    {
        return $this->repositorio->consultar($this->validarEmail($email));
    }

    public function crear(string $email, string $contrasena, array $idsRol): array
    {
        return $this->repositorio->crear(
            $this->validarEmail($email), $contrasena,
            $this->validarRoles($idsRol));
    }

    public function actualizar(string $email, ?string $contrasena, array $idsRol): array
    {
        return $this->repositorio->actualizar(
            $this->validarEmail($email), $contrasena,
            $this->validarRoles($idsRol));
    }

    public function actualizarParcial(string $email, ?string $contrasena, ?array $idsRol): array
    {
        $email = $this->validarEmail($email);

        if ($idsRol === null) {
            // Conservar los roles cuesta UNA consulta, porque el
            // procedimiento siempre los reemplaza (ver el encabezado).
            $actual = $this->repositorio->consultar($email);
            $idsRol = [];
            foreach (($actual['roles'] ?? []) as $rol) {
                if (isset($rol['idrol'])) {
                    $idsRol[] = (int) $rol['idrol'];
                }
            }
            if ($idsRol === []) {
                throw new InvalidArgumentException(
                    'El usuario no tiene roles: mande la lista de roles.');
            }
        }

        return $this->repositorio->actualizar($email, $contrasena,
                                              $this->validarRoles($idsRol));
    }

    public function eliminar(string $email): array
    {
        return $this->repositorio->eliminar($this->validarEmail($email));
    }
}
