<?php
/**
 * Persona — el modelo de la tabla `persona`: una fila vista como objeto.
 *
 * Los datos de contacto de alguien. Una persona no es todavía ni cliente ni vendedor: es el dato de quién es.
 *
 * Mismo estilo clásico de P.O.O. que el `Producto` de la v1: propiedades
 * privadas, getters para leer, setters para lo que puede cambiar, y
 * `toArray()` para el JSON.
 *   - codigo NO tiene setter: es la llave primaria — se fija al crear el
 *     objeto y no cambia nunca.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Persona
{
    private string $codigo;
    private string $nombre;
    private string $email;
    private string $telefono;

    public function __construct(
        string $codigo,
        string $nombre,
        string $email,
        string $telefono,
    ) {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->telefono = $telefono;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTelefono(): string
    {
        return $this->telefono;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que puede cambiar (la llave nunca)
    // ------------------------------------------------------------------

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setTelefono(string $telefono): void
    {
        $this->telefono = $telefono;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /** El objeto como array (columna => valor), listo para json_encode. */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
        ];
    }
}
