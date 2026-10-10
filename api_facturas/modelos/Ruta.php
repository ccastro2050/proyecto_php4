<?php
/**
 * Ruta — el MODELO de `ruta`: una fila de la tabla vista como objeto.
 *
 * Una `ruta` es una direccion protegible del aplicativo: `/producto`,
 * `/factura`, `/home`. No es una pagina: es el NOMBRE con el que la base de
 * datos decide quien entra (ver la tabla puente `rutarol`).
 *
 * La columna `ruta` es UNIQUE, y de ahi sale el **409** de este recurso:
 * repetirla no es «usted escribio mal» (400) sino «choca con lo que ya hay».
 *
 * Estilo clasico de P.O.O. (encapsulamiento): propiedades PRIVADAS, lectura
 * con getters, escritura con setters — y la LLAVE PRIMARIA sin setter, porque
 * se fija al crear el objeto y no cambia nunca.
 */

// Modo estricto de tipos (ver explicacion completa en index.php):
declare(strict_types=1);

class Ruta
{
    private int $id;   // la llave, la GENERA la base de datos
    private string $ruta;   // ej. "/producto" — es UNIQUE
    private string $descripcion;   // para que sirve

    public function __construct(int $id, string $ruta, string $descripcion)
    {
        $this->id = $id;
        $this->ruta = $ruta;
        $this->descripcion = $descripcion;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getId(): int
    {
        return $this->id;
    }

    public function getRuta(): string
    {
        return $this->ruta;
    }

    public function getDescripcion(): string
    {
        return $this->descripcion;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que PUEDE cambiar (la llave no tiene)
    // ------------------------------------------------------------------

    public function setRuta(string $ruta): void
    {
        $this->ruta = $ruta;
    }

    public function setDescripcion(string $descripcion): void
    {
        $this->descripcion = $descripcion;
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
            'ruta' => $this->ruta,
            'descripcion' => $this->descripcion,
        ];
    }
}
