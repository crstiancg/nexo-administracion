@extends('impresion.layout')

@section('titulo', "Cotización {$cotizacion->codigo}")

{{-- Cotización (proforma) en A4, versión de presentación: el mismo diseño que
     la orden de servicio premium (cabecera con ondas, tarjetas con pestaña,
     marca de agua, pie con Puno). Lo que ve el cliente: precios con la
     ganancia de la PC repartida, nunca el costo ni las notas internas.
     El detalle puede ser largo: la hoja crece y pasa a otra página. --}}
@php
    $zona = \App\Support\Fechas::zona();
    $svg = fn ($n, $c = '') => \App\Support\Iconos::svg($n, $c);
    $dinero = fn ($v) => 'S/ '.number_format((float) $v, 2);
    $cantidad = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

    $esPc = $cotizacion->tipo === 'pc';
    // Una PC armada puede salir sólo con el total.
    $conPrecios = ! $esPc || $cotizacion->mostrar_precios !== false;
    $equipos = $cotizacion->multiplicador();
    $cliente = $cotizacion->cliente;

    $filas = $cotizacion->items->map(function ($item) use ($ventas) {
        $v = $item->variante;
        $foto = $item->archivos->first();

        return [
            'componente' => $item->componente,
            'desc' => $v
                ? collect([$v->producto?->nombre, trim(($v->presentacion ?? '').' '.($v->color?->nombre ?? ''))])->filter()->join(' — ')
                : $item->descripcion,
            'sku' => $v?->sku,
            'cant' => $item->cantidad,
            'und' => $v ? $v->unidad?->abreviatura : $item->unidad,
            'foto' => $foto ? \Illuminate\Support\Facades\Storage::disk('public')->url($foto->miniatura ?? $foto->ruta) : null,
            'pu' => $ventas[$item->id]['precio_unitario'],
            'imp' => $ventas[$item->id]['subtotal'],
        ];
    });

    // "7 días · hasta el 10/04/2026".
    $fecha = $cotizacion->created_at->timezone($zona);
    $hasta = $cotizacion->valida_hasta;
    // Días de calendario entre las dos fechas (sin horas ni zonas de por medio).
    $dias = $hasta ? max(1, (int) round(\Illuminate\Support\Carbon::parse($fecha->toDateString())->diffInDays(\Illuminate\Support\Carbon::parse($hasta->toDateString())))) : null;
    $validez = $hasta ? $dias.' '.($dias === 1 ? 'día' : 'días').' · hasta el '.$hasta->format('d/m/Y') : null;

    $condiciones = collect([
        ['Forma de pago', $cotizacion->forma_pago ?: 'Contado'],
        ['Validez', $validez],
        ['Entrega', $cotizacion->entrega],
        ['Garantía', $cotizacion->garantia],
    ])->filter(fn ($c) => filled($c[1]));
    $libres = collect(preg_split('/\R+/', (string) $cotizacion->condiciones))->map(fn ($l) => trim($l))->filter();
@endphp

