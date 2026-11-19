{{-- Marca de agua y pie con Puno de los documentos premium. --}}
    <img class="p-marca-agua" src="{{ asset('img/marca-agua-azul.png') }}" alt="" aria-hidden="true">

    {{-- ── Pie ── --}}
    <footer class="p-pie">
        {{-- viewBox en mm, igual al tamaño real (210 × 9): nada se estira y las
             líneas tienen el mismo grosor en toda la curva. Las tres capas son
             la MISMA curva corrida hacia arriba: van paralelas, de borde a borde,
             sin cruzarse. --}}
        <svg class="p-pie__ola" viewBox="0 0 210 9" preserveAspectRatio="none">
            {{-- El mismo degradado que la banda: sin escalón de color donde se juntan. --}}
            <defs>
                <linearGradient id="pie-noche" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#06143F"/><stop offset=".45" stop-color="#0A1E5E"/><stop offset="1" stop-color="#0B2266"/>
                </linearGradient>
            </defs>
            {{-- El valle más bajo de la ola (y≈7.4) queda arriba de donde empieza la banda (8mm). --}}
            <path d="M0 9 V7.2 Q50 2.8 110 6.2 T210 3.6 V9 Z" fill="url(#pie-noche)"/>
            <path d="M0 6 Q50 1.6 110 5 T210 2.4" fill="none" stroke="#E01E26" stroke-width=".7"/>
            <path d="M0 4.65 Q50 .25 110 3.65 T210 1.05" fill="none" stroke="#1D5FD1" stroke-width=".5"/>
        </svg>
        <div class="p-pie__banda">
            <img class="p-pie__puno" src="{{ asset('img/catedral.png') }}" alt="">
            <div class="p-pie__lema">Tecnología que te conecta</div>
            <div class="p-pie__serv">
                <div><svg viewBox="0 0 24 24" class="ic"><path d="M4 6h16v10H4zM2 18h20"/></svg>Soporte<br>técnico</div>
                <div><svg viewBox="0 0 24 24" class="ic"><path d="{{ \App\Support\Iconos::TRAZOS['llave'] }}"/></svg>Mantenimiento</div>
                <div><svg viewBox="0 0 24 24" class="ic"><path d="M7 7h10v10H7zM10 10h4v4h-4zM9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4"/></svg>Soluciones<br>tecnológicas</div>
            </div>
        </div>
    </footer>
