{{-- Media hoja A4 (apaisada, 210 × 148 mm) con toda la orden. Se usa dos
     veces en la misma hoja: copia de la tienda arriba y del cliente abajo.

     Mismo lenguaje que la proforma (CotizacionDocumento.vue): membrete con el
     lema al centro, cuadro del RUC con la banda azul, línea de contacto entre
     bordes azules, bloques con franja celeste, tabla de encabezado azul,
     total en azul sólido y el sello punteado. Así los documentos de Nexo son
     una sola familia. --}}
@php($tienda = $copia === 'Copia tienda')
@php($zona = \App\Support\Fechas::zona())
<div class="mitad">
    <header class="os-membrete">
        <img class="os-logo" src="{{ $empresa['logo_url'] ?? asset('img/logo.png') }}" alt="{{ $empresa['nombre'] }}">
        <p class="os-lema">{{ $empresa['lema'] }}</p>
        <div class="os-caja">
            <div class="os-ruc">RUC {{ $empresa['ruc'] }}</div>
            <div class="os-titulo">ORDEN DE SERVICIO</div>
            <div class="os-codigo">N° {{ $orden->codigo }}</div>
        </div>
    </header>

    <div class="os-contacto">
        <span>{{ $orden->sede?->direccion ?: $empresa['direccion'] }}</span>
        <span>Contacto: {{ $orden->sede?->telefono ?: $empresa['telefono'] }}</span>
    </div>

    <section class="os-bloque">
        <h3 class="os-banda">Datos del cliente y del equipo</h3>
        <div class="os-datos">
            <dl>
                <dt>Cliente</dt>
                <dd class="os-fuerte">{{ $orden->cliente->nombre }}</dd>
                <dt>{{ $orden->cliente->tipo_documento ?: 'DNI/RUC' }}</dt>
                <dd>{{ $orden->cliente->numero_documento ?: '—' }}</dd>
                <dt>Teléfono</dt>
                <dd>{{ $orden->cliente->telefono ?: '—' }}</dd>
            </dl>
            <dl>
                <dt>Equipo</dt>
                <dd class="os-fuerte">{{ $orden->equipo() }}</dd>
                <dt>Serie / IMEI</dt>
                <dd>{{ $orden->serie ?: '—' }} · Bloqueo: {{ $seguridad }}</dd>
                <dt>Accesorios</dt>
                <dd>{{ $orden->accesorios ?: 'Ninguno' }}</dd>
            </dl>
            <dl>
                <dt>Recibido</dt>
                <dd>{{ $orden->recibido_at->timezone($zona)->format('d/m/Y H:i') }}</dd>
                <dt>Técnico</dt>
                <dd>{{ $orden->tecnico?->name ?? 'Por asignar' }}</dd>
                <dt>Estado</dt>
                <dd>{{ $estado }}{{ $orden->es_garantia ? ' (garantía)' : '' }}</dd>
            </dl>
        </div>
    </section>

    <table class="os-condiciones">
        <tbody>
            <tr>
                <th>Falla reportada</th>
                <td>
                    {{ $orden->falla }}
                    @if ($orden->observacion)
                        <span class="os-obs">— Obs.: {{ $orden->observacion }}</span>
                    @endif
                </td>
            </tr>
            @if ($orden->checklist)
                <tr>
                    <th>Estado al recibir</th>
                    <td class="os-check">
                        @foreach ($orden->checklist as $punto => $ok)
                            <span class="{{ $ok ? '' : 'os-no' }}">{{ $ok ? '☑' : '☐' }} {{ $punto }}</span>
                        @endforeach
                    </td>
                </tr>
            @endif
            @if ($tienda || $orden->diagnostico)
                <tr>
                    <th>Diagnóstico</th>
                    <td class="{{ $tienda ? 'os-escribir' : '' }}">
                        {{ $orden->diagnostico }}
                        @if ($tienda)
                            @for ($i = 0; $i < ($orden->diagnostico ? 2 : 3); $i++)<div class="os-renglon"></div>@endfor
                        @endif
                    </td>
                </tr>
            @endif
            @unless ($tienda)
                <tr>
                    <th>Para recoger</th>
                    <td>
                        Presenta esta copia y tu documento en {{ $orden->sede?->nombre }}
                        ({{ $orden->sede?->direccion ?: $empresa['direccion'] }}).
                        @if ($orden->sede?->horario)
                            Horario: {{ rtrim($orden->sede->horario, '. ') }}.
                        @endif
                        Te avisamos por WhatsApp cuando esté listo.
                    </td>
                </tr>
            @endunless
        </tbody>
    </table>

    <div class="os-cuerpo">
        <div class="os-izq">
            <table class="os-tabla">
                <thead>
                    <tr><th>Descripción</th><th class="os-cant">Cant.</th><th class="os-num">Importe</th></tr>
                </thead>
                <tbody>
                    @foreach ($repuestos->take(3) as $item)
                        <tr>
                            <td class="os-producto">{{ $item->variante?->producto?->nombre }} — {{ $item->variante?->presentacion }}</td>
                            <td class="os-cant">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, '.', ''), '0'), '.') }}</td>
                            <td class="os-num">S/ {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($repuestos->count() > 3)
                        <tr>
                            <td colspan="2">… y {{ $repuestos->count() - 3 }} repuestos más</td>
                            <td class="os-num">S/ {{ number_format((float) $repuestos->skip(3)->sum('subtotal'), 2) }}</td>
                        </tr>
                    @endif
                    @if ((float) $orden->mano_obra > 0)
                        <tr>
                            <td class="os-producto">Mano de obra</td>
                            <td class="os-cant">1</td>
                            <td class="os-num">S/ {{ number_format((float) $orden->mano_obra, 2) }}</td>
                        </tr>
                    @endif
                    @if ($repuestos->isEmpty() && (float) $orden->mano_obra <= 0)
                        <tr><td colspan="3" class="os-vacio">Se cotiza después del diagnóstico.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="os-der">
            <dl class="os-totales">
                <dt>Total</dt><dd>S/ {{ number_format($total, 2) }}</dd>
                <dt>A cuenta</dt><dd>S/ {{ number_format($pagado, 2) }}</dd>
                <dt class="os-total">{{ $saldo > 0 ? 'Saldo' : 'Pagado' }}</dt>
                <dd class="os-total">S/ {{ number_format($saldo > 0 ? $saldo : $total, 2) }}</dd>
            </dl>
        </div>

        <div class="os-qr">
            <img src="{{ $qr }}" alt="QR de seguimiento">
            <span>{{ $tienda ? 'Seguimiento' : 'Sigue tu reparación' }}</span>
        </div>
    </div>

    <footer class="os-pie">
        <div class="os-terminos">
            @if ($orden->garantia_dias > 0)
                <strong>*** Garantía de {{ $orden->garantia_dias }} días</strong> desde la entrega, sobre la falla reparada.
            @endif
            {{ $empresa['terminos_orden'] }}
        </div>
        <div class="os-firmas">
            <div>
                @if ($orden->firma_recepcion)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($orden->firma_recepcion) }}" alt="Firma del cliente">
                @endif
                Firma del cliente
            </div>
            <div>{{ $orden->usuario?->name ?? $empresa['nombre'] }}<br><span>Recibido por</span></div>
        </div>
        <div class="os-sello" aria-hidden="true">{{ $copia }}</div>
    </footer>
</div>
