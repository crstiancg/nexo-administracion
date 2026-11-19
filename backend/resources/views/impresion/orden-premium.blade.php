@extends('impresion.layout')

@section('titulo', "Orden de servicio {$orden->codigo}")

{{-- Orden de servicio en A4 completo, versión de presentación: cabecera con
     ondas azul noche, secciones con pestaña, foto del equipo, condiciones,
     aprobación del cliente, QR y el pie con Puno. Es un formato aparte: la
     orden simple y la de dos copias siguen igual. --}}
@php
    $zona = \App\Support\Fechas::zona();
    $foto = $orden->archivos()->first();
    $fotoUrl = $foto ? \Illuminate\Support\Facades\Storage::disk('public')->url($foto->miniatura ?? $foto->ruta) : null;
    // Las condiciones del servicio de la orden premium (el punto 4 sale resaltado).
    $clausulas = [
        ['Diagnóstico y Presupuesto', 'El diagnóstico cuesta entre S/ 10.00 y S/ 25.00 (exonerado si acepta la reparación). Presupuestos válidos por 5 días hábiles; pasado este plazo, los precios pueden variar.', false],
        ['Responsabilidad de Datos', 'Nexo Technology NO se responsabiliza por pérdida de datos, software o licencias. Es responsabilidad exclusiva del cliente realizar su respaldo previo. El formateo solo incluye Sistema Operativo, no recuperación de archivos personales (salvo contratación expresa del servicio).', false],
        ['Garantía del Servicio', 'Garantía de 1 a 15 días únicamente sobre mano de obra y repuesto reemplazado. Se anula por humedad, golpes, fallas eléctricas, mal uso o manipulación de terceros. Software no tiene garantía.', false],
        ['Recojo, Almacenaje y Abandono', 'Notificado el cliente (WhatsApp/llamada), tiene 15 días calendario para recoger su equipo sin costo. Luego se cobrará S/ 0.50 diarios por custodia. Pasados 90 días calendario sin retiro ni pago, el equipo entra en abandono, facultando a Nexo Technology Perú S.R.L. a trasladarlo a almacén externo (sin responsabilidad por deterioro) o disponer/despiezar el bien conforme al Código Civil y Ley N.º 29571 para cubrir los gastos generados.', true],
    ];
    $svg = fn ($n, $c = '') => \App\Support\Iconos::svg($n, $c);
    $filas = $repuestos->map(fn ($r) => [
        'desc' => trim(($r->variante?->producto?->nombre ?? '').' — '.($r->variante?->presentacion ?? ''), ' —'),
        'cant' => rtrim(rtrim(number_format((float) $r->cantidad, 3, '.', ''), '0'), '.'),
        'pu' => (float) $r->precio_unitario,
        'imp' => (float) $r->subtotal,
    ])->values();
    if ((float) $orden->mano_obra > 0) {
        $filas->push(['desc' => 'Mano de obra / servicio técnico', 'cant' => '1', 'pu' => (float) $orden->mano_obra, 'imp' => (float) $orden->mano_obra]);
    }
@endphp

