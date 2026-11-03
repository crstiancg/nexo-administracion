<?php

namespace Database\Seeders;

use App\Models\ConceptoFinanciero;
use Illuminate\Database\Seeder;

/**
 * Los conceptos de siempre de un taller y tienda de tecnología. Se editan en
 * Finanzas › Ingresos y gastos.
 */
class ConceptoFinancieroSeeder extends Seeder
{
    public const GASTOS = [
        'Alquiler del local', 'Sueldos y adelantos', 'Servicios (luz, agua, internet)', 'Compra de repuestos',
        'Herramientas del taller', 'Publicidad (Facebook, TikTok)', 'Envíos y courier', 'Pago a proveedores',
        'Licencias y software', 'Alimentación', 'Impuestos y trámites', 'Otros gastos',
    ];

    public const INGRESOS = [
        'Préstamo recibido', 'Aporte del dueño', 'Venta de chatarra o reciclaje', 'Comisiones (recargas, servicios)',
        'Otros ingresos',
    ];

    public function run(): void
    {
        foreach ([ConceptoFinanciero::GASTO => self::GASTOS, ConceptoFinanciero::INGRESO => self::INGRESOS] as $tipo => $nombres) {
            foreach ($nombres as $nombre) {
                ConceptoFinanciero::firstOrCreate(['tipo' => $tipo, 'nombre' => $nombre], ['activo' => true]);
            }
        }
    }
}
