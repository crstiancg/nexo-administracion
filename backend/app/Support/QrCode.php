<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR como SVG en data URI: nítido a cualquier tamaño de impresión y se usa
 * directo en un <img src> de las vistas impresas.
 */
final class QrCode
{
    public static function svg(string $datos, int $tamano = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($datos));
    }
}