@section('estilos')
    @include('impresion.premium.estilos')

    /* ── Cuerpo ── */
    .p-cuerpo { display: flex; flex-direction: column; justify-content: space-between; gap: 2.2mm; height: calc(297mm - 52mm - 33mm - 5mm); padding: 1mm 8mm 0; }
    /* Ninguna sección se encoge (se perdía su texto): sólo se reparte el aire. */
    .p-cuerpo > * { flex-shrink: 0; }
    .equipo { display: flex; }
    .equipo .card__cont { flex: 1; min-width: 0; }
    /* La foto ocupa todo el lado derecho de la tarjeta, al ras arriba, abajo y a la
       derecha (la esquina la redondea la tarjeta), como la pestaña a la izquierda. */
    .equipo__foto { flex-shrink: 0; align-self: stretch; width: 30mm; object-fit: cover; }
    .equipo__sinfoto { flex-shrink: 0; display: grid; place-items: center; width: 30mm; background: #EEF3FD; color: #8EA6D8; }
    .equipo__sinfoto .ic { width: 16mm; height: 16mm; }

    /* Totales: una barra de cinco casillas (etiqueta arriba, monto abajo); el
       total y el saldo en azul noche (verde si está pagado), como antes. */
    .resumen { display: grid; grid-template-columns: 1fr 1fr 1.2fr 1fr 1.3fr; overflow: hidden; border-radius: 2.4mm; box-shadow: inset 0 0 0 0.75pt #C7D5F2; background: #fff; }
    .resumen > div { display: flex; flex-direction: column; justify-content: center; gap: .3mm; padding: 1.5mm 3.4mm; text-align: right; }
    .resumen > div + div { border-left: 0.75pt solid #DCE4F5; }
    .resumen small { font-size: 7.8pt; font-weight: 700; color: #6B7A99; text-transform: uppercase; letter-spacing: .2px; text-align: left; }
    .resumen b { font-size: 11pt; font-weight: 700; white-space: nowrap; }
    .resumen .fuerte { background: linear-gradient(180deg, #13328C, #0A1E5E); color: #fff; border-left-color: transparent; }
    .resumen .fuerte + div { border-left-color: transparent; }
    .resumen .fuerte small { color: rgba(255,255,255,.75); }
    .resumen .fuerte b { font-size: 13.5pt; font-weight: 900; }
    .resumen .pagado { background: #15803D; }

    /* Condiciones del servicio: letra chica (la hoja es una sola y no crece), en
       dos columnas; la 4 (recojo y abandono) resaltada en rojo. */
    .clausulas { display: grid; grid-template-columns: 1.08fr 1fr; gap: 5mm; padding-top: 1.8mm; }
    .clausulas ol { display: grid; align-content: start; gap: 1mm; margin: 0; padding: 0; list-style: none; font-size: 7.3pt; line-height: 1.25; text-align: justify; color: #33415E; }
    .clausulas b { font-weight: 800; color: #0B2266; }
    .clausula--resaltada { padding: 1.6mm 2.2mm; border-left: 1mm solid #E01E26; border-radius: 0 1.6mm 1.6mm 0; background: #FDECEC; color: #4A1015; }
    .clausula--resaltada b { color: #B3121B; }

    .texto { font-size: 11pt; font-weight: 700; }
    .renglon { height: 5.6mm; border-bottom: 0.6pt solid #C7D5F2; }

    /* Aprobación. */
    .p-firma { display: grid; grid-template-columns: 1fr 0 80mm; gap: 6mm; align-items: center; }
    .aprob h3 { display: flex; align-items: center; gap: 2.4mm; margin: 0 0 2.2mm; font-size: 12pt; font-weight: 900; color: #0B2266; text-transform: uppercase; }
    .aprob h3 .ic { width: 6mm; height: 6mm; color: #1D5FD1; }
    .aprob__fila { display: flex; align-items: flex-end; gap: 2mm; margin-bottom: 2.2mm; font-size: 9.6pt; }
    .aprob__linea { flex: 1; min-width: 0; height: 5mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; border-bottom: 0.75pt solid #0E1B3D; font-weight: 600; }
    .aprob__firma { position: relative; width: 62mm; margin: 4mm 0 0 auto; padding-top: 1mm; border-top: 0.75pt solid #0E1B3D; text-align: center; font-size: 10.2pt; }
    .aprob__firma img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 11mm; }
    .p-firma__div { height: 23mm; border-left: 0.75pt solid #0E1B3D; }
    /* QR un poco más chico que en la cotización: aquí la hoja va llena (condiciones completas). */
    .p-firma .qr__img img { width: 21mm; height: 21mm; }
@endsection

@section('contenido')
<div class="p">
    {{-- ── Cabecera ── --}}
    @include('impresion.premium.cabecera', [
        'sede' => $orden->sede,
        'titulo' => 'ORDEN DE SERVICIO',
        'codigo' => $orden->codigo,
        'fechas' => [['calendario', 'Recibido', $orden->recibido_at->timezone($zona)->format('d/m/Y')], ['reloj', 'Estado', $estado]],
    ])

    <div class="p-cuerpo">
        {{-- Cliente y equipo --}}
        <div class="p-par">
            <section class="card">
                <div class="tab">{!! $svg('persona') !!} Datos del cliente</div>
                <div class="card__cont">
                    <dl class="datos">
                        <dt>Nombre</dt><dd class="dos">:</dd><dd class="fuerte">{{ $orden->cliente->nombre }}</dd>
                        <dt>{{ $orden->cliente->tipo_documento ?: 'DNI / RUC' }}</dt><dd class="dos">:</dd><dd>{{ $orden->cliente->numero_documento ?: '—' }}</dd>
                        <dt>Teléfono</dt><dd class="dos">:</dd><dd>{{ $orden->cliente->telefono ?: '—' }}</dd>
                        <dt>Correo</dt><dd class="dos">:</dd><dd>{{ $orden->cliente->email ?: '—' }}</dd>
                    </dl>
                </div>
            </section>
            <section class="card equipo">
                <div class="card__cont" style="padding: 0">
                    <div class="tab">{!! $svg('laptop') !!} Datos del equipo</div>
                    <div class="card__cont">
                        <dl class="datos datos--chico">
                            <dt>Tipo</dt><dd class="dos">:</dd><dd>{{ $orden->tipoEquipo?->nombre ?? '—' }}</dd>
                            <dt>Marca</dt><dd class="dos">:</dd><dd>{{ $orden->marca?->nombre ?? '—' }}</dd>
                            <dt>Modelo</dt><dd class="dos">:</dd><dd>{{ $orden->modelo ?: '—' }}</dd>
                            <dt>N° Serie</dt><dd class="dos">:</dd><dd>{{ $orden->serie ?: '—' }}</dd>
                            <dt>Accesorios</dt><dd class="dos">:</dd><dd class="largo">{{ $orden->accesorios ?: 'Ninguno' }}</dd>
                        </dl>
                    </div>
                </div>
                @if ($fotoUrl)
                    <img class="equipo__foto" src="{{ $fotoUrl }}" alt="Foto del equipo">
                @else
                    <div class="equipo__sinfoto">{!! $svg('laptop') !!}</div>
                @endif
            </section>
        </div>

        {{-- Falla --}}
        <section class="card">
            <div class="tab">{!! $svg('alerta') !!} Falla reportada</div>
            <div class="card__cont texto">
                {{ $orden->falla }}
                @if ($orden->observacion)
                    <div style="font-weight: 400; color: #33415E">Observaciones: {{ $orden->observacion }}</div>
                @endif
            </div>
        </section>

        {{-- Diagnóstico --}}
        <section class="card">
            <div class="tab">{!! $svg('engrane') !!} Diagnóstico y trabajo realizado</div>
            <div class="card__cont">
                @if ($orden->diagnostico)
                    <div class="texto">{{ $orden->diagnostico }}</div>
                @else
                    <div class="renglon"></div><div class="renglon"></div>
                @endif
            </div>
        </section>

        {{-- Detalle --}}
        <table class="tabla">
            <thead>
                <tr><th class="n">N°</th><th>Descripción</th><th class="c">Cant.</th><th class="r">P. Unitario</th><th class="r">Importe</th></tr>
            </thead>
            <tbody>
                @forelse ($filas->take(5) as $n => $f)
                    <tr>
                        <td class="c">{{ $n + 1 }}</td>
                        <td>{{ $f['desc'] }}</td>
                        <td class="c">{{ $f['cant'] }}</td>
                        <td class="r">S/ {{ number_format($f['pu'], 2) }}</td>
                        <td class="r">S/ {{ number_format($f['imp'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="c">—</td><td colspan="4" style="color: #33415E; font-style: italic">El detalle se cotiza después del diagnóstico.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Totales en una barra bajo el detalle: así entran las condiciones completas. --}}
        <div class="resumen">
            <div><small>Repuestos</small><b>S/ {{ number_format($repuestos->sum('subtotal'), 2) }}</b></div>
            <div><small>Mano de obra</small><b>S/ {{ number_format((float) $orden->mano_obra, 2) }}</b></div>
            <div class="fuerte"><small>Total</small><b>S/ {{ number_format($total, 2) }}</b></div>
            {{-- Con saldo: lo dado a cuenta y lo que falta. Ya pagado todo: el saldo en
                 cero y, resaltado, cuánto pagó (por adelantado si el equipo aún no se entregó). --}}
            @if ($saldo > 0)
                <div><small>A cuenta</small><b>S/ {{ number_format($pagado, 2) }}</b></div>
                <div class="fuerte"><small>Saldo pendiente</small><b>S/ {{ number_format($saldo, 2) }}</b></div>
            @else
                <div><small>Saldo</small><b>S/ {{ number_format(max($saldo, 0), 2) }}</b></div>
                <div class="fuerte pagado"><small>{{ $orden->entregado_at ? 'Pagado' : 'Pagado por adelantado' }}</small><b>S/ {{ number_format($pagado, 2) }}</b></div>
            @endif
        </div>

        {{-- Condiciones del servicio: 1 a 3 a la izquierda, la 4 (resaltada) a la derecha. --}}
        <section class="card">
            <div class="tab">{!! $svg('escudo') !!} Condiciones del servicio</div>
            <div class="card__cont clausulas">
                @foreach (collect($clausulas)->values()->chunk(3) as $columna)
                    <ol>
                        @foreach ($columna as $n => [$titulo, $texto, $resaltar])
                            <li @class(['clausula--resaltada' => $resaltar])><b>{{ $n + 1 }}.- {{ $titulo }}:</b> {{ $texto }}</li>
                        @endforeach
                    </ol>
                @endforeach
            </div>
        </section>

        {{-- Aprobación + QR --}}
        <div class="p-firma">
            <div class="aprob">
                <h3>{!! $svg('lapiz') !!} Conformidad del cliente</h3>
                <div class="aprob__fila">Nombre: <span class="aprob__linea">{{ $orden->cliente->nombre }}</span> DNI: <span class="aprob__linea" style="flex: 0 0 30mm">{{ $orden->cliente->numero_documento }}</span></div>
                <div class="aprob__fila">Fecha: <span class="aprob__linea" style="flex: 0 0 34mm">{{ $orden->recibido_at->timezone($zona)->format('d/m/Y') }}</span></div>
                <div class="aprob__firma">
                    @if ($orden->firma_recepcion)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($orden->firma_recepcion) }}" alt="Firma del cliente">
                    @endif
                    Firma del cliente
                </div>
            </div>
            <div class="p-firma__div"></div>
            <div class="qr">
                <div class="qr__img"><img src="{{ $qr }}" alt="QR de seguimiento"></div>
                <div>
                    <h3>Consulta tu<br>orden</h3>
                    <p>Escanea el código QR para ver el estado de tu equipo.</p>
                    <span class="qr__pill">{!! $svg('check') !!} Seguimiento en línea</span>
                </div>
            </div>
        </div>
    </div>

    @include('impresion.premium.pie')
</div>
@endsection
