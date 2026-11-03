<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Variante;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los Excel que se descargan del sistema (cotización, catálogo de
 * productos). Montos como números con formato de soles, no como texto: se
 * pueden sumar y filtrar en Excel.
 */
class Exportaciones
{
    private const SOLES = '"S/ "#,##0.00';

    // General: 700 y 2.5 tal cual (con '0.###' Excel deja "700.").
    private const CANTIDAD = 'General';

    /**
     * La cotización como en el PDF: encabezado, cliente, ítems, totales y
     * condiciones.
     */
    public function cotizacion(Cotizacion $cotizacion): Spreadsheet
    {
        $cotizacion->loadMissing(['cliente', 'sede', 'usuario', 'items' => fn ($q) => $q->orderBy('id'), 'items.variante.producto', 'items.variante.unidad', 'items.variante.color']);

        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle($cotizacion->codigo);

        // ── Encabezado ──
        $hoja->setCellValue('A1', config('greenter.company.razonSocial'))->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $fila = 2;
        foreach (array_filter([
            $this->rucEmpresa() ? 'RUC '.$this->rucEmpresa() : null,
            $cotizacion->sede?->direccion,
            $cotizacion->sede?->telefono ? 'Tel. '.$cotizacion->sede->telefono : null,
        ]) as $linea) {
            $hoja->setCellValue("A{$fila}", $linea);
            $fila++;
        }

        $hoja->setCellValue('F1', 'COTIZACIÓN')->getStyle('F1')->getFont()->setBold(true)->setSize(14);
        $hoja->setCellValue('F2', $cotizacion->codigo)->getStyle('F2')->getFont()->setBold(true);
        $hoja->setCellValue('F3', 'Fecha: '.$cotizacion->created_at?->format('d/m/Y'));
        $hoja->setCellValue('F4', 'Válida hasta: '.$cotizacion->valida_hasta?->format('d/m/Y'));

        // ── Cliente ──
        $fila = max($fila, 5) + 1;
        $cliente = $cotizacion->cliente;
        foreach (array_filter([
            'Cliente' => $cliente?->nombre ?? 'Clientes varios',
            $cliente?->tipo_documento ?? 'Documento' => $cliente?->numero_documento,
            'Dirección' => $cliente?->direccion,
            'Teléfono' => $cliente?->telefono,
        ]) as $etiqueta => $valor) {
            $hoja->setCellValue("A{$fila}", "{$etiqueta}:")->getStyle("A{$fila}")->getFont()->setBold(true);
            $hoja->setCellValue("B{$fila}", $valor);
            $fila++;
        }

        // ── Ítems ──
        $fila++;
        $inicio = $fila;
        $this->cabecera($hoja, $fila, ['#', 'Descripción', 'SKU', 'Cant.', 'Und.', 'P. unit.', 'Importe']);
        foreach ($cotizacion->items as $n => $item) {
            $fila++;
            $variante = $item->variante;
            $hoja->fromArray([
                $n + 1,
                trim("{$variante->producto?->nombre} — {$variante->presentacion} ".($variante->color?->nombre ?? '')),
                $variante->sku,
                (float) $item->cantidad,
                $variante->unidad?->abreviatura,
                (float) $item->precio_unitario,
                (float) $item->subtotal,
            ], null, "A{$fila}");
        }
        $this->bordes($hoja, "A{$inicio}:G{$fila}");
        $hoja->getStyle('D'.($inicio + 1).":D{$fila}")->getNumberFormat()->setFormatCode(self::CANTIDAD);
        $hoja->getStyle('F'.($inicio + 1).":G{$fila}")->getNumberFormat()->setFormatCode(self::SOLES);

        // ── Totales ──
        $fila++;
        $totales = array_filter([
            'Subtotal' => (float) $cotizacion->descuento ? (float) $cotizacion->subtotal : null,
            'Descuento' => (float) $cotizacion->descuento ? -(float) $cotizacion->descuento : null,
            'Op. gravada' => (float) $cotizacion->op_gravada,
            'IGV ('.round((float) config('app.igv', 0.18) * 100).'%)' => (float) $cotizacion->igv,
            'TOTAL' => (float) $cotizacion->total,
        ], fn ($v) => $v !== null);
        foreach ($totales as $etiqueta => $monto) {
            $fila++;
            $hoja->setCellValue("F{$fila}", $etiqueta);
            $hoja->setCellValue("G{$fila}", $monto);
            $hoja->getStyle("G{$fila}")->getNumberFormat()->setFormatCode(self::SOLES);
            if ($etiqueta === 'TOTAL') {
                $hoja->getStyle("F{$fila}:G{$fila}")->getFont()->setBold(true)->setSize(12);
            }
        }

        // ── Condiciones ──
        if ($cotizacion->condiciones) {
            $fila += 2;
            $hoja->setCellValue("A{$fila}", 'Condiciones')->getStyle("A{$fila}")->getFont()->setBold(true);
            $fila++;
            $hoja->setCellValue("A{$fila}", $cotizacion->condiciones);
            $hoja->mergeCells("A{$fila}:G{$fila}");
            $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $hoja->getRowDimension($fila)->setRowHeight(15 * max(2, substr_count($cotizacion->condiciones, "\n") + 2));
        }
        if ($cotizacion->usuario) {
            $fila += 2;
            $hoja->setCellValue("A{$fila}", 'Atendido por: '.$cotizacion->usuario->name);
        }

        $hoja->getColumnDimension('A')->setWidth(14);
        $hoja->getColumnDimension('B')->setWidth(48);
        foreach (['C' => 16, 'D' => 9, 'E' => 8, 'F' => 16, 'G' => 14] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        return $libro;
    }

    /**
     * El catálogo: una fila por presentación con su precio y su stock (de la
     * sede, o de la empresa si `$sede` es null). El costo sólo si lo puede ver.
     *
     * @param  Collection<int, Variante>  $variantes  con producto (categoría, marca), unidad y color
     */
    public function productos(Collection $variantes, ?string $sede, bool $conCosto): Spreadsheet
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('Productos');

        $hoja->setCellValue('A1', 'Productos — '.($sede ?? 'toda la empresa'))->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->setCellValue('A2', 'Exportado el '.now()->format('d/m/Y H:i'));

        $columnas = ['Producto', 'Categoría', 'Marca', 'Presentación', 'Color', 'SKU', 'Código de barras', 'Unidad', 'Precio', 'Precio por mayor'];
        if ($conCosto) {
            $columnas[] = 'Costo promedio';
        }
        array_push($columnas, $sede ? 'Stock en sede' : 'Stock empresa', 'Mínimo', 'Estado');

        $fila = 4;
        $this->cabecera($hoja, $fila, $columnas);
        foreach ($variantes as $v) {
            $fila++;
            $valores = [
                $v->producto->nombre,
                $v->producto->categoria?->nombre,
                $v->producto->marca?->nombre,
                $v->presentacion,
                $v->color?->nombre,
                $v->sku,
                $v->codigo_barras,
                $v->unidad?->abreviatura,
                $v->precioBase((float) $v->producto->precio),
                $v->precio_mayor !== null ? (float) $v->precio_mayor : null,
            ];
            if ($conCosto) {
                $valores[] = $v->costo_promedio !== null ? round((float) $v->costo_promedio, 2) : null;
            }
            array_push($valores, $v->stockVisible(), $sede ? $v->stockMinimoVisible() : null, $v->producto->activo ? 'Activo' : 'Inactivo');
            $hoja->fromArray($valores, null, "A{$fila}");
            // El código de barras como texto: si no, Excel lo muestra en notación científica.
            $hoja->setCellValueExplicit("G{$fila}", (string) $v->codigo_barras, DataType::TYPE_STRING);
        }

        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        $this->bordes($hoja, "A4:{$ultima}{$fila}");
        $montos = $conCosto ? ['I', 'J', 'K'] : ['I', 'J'];
        foreach ($montos as $col) {
            $hoja->getStyle("{$col}5:{$col}{$fila}")->getNumberFormat()->setFormatCode(self::SOLES);
        }
        $stockCol = $conCosto ? 'L' : 'K';
        $minCol = $conCosto ? 'M' : 'L';
        $hoja->getStyle("{$stockCol}5:{$minCol}{$fila}")->getNumberFormat()->setFormatCode(self::CANTIDAD);

        // Filtros y encabezado fijo: se ordena y busca dentro de Excel.
        $hoja->setAutoFilter("A4:{$ultima}{$fila}");
        $hoja->freezePane('A5');
        foreach (range(1, count($columnas)) as $i) {
            $hoja->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        return $libro;
    }

    /** El .xlsx como descarga. */
    public function descargar(Spreadsheet $libro, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** En beta el RUC es de prueba: no se imprime. */
    private function rucEmpresa(): ?string
    {
        return config('greenter.mode') === 'prod' ? config('greenter.company.ruc') : null;
    }

    /**
     * @param  array<int, string>  $titulos
     */
    private function cabecera(Worksheet $hoja, int $fila, array $titulos): void
    {
        $hoja->fromArray($titulos, null, "A{$fila}");
        $rango = "A{$fila}:".Coordinate::stringFromColumnIndex(count($titulos)).$fila;
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
    }

    private function bordes(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
