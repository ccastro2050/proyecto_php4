<?php
/**
 * Vendedor — el modelo de la tabla `vendedor`: una fila vista como objeto.
 *
 * Quien factura. También es una persona, vista desde otro papel.
 *
 * Mismo estilo clásico de P.O.O. que el `Producto` de la v1: propiedades
 * privadas, getters para leer, setters para lo que puede cambiar, y
 * `toArray()` para el JSON.
 *   - id lo GENERA la base de datos (AUTO_INCREMENT), no el cliente.
 *     Por eso su tipo es "?int": una ficha recién construida, todavía sin
 *     guardar, no tiene id — y decirlo con el tipo es más honesto que
 *     inventarse un 0.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Vendedor
{
    private ?int $id;
    private int $carnet;   // El número de carné del vendedor.
    private string $direccion;
    private string $fkcodpersona;   // El código de la persona que es este vendedor.

    public function __construct(
        ?int $id,
        int $carnet,
        string $direccion,
        string $fkcodpersona,
    ) {
        $this->id = $id;
        $this->carnet = $carnet;
        $this->direccion = $direccion;
        $this->fkcodpersona = $fkcodpersona;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCarnet(): int
    {
        return $this->carnet;
    }

    public function getDireccion(): string
    {
        return $this->direccion;
    }

    public function getFkcodpersona(): string
    {
        return $this->fkcodpersona;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que puede cambiar (la llave nunca)
    // ------------------------------------------------------------------

    public function setCarnet(int $carnet): void
    {
        $this->carnet = $carnet;
    }

    public function setDireccion(string $direccion): void
    {
        $this->direccion = $direccion;
    }

    public function setFkcodpersona(string $fkcodpersona): void
    {
        $this->fkcodpersona = $fkcodpersona;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /** El objeto como array (columna => valor), listo para json_encode. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carnet' => $this->carnet,
            'direccion' => $this->direccion,
            'fkcodpersona' => $this->fkcodpersona,
        ];
    }
}
