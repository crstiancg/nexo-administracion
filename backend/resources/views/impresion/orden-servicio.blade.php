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
    /* ── A4 en dos copias, con el lenguaje de la proforma ──
       Azul de relleno #1D4ED8 (AA con blanco), su tinte #DBEAFE, tinta y gris.
       Mismas piezas que CotizacionDocumento.vue, a escala de media hoja. */
    .doble { width: 210mm; height: 296mm; overflow: hidden; margin: 0 auto 24px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    .doble, .doble * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .mitad { display: flex; flex-direction: column; gap: 2.6mm; height: 148mm; padding: 6mm 9mm 5mm; overflow: hidden; font-family: Arial, Helvetica, sans-serif; font-size: 8pt; line-height: 1.35; color: #111827; }
    /* Ningún bloque se encoge: si no entra, se nota al probar y no se corta en silencio. */
    .mitad > * { flex-shrink: 0; }
    .corte { position: relative; height: 0; border-top: 0.75pt dashed #9CA3AF; }
    .corte span { position: absolute; top: -6px; left: 50%; transform: translateX(-50%); padding: 0 8px; background: #fff; font-family: Arial, sans-serif; font-size: 7pt; color: #6B7280; }

    /* Membrete: logo | lema | cuadro del RUC. */
    .os-membrete { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 12px; }
    .os-logo { display: block; height: 46px; }
    .os-lema { margin: 0; font-size: 6.6pt; font-weight: 700; letter-spacing: .2px; line-height: 1.5; text-align: center; text-transform: uppercase; color: #4B5563; }
    .os-caja { min-width: 46mm; padding: 4px 10px; border: 1.2pt solid #1D4ED8; border-radius: 6px; text-align: center; }
    .os-ruc { font-size: 8pt; }
    .os-titulo { margin: 2px 0; padding: 1px 0; background: #1D4ED8; font-size: 10pt; font-weight: 800; letter-spacing: 1.2px; color: #fff; }
    .os-codigo { font-family: monospace; font-size: 9pt; font-weight: 700; }

    .os-contacto { display: flex; justify-content: space-between; gap: 12px; padding: 1.2mm 1px; border-top: 2.2pt solid #1D4ED8; border-bottom: 0.75pt solid #1D4ED8; font-size: 7.4pt; font-weight: 700; text-transform: uppercase; }

    /* Bloque con franja celeste. */
    .os-bloque { overflow: hidden; border: 0.75pt solid #1D4ED8; border-radius: 6px; }
    .os-banda { margin: 0; padding: 2px 8px; background: #DBEAFE; font-size: 7pt; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #1D4ED8; }
    .os-datos { display: grid; grid-template-columns: 1.15fr 1.25fr .9fr; gap: 5mm; padding: 2mm 8px 2.2mm; }
    .os-datos dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 7px; margin: 0; }
    .os-datos dt { font-size: 6.4pt; font-weight: 700; text-transform: uppercase; color: #4B5563; padding-top: 1px; }
    .os-datos dd { margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .os-fuerte { font-weight: 700; text-transform: uppercase; }

    /* Tabla de condiciones (etiqueta azul a la izquierda), como la proforma. */
    .os-condiciones { width: 100%; border-collapse: collapse; font-size: 7.8pt; }
    .os-condiciones th, .os-condiciones td { padding: 1.6mm 8px; border: 0.75pt solid #1D4ED8; text-align: left; vertical-align: top; }
    .os-condiciones th { width: 34mm; background: #DBEAFE; font-weight: 700; text-transform: uppercase; color: #1D4ED8; }
    .os-condiciones td { font-weight: 700; }
    .os-obs { font-weight: 400; color: #4B5563; }
    .os-check { font-weight: 400 !important; }
    .os-check span { display: inline-block; margin-right: 9px; white-space: nowrap; }
    .os-no { color: #B91C1C; font-weight: 700; }
    .os-escribir { padding-bottom: .4mm !important; }
    .os-renglon { height: 4.6mm; border-bottom: 0.5pt solid #BFDBFE; }

    /* Detalle + totales + QR en una fila. */
    .os-cuerpo { display: flex; align-items: flex-start; gap: 4mm; }
    .os-izq { flex: 1; min-width: 0; }
    .os-tabla { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 0.75pt solid #1D4ED8; border-radius: 6px; }
    .os-tabla th { padding: 1.2mm 8px; background: #1D4ED8; font-size: 7pt; font-weight: 700; text-align: left; text-transform: uppercase; color: #fff; }
    .os-tabla td { padding: 1.5mm 8px; border-top: 0.5pt solid #E5E7EB; }
    .os-tabla tbody tr:nth-child(even) td { background: #F5F8FF; }
    .os-producto { font-weight: 700; text-transform: uppercase; }
    .os-cant { width: 12mm; text-align: center !important; white-space: nowrap; }
    .os-num { width: 22mm; text-align: right !important; white-space: nowrap; }
    .os-vacio { color: #4B5563; font-style: italic; }

    .os-der { width: 50mm; }
    .os-totales { display: grid; grid-template-columns: 1fr auto; gap: 1px 10px; margin: 0; }
    .os-totales dt { color: #4B5563; }
    .os-totales dd { margin: 0; text-align: right; }
    .os-total { margin-top: 2px; padding: 1.2mm 6px !important; background: #1D4ED8; font-size: 9.5pt; font-weight: 800; text-transform: uppercase; color: #fff !important; }
    dt.os-total { border-radius: 5px 0 0 5px; }
    dd.os-total { border-radius: 0 5px 5px 0; }

    .os-qr { display: flex; flex-direction: column; align-items: center; gap: 1px; width: 22mm; font-size: 6pt; font-weight: 700; text-align: center; text-transform: uppercase; color: #1D4ED8; }
    .os-qr img { width: 21mm; height: 21mm; }

    /* Pie como el de la proforma: recuadro, firmas y el sello girado. */
    .os-pie { display: flex; align-items: center; gap: 5mm; margin-top: auto; padding: 1.8mm 10px; border: 0.75pt solid #1D4ED8; border-radius: 6px; }
    .os-terminos { flex: 1; font-size: 6.2pt; line-height: 1.35; color: #4B5563; }
    .os-terminos strong { color: #111827; }
    .os-firmas { display: flex; gap: 5mm; width: 74mm; padding-top: 16px; }
    .os-firmas div { position: relative; flex: 1; border-top: 0.75pt solid #111827; text-align: center; padding-top: 1px; font-size: 6.6pt; font-weight: 700; }
    .os-firmas span { font-weight: 400; color: #4B5563; }
    .os-firmas img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 20px; }
    .os-sello { flex-shrink: 0; width: 21mm; padding: 2mm 1mm; border: 1.5pt dashed #1D4ED8; border-radius: 999px; font-size: 6.4pt; font-weight: 800; line-height: 1.15; text-align: center; text-transform: uppercase; color: #1D4ED8; transform: rotate(-8deg); }
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
