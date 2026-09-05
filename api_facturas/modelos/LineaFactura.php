<?php
/**
 * LineaFactura — un renglón del detalle de una factura.
 *
 * Corresponde a una fila de `productosporfactura`, pero trae además el
 * nombre y el precio del producto: la base los junta con un JOIN al
 * devolver la factura, porque un renglón que solo dijera «PR001 × 3» no le
 * sirve a nadie.
 *
 * DOS COSAS QUE ESTE RENGLÓN NO DECIDE:
 *
 *   - `subtotal` lo calcula el **trigger** al insertar (cantidad × precio del
 *     producto en ese momento). Si lo calculara PHP, dos sistemas estarían
 *     haciendo la misma cuenta — y el día que difieran, ¿cuál tiene razón?
 *   - el **stock** del producto también lo mueve el trigger. La API tiene
 *     prohibido descontarlo por su cuenta.
 *
 * Es la lección de la v2 sobre dónde vive la lógica: parte del negocio está
 * en la base de datos, y la API la respeta en vez de repetirla.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class LineaFactura
{
    private string $codigoProducto;
    private string $nombreProducto;
    private int $cantidad;
    private float $valorunitario;
    private float $subtotal;

    public function __construct(
        string $codigoProducto,
        string $nombreProducto,
        int $cantidad,
        float $valorunitario,
        float $subtotal,
    ) {
        $this->codigoProducto = $codigoProducto;
        $this->nombreProducto = $nombreProducto;
        $this->cantidad = $cantidad;
        $this->valorunitario = $valorunitario;
        $this->subtotal = $subtotal;
    }

    public function getCodigoProducto(): string
    {
        return $this->codigoProducto;
    }

    public function getNombreProducto(): string
    {
        return $this->nombreProducto;
    }

    public function getCantidad(): int
    {
        return $this->cantidad;
    }

    public function getValorunitario(): float
    {
        return $this->valorunitario;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function toArray(): array
    {
        return [
            'codigoProducto' => $this->codigoProducto,
            'nombreProducto' => $this->nombreProducto,
            'cantidad'       => $this->cantidad,
            'valorunitario'  => $this->valorunitario,
            'subtotal'       => $this->subtotal,
        ];
    }
}
