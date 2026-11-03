@extends('impresion.layout')

@section('titulo', "Orden de servicio {$orden->codigo}")

@section('estilos')
    @page { size: A4; margin: 12mm; }
    .hoja { width: 210mm; min-height: 297mm; margin: 0 auto 24px; padding: 12mm; background: #fff; font-size: 12px; line-height: 1.45; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    .cabecera { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 10px; border-bottom: 3px solid #2563EB; }
    .cabecera img { height: 58px; }
    .empresa { font-size: 11px; }
    .empresa strong { font-size: 13px; }
    .caja { border: 1.5px solid #2563EB; border-radius: 8px; padding: 6px 14px; text-align: center; min-width: 170px; }
    .caja .titulo { background: #2563EB; color: #fff; font-weight: 800; letter-spacing: 1px; padding: 2px 0; margin: 2px 0; }
    .caja .codigo { font-family: monospace; font-size: 17px; font-weight: 700; }
    h4 { margin: 0 0 4px; font-size: 10.5px; letter-spacing: .5px; text-transform: uppercase; color: #2563EB; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 12px 0; }
    .bloque { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; }
    .checklist { display: flex; flex-wrap: wrap; gap: 3px 16px; }
    table { width: 100%; border-collapse: collapse; }
    .tabla th { background: #2563EB; color: #fff; text-align: left; font-size: 10.5px; text-transform: uppercase; padding: 5px 8px; }
    .tabla td { border-bottom: 1px solid #e5e7eb; padding: 5px 8px; }
    .num { text-align: right; white-space: nowrap; }
    .totales td { padding: 3px 8px; }
    .totales .saldo td { font-size: 14px; font-weight: 800; }
    .pie { display: flex; justify-content: space-between; gap: 20px; margin-top: 14px; padding-top: 10px; border-top: 1px solid #111827; }
    .condiciones { flex: 1; font-size: 10.5px; }
    .condiciones p { margin: 0 0 5px; }
    .firmas { display: flex; gap: 30px; margin-top: 46px; }
    .firmas div { flex: 1; border-top: 1px solid #111827; text-align: center; padding-top: 4px; position: relative; }
    .firmas img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 44px; }
    .qr { width: 135px; text-align: center; font-size: 9.5px; }
    .qr img { width: 125px; height: 125px; }
    @media print { .hoja { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; } }
@endsection

@section('contenido')
<div class="hoja">
    <div class="cabecera">
        <img src="{{ asset('img/logo.png') }}" alt="Nexo Technology">
        <div class="empresa">
            <strong>{{ $empresa['nombre'] }}</strong><br>
            RUC {{ $empresa['ruc'] }}<br>
            {{ $orden->sede?->direccion ?: $empresa['direccion'] }}<br>
            Tel. {{ $orden->sede?->telefono ?: $empresa['telefono'] }}
        </div>
        <div class="caja">
            <div>Orden de servicio</div>
            <div class="titulo">{{ mb_strtoupper($estado) }}</div>
            <div class="codigo">{{ $orden->codigo }}</div>
            <div class="muted">{{ $orden->recibido_at->timezone(\App\Support\Fechas::zona())->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="bloque">
            <h4>Cliente</h4>
            <strong>{{ $orden->cliente->nombre }}</strong><br>
            @if ($orden->cliente->numero_documento)
                {{ $orden->cliente->tipo_documento }} {{ $orden->cliente->numero_documento }}<br>
            @endif
            @if ($orden->cliente->telefono)
                Tel. {{ $orden->cliente->telefono }}
            @endif
        </div>
        <div class="bloque">
            <h4>Equipo</h4>
            <strong>{{ $orden->equipo() }}</strong><br>
            @if ($orden->serie)
                Serie / IMEI: {{ $orden->serie }}<br>
            @endif
            @if ($orden->accesorios)
                Accesorios: {{ $orden->accesorios }}<br>
            @endif
            Bloqueo: {{ $seguridad }}
        </div>
    </div>

    @if ($orden->checklist)
        <div class="bloque" style="margin-bottom: 12px">
            <h4>Estado al recibir</h4>
            <div class="checklist">
                @foreach ($orden->checklist as $punto => $ok)
                    <span>{{ $ok ? '☑' : '☐' }} {{ $punto }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bloque" style="margin-bottom: 12px">
        <h4>Falla reportada</h4>
        <div>{{ $orden->falla }}</div>
        @if ($orden->diagnostico)
            <h4 style="margin-top: 8px">Diagnóstico / trabajo</h4>
            <div>{{ $orden->diagnostico }}</div>
        @endif
    </div>

    @if ($repuestos->isNotEmpty() || (float) $orden->mano_obra > 0)
        <table class="tabla">
            <thead>
                <tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Importe</th></tr>
            </thead>
            <tbody>
                @foreach ($repuestos as $item)
                    <tr>
                        <td>{{ $item->variante?->producto?->nombre }} — {{ $item->variante?->presentacion }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, '.', ''), '0'), '.') }}</td>
                        <td class="num">S/ {{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
                @if ((float) $orden->mano_obra > 0)
                    <tr>
                        <td>Mano de obra</td>
                        <td class="num">1</td>
                        <td class="num">S/ {{ number_format((float) $orden->mano_obra, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
        <table class="totales" style="width: 260px; margin: 6px 0 0 auto">
            <tr><td>Total</td><td class="num">S/ {{ number_format($total, 2) }}</td></tr>
            <tr><td>A cuenta</td><td class="num">S/ {{ number_format($pagado, 2) }}</td></tr>
            <tr class="saldo"><td>Saldo</td><td class="num">S/ {{ number_format($saldo, 2) }}</td></tr>
        </table>
    @endif

    <div class="pie">
        <div class="condiciones">
            @if ($orden->garantia_dias > 0)
                <p>Garantía del trabajo: {{ $orden->garantia_dias }} días desde la entrega, sobre la falla reparada.</p>
            @endif
            <p>El cliente declara entregar el equipo en el estado descrito. Los equipos no recogidos en 90 días desde el aviso de reparación podrán considerarse abandonados.</p>
            <p>Presente esta orden para recoger el equipo.</p>
            <div class="firmas">
                <div>
                    @if ($orden->firma_recepcion)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($orden->firma_recepcion) }}" alt="Firma del cliente">
                    @endif
                    Firma del cliente
                </div>
                <div>Recibido por{{ $orden->usuario ? ': '.$orden->usuario->name : '' }}</div>
            </div>
        </div>
        <div class="qr">
            <img src="{{ $qr }}" alt="QR de seguimiento">
            <div>Escanee para ver el estado de su equipo</div>
        </div>
    </div>
</div>
@endsection
