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
 * Mensajes de WhatsApp por estado de la orden. Lo envía quien atiende, desde
 * su WhatsApp (wa.me): no hay costo ni API. Se cambian en Configuración; los
 * que no se cambiaron son éstos.
 */
const PIE = '\n\nSigue tu orden aquí: {enlace}\n— {empresa}'

export const PLANTILLAS_WHATSAPP = {
  recibido: 'Hola {cliente}, recibimos tu {equipo} (orden {codigo}). Te avisaremos apenas tengamos el diagnóstico.' + PIE,
  diagnostico: 'Hola {cliente}, ya estamos revisando tu {equipo} (orden {codigo}). En cuanto tengamos el diagnóstico te escribimos.' + PIE,
  esperando_repuesto: 'Hola {cliente}, para reparar tu {equipo} (orden {codigo}) estamos esperando un repuesto. Te avisaremos apenas llegue.' + PIE,
  en_reparacion: 'Hola {cliente}, tu {equipo} (orden {codigo}) ya está en reparación.' + PIE,
  reparado: 'Hola {cliente}, ¡buenas noticias! Tu {equipo} (orden {codigo}) ya está reparado y listo para recoger.{saldo_pendiente}' + PIE,
  sin_reparacion: 'Hola {cliente}, revisamos tu {equipo} (orden {codigo}) y lamentablemente no fue posible repararlo. Puedes pasar a recogerlo cuando gustes.' + PIE,
  entregado: 'Hola {cliente}, gracias por confiar en nosotros. Entregamos tu {equipo} (orden {codigo}).{garantia}' + PIE,
  abandonado: 'Hola {cliente}, tu {equipo} (orden {codigo}) sigue en nuestro taller sin ser recogido. Por favor comunícate con nosotros para coordinar el retiro.' + PIE
}

/** Lo que se puede usar en una plantilla, con un ejemplo para la ayuda. */
export const VARIABLES_WHATSAPP = [
  { clave: 'cliente', ayuda: 'Primer nombre del cliente' },
  { clave: 'equipo', ayuda: 'Laptop HP 250 G7' },
  { clave: 'codigo', ayuda: 'OS-000012' },
  { clave: 'estado', ayuda: 'Reparado' },
  { clave: 'total', ayuda: 'S/ 408.00' },
  { clave: 'saldo', ayuda: 'S/ 208.00' },
  { clave: 'saldo_pendiente', ayuda: '" Saldo pendiente: S/ 208.00." (vacío si no debe)' },
  { clave: 'garantia', ayuda: '" Garantía hasta el 03/12/2026." (vacío si no tiene)' },
  { clave: 'enlace', ayuda: 'Enlace de seguimiento de la orden' },
  { clave: 'empresa', ayuda: 'Nombre de la empresa' }
]

/** Reemplaza {variable} por su valor; lo que no existe queda como está. */
export function rellenarPlantilla (plantilla, valores) {
  return plantilla.replace(/\{(\w+)\}/g, (todo, clave) => (clave in valores ? valores[clave] : todo))
}

export function valoresWhatsapp (orden) {
  return {
    cliente: orden.cliente?.nombre?.split(' ')[0] ?? '',
    equipo: orden.equipo ?? '',
    codigo: orden.codigo ?? '',
    estado: ESTADOS[orden.estado]?.label ?? '',
    total: formatearPrecio(orden.total ?? 0),
    saldo: formatearPrecio(orden.saldo ?? 0),
    saldo_pendiente: Number(orden.saldo) > 0 ? ` Saldo pendiente: ${formatearPrecio(orden.saldo)}.` : '',
    garantia: orden.garantia_hasta ? ` Garantía hasta el ${fechaCorta(orden.garantia_hasta)}.` : '',
    enlace: orden.token ? enlaceSeguimiento(orden.token) : '',
    empresa: TIENDA.nombre
  }
}

export function mensajeWhatsapp (orden) {
  const plantilla = TIENDA.whatsapp?.[orden.estado] || PLANTILLAS_WHATSAPP[orden.estado] || PLANTILLAS_WHATSAPP.recibido
  return rellenarPlantilla(plantilla, valoresWhatsapp(orden))
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
