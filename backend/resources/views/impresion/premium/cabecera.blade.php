{{-- Cabecera de los documentos premium. Recibe: $empresa, $sede (o null),
     $conHorario (opcional: el horario de la sede bajo el teléfono),
     $compacta (opcional: 40mm en vez de 52mm; los tamaños los pone el documento),
     $titulo, $codigo y $fechas: lista de [ícono, etiqueta, valor] bajo el código. --}}
    <header @class(['p-cab', 'p-cab--compacta' => $compacta ?? false])>
        <svg class="p-cab__fondo" viewBox="0 0 210 52" preserveAspectRatio="none">
            <defs>
                <linearGradient id="noche" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#0B2266"/><stop offset="1" stop-color="#06143F"/>
                </linearGradient>
            </defs>
            {{-- Como el pie: el borde de abajo del panel y las dos líneas son la MISMA
                 curva (0,47.5 → 210,40) corrida hacia abajo, así van paralelas de
                 borde a borde sin cruzarse. El panel se recorta por esa curva; el
                 trazo curvo de su izquierda muere sobre la línea azul. --}}
            <clipPath id="sobre-ola"><path d="M-5 0 H215 V40 Q105 47 0 47.5 H-5 Z"/></clipPath>
            <path d="M127 0 H210 V52 H112 Q128 31 127 0 Z" fill="url(#noche)" clip-path="url(#sobre-ola)"/>
            <path d="M123 0 Q125 28 110 46.5" fill="none" stroke="#1D5FD1" stroke-width=".9"/>
            <path d="M0 48.8 Q105 48.3 210 41.3" fill="none" stroke="#1D5FD1" stroke-width=".9"/>
            <path d="M0 50.5 Q105 50 210 43" fill="none" stroke="#E01E26" stroke-width=".9"/>
        </svg>
        <svg class="p-circuito" viewBox="0 0 70 24" fill="none" stroke="#C9D6F2" stroke-width=".35">
            {{-- Termina antes de las líneas de abajo (y≤17 → 47mm): no las pisa. --}}
            <path d="M0 4h18l4 4h14M0 10h10l3 3h22l3-3h10M0 16h45"/>
            <circle cx="36" cy="8" r=".9"/><circle cx="48" cy="10" r=".9"/><circle cx="45.9" cy="16" r=".9"/>
        </svg>

        <div class="p-marca">
            <img src="{{ $empresa['logo_url'] ?? asset('img/logo.png') }}" alt="{{ $empresa['nombre'] }}">
            <span class="p-marca__div"></span>
            <div class="p-empresa">
                <strong>{{ $empresa['nombre'] }}</strong>
                <b>RUC: {{ $empresa['ruc'] }}</b>
                <span>{!! \App\Support\Iconos::svg('lugar') !!}{{ $sede?->direccion ?: $empresa['direccion'] }}</span>
                <span>{!! \App\Support\Iconos::svg('fono') !!}{{ $sede?->telefono ?: $empresa['telefono'] }}</span>
                @if (($conHorario ?? false) && filled($sede?->horario))
                    <span class="p-empresa__horario">{!! \App\Support\Iconos::svg('reloj') !!}{{ $sede->horario }}</span>
                @endif
            </div>
        </div>

        <div class="p-titulo">
            <h1>{{ $titulo }}</h1>
            <div class="p-codigo">{{ $codigo }}</div>
            <div class="p-fechas">
                @foreach ($fechas as [$icono, $etiqueta, $valor])
                    <div>{!! \App\Support\Iconos::svg($icono) !!}<span><small>{{ $etiqueta }}</small><b>{{ $valor }}</b></span></div>
                @endforeach
            </div>
        </div>
    </header>
