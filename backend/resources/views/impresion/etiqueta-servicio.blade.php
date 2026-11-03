@extends('impresion.layout')

@section('titulo', "Etiqueta {$orden->codigo}")

@section('estilos')
    {{-- Adhesivo de 60 × 40 mm para pegar en el equipo (impresora de etiquetas o
         ticketera con rollo de etiquetas). Sin márgenes: el rollo ya los tiene. --}}
    @page { size: 60mm 40mm; margin: 0; }
    .etiqueta { width: 60mm; height: 40mm; margin: 0 auto 24px; padding: 2.5mm; background: #fff; display: grid; grid-template-columns: 22mm 1fr; gap: 2.5mm; align-items: center; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    .etiqueta img { width: 22mm; height: 22mm; }
    .datos { min-width: 0; font-size: 7.5pt; line-height: 1.25; }
    .codigo { font-family: monospace; font-size: 12pt; font-weight: 800; }
    .linea { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fuerte { font-weight: 700; }
    .pie { grid-column: 1 / -1; display: flex; justify-content: space-between; font-size: 6.5pt; border-top: 0.3mm solid #111; padding-top: 1mm; }
    @media print { .etiqueta { margin: 0; box-shadow: none; } }
@endsection

@section('contenido')
<div class="etiqueta">
    <img src="{{ $qr }}" alt="QR de seguimiento">
    <div class="datos">
        <div class="codigo">{{ $orden->codigo }}</div>
        <div class="linea fuerte">{{ $orden->cliente->nombre }}</div>
        @if ($orden->cliente->telefono)
            <div class="linea">Tel. {{ $orden->cliente->telefono }}</div>
        @endif
        <div class="linea">{{ $orden->equipo() }}</div>
        <div class="linea muted">{{ \Illuminate\Support\Str::limit($orden->falla, 40) }}</div>
    </div>
    <div class="pie">
        <span>{{ $orden->recibido_at->timezone(\App\Support\Fechas::zona())->format('d/m/Y') }}</span>
        <span class="fuerte">NEXO TECHNOLOGY</span>
    </div>
</div>
@endsection
