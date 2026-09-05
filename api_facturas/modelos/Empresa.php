<?php
/**
 * Empresa — el modelo de la tabla `empresa`: una fila vista como objeto.
 *
 * Las compañías a las que puede pertenecer un cliente.
 *
 * Mismo estilo clásico de P.O.O. que el `Producto` de la v1: propiedades
 * privadas, getters para leer, setters para lo que puede cambiar, y
 * `toArray()` para el JSON.
 *   - codigo NO tiene setter: es la llave primaria — se fija al crear el
 *     objeto y no cambia nunca.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Empresa
{
    private string $codigo;
    private string $nombre;   // El nombre de la compañía.

    public function __construct(
        string $codigo,
        string $nombre,
    ) {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
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

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que puede cambiar (la llave nunca)
    // ------------------------------------------------------------------

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
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
        ];
    }
}
