import { TIENDA } from '@/config/tienda'
import { rellenarPlantilla } from '@/modules/OrdenesServicio/constantes'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Estados: label y status del AppChip nacen juntos. "vencida" la calcula el
 * backend (pendiente con la validez pasada).
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  vencida: { label: 'Vencida', status: 'negative' },
  // En un pedido o en una orden de servicio (la lista muestra a cuál).
  convertida: { label: 'Convertida', status: 'positive' },
  rechazada: { label: 'Rechazada', status: 'info' }
}

/**
 * Fecha local YYYY-MM-DD (no UTC: a las 9 p. m. en Lima, toISOString ya es
 * "mañana"), corrida `dias` días.
 */
export function hoyLocal (dias = 0) {
  const d = new Date()
  d.setDate(d.getDate() + dias)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** "2026-10-03" → "03/10/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}

/** La proforma pública que se le manda al cliente (sin sesión). */
export function enlaceProforma (token) {
  return `${window.location.origin}/proforma/${token}`
}

/**
 * El mensaje con el que se manda la proforma por WhatsApp (wa.me, desde el
 * WhatsApp de quien atiende). Se cambia en Configuración.
 */
export const PLANTILLA_WHATSAPP = 'Hola {cliente}, le enviamos la cotización {codigo} por {total}, válida hasta el {valida_hasta}.' +
  '\n\nPuede verla, imprimirla o guardarla en PDF aquí: {enlace}\n— {empresa}'

/** Lo que se puede usar en el mensaje, con un ejemplo para la ayuda. */
export const VARIABLES_WHATSAPP = [
  { clave: 'cliente', ayuda: 'Primer nombre del cliente' },
  { clave: 'codigo', ayuda: 'COT-000010' },
  { clave: 'total', ayuda: 'S/ 1,250.00' },
  { clave: 'valida_hasta', ayuda: '12/11/2026' },
  { clave: 'enlace', ayuda: 'Enlace de la proforma' },
  { clave: 'empresa', ayuda: 'Nombre de la empresa' }
]

export function valoresWhatsapp (cotizacion) {
  return {
    // Una "cotización sin nombre" no tiene a quién saludar por su nombre.
    cliente: cotizacion.cliente?.nombre?.split(' ')[0] ?? 'estimado cliente',
    codigo: cotizacion.codigo ?? '',
    total: formatearPrecio(cotizacion.total ?? 0),
    valida_hasta: fechaCorta(cotizacion.valida_hasta),
    enlace: cotizacion.token ? enlaceProforma(cotizacion.token) : '',
    empresa: TIENDA.nombre
  }
}

export function mensajeWhatsapp (cotizacion) {
  return rellenarPlantilla(TIENDA.whatsapp?.cotizacion || PLANTILLA_WHATSAPP, valoresWhatsapp(cotizacion))
}

/**
 * Un celular peruano en formato internacional (51 + 9 dígitos), o null.
 * Toma el primero si hay varios ("900 551 900 - 900 180 000").
 */
export function numeroWhatsapp (telefono) {
  const digitos = String(telefono ?? '').replace(/[\s-]/g, '')
  const celular = digitos.match(/(?:\+?51)?(9\d{8})/)
  return celular ? `51${celular[1]}` : null
}

/** wa.me con el mensaje. Sin celular del cliente, WhatsApp pide elegir el contacto. */
export function enlaceWhatsapp (cotizacion) {
  const numero = numeroWhatsapp(cotizacion.cliente?.telefono) ?? ''
  return `https://wa.me/${numero}?text=${encodeURIComponent(mensajeWhatsapp(cotizacion))}`
}
