import { TIENDA } from '@/config/tienda'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Estados: label, status del AppChip e ícono nacen juntos. Mismas claves que
 * OrdenServicio::ESTADOS del backend, en el orden del flujo del taller.
 */
export const ESTADOS = {
  recibido: { label: 'Recibido', status: 'warning', icon: 'move_to_inbox' },
  diagnostico: { label: 'En diagnóstico', status: 'info', icon: 'troubleshoot' },
  esperando_repuesto: { label: 'Esperando repuesto', status: 'warning', icon: 'hourglass_top' },
  en_reparacion: { label: 'En reparación', status: 'info', icon: 'build' },
  reparado: { label: 'Reparado', status: 'positive', icon: 'task_alt' },
  sin_reparacion: { label: 'Sin reparación', status: 'negative', icon: 'block' },
  entregado: { label: 'Entregado', status: 'positive', icon: 'handshake' },
  abandonado: { label: 'Abandonado', status: 'negative', icon: 'inventory' }
}

/** Desde estos se puede entregar (mismo criterio que OrdenServicio::LISTOS). */
export const LISTOS = ['reparado', 'sin_reparacion', 'abandonado']

// Mismas claves que OrdenServicio::SEGURIDAD.
export const SEGURIDAD = [
  { value: 'ninguno', label: 'Sin bloqueo' },
  { value: 'pin', label: 'PIN' },
  { value: 'contrasena', label: 'Contraseña' },
  { value: 'patron', label: 'Patrón' }
]

export const GARANTIAS = [
  { value: 0, label: 'Sin garantía' },
  { value: 7, label: '1 semana' },
  { value: 15, label: '15 días' },
  { value: 30, label: '1 mes' },
  { value: 60, label: '2 meses' },
  { value: 90, label: '3 meses' },
  { value: 180, label: '6 meses' },
  { value: 365, label: '1 año' }
]

/** El enlace público que abre el QR del ticket (sin sesión). */
export function enlaceSeguimiento (token) {
  return `${window.location.origin}/seguimiento/${token}`
}

/**
 * Mensaje de WhatsApp para avisarle al cliente según el estado de la orden.
 * Lo envía quien atiende, desde su WhatsApp (wa.me): no hay costo ni API.
 */
const MENSAJES = {
  recibido: (o) => `recibimos tu ${o.equipo} (orden ${o.codigo}). Te avisaremos apenas tengamos el diagnóstico.`,
  diagnostico: (o) => `ya estamos revisando tu ${o.equipo} (orden ${o.codigo}). En cuanto tengamos el diagnóstico te escribimos.`,
  esperando_repuesto: (o) => `para reparar tu ${o.equipo} (orden ${o.codigo}) estamos esperando un repuesto. Te avisaremos apenas llegue.`,
  en_reparacion: (o) => `tu ${o.equipo} (orden ${o.codigo}) ya está en reparación.`,
  reparado: (o) => `¡buenas noticias! Tu ${o.equipo} (orden ${o.codigo}) ya está reparado y listo para recoger.${Number(o.saldo) > 0 ? ` Saldo pendiente: ${formatearPrecio(o.saldo)}.` : ''}`,
  sin_reparacion: (o) => `revisamos tu ${o.equipo} (orden ${o.codigo}) y lamentablemente no fue posible repararlo. Puedes pasar a recogerlo cuando gustes.`,
  entregado: (o) => `gracias por confiar en nosotros. Entregamos tu ${o.equipo} (orden ${o.codigo}).${o.garantia_hasta ? ` Garantía hasta el ${fechaCorta(o.garantia_hasta)}.` : ''}`,
  abandonado: (o) => `tu ${o.equipo} (orden ${o.codigo}) sigue en nuestro taller sin ser recogido. Por favor comunícate con nosotros para coordinar el retiro.`
}

export function mensajeWhatsapp (orden) {
  const nombre = orden.cliente?.nombre?.split(' ')[0] ?? ''
  const cuerpo = (MENSAJES[orden.estado] ?? MENSAJES.recibido)(orden)
  return `Hola ${nombre}, ${cuerpo}\n\nSigue tu orden aquí: ${enlaceSeguimiento(orden.token)}\n— ${TIENDA.nombre}`
}

/**
 * wa.me con el número en formato internacional. Un celular peruano de 9
 * dígitos lleva el 51 adelante. null si el cliente no tiene teléfono.
 */
export function enlaceWhatsapp (orden) {
  let numero = (orden.cliente?.telefono ?? '').replace(/\D/g, '')
  if (!numero) return null
  if (numero.length === 9) numero = `51${numero}`
  return `https://wa.me/${numero}?text=${encodeURIComponent(mensajeWhatsapp(orden))}`
}

/** "2026-10-03" → "03/10/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}
