<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; color: #111827; background: #e5e7eb; }
        .barra { display: flex; justify-content: center; gap: 10px; margin: 16px; }
        .barra button { padding: 9px 22px; font-size: 14px; font-weight: 600; cursor: pointer; border: 0; border-radius: 9px; background: #2563EB; color: #fff; }
        .barra button.sec { background: #fff; color: #111827; border: 1px solid #d1d5db; }
        .muted { color: #4b5563; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
        }
        @yield('estilos')
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button type="button" class="sec" onclick="window.close()">Cerrar</button>
    </div>

    @yield('contenido')
</body>
</html>
