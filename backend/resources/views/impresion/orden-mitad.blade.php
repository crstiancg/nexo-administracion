{{-- Media hoja A4 (apaisada, 210 × 148 mm) con toda la orden. Se usa dos
     veces en la misma hoja: copia de la tienda arriba y del cliente abajo. --}}
<div class="mitad">
    <div class="m-cab">
        <img src="{{ $empresa['logo_url'] ?? asset('img/logo.png') }}" alt="{{ $empresa['nombre'] }}">
        <div class="m-empresa">
            <strong>{{ $empresa['nombre'] }}</strong> · RUC {{ $empresa['ruc'] }}<br>
            {{ $orden->sede?->direccion ?: $empresa['direccion'] }} · Tel. {{ $orden->sede?->telefono ?: $empresa['telefono'] }}
        </div>
        <div class="m-caja">
            <div class="m-copia">{{ $copia }}</div>
            <div class="m-codigo">{{ $orden->codigo }}</div>
            <div>{{ $orden->recibido_at->timezone(\App\Support\Fechas::zona())->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="m-cuerpo">
        <div class="m-izq">
            <div class="m-fila">
                <div class="m-bloque">
                    <h4>Cliente</h4>
                    <strong>{{ $orden->cliente->nombre }}</strong><br>
                    @if ($orden->cliente->numero_documento)
                        {{ $orden->cliente->tipo_documento }} {{ $orden->cliente->numero_documento }}
                    @endif
                    @if ($orden->cliente->telefono)
                        · Tel. {{ $orden->cliente->telefono }}
                    @endif
                </div>
                <div class="m-bloque">
                    <h4>Equipo</h4>
                    <strong>{{ $orden->equipo() }}</strong><br>
                    @if ($orden->serie)
                        S/N: {{ $orden->serie }} ·
                    @endif
                    Bloqueo: {{ $seguridad }}
                    @if ($orden->accesorios)
                        <br>Accesorios: {{ $orden->accesorios }}
                    @endif
                </div>
            </div>

            @if ($orden->checklist)
                <div class="m-check">
                    <b>Al recibir:</b>
                    @foreach ($orden->checklist as $punto => $ok)
                        <span>{{ $ok ? '☑' : '☐' }} {{ $punto }}</span>
                    @endforeach
                </div>
            @endif

            <div class="m-bloque">
                <h4>Falla reportada</h4>
                <div class="m-texto">{{ $orden->falla }}</div>
                @if ($orden->diagnostico)
                    <h4 class="m-sub">Diagnóstico / trabajo</h4>
                    <div class="m-texto">{{ $orden->diagnostico }}</div>
                @endif
                @if ($orden->observacion)
                    <h4 class="m-sub">Observaciones</h4>
                    <div class="m-texto">{{ $orden->observacion }}</div>
                @endif
            </div>

            @if ($repuestos->isNotEmpty() || (float) $orden->mano_obra > 0)
                <table class="m-tabla">
                    <thead>
                        <tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Importe</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($repuestos->take(4) as $item)
                            <tr>
                                <td>{{ $item->variante?->producto?->nombre }} — {{ $item->variante?->presentacion }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, '.', ''), '0'), '.') }}</td>
                                <td class="num">S/ {{ number_format((float) $item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                        @if ($repuestos->count() > 4)
                            <tr>
                                <td colspan="2">… y {{ $repuestos->count() - 4 }} repuestos más</td>
                                <td class="num">S/ {{ number_format((float) $repuestos->skip(4)->sum('subtotal'), 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $orden->mano_obra > 0)
                            <tr>
                                <td>Mano de obra</td>
                                <td class="num">1</td>
                                <td class="num">S/ {{ number_format((float) $orden->mano_obra, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>

        <div class="m-der">
            <div class="m-datos">
                <div><h4>Sede</h4>{{ $orden->sede?->nombre ?? '—' }}</div>
                <div><h4>Técnico</h4>{{ $orden->tecnico?->name ?? 'Por asignar' }}</div>
                <div><h4>Estado</h4>{{ $estado }}{{ $orden->es_garantia ? ' · Garantía' : '' }}</div>
            </div>

            @if ($total > 0)
                <table class="m-totales">
                    <tr><td>Total</td><td class="num">S/ {{ number_format($total, 2) }}</td></tr>
                    <tr><td>A cuenta</td><td class="num">S/ {{ number_format($pagado, 2) }}</td></tr>
                    <tr class="m-saldo"><td>Saldo</td><td class="num">S/ {{ number_format($saldo, 2) }}</td></tr>
                </table>
            @endif

            <div class="m-qr">
                <img src="{{ $qr }}" alt="QR de seguimiento">
                <div>Escanee para ver el estado de su equipo</div>
            </div>
        </div>
    </div>

    <div class="m-pie">
        <div class="m-condiciones">
            @if ($orden->garantia_dias > 0)
                Garantía del trabajo: {{ $orden->garantia_dias }} días desde la entrega, sobre la falla reparada.
            @endif
            {{ $empresa['terminos_orden'] }}
        </div>
        <div class="m-firmas">
            <div>
                @if ($orden->firma_recepcion)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($orden->firma_recepcion) }}" alt="Firma del cliente">
                @endif
                Firma del cliente
            </div>
            <div>Recibido por{{ $orden->usuario ? ': '.$orden->usuario->name : '' }}</div>
        </div>
    </div>
</div>
