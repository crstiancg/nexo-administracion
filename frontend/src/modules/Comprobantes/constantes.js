// Mismos códigos que App\Models\Comprobante (catálogo 01 de SUNAT).
export const BOLETA = '03'
export const FACTURA = '01'

// Estado ante SUNAT: el chip no comunica sólo con color (AppChip pone ícono).
export const ESTADOS_SUNAT = {
  aceptado: { status: 'positive', label: 'Aceptado por SUNAT' },
  pendiente: { status: 'info', label: 'Enviando a SUNAT' },
  rechazado: { status: 'negative', label: 'Rechazado por SUNAT' },
  error: { status: 'warning', label: 'No llegó a SUNAT' }
}

// Desde este total la boleta tiene que identificar al cliente (Facturacion::BOLETA_MONTO_IDENTIFICADO).
export const BOLETA_MONTO_IDENTIFICADO = 700

/**
 * Si el cliente puede llevar ese comprobante: el mismo criterio que el
 * backend, para avisar ANTES de cobrar. null = se puede.
 *
 * @param {'ticket'|'boleta'|'factura'} tipo
 */
export function motivoNoPermitido (tipo, cliente, total) {
  if (tipo === 'factura' && !(cliente?.tipo_documento === 'RUC' && /^\d{11}$/.test(cliente?.numero_documento ?? ''))) {
    return 'Para factura, elegí un cliente con RUC.'
  }
  if (tipo === 'boleta' && Number(total) >= BOLETA_MONTO_IDENTIFICADO && !cliente?.numero_documento) {
    return `Desde S/ ${BOLETA_MONTO_IDENTIFICADO} la boleta lleva el DNI del cliente: elegí o creá el cliente.`
  }
  return null
}
