{{-- Estilos de los documentos A4 "premium" (orden de servicio, cotización):
     hoja, cabecera con ondas, tarjetas con pestaña, tabla, totales, QR, marca
     de agua y pie con Puno. Cada documento suma lo suyo después. --}}
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
    /* 1mm de más a cada lado, como la ola del pie: el canto de la hoja la recorta limpio. */
    .p-cab__fondo { position: absolute; left: -1mm; top: 0; width: calc(100% + 2mm); height: 100%; }
    .p-circuito { position: absolute; left: 0; top: 30mm; width: 70mm; opacity: .5; }
    .p-marca { position: absolute; left: 9mm; top: 8mm; display: flex; align-items: center; gap: 4mm; }
    .p-marca img { height: 24mm; }
    .p-marca__div { width: 0; height: 24mm; border-left: 1pt solid #0B2266; }
    .p-empresa { display: grid; gap: 1.1mm; max-width: 60mm; font-size: 9.2pt; }
    .p-empresa strong { font-size: 13pt; font-weight: 800; white-space: nowrap; letter-spacing: .2px; color: #0B2266; }
    .p-empresa b { font-size: 11pt; font-weight: 800; color: #0B2266; }
    .p-empresa span { display: flex; align-items: center; gap: 2mm; }
    .p-empresa .ic { width: 4mm; height: 4mm; color: #0B2266; }
    /* El horario es largo: más chico y angosto, corta antes de la curva azul del panel. */
    .p-empresa__horario { align-items: flex-start !important; max-width: 52mm; font-size: 8.6pt; line-height: 1.2; }
    .p-titulo { position: absolute; right: 4mm; top: 5mm; width: 74mm; text-align: center; color: #fff; }
    .p-titulo h1 { margin: 0; font-size: 18pt; font-weight: 900; line-height: 1; letter-spacing: -.3px; white-space: nowrap; }
    .p-codigo { display: inline-block; margin-top: 2mm; padding: .4mm 8mm; border-radius: 2mm; background: #1D5FD1; font-size: 18pt; font-weight: 900; letter-spacing: .3px; }
    .p-fechas { display: flex; justify-content: center; gap: 3mm; margin-top: 3mm; text-align: left; }
    .p-fechas > div { display: flex; align-items: center; gap: 2mm; }
    .p-fechas > div + div { padding-left: 3mm; border-left: 0.75pt solid rgba(255,255,255,.5); }
    .p-fechas .ic { width: 6.5mm; height: 6.5mm; }
    .p-fechas small { display: block; font-size: 8.6pt; opacity: .9; }
    /* Un estado largo ("Esperando repuesto") corta en dos renglones en vez de salirse de la hoja; las fechas no cortan. */
    .p-fechas b { display: block; max-width: 32mm; font-size: 10.6pt; font-weight: 800; line-height: 1.15; }
    .p-fechas small { white-space: nowrap; }

    /* El contorno va por dentro (sombra), no como borde: así la pestaña y las cabeceras oscuras quedan al ras de la esquina, sin una tira celeste alrededor. */
    .card { position: relative; overflow: hidden; box-shadow: inset 0 0 0 0.75pt #C7D5F2; border-radius: 2.4mm; background: linear-gradient(180deg, #F5F8FF, #fff 60%); }
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

    /* Tabla de detalle. */
    .tabla { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; box-shadow: inset 0 0 0 0.75pt #C7D5F2; border-radius: 2.4mm; font-size: 10.4pt; }
    .tabla th { padding: 2mm 3mm; text-align: center !important; background: linear-gradient(180deg, #13328C, #0A1E5E); color: #fff; font-size: 10.4pt; font-weight: 800; text-transform: uppercase; }
    .tabla th + th, .tabla td + td { border-left: 0.75pt solid #C7D5F2; }
    .tabla th + th { border-left-color: rgba(255,255,255,.25); }
    .tabla td { padding: 1.6mm 3mm; border-top: 0.6pt solid #DCE4F5; }
    /* Bajo la cabecera oscura no va línea clara: el azul corta solo. */
    .tabla tbody tr:first-child td { border-top-color: transparent; }
    .tabla .c { text-align: center; }
    .tabla .r { text-align: right; white-space: nowrap; }
    .tabla .n { width: 10mm; }

    /* Condiciones + totales. */
    /* Totales: la columna crece si un monto es largo, en vez de salirse del margen. */
    .p-fila { display: grid; grid-template-columns: 1fr auto; gap: 5mm; align-items: start; }
    .cond { padding: 2.8mm 5mm; border: 0.75pt solid #C7D5F2; border-radius: 2.4mm; background: #F2F6FE; }
    .cond h3 { display: flex; align-items: center; gap: 2.4mm; margin: 0 0 2.2mm; font-size: 12pt; font-weight: 900; color: #0B2266; text-transform: uppercase; }
    .cond h3 .ic { width: 7mm; height: 7mm; color: #1D5FD1; }
    .cond ul { display: grid; gap: 1.1mm; margin: 0; padding: 0; list-style: none; font-size: 9.6pt; }
    .cond li { display: flex; gap: 2mm; }
    .cond li .ic { width: 4.6mm; height: 4.6mm; margin-top: .3mm; color: #1D5FD1; }
    /* Filas blancas transparentes: dejan ver el contorno (sombra interior de la tabla); las oscuras lo tapan y quedan al ras. */
    .totales { min-width: 78mm; border-collapse: separate; border-spacing: 0; overflow: hidden; box-shadow: inset 0 0 0 0.75pt #C7D5F2; border-radius: 2.4mm; background: #fff; font-size: 10.8pt; }
    .totales td { padding: 1.5mm 3.4mm; white-space: nowrap; border-top: 0.6pt solid #DCE4F5; background: #F2F6FE; font-weight: 700; }
    .totales tr:first-child td { border-top: 0; }
    .totales td + td { text-align: right; border-left: 0.75pt solid #DCE4F5; }
    .totales .suave td { background: transparent; font-weight: 400; color: #33415E; }
    .totales .fuerte td { background: linear-gradient(180deg, #13328C, #0A1E5E); color: #fff; font-size: 12.4pt; font-weight: 900; text-transform: uppercase; }
    .totales .fuerte td + td { font-size: 14.5pt; border-left-color: rgba(255,255,255,.25); }
    /* Sin línea clara pegada al azul, ni arriba ni abajo. */
    .totales .fuerte td, .totales .fuerte + tr td { border-top-color: transparent; }
    .totales .pagado td { background: #15803D; }

    /* QR. */
    .qr { display: flex; align-items: center; gap: 4mm; }
    .qr__img { padding: 1.6mm; border: 0.9pt solid #1D5FD1; border-radius: 2.4mm; }
    .qr__img img { display: block; width: 26mm; height: 26mm; }
    .qr h3 { margin: 0 0 1.8mm; font-size: 14pt; font-weight: 900; line-height: 1.05; color: #0B2266; text-transform: uppercase; }
    .qr p { margin: 0 0 2mm; font-size: 9.4pt; color: #33415E; }
    .qr__pill { display: inline-flex; align-items: center; gap: 1.4mm; padding: .8mm 2.4mm; border-radius: 99px; background: #16A34A; color: #fff; font-size: 8.4pt; font-weight: 800; }
    .qr__pill .ic { width: 3.6mm; height: 3.6mm; }

    /* ── Marca de agua ──
       El logo en trazos, centrado en el cuerpo de la primera hoja (entre cabecera
       y pie). El PNG ya viene en el azul de la marca: una máscara CSS se veía en
       pantalla pero al imprimir Chrome la ignoraba y salía un rectángulo. Va ENCIMA
       del contenido (las tarjetas tienen fondo y la taparían) pero muy tenue: no
       tapa texto ni recibe clics. */
    /* Centrada con cuentas, sin transform: al paginar, Chrome mide la caja sin el
       transform y una caja "bajada" agregaba una hoja en blanco en la cotización. */
    .p-marca-agua { position: absolute; left: calc(50% - 75mm); top: calc(52mm + (297mm - 52mm - 33mm) / 2 - 75mm); width: 150mm; height: 150mm; opacity: .06; pointer-events: none; }

    /* ── Pie con Puno ── */

    .p-pie { position: absolute; left: 0; right: 0; bottom: 0; height: 33mm; }
    /* 1mm más ancha por cada lado: la recorta el canto de la hoja (.p oculta lo que sobra),
       así no queda un hilo blanco suavizado entre la ola y el borde al hacer zoom. */
    .p-pie__ola { position: absolute; left: -1mm; top: 0; width: calc(100% + 2mm); height: 9mm; }
    .p-pie__banda { position: absolute; left: 0; right: 0; bottom: 0; height: 25mm; display: flex; align-items: center; background: linear-gradient(90deg, #06143F, #0A1E5E 45%, #0B2266); color: #fff; }
    /* Dibujo de la catedral: líneas negras sobre transparente → blancas sobre la banda. */
    .p-pie__puno { position: absolute; left: 10mm; bottom: 0; height: 29mm; width: auto; filter: invert(1); opacity: .85; }
    .p-pie__lema { position: relative; margin-left: 50mm; font-size: 17pt; font-weight: 900; font-style: italic; white-space: nowrap; letter-spacing: .2px; }
    .p-pie__lema::after { content: ''; display: block; width: 46mm; height: 1.2mm; margin: 1.4mm 0 0 18mm; border-radius: 1mm; background: linear-gradient(90deg, transparent, #E01E26 30%, #E01E26 70%, transparent); }
    .p-pie__serv { position: relative; display: flex; margin-left: auto; margin-right: 6mm; }
    .p-pie__serv div { display: flex; flex-direction: column; align-items: center; gap: 1.4mm; width: 27mm; padding: 0 1.5mm; font-size: 8.8pt; font-weight: 800; text-align: center; line-height: 1.15; }
    .p-pie__serv div + div { border-left: 0.75pt solid rgba(255,255,255,.35); }
    .p-pie__serv .ic { width: 7mm; height: 7mm; fill: none; stroke: #fff; stroke-width: 1.4; }
