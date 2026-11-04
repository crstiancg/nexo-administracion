@extends('impresion.layout')

@section('titulo', "Orden de servicio {$orden->codigo}")

@section('estilos')
    @page { size: A4; margin: {{ $doble ? '0' : '12mm' }}; }
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
    .sub { margin-top: 8px; }
    .sub { margin-top: 8px; }
    /* ── A4 en dos copias: cada mitad es 210 × 148,5 mm, con toda la orden ── */
    .doble { width: 210mm; height: 296mm; overflow: hidden; margin: 0 auto 24px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    .mitad { display: flex; flex-direction: column; height: 148mm; padding: 6mm 8mm 5mm; overflow: hidden; font-size: 8.6px; line-height: 1.35; }
    .corte { position: relative; height: 0; border-top: 1px dashed #6b7280; }
    .corte span { position: absolute; top: -6px; left: 50%; transform: translateX(-50%); padding: 0 8px; background: #fff; font-size: 8px; color: #6b7280; }
    .m-cab { display: flex; align-items: center; gap: 10px; padding-bottom: 5px; border-bottom: 2px solid #2563EB; }
    .m-cab img { height: 34px; }
    .m-empresa { flex: 1; font-size: 8px; }
    .m-empresa strong { font-size: 9.5px; }
    .m-caja { min-width: 120px; padding: 3px 8px; border: 1.5px solid #2563EB; border-radius: 6px; text-align: center; font-size: 8px; }
    .m-copia { background: #2563EB; color: #fff; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; padding: 1px 0; }
    .m-codigo { font-family: monospace; font-size: 13px; font-weight: 700; }
    .m-cuerpo { display: flex; gap: 8px; flex: 1; min-height: 0; margin-top: 6px; }
    .m-izq { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }
    .m-der { display: flex; flex-direction: column; gap: 6px; width: 46mm; }
    .m-fila { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
    .m-bloque { border: 1px solid #d1d5db; border-radius: 5px; padding: 4px 6px; }
    .mitad h4 { margin: 0 0 1px; font-size: 7.5px; letter-spacing: .4px; text-transform: uppercase; color: #2563EB; }
    .m-sub { margin-top: 3px !important; }
    .m-texto { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .m-check { display: flex; flex-wrap: wrap; gap: 1px 9px; font-size: 8px; }
    .m-tabla { width: 100%; border-collapse: collapse; }
    .m-tabla th { background: #2563EB; color: #fff; text-align: left; font-size: 7.5px; text-transform: uppercase; padding: 2px 5px; }
    .m-tabla td { border-bottom: 1px solid #e5e7eb; padding: 2px 5px; }
    .m-datos { display: grid; gap: 3px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 5px; }
    .m-totales { width: 100%; border-collapse: collapse; }
    .m-totales td { padding: 1px 4px; }
    .m-saldo td { font-size: 11px; font-weight: 800; border-top: 1px solid #111827; }
    .m-qr { margin-top: auto; text-align: center; font-size: 7.5px; }
    .m-qr img { width: 74px; height: 74px; }
    .m-pie { display: flex; gap: 12px; align-items: flex-end; margin-top: 5px; padding-top: 4px; border-top: 1px solid #111827; }
    .m-condiciones { flex: 1; font-size: 7.3px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
    .m-firmas { display: flex; gap: 12px; width: 92mm; padding-top: 22px; }
    .m-firmas div { position: relative; flex: 1; border-top: 1px solid #111827; text-align: center; padding-top: 2px; font-size: 7.5px; }
    .m-firmas img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 24px; }
    .num { text-align: right; white-space: nowrap; }
    @media print {
        .hoja { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; }
        .doble { margin: 0; box-shadow: none; }
    }
@endsection

@section('contenido')
@if ($doble)
<div class="doble">
    @include('impresion.orden-mitad', ['copia' => 'Copia tienda'])
    <div class="corte"><span>✂ Cortar aquí</span></div>
    @include('impresion.orden-mitad', ['copia' => 'Copia cliente'])
</div>
@else
<div class="hoja">
    <div class="cabecera">
        <img src="{{ $empresa['logo_url'] ?? asset('img/logo.png') }}" alt="{{ $empresa['nombre'] }}">
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

    <div class="grid" style="grid-template-columns: repeat(3, 1fr)">
        <div><h4>Sede</h4>{{ $orden->sede?->nombre ?? '—' }}</div>
        <div><h4>Técnico</h4>{{ $orden->tecnico?->name ?? 'Por asignar' }}</div>
        <div><h4>Tipo</h4>{{ $orden->es_garantia ? 'Garantía' : 'Servicio' }}</div>
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
            <h4 class="sub">Diagnóstico / trabajo</h4>
            <div>{{ $orden->diagnostico }}</div>
        @endif
        @if ($orden->observacion)
            <h4 class="sub">Observaciones</h4>
            <div>{{ $orden->observacion }}</div>
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
            <p>{{ $empresa['terminos_orden'] }}</p>
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
@endif
@endsection
