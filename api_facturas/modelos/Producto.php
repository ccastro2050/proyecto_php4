<?php
/**
 * Producto — el MODELO de la v1: la clase que representa una fila de la
 * tabla producto como un objeto.
 *
 * Estilo clásico de P.O.O. (encapsulamiento):
 *   - las propiedades son PRIVADAS: nadie por fuera las toca directamente;
 *   - se LEEN con getters (getCodigo, getNombre, getStock, getValorunitario);
 *   - se CAMBIAN con setters (setNombre, setStock, setValorunitario);
 *   - el código NO tiene setter: es la llave primaria — se fija al crear
 *     el objeto y no cambia nunca.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Producto
{
    // Las 4 propiedades, PRIVADAS y con su tipo. "private" = solo esta
    // clase puede leerlas o escribirlas; el resto del mundo usa los
    // métodos de abajo. Eso es ENCAPSULAMIENTO.
    private string $codigo;         // ej. "PR001" (la llave primaria)
    private string $nombre;         // ej. "Laptop Lenovo IdeaPad"
    private int $stock;             // unidades disponibles (entero)
    private float $valorunitario;   // precio (decimal)

    // El constructor recibe los 4 valores y los asigna a las propiedades.
    // $this = "este objeto": $this->codigo es la propiedad; $codigo es el
    // parámetro que llegó.
    public function __construct(string $codigo, string $nombre, int $stock, float $valorunitario)
    {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->stock = $stock;
        $this->valorunitario = $valorunitario;
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

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getValorunitario(): float
    {
        return $this->valorunitario;
    }

    // ------------------------------------------------------------------
    // SETTERS — para CAMBIAR las propiedades que pueden cambiar
    // (el codigo NO tiene setter: la llave primaria no se cambia)
    // ------------------------------------------------------------------

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function setValorunitario(float $valorunitario): void
    {
        $this->valorunitario = $valorunitario;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /**
     * Devuelve el producto como array (columna => valor), listo para que
     * json_encode lo convierta en JSON. Es necesario porque las propiedades
     * son privadas: json_encode no las ve — este método las entrega.
     */
    public function toArray(): array
    {
        return [
            'codigo'        => $this->codigo,
            'nombre'        => $this->nombre,
            'stock'         => $this->stock,
            'valorunitario' => $this->valorunitario,
        ];
    }
}
