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
    $terminos = collect(preg_split('/(?<=\.)\s+/', (string) $empresa['terminos_orden']))->filter()->take(2);
    $i = [
        'lugar' => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z',
        'fono' => 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.61 21 3 13.39 3 4c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        'persona' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
        'laptop' => 'M20 18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z',
        'alerta' => 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
        'engrane' => 'M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.48.48 0 0 0-.48-.41h-3.84a.48.48 0 0 0-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.49.49 0 0 0-.59.22L2.74 8.87a.48.48 0 0 0 .12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.48.48 0 0 0-.12-.61l-2.01-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z',
        'calendario' => 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z',
        'reloj' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z',
        'escudo' => 'M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z',
        'check' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
        'lapiz' => 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z',
        'llave' => 'M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z',
        'chip' => 'M15 9H9v6h6V9zm-2 4h-2v-2h2v2zm8-2V9h-2V7c0-1.1-.9-2-2-2h-2V3h-2v2h-2V3H9v2H7c-1.1 0-2 .9-2 2v2H3v2h2v2H3v2h2v2c0 1.1.9 2 2 2h2v2h2v-2h2v2h2v-2h2c1.1 0 2-.9 2-2v-2h2v-2h-2v-2h2zm-4 6H7V7h10v10z',
    ];
    $svg = fn ($n, $c = '') => '<svg viewBox="0 0 24 24" class="ic '.$c.'"><path d="'.$i[$n].'"/></svg>';
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
    @page { size: A4; margin: 0; }
    /* La misma letra del sistema (DM Sans), incrustada: gruesa y geométrica,
       no depende de lo que tenga instalado la PC que imprime. */
    @font-face { font-family: 'Nexo Sans'; src: url('{{ asset('fonts/dm-sans.woff2') }}') format('woff2'); font-weight: 100 1000; font-display: block; }
    .p { position: relative; width: 210mm; height: 297mm; margin: 0 auto 24px; overflow: hidden; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); font-family: 'Nexo Sans', 'Segoe UI', Arial, sans-serif; font-size: 10.4pt; line-height: 1.35; color: #0E1B3D; }
    .p, .p * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ic { width: 1em; height: 1em; fill: currentColor; flex-shrink: 0; }
    @media print { .p { margin: 0; box-shadow: none; } }

    /* ── Cabecera ── */
    .p-cab { position: relative; height: 52mm; }
    .p-cab__fondo { position: absolute; inset: 0; width: 100%; height: 100%; }
    .p-circuito { position: absolute; left: 0; top: 30mm; width: 70mm; opacity: .5; }
    .p-marca { position: absolute; left: 9mm; top: 8mm; display: flex; align-items: center; gap: 4mm; }
    .p-marca img { height: 24mm; }
    .p-marca__div { width: 0; height: 24mm; border-left: 1pt solid #0B2266; }
    .p-empresa { display: grid; gap: 1.1mm; max-width: 60mm; font-size: 9.2pt; }
    .p-empresa strong { font-size: 13pt; font-weight: 800; white-space: nowrap; letter-spacing: .2px; color: #0B2266; }
    .p-empresa b { font-size: 11pt; font-weight: 800; color: #0B2266; }
    .p-empresa span { display: flex; align-items: center; gap: 2mm; }
    .p-empresa .ic { width: 4mm; height: 4mm; color: #0B2266; }
    .p-titulo { position: absolute; right: 4mm; top: 5mm; width: 74mm; text-align: center; color: #fff; }
    .p-titulo h1 { margin: 0; font-size: 18pt; font-weight: 900; line-height: 1; letter-spacing: -.3px; white-space: nowrap; }
    .p-codigo { display: inline-block; margin-top: 2mm; padding: .4mm 8mm; border-radius: 2mm; background: #1D5FD1; font-size: 18pt; font-weight: 900; letter-spacing: .3px; }
    .p-fechas { display: flex; justify-content: center; gap: 3mm; margin-top: 3mm; text-align: left; }
    .p-fechas > div { display: flex; align-items: center; gap: 2mm; }
    .p-fechas > div + div { padding-left: 3mm; border-left: 0.75pt solid rgba(255,255,255,.5); }
    .p-fechas .ic { width: 6.5mm; height: 6.5mm; }
    .p-fechas small { display: block; font-size: 8.6pt; opacity: .9; }
    .p-fechas b { font-size: 10.6pt; font-weight: 800; white-space: nowrap; }
    .p-fechas small { white-space: nowrap; }

    /* ── Cuerpo ── */
    .p-cuerpo { display: flex; flex-direction: column; justify-content: space-between; gap: 2.8mm; height: calc(297mm - 52mm - 33mm - 5mm); padding: 1mm 8mm 0; }
    /* Ninguna sección se encoge (se perdía su texto): sólo se reparte el aire. */
    .p-cuerpo > * { flex-shrink: 0; }
    .card { position: relative; overflow: hidden; border: 0.75pt solid #C7D5F2; border-radius: 2.4mm; background: linear-gradient(180deg, #F5F8FF, #fff 60%); }
    .tab { display: inline-flex; align-items: center; gap: 2.4mm; height: 7.8mm; padding: 0 10mm 0 4.5mm; background: linear-gradient(90deg, #0A1E5E, #13328C); color: #fff; font-size: 11.2pt; font-weight: 800; letter-spacing: .1px; text-transform: uppercase; clip-path: polygon(0 0, calc(100% - 4mm) 0, 100% 50%, calc(100% - 4mm) 100%, 0 100%); }
    .tab .ic { width: 5.8mm; height: 5.8mm; }
    .tab--total { display: flex; clip-path: none; padding-right: 4mm; }
    .card__cont { padding: 2mm 5mm 2.4mm; }

    .p-par { display: grid; grid-template-columns: 1fr 1.3fr; gap: 4mm; }
    .datos { display: grid; grid-template-columns: auto auto 1fr; gap: 1.4mm 2.6mm; margin: 0; font-size: 9.8pt; }
    .datos dt { color: #33415E; }
    .datos dd { margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
    .datos .dos { margin: 0; color: #33415E; }
    .datos--chico { font-size: 9.2pt; gap: .9mm 2.6mm; }
    .datos .fuerte { font-weight: 800; text-transform: uppercase; }
    .datos .largo { white-space: normal; }
    .equipo { display: flex; }
    .equipo .card__cont { flex: 1; min-width: 0; }
    .equipo__foto { width: 27mm; margin: 2.4mm 2.4mm 2.4mm 0; border-radius: 2mm; object-fit: cover; }
    .equipo__sinfoto { display: grid; place-items: center; width: 27mm; margin: 2.4mm 2.4mm 2.4mm 0; border-radius: 2mm; background: #EEF3FD; color: #8EA6D8; }
    .equipo__sinfoto .ic { width: 16mm; height: 16mm; }

    .texto { font-size: 11pt; font-weight: 700; }
    .renglon { height: 5.6mm; border-bottom: 0.6pt solid #C7D5F2; }

    /* Tabla de detalle. */
    .tabla { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 0.75pt solid #C7D5F2; border-radius: 2.4mm; font-size: 10.4pt; }
    .tabla th { padding: 2mm 3mm; text-align: center !important; background: linear-gradient(180deg, #13328C, #0A1E5E); color: #fff; font-size: 10.4pt; font-weight: 800; text-transform: uppercase; }
    .tabla th + th, .tabla td + td { border-left: 0.75pt solid #C7D5F2; }
    .tabla th + th { border-left-color: rgba(255,255,255,.25); }
    .tabla td { padding: 1.6mm 3mm; border-top: 0.6pt solid #DCE4F5; }
    .tabla .c { text-align: center; }
    .tabla .r { text-align: right; white-space: nowrap; }
    .tabla .n { width: 10mm; }

    /* Condiciones + totales. */
    .p-fila { display: grid; grid-template-columns: 1fr 78mm; gap: 5mm; align-items: start; }
    .cond { padding: 2.8mm 5mm; border: 0.75pt solid #C7D5F2; border-radius: 2.4mm; background: #F2F6FE; }
    .cond h3 { display: flex; align-items: center; gap: 2.4mm; margin: 0 0 2.2mm; font-size: 12pt; font-weight: 900; color: #0B2266; text-transform: uppercase; }
    .cond h3 .ic { width: 7mm; height: 7mm; color: #1D5FD1; }
    .cond ul { display: grid; gap: 1.1mm; margin: 0; padding: 0; list-style: none; font-size: 9.6pt; }
    .cond li { display: flex; gap: 2mm; }
    .cond li .ic { width: 4.6mm; height: 4.6mm; margin-top: .3mm; color: #1D5FD1; }
    .totales { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 0.75pt solid #C7D5F2; border-radius: 2.4mm; font-size: 10.8pt; }
    .totales td { padding: 1.5mm 3.4mm; white-space: nowrap; border-top: 0.6pt solid #DCE4F5; background: #F2F6FE; font-weight: 700; }
    .totales tr:first-child td { border-top: 0; }
    .totales td + td { text-align: right; border-left: 0.75pt solid #DCE4F5; }
    .totales .suave td { background: #fff; font-weight: 400; color: #33415E; }
    .totales .fuerte td { background: linear-gradient(180deg, #13328C, #0A1E5E); color: #fff; font-size: 12.4pt; font-weight: 900; text-transform: uppercase; }
    .totales .fuerte td + td { font-size: 14.5pt; border-left-color: rgba(255,255,255,.25); }
    .totales .pagado td { background: #15803D; }

    /* Aprobación + QR. */
    .p-firma { display: grid; grid-template-columns: 1fr 0 80mm; gap: 6mm; align-items: center; }
    .aprob h3 { display: flex; align-items: center; gap: 2.4mm; margin: 0 0 2.2mm; font-size: 12pt; font-weight: 900; color: #0B2266; text-transform: uppercase; }
    .aprob h3 .ic { width: 6mm; height: 6mm; color: #1D5FD1; }
    .aprob__fila { display: flex; align-items: flex-end; gap: 2mm; margin-bottom: 2.2mm; font-size: 9.6pt; }
    .aprob__linea { flex: 1; min-width: 0; height: 5mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; border-bottom: 0.75pt solid #0E1B3D; font-weight: 600; }
    .aprob__firma { position: relative; width: 62mm; margin: 4mm 0 0 auto; padding-top: 1mm; border-top: 0.75pt solid #0E1B3D; text-align: center; font-size: 10.2pt; }
    .aprob__firma img { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); height: 11mm; }
    .p-firma__div { height: 29mm; border-left: 0.75pt solid #0E1B3D; }
    .qr { display: flex; align-items: center; gap: 4mm; }
    .qr__img { padding: 1.6mm; border: 0.9pt solid #1D5FD1; border-radius: 2.4mm; }
    .qr__img img { display: block; width: 26mm; height: 26mm; }
    .qr h3 { margin: 0 0 1.8mm; font-size: 14pt; font-weight: 900; line-height: 1.05; color: #0B2266; text-transform: uppercase; }
    .qr p { margin: 0 0 2mm; font-size: 9.4pt; color: #33415E; }
    .qr__pill { display: inline-flex; align-items: center; gap: 1.4mm; padding: .8mm 2.4mm; border-radius: 99px; background: #16A34A; color: #fff; font-size: 8.4pt; font-weight: 800; }
    .qr__pill .ic { width: 3.6mm; height: 3.6mm; }

    /* ── Pie con Puno ── */
    .p-pie { position: absolute; left: 0; right: 0; bottom: 0; height: 33mm; }
    .p-pie__ola { position: absolute; left: 0; top: 0; width: 100%; height: 9mm; }
    .p-pie__banda { position: absolute; left: 0; right: 0; bottom: 0; height: 25mm; display: flex; align-items: center; background: linear-gradient(90deg, #06143F, #0A1E5E 45%, #0B2266); color: #fff; }
    .p-pie__puno { position: absolute; left: 0; top: 0; bottom: 0; width: 72mm; background: url('{{ asset('img/banner.webp') }}') 100% 44% / 360% auto no-repeat; -webkit-mask-image: linear-gradient(90deg, transparent, #000 18%, #000 50%, transparent); mask-image: linear-gradient(90deg, transparent, #000 18%, #000 50%, transparent); opacity: .9; }
    .p-pie__lema { position: relative; margin-left: 50mm; font-size: 17pt; font-weight: 900; font-style: italic; white-space: nowrap; letter-spacing: .2px; }
    .p-pie__lema::after { content: ''; display: block; width: 46mm; height: 1.2mm; margin: 1.4mm 0 0 18mm; border-radius: 1mm; background: linear-gradient(90deg, transparent, #E01E26 30%, #E01E26 70%, transparent); }
    .p-pie__serv { position: relative; display: flex; margin-left: auto; margin-right: 6mm; }
    .p-pie__serv div { display: flex; flex-direction: column; align-items: center; gap: 1.4mm; width: 24mm; font-size: 8.8pt; font-weight: 800; text-align: center; line-height: 1.15; }
    .p-pie__serv div + div { border-left: 0.75pt solid rgba(255,255,255,.35); }
    .p-pie__serv .ic { width: 7mm; height: 7mm; fill: none; stroke: #fff; stroke-width: 1.4; }
@endsection

@section('contenido')
<div class="p">
    {{-- ── Cabecera ── --}}
    <header class="p-cab">
        <svg class="p-cab__fondo" viewBox="0 0 210 52" preserveAspectRatio="none">
            <defs>
                <linearGradient id="noche" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#0B2266"/><stop offset="1" stop-color="#06143F"/>
                </linearGradient>
            </defs>
            <path d="M127 0 H210 V40 Q176 43 113 50 Q128 31 127 0 Z" fill="url(#noche)"/>
            <path d="M123 0 Q125 28 110 49" fill="none" stroke="#1D5FD1" stroke-width="1.1"/>
            <path d="M0 50 Q90 46 210 41" fill="none" stroke="#1D5FD1" stroke-width="1.2"/>
            <path d="M60 52 Q140 47 210 44.4" fill="none" stroke="#E01E26" stroke-width="1.3"/>
        </svg>
        <svg class="p-circuito" viewBox="0 0 70 24" fill="none" stroke="#C9D6F2" stroke-width=".35">
            <path d="M0 4h18l4 4h14M0 10h10l3 3h22l3-3h10M0 16h24l3 3h18M0 21h8"/>
            <circle cx="36" cy="8" r=".9"/><circle cx="48" cy="10" r=".9"/><circle cx="45" cy="19" r=".9"/><circle cx="8" cy="21" r=".9"/>
        </svg>

        <div class="p-marca">
            <img src="{{ $empresa['logo_url'] ?? asset('img/logo.png') }}" alt="{{ $empresa['nombre'] }}">
            <span class="p-marca__div"></span>
            <div class="p-empresa">
                <strong>{{ $empresa['nombre'] }}</strong>
                <b>RUC: {{ $empresa['ruc'] }}</b>
                <span>{!! $svg('lugar') !!}{{ $orden->sede?->direccion ?: $empresa['direccion'] }}</span>
                <span>{!! $svg('fono') !!}{{ $orden->sede?->telefono ?: $empresa['telefono'] }}</span>
            </div>
        </div>

        <div class="p-titulo">
            <h1>ORDEN DE SERVICIO</h1>
            <div class="p-codigo">{{ $orden->codigo }}</div>
            <div class="p-fechas">
                <div>{!! $svg('calendario') !!}<span><small>Recibido</small><b>{{ $orden->recibido_at->timezone($zona)->format('d/m/Y') }}</b></span></div>
                <div>{!! $svg('reloj') !!}<span><small>Estado</small><b>{{ $estado }}</b></span></div>
            </div>
        </div>
    </header>

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

        {{-- Condiciones + totales --}}
        <div class="p-fila">
            <div class="cond">
                <h3>{!! $svg('escudo') !!} Condiciones del servicio</h3>
                <ul>
                    @if ($orden->garantia_dias > 0)
                        <li>{!! $svg('check') !!}Garantía de {{ $orden->garantia_dias }} días desde la entrega, sobre la falla reparada.</li>
                    @endif
                    <li>{!! $svg('check') !!}Los trabajos adicionales requieren la aprobación del cliente.</li>
                    @foreach ($terminos as $t)
                        <li>{!! $svg('check') !!}{{ $t }}</li>
                    @endforeach
                </ul>
            </div>
            <table class="totales">
                <tr class="suave"><td>Repuestos</td><td>S/ {{ number_format($repuestos->sum('subtotal'), 2) }}</td></tr>
                <tr class="suave"><td>Mano de obra</td><td>S/ {{ number_format((float) $orden->mano_obra, 2) }}</td></tr>
                <tr class="fuerte"><td>Total</td><td>S/ {{ number_format($total, 2) }}</td></tr>
                <tr class="suave"><td>A cuenta</td><td>S/ {{ number_format($pagado, 2) }}</td></tr>
                <tr class="fuerte {{ $saldo > 0 ? '' : 'pagado' }}"><td>{{ $saldo > 0 ? 'Saldo pendiente' : 'Pagado' }}</td><td>S/ {{ number_format($saldo, 2) }}</td></tr>
            </table>
        </div>

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

    {{-- ── Pie ── --}}
    <footer class="p-pie">
        <svg class="p-pie__ola" viewBox="0 0 210 12" preserveAspectRatio="none">
            <path d="M0 12 V5 Q60 -1 120 5 T210 3 V12 Z" fill="#0A1E5E"/>
            <path d="M0 4 Q60 -2 120 4 T210 2" fill="none" stroke="#1D5FD1" stroke-width="1"/>
            <path d="M40 6 Q100 1 150 6 T210 4.5" fill="none" stroke="#E01E26" stroke-width=".9"/>
        </svg>
        <div class="p-pie__banda">
            <div class="p-pie__puno"></div>
            <div class="p-pie__lema">Tecnología que te conecta</div>
            <div class="p-pie__serv">
                <div><svg viewBox="0 0 24 24" class="ic"><path d="M4 6h16v10H4zM2 18h20"/></svg>Soporte<br>técnico</div>
                <div><svg viewBox="0 0 24 24" class="ic"><path d="{{ $i['llave'] }}"/></svg>Mantenimiento</div>
                <div><svg viewBox="0 0 24 24" class="ic"><path d="M7 7h10v10H7zM10 10h4v4h-4zM9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4"/></svg>Soluciones<br>tecnológicas</div>
            </div>
        </div>
    </footer>
</div>
@endsection
