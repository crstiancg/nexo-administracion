<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML de un editor de texto enriquecido (la descripción de un
 * producto) con una lista blanca: sólo formato básico, sin atributos (salvo
 * el href de un enlace http/https/mailto). Lo que no está permitido se
 * desenvuelve (queda su texto) y lo peligroso se borra entero. Así el front
 * lo puede mostrar con v-html sin abrir la puerta a un <script>.
 */
class HtmlSeguro
{
    /** Etiquetas que quedan (todo lo que genera el editor del formulario). */
    private const PERMITIDAS = ['p', 'div', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a'];

    /** Se borran con su contenido: nada de ellas tiene sentido como texto. */
    private const PELIGROSAS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'svg', 'math', 'template', 'link', 'meta', 'noscript'];

    public static function limpiar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument;
        // El prefijo xml fuerza UTF-8 (si no, libxml lee Latin-1 y rompe las tildes).
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="raiz">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

        $raiz = $doc->getElementById('raiz');
        if (! $raiz) {
            return null;
        }

        self::limpiarHijos($raiz);

        $resultado = '';
        foreach ($raiz->childNodes as $hijo) {
            $resultado .= $doc->saveHTML($hijo);
        }
        $resultado = trim($resultado);

        // Sólo etiquetas vacías ("<p><br></p>"): no hay descripción.
        return trim(strip_tags($resultado)) === '' ? null : $resultado;
    }

    private static function limpiarHijos(DOMNode $nodo): void
    {
        // Copia: la lista cambia mientras se quitan o desenvuelven nodos.
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo->nodeType === XML_COMMENT_NODE || $hijo->nodeType === XML_PI_NODE) {
                $nodo->removeChild($hijo);

                continue;
            }

            if (! $hijo instanceof DOMElement) {
                continue;
            }

            $etiqueta = strtolower($hijo->tagName);

            if (in_array($etiqueta, self::PELIGROSAS, true)) {
                $nodo->removeChild($hijo);

                continue;
            }

            self::limpiarHijos($hijo);

            if (! in_array($etiqueta, self::PERMITIDAS, true)) {
                // Desenvolver: el texto de un <span style> o un <font> queda.
                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }
                $nodo->removeChild($hijo);

                continue;
            }

            self::limpiarAtributos($hijo, $etiqueta);
        }
    }

    private static function limpiarAtributos(DOMElement $elemento, string $etiqueta): void
    {
        $href = $etiqueta === 'a' ? trim($elemento->getAttribute('href')) : '';

        foreach (iterator_to_array($elemento->attributes) as $atributo) {
            $elemento->removeAttribute($atributo->nodeName);
        }

        if ($etiqueta === 'a' && preg_match('#^(https?://|mailto:)#i', $href)) {
            $elemento->setAttribute('href', $href);
            $elemento->setAttribute('target', '_blank');
            $elemento->setAttribute('rel', 'noopener noreferrer');
        }
    }
}
