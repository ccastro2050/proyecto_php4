<?php
/**
 * Usuario — el MODELO de `usuario`: una fila de la tabla vista como objeto.
 *
 * ESTE MODELO TIENE UNA PROPIEDAD QUE NO SALE NUNCA, y es la lección del
 * recurso: `contrasena` existe para viajar HACIA la base de datos, no desde
 * ella. Por eso:
 *
 *   · `toArray()` devuelve SOLO el email. Una contraseña que viaja en una
 *     respuesta ya está comprometida — y da igual que vaya en hash: el hash
 *     también se puede atacar con un diccionario, con calma y sin que nadie
 *     se entere.
 *   · no hay `getContrasena()` público: el único que necesita leerla es el
 *     repositorio al insertar, y para eso está `getContrasenaParaGuardar()`,
 *     cuyo nombre largo es a propósito — que incomode usarlo en otro sitio.
 *
 * Y la otra rareza: **la llave primaria es el email**, un texto que el
 * cliente conoce. Por eso el POST sí la lleva, al contrario de `rol` y
 * `ruta`.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Usuario
{
    private string $email;        // la llave primaria (texto)
    private string $contrasena;   // EN CLARO al crear, en hash al leer

    public function __construct(string $email, string $contrasena = '')
    {
        $this->email = $email;
        $this->contrasena = $contrasena;
    }

    // ------------------------------------------------------------------
    // GETTERS
    // ------------------------------------------------------------------

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * La contraseña tal como está en el objeto. **Solo para el repositorio**,
     * que la hashea antes de guardarla.
     *
     * El nombre es largo adrede: quien lo escriba en un controlador o en una
     * vista va a notar que está haciendo algo raro.
     */
    public function getContrasenaParaGuardar(): string
    {
        return $this->contrasena;
    }

    // ------------------------------------------------------------------
    // SETTERS — el email NO tiene: es la llave primaria
    // ------------------------------------------------------------------

    public function setContrasena(string $contrasena): void
    {
        $this->contrasena = $contrasena;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /**
     * El usuario como array **sin la contraseña**. No es un olvido: es el
     * requisito. Si mañana alguien agrega `'contrasena' => …` aquí, la API
     * empieza a repartir hashes en cada GET.
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
