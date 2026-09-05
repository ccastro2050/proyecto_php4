<?php
/**
 * ServicioFactura — la capa de NEGOCIO de las facturas.
 *
 * Es el servicio más corto del proyecto, y eso dice algo: **casi todas las
 * reglas de la factura viven en la base de datos** (los triggers calculan el
 * total y mueven el stock; los procedimientos exigen al menos un renglón y
 * se niegan a anular dos veces).
 *
 * Lo que queda aquí es lo que la base no puede saber: qué significa «no
 * existe» para esta API, y qué forma tiene el detalle que llega del cliente.
 *
 * Un servicio flaco no es un servicio mal hecho. Sería peor al revés:
 * recalcular en PHP un total que el trigger ya calculó, para que algún día
 * los dos no coincidan y nadie sepa cuál creer.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/IServicioFactura.php';
require_once __DIR__ . '/../repositorios/IRepositorioFactura.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';
require_once __DIR__ . '/../modelos/Factura.php';

class ServicioFactura implements IServicioFactura
{
    public function __construct(
        private readonly IRepositorioFactura $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validación de negocio
    // ------------------------------------------------------------------

    private function validarNumero(int $numero): int
    {
        if ($numero <= 0) {
            throw new InvalidArgumentException(
                'El número de la factura debe ser un entero mayor que cero.'
            );
        }
        return $numero;
    }

    /**
     * Deja el detalle en la forma que espera el procedimiento almacenado:
     * una lista de ['codigo' => ..., 'cantidad' => ...].
     *
     * El controlador ya comprobó que cada renglón traiga las dos cosas y con
     * el tipo correcto. Lo que se hace aquí es distinto: quedarse SOLO con
     * esas dos llaves. Si el cliente mandó además un 'subtotal', se ignora —
     * el subtotal no lo decide quien factura, lo calcula el trigger.
     */
    private function normalizarDetalle(array $renglones): array
    {
        $limpio = [];
        foreach ($renglones as $renglon) {
            $limpio[] = [
                'codigo'   => $renglon['codigo'],
                'cantidad' => $renglon['cantidad'],
            ];
        }
        return $limpio;
    }

    // ------------------------------------------------------------------
    // Operaciones de negocio
    // ------------------------------------------------------------------

    public function listar(): array
    {
        // Sin ?limite: el procedimiento de listar no lo recibe. Decirlo aquí
        // es más honesto que aceptar un límite y no usarlo.
        return $this->repositorio->obtenerTodas();
    }

    public function obtener(int $numero): Factura
    {
        $numero = $this->validarNumero($numero);
        $factura = $this->repositorio->obtenerPorNumero($numero);
        if ($factura === null) {
            throw new NoEncontradoExcepcion("No existe la factura número $numero");
        }
        return $factura;
    }

    public function crear(array $datos): Factura
    {
        return $this->repositorio->crear(
            $datos['fkidcliente'],
            $datos['fkidvendedor'],
            $this->normalizarDetalle($datos['detalle']),
        );
    }

    public function reemplazar(int $numero, array $datos): Factura
    {
        $numero = $this->validarNumero($numero);
        // Si el número no existe, el procedimiento lo dice con su SIGNAL y
        // el repositorio lo convierte en conflicto. No se comprueba antes
        // por la misma razón de siempre: la base ya lo sabe.
        return $this->repositorio->reemplazar(
            $numero,
            $datos['fkidcliente'],
            $datos['fkidvendedor'],
            $this->normalizarDetalle($datos['detalle']),
        );
    }

    public function anular(int $numero): array
    {
        return $this->repositorio->anular($this->validarNumero($numero));
    }

    public function eliminar(int $numero): array
    {
        return $this->repositorio->eliminar($this->validarNumero($numero));
    }
}