@section('estilos')
    @include('impresion.premium.estilos')

    /* ── Varias hojas: cabecera y pie fijos en TODAS ──
       En pantalla es una hoja larga: cabecera, cuerpo y pie al fondo.
       Al imprimir, la cabecera y el pie son `position: fixed` (Chrome los repite
       en cada hoja) y el cuerpo va dentro de una tabla cuyo thead/tfoot (vacíos,
       de la altura de la cabecera y del pie) también se repiten: reservan ese
       lugar en cada hoja, así los ítems corren entre los dos sin pisarlos. */
    @page { size: A4; margin: 0; }
    .p--flujo { display: flex; flex-direction: column; height: auto; min-height: 297mm; overflow: visible; overflow-x: clip; }
    .p--flujo .p-cab { flex-shrink: 0; }
    .p--flujo .p-pie { position: relative; flex-shrink: 0; margin-top: 4mm; }
    .hoja { flex: 1; width: 100%; border-collapse: collapse; }
    .hoja > tbody > tr > td { padding: 0; vertical-align: top; }
    .hoja > thead, .hoja > tfoot { display: none; }
    .hoja__cab { height: 41mm; }
    .hoja__pie { height: 37mm; }
    @media print {
        .p--flujo { display: block; min-height: 0; }
        .p--flujo .p-cab { position: fixed; top: 0; left: 0; width: 210mm; }
        .p--flujo .p-pie { position: fixed; bottom: 0; left: 0; width: 210mm; margin: 0; }
        .p--flujo .p-marca-agua { position: fixed; }
        .hoja > thead { display: table-header-group; }
        .hoja > tfoot { display: table-footer-group; }
        .hoja > thead td, .hoja > tfoot td { padding: 0; }
    }
    .p-cuerpo { display: flex; flex-direction: column; gap: 3mm; padding: 1mm 8mm 0; }
    .card, .p-fila { break-inside: avoid; }
    .tabla tr { break-inside: avoid; }

    /* ── Compacta: que una cotización normal (o una PC de ~15 piezas) entre en un A4 ──
       Cabecera de 40mm: el dibujo es el mismo de 52mm corrido 12mm hacia arriba
       (se recorta la parte de arriba del panel, no se aplasta: las ondas quedan
       iguales); logo, textos y título más chicos; sin el adorno de circuito. */
    .p-cab--compacta { height: 40mm; overflow: hidden; }
    .p-cab--compacta .p-cab__fondo { top: -12mm; height: 52mm; }
    .p-cab--compacta .p-circuito { display: none; }
    .p-cab--compacta .p-marca { top: 4.5mm; gap: 3.4mm; }
    .p-cab--compacta .p-marca img, .p-cab--compacta .p-marca__div { height: 19mm; }
    .p-cab--compacta .p-empresa { gap: .5mm; max-width: none; font-size: 8.2pt; }
    .p-cab--compacta .p-empresa strong { font-size: 11.5pt; }
    .p-cab--compacta .p-empresa b { font-size: 9.6pt; }
    .p-cab--compacta .p-empresa .ic { width: 3.4mm; height: 3.4mm; }
    .p-cab--compacta .p-empresa__horario { max-width: none; font-size: 8.2pt; white-space: nowrap; }
    .p-cab--compacta .p-titulo { top: 3mm; }
    .p-cab--compacta .p-titulo h1 { font-size: 15pt; }
    .p-cab--compacta .p-codigo { margin-top: 1.4mm; padding: .2mm 7mm; font-size: 15pt; }
    .p-cab--compacta .p-fechas { margin-top: 2mm; }
    .p-cab--compacta .p-fechas .ic { width: 5.4mm; height: 5.4mm; }
    .p-cab--compacta .p-fechas small { font-size: 7.6pt; }
    .p-cab--compacta .p-fechas b { font-size: 9.6pt; }
    /* La marca de agua, centrada en el cuerpo de esta cabecera más baja. */
    .p-marca-agua { top: calc(40mm + (297mm - 40mm - 33mm) / 2 - 75mm); }

    /* Tarjetas, tabla y totales más bajos. */
    .tab { height: 6.6mm; font-size: 10pt; }
    .tab .ic { width: 5mm; height: 5mm; }
    .card__cont { padding: 1.5mm 4.5mm 1.8mm; }
    .datos { gap: .5mm 2.4mm; font-size: 9pt; }
    .tabla, .tabla th { font-size: 9.2pt; }
    .tabla th { padding: 1.5mm 2.6mm; }
    .tabla td { padding: .9mm 2.6mm; line-height: 1.25; }
    .totales { font-size: 9.8pt; }
    .totales td { padding: 1.05mm 3.2mm; }
    .totales .fuerte td { font-size: 11pt; }
    .totales .fuerte td + td { font-size: 13pt; }

    .p-par { grid-template-columns: 1fr 1fr; }
    .datos dd.largo { white-space: normal; }

    /* Detalle: foto chica, producto y SKU. */
    .item { display: flex; align-items: center; gap: 2.6mm; }
    .item img { flex-shrink: 0; width: 9mm; height: 9mm; border-radius: 1.4mm; object-fit: cover; box-shadow: 0 0 0 0.6pt #DCE4F5; }
    .item b { display: block; font-weight: 600; }
    .item small { display: block; font-size: 7.6pt; color: #6B7A99; }
    .tabla .comp { font-weight: 800; color: #0B2266; white-space: nowrap; }
    .tabla .und { margin-left: .8mm; font-size: 7.6pt; color: #6B7A99; }
    .tabla td { vertical-align: middle; }


    /* Condiciones: etiqueta + valor por renglón. */
    /* Condiciones de la oferta: tarjeta con pestaña, como las de arriba, y una
       tabla corta etiqueta | valor; IGV, variación de precios y garantía de la
       tienda van juntos en una nota chica al pie. */
    .oferta__filas { display: grid; grid-template-columns: auto 1fr; column-gap: 4mm; margin: 0; font-size: 9.2pt; line-height: 1.25; }
    .oferta__filas > * { margin: 0; padding: 1.1mm 0; border-top: 0.6pt solid #DCE4F5; }
    .oferta__filas > :nth-child(-n+2) { border-top: 0; padding-top: 0; }
    .oferta__filas dt { font-size: 8.2pt; font-weight: 800; color: #0B2266; text-transform: uppercase; letter-spacing: .2px; white-space: nowrap; }
    .oferta__filas dd { color: #0E1B3D; }
    .oferta__filas .oferta__libre { grid-column: 1 / -1; color: #33415E; }
    .oferta__nota { margin: 1.4mm 0 0; padding-top: 1.4mm; border-top: 0.6pt solid #C7D5F2; font-size: 7.8pt; font-style: italic; line-height: 1.3; color: #5B6785; }
    .p-totales { display: grid; gap: 1.8mm; }
    .letras { margin: 0; max-width: 78mm; justify-self: end; font-size: 8.6pt; font-weight: 700; line-height: 1.3; text-align: right; color: #33415E; text-transform: uppercase; }
@endsection

@section('contenido')
<div class="p p--flujo">
    @include('impresion.premium.cabecera', [
        'sede' => $cotizacion->sede,
        'conHorario' => true,
        'compacta' => true,
        'titulo' => 'COTIZACIÓN',
        'codigo' => $cotizacion->codigo,
        'fechas' => [
            ['calendario', 'Fecha', $fecha->format('d/m/Y')],
            ['reloj', 'Válida hasta', $hasta?->format('d/m/Y') ?? '—'],
        ],
    ])

    <table class="hoja">
        <thead><tr><td><div class="hoja__cab"></div></td></tr></thead>
        <tfoot><tr><td><div class="hoja__pie"></div></td></tr></tfoot>
        <tbody><tr><td>
    <div class="p-cuerpo">
        {{-- Cliente y cotización --}}
        <div class="p-par">
            <section class="card">
                <div class="tab">{!! $svg('persona') !!} Datos del cliente</div>
                <div class="card__cont">
                    <dl class="datos">
                        <dt>Nombre</dt><dd class="dos">:</dd><dd class="fuerte">{{ $cliente?->nombre ?? 'Clientes varios' }}</dd>
                        <dt>{{ $cliente?->tipo_documento ?: 'DNI / RUC' }}</dt><dd class="dos">:</dd><dd>{{ $cliente?->numero_documento ?: '—' }}</dd>
                        <dt>Teléfono</dt><dd class="dos">:</dd><dd>{{ $cliente?->telefono ?: '—' }}</dd>
                        <dt>Dirección</dt><dd class="dos">:</dd><dd class="largo">{{ $cliente?->direccion ?: '—' }}</dd>
                    </dl>
                </div>
            </section>
            <section class="card">
                <div class="tab">{!! $svg('documento') !!} Datos de la cotización</div>
                <div class="card__cont">
                    <dl class="datos">
                        <dt>Tipo</dt><dd class="dos">:</dd><dd>{{ $esPc ? 'PC armada'.($equipos > 1 ? " · {$equipos} equipos" : '') : 'Productos' }}</dd>
                        <dt>Atendido por</dt><dd class="dos">:</dd><dd>{{ $cotizacion->usuario?->name ?? '—' }}</dd>
                        <dt>Sede</dt><dd class="dos">:</dd><dd>{{ $cotizacion->sede?->nombre ?? '—' }}</dd>
                    </dl>
                </div>
            </section>
        </div>

        {{-- Detalle. PC armada: el componente en su columna, y los precios de
             cada pieza sólo si se pidió (si no, sale únicamente el total). --}}
        <table class="tabla">
            <thead>
                <tr>
                    <th class="n">N°</th>
                    @if ($esPc)<th>Componente</th>@endif
                    <th>Descripción</th>
                    <th class="c">Cant.</th>
                    @if ($conPrecios)
                        <th class="r">P. Unitario</th>
                        <th class="r">Importe</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $n => $f)
                    <tr>
                        <td class="c">{{ $n + 1 }}</td>
                        @if ($esPc)<td class="comp">{{ $f['componente'] ?: '—' }}</td>@endif
                        <td>
                            <div class="item">
                                @if ($f['foto'])<img src="{{ $f['foto'] }}" alt="">@endif
                                <span><b>{{ $f['desc'] }}</b>@if ($f['sku'])<small>{{ $f['sku'] }}</small>@endif</span>
                            </div>
                        </td>
                        <td class="c" style="white-space: nowrap">{{ $cantidad($f['cant']) }}@if ($f['und'])<span class="und">{{ $f['und'] }}</span>@endif</td>
                        @if ($conPrecios)
                            <td class="r">{{ $dinero($f['pu']) }}</td>
                            <td class="r">{{ $dinero($f['imp']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>


        {{-- Condiciones + totales --}}
        <div class="p-fila">
            <section class="card oferta">
                <div class="tab">{!! $svg('escudo') !!} Condiciones de la oferta</div>
                <div class="card__cont">
                    <dl class="oferta__filas">
                        @foreach ($condiciones as [$etiqueta, $valor])
                            <dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd>
                        @endforeach
                        @foreach ($libres as $linea)
                            <dd class="oferta__libre">{{ $linea }}</dd>
                        @endforeach
                    </dl>
                    <p class="oferta__nota">Precios incluyen IGV, sujetos a variación sin previo aviso.@if (filled($empresa['garantia'] ?? null)) {{ $empresa['garantia'] }}@endif</p>
                </div>
            </section>
            <div class="p-totales">
                <table class="totales">
                    {{-- PC para varios equipos: el detalle es de uno. --}}
                    @if ($equipos > 1)
                        <tr class="suave"><td>Precio por equipo</td><td>{{ $dinero((float) $cotizacion->subtotal / $equipos) }}</td></tr>
                        <tr class="suave"><td>N° de equipos</td><td>× {{ $equipos }}</td></tr>
                    @endif
                    @if ((float) $cotizacion->descuento || $equipos > 1)
                        <tr class="suave"><td>Subtotal</td><td>{{ $dinero($cotizacion->subtotal) }}</td></tr>
                    @endif
                    @if ((float) $cotizacion->descuento)
                        <tr class="suave"><td>Descuento</td><td>−{{ $dinero($cotizacion->descuento) }}</td></tr>
                    @endif
                    <tr class="suave"><td>Op. gravada</td><td>{{ $dinero($cotizacion->op_gravada) }}</td></tr>
                    <tr class="suave"><td>IGV ({{ rtrim(rtrim(number_format(\App\Support\Igv::tasa() * 100, 2), '0'), '.') }}%)</td><td>{{ $dinero($cotizacion->igv) }}</td></tr>
                    <tr class="fuerte"><td>Total</td><td>{{ $dinero($cotizacion->total) }}</td></tr>
                </table>
                <p class="letras">{{ $totalLetras }}</p>
            </div>
        </div>
    </div>
        </td></tr></tbody>
    </table>

    @include('impresion.premium.pie')
</div>
@endsection
