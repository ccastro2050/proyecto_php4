<?php
/**
 * Cliente — el modelo de la tabla `cliente`: una fila vista como objeto.
 *
 * Una persona que compra. Puede estar asociada a una empresa, o comprar a título propio.
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

class Cliente
{
    private ?int $id;
    private float $credito;   // Cuánto se le fía.
    private string $fkcodpersona;   // El código de la persona que es este cliente.
    private ?string $fkcodempresa;   // Opcional: déjelo vacío si compra a título propio.

    public function __construct(
        ?int $id,
        float $credito,
        string $fkcodpersona,
        ?string $fkcodempresa,
    ) {
        $this->id = $id;
        $this->credito = $credito;
        $this->fkcodpersona = $fkcodpersona;
        $this->fkcodempresa = $fkcodempresa;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCredito(): float
    {
        return $this->credito;
    }

    public function getFkcodpersona(): string
    {
        return $this->fkcodpersona;
    }

    public function getFkcodempresa(): ?string
    {
        return $this->fkcodempresa;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que puede cambiar (la llave nunca)
    // ------------------------------------------------------------------

    public function setCredito(float $credito): void
    {
        $this->credito = $credito;
    }

    public function setFkcodpersona(string $fkcodpersona): void
    {
        $this->fkcodpersona = $fkcodpersona;
    }

    public function setFkcodempresa(?string $fkcodempresa): void
    {
        $this->fkcodempresa = $fkcodempresa;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /** El objeto como array (columna => valor), listo para json_encode. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'credito' => $this->credito,
            'fkcodpersona' => $this->fkcodpersona,
            'fkcodempresa' => $this->fkcodempresa,
        ];
    }
}
