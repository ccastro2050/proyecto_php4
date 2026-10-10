<?php
/**
 * Rol — el MODELO de `rol`: una fila de la tabla vista como objeto.
 *
 * `rol` es la primera tabla del proyecto con **llave generada por la base de
 * datos**: el id no se escribe, se recibe. De ahi que el constructor lo pida
 * igual —el repositorio arma el objeto con el id que leyo— pero al crear se
 * le pase un cero de relleno que nadie usa.
 *
 * Estilo clasico de P.O.O. (encapsulamiento): propiedades PRIVADAS, lectura
 * con getters, escritura con setters — y la LLAVE PRIMARIA sin setter, porque
 * se fija al crear el objeto y no cambia nunca.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

class Rol
{
    private int $id;   // la llave, la GENERA la base de datos
    private string $nombre;   // ej. "Administrador"

    public function __construct(int $id, string $nombre)
    {
        $this->id = $id;
        $this->nombre = $nombre;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getId(): int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que PUEDE cambiar (la llave no tiene)
    // ------------------------------------------------------------------

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    // ------------------------------------------------------------------
    // Conversion para la respuesta JSON
    // ------------------------------------------------------------------

    /**
     * El objeto como array (columna => valor), listo para json_encode. Hace
     * falta porque las propiedades son privadas: json_encode no las ve.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
        ];
    }
}
