<?php
/**
 * Factura — el modelo del ENCABEZADO de una factura.
 *
 * Una factura son dos cosas: el encabezado (quién compró, a quién, cuándo,
 * cuánto) y el detalle (qué productos y cuántos). Este archivo es el
 * encabezado; cada renglón del detalle es una `LineaFactura`.
 *
 * A eso se le llama **maestro-detalle**, y es lo que la v2 viene a enseñar:
 * dos tablas que no tienen sentido por separado. Una factura sin renglones no
 * es una factura a medias — es una factura inválida, y la base lo impide.
 *
 * TRES PROPIEDADES QUE ESTE MODELO NO DEJA CAMBIAR, Y CONVIENE SABER POR QUÉ:
 *
 *   - `numero`  la genera la base (AUTO_INCREMENT), como el id de un cliente;
 *   - `total`   **lo calcula un TRIGGER** cada vez que cambia el detalle. La
 *               API tiene prohibido escribirlo: si PHP y el trigger llevaran
 *               cada uno su cuenta, tarde o temprano dirían cosas distintas;
 *   - `estado`  solo lo cambia la operación de anular, que es un procedimiento
 *               almacenado. No es un campo que se edite en un formulario.
 *
 * Por eso aquí no hay setters. El modelo es de solo lectura, y eso no es un
 * descuido: es la regla escrita en código.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

// Una Factura CONTIENE LineaFactura, así que su archivo la trae consigo.
// Quien cargue este modelo recibe el maestro y el detalle juntos, que es
// como se usan — y quien escriba un repositorio falso para probar no tiene
// que acordarse de cargar los dos.
require_once __DIR__ . '/LineaFactura.php';

class Factura
{
    private ?int $numero;        // lo pone la base
    private string $fecha;       // cuándo se hizo
    private float $total;        // lo calcula el trigger
    private string $estado;      // 'activa' o 'anulada'
    private int $fkidcliente;    // quién compró
    private int $fkidvendedor;   // quién le vendió

    // Los nombres NO son columnas de `factura`: la base los trae con un JOIN
    // hasta `persona`. Viajan aquí porque una pantalla que dijera «cliente 2»
    // no le sirve a nadie. Son ?string porque un procedimiento puede no
    // traerlos, y entonces es honesto decir que no se saben.
    private ?string $nombreCliente;
    private ?string $nombreVendedor;

    /** @var LineaFactura[] Los renglones. Puede llegar vacío en un listado. */
    private array $detalle;

    public function __construct(
        ?int $numero,
        string $fecha,
        float $total,
        string $estado,
        int $fkidcliente,
        int $fkidvendedor,
        array $detalle = [],
        ?string $nombreCliente = null,
        ?string $nombreVendedor = null,
    ) {
        $this->numero = $numero;
        $this->fecha = $fecha;
        $this->total = $total;
        $this->estado = $estado;
        $this->fkidcliente = $fkidcliente;
        $this->fkidvendedor = $fkidvendedor;
        $this->detalle = $detalle;
        $this->nombreCliente = $nombreCliente;
        $this->nombreVendedor = $nombreVendedor;
    }

    // ------------------------------------------------------------------
    // GETTERS — no hay setters, y es a propósito (ver arriba)
    // ------------------------------------------------------------------

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function getFecha(): string
    {
        return $this->fecha;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getFkidcliente(): int
    {
        return $this->fkidcliente;
    }

    public function getFkidvendedor(): int
    {
        return $this->fkidvendedor;
    }

    /** @return LineaFactura[] */
    public function getDetalle(): array
    {
        return $this->detalle;
    }

    public function getNombreCliente(): ?string
    {
        return $this->nombreCliente;
    }

    public function getNombreVendedor(): ?string
    {
        return $this->nombreVendedor;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /**
     * El encabezado CON su detalle adentro.
     *
     * Fíjese en la forma: el detalle va anidado, no en una lista aparte. Es
     * la que corresponde a maestro-detalle — quien recibe la factura recibe
     * también sus renglones, sin tener que pedirlos en otra petición.
     */
    public function toArray(): array
    {
        $renglones = [];
        foreach ($this->detalle as $linea) {
            $renglones[] = $linea->toArray();
        }

        return [
            'numero'       => $this->numero,
            'fecha'        => $this->fecha,
            'total'        => $this->total,
            'estado'       => $this->estado,
            'fkidcliente'  => $this->fkidcliente,
            'nombreCliente' => $this->nombreCliente,
            'fkidvendedor' => $this->fkidvendedor,
            'nombreVendedor' => $this->nombreVendedor,
            'detalle'      => $renglones,
        ];
    }
}
