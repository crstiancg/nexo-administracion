<?php

namespace Database\Seeders;

use App\Models\TipoEquipo;
use Illuminate\Database\Seeder;

/**
 * Los equipos que más entran a un servicio técnico, cada uno con lo que se
 * revisa al recibirlo. Se editan en Catálogos › Tipos de equipo.
 */
class TipoEquipoSeeder extends Seeder
{
    public const TIPOS = [
        'Laptop' => ['Enciende', 'Pantalla sin daños', 'Teclado completo', 'Touchpad', 'Bisagras', 'Carcasa sin golpes', 'Cargador', 'Batería', 'Signos de humedad'],
        'PC de escritorio' => ['Enciende', 'Case sin golpes', 'Cable de poder', 'Puertos USB', 'Signos de humedad'],
        'Celular' => ['Enciende', 'Pantalla sin daños', 'Táctil responde', 'Cámaras', 'Botones', 'Puerto de carga', 'Bandeja SIM', 'Signos de humedad'],
        'Tablet' => ['Enciende', 'Pantalla sin daños', 'Táctil responde', 'Cámaras', 'Puerto de carga', 'Cargador', 'Signos de humedad'],
        'Impresora' => ['Enciende', 'Cartuchos o tóner', 'Cable de poder', 'Cable USB', 'Bandeja de papel', 'Escáner'],
        'Monitor' => ['Enciende', 'Pantalla sin daños', 'Cable de poder', 'Cable de video', 'Base'],
        'Consola' => ['Enciende', 'Mandos', 'Cable de poder', 'Cable HDMI', 'Lector de discos'],
        'Otro' => ['Enciende', 'Carcasa sin golpes', 'Accesorios'],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $nombre => $checklist) {
            TipoEquipo::firstOrCreate(['nombre' => $nombre], ['checklist' => $checklist, 'activo' => true]);
        }
    }
}
