<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica (SUNAT): boletas y facturas de los pedidos.
 *
 * - Series por sede: SUNAT las pide por establecimiento (B001/F001 en una,
 *   B002/F002 en otra...). El correlativo sube de a uno y no se reutiliza.
 * - Un comprobante por emisión, con lo que se le mandó a SUNAT y lo que
 *   respondió (CDR). No se edita: un rechazado se corrige emitiendo otro.
 * - Cada unidad de medida con su código SUNAT (catálogo 03): NIU, KGM...
 */
return new class extends Migration
{
    /** Abreviatura del sistema → código de unidad SUNAT (catálogo 03, UN/ECE rec. 20). */
    private const CODIGOS_UNIDAD = [
        'KG' => 'KGM', 'G' => 'GRM', 'TN' => 'TNE',
        'M' => 'MTR', 'M2' => 'MTK', 'M3' => 'MTQ', 'CM' => 'CMT',
        'L' => 'LTR', 'ML' => 'MLT', 'GL' => 'GLL',
        'BLS' => 'BG', 'BOL' => 'BG', 'CJ' => 'BX', 'CAJ' => 'BX',
        'BLD' => 'BJ', 'RLL' => 'RO', 'ROL' => 'RO', 'KIT' => 'KT',
        'CIL' => 'CY', 'LAT' => 'CA', 'LT' => 'CA', 'SCO' => 'BG',
    ];

    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            // NIU = "unidad (bienes)": el genérico cuando no hay uno específico.
            $table->string('codigo_sunat', 3)->default('NIU')->after('abreviatura');
        });
        foreach (DB::table('unidades')->get(['id', 'abreviatura']) as $unidad) {
            $codigo = self::CODIGOS_UNIDAD[mb_strtoupper(trim((string) $unidad->abreviatura))] ?? 'NIU';
            DB::table('unidades')->where('id', $unidad->id)->update(['codigo_sunat' => $codigo]);
        }

        Schema::create('comprobante_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            // 01 factura, 03 boleta (catálogo 01 de SUNAT).
            $table->string('tipo', 2);
            $table->string('serie', 4)->unique();
            // El último emitido: el próximo es correlativo + 1.
            $table->unsignedInteger('correlativo')->default(0);
            $table->timestamps();

            $table->unique(['sede_id', 'tipo']);
        });

        // Una serie de boleta y una de factura por sede: B001/F001, B002/F002…
        foreach (DB::table('sedes')->orderBy('id')->pluck('id')->values() as $i => $sedeId) {
            $numero = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            DB::table('comprobante_series')->insert([
                ['sede_id' => $sedeId, 'tipo' => '03', 'serie' => "B{$numero}", 'correlativo' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['sede_id' => $sedeId, 'tipo' => '01', 'serie' => "F{$numero}", 'correlativo' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('tipo', 2);
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->dateTime('fecha_emision');

            // El cliente tal como se mandó (catálogo 06: 0 sin doc, 1 DNI, 4 CE, 6 RUC).
            $table->string('cliente_tipo_doc', 1);
            $table->string('cliente_num_doc', 15);
            $table->string('cliente_nombre', 200);

            $table->decimal('op_gravada', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('total_letras');

            // pendiente (aún sin respuesta) | aceptado | rechazado | error (no llegó a SUNAT)
            $table->string('estado', 12)->default('pendiente');
            $table->string('sunat_codigo', 10)->nullable();
            $table->text('sunat_mensaje')->nullable();
            $table->string('hash', 100)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['serie', 'correlativo']);
            $table->index(['pedido_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
        Schema::dropIfExists('comprobante_series');
        Schema::table('unidades', fn (Blueprint $table) => $table->dropColumn('codigo_sunat'));
    }
};
