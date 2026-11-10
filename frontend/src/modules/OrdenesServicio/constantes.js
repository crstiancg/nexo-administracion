import { reactive, watch } from 'vue'
import { TIENDA } from '@/config/tienda'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Tipos de estado (App\Models\EstadoOrden): deciden qué hace el sistema con
 * la orden. Inicial y Entregado son del sistema (uno de cada uno).
 */
export const TIPOS_ESTADO = {
  inicial: { label: 'Inicial', ayuda: 'Donde nace toda orden, al recibir el equipo.' },
  en_proceso: { label: 'En proceso', ayuda: 'En el taller, sin terminar: cuenta para atrasos y "sin técnico".' },
  listo: { label: 'Listo para entregar', ayuda: 'Terminado: se entrega desde acá y avisa si no lo recogen.' },
  cerrado: { label: 'Cerrado sin entregar', ayuda: 'Quedó en el taller (abandonado): se puede entregar si vuelve el cliente, sin avisos.' },
  entregado: { label: 'Entregado', ayuda: 'La entrega: cobra el saldo y saca los repuestos del stock. Final.' }
}

/** Colores del chip (mismos que AppChip y EstadoOrden::COLORES). */
export const COLORES_ESTADO = [
  { value: 'info', label: 'Azul' },
  { value: 'warning', label: 'Ámbar' },
  { value: 'positive', label: 'Verde' },
  { value: 'negative', label: 'Rojo' }
]

// Los de fábrica: se usan hasta que llega Configuración (y en los tests).
const DE_FABRICA = [
  { clave: 'recibido', nombre: 'Recibido', tipo: 'inicial', color: 'warning', icono: 'move_to_inbox', notificar: false },
  { clave: 'diagnostico', nombre: 'En diagnóstico', tipo: 'en_proceso', color: 'info', icono: 'troubleshoot', notificar: false },
  { clave: 'esperando_repuesto', nombre: 'Esperando repuesto', tipo: 'en_proceso', color: 'warning', icono: 'hourglass_top', notificar: true },
  { clave: 'en_reparacion', nombre: 'En reparación', tipo: 'en_proceso', color: 'info', icono: 'build', notificar: false },
  { clave: 'reparado', nombre: 'Reparado', tipo: 'listo', color: 'positive', icono: 'task_alt', notificar: true },
  { clave: 'sin_reparacion', nombre: 'Sin reparación', tipo: 'listo', color: 'negative', icono: 'block', notificar: true },
  { clave: 'entregado', nombre: 'Entregado', tipo: 'entregado', color: 'positive', icono: 'handshake', notificar: true },
  { clave: 'abandonado', nombre: 'Abandonado', tipo: 'cerrado', color: 'negative', icono: 'inventory', notificar: true }
]

/**
 * Estados de la orden por clave: { label, status (color del AppChip), icon,
 * tipo, activo, notificar (avisar por WhatsApp al pasar a él) }, en el orden del flujo. Se configuran en Catálogos > Estados
 * de orden y llegan con Configuración al iniciar sesión (TIENDA.estados_orden);
 * es reactivo, así que chips y listas se actualizan solos.
 */
export const ESTADOS = reactive({})

function aplicarEstados (lista) {
  const estados = lista?.length ? lista : DE_FABRICA
  Object.keys(ESTADOS).forEach((clave) => { delete ESTADOS[clave] })
  estados.forEach((e) => {
    ESTADOS[e.clave] = {
      label: e.nombre,
      status: e.color,
      icon: e.icono,
      tipo: e.tipo,
      activo: e.activo !== false,
      notificar: Boolean(e.notificar)
    }
  })
}

watch(() => TIENDA.estados_orden, aplicarEstados, { immediate: true })

/** Un estado que ya no existe (o todavía no llegó) se ve igual, sin romper. */
export function estadoDe (clave) {
  return ESTADOS[clave] ?? { label: clave ?? '', status: 'info', icon: 'help_outline', tipo: null, activo: false, notificar: false }
}

/** Claves de los estados de esos tipos (activos o no). */
export function clavesDeTipo (...tipos) {
  return Object.keys(ESTADOS).filter((clave) => tipos.includes(ESTADOS[clave].tipo))
}

/** Desde acá se puede entregar (mismo criterio que EstadoOrden::entregables()). */
export function puedeEntregarse (clave) {
  return ['listo', 'cerrado'].includes(ESTADOS[clave]?.tipo)
}

/** Clave del estado de entrega. */
export function claveEntregado () {
  return clavesDeTipo('entregado')[0] ?? 'entregado'
}

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

// Para un estado creado en Catálogos que todavía no tiene mensaje propio.
export const PLANTILLA_WHATSAPP_GENERICA = 'Hola {cliente}, tu {equipo} (orden {codigo}) está: {estado}.' + PIE

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
    estado: estadoDe(orden.estado).label,
    total: formatearPrecio(orden.total ?? 0),
    saldo: formatearPrecio(orden.saldo ?? 0),
    saldo_pendiente: Number(orden.saldo) > 0 ? ` Saldo pendiente: ${formatearPrecio(orden.saldo)}.` : '',
    garantia: orden.garantia_hasta ? ` Garantía hasta el ${fechaCorta(orden.garantia_hasta)}.` : '',
    enlace: orden.token ? enlaceSeguimiento(orden.token) : '',
    empresa: TIENDA.nombre
  }
}

export function mensajeWhatsapp (orden) {
  const plantilla = TIENDA.whatsapp?.[orden.estado] || PLANTILLAS_WHATSAPP[orden.estado] || PLANTILLA_WHATSAPP_GENERICA
  return rellenarPlantilla(plantilla, valoresWhatsapp(orden))
}

/**
 * El número para wa.me, en formato internacional: un celular peruano de 9
 * dígitos lleva el 51 adelante. null si el cliente no tiene teléfono.
 */
export function numeroWhatsappOrden (orden) {
  const numero = (orden.cliente?.telefono ?? '').replace(/\D/g, '')
  if (!numero) return null
  return numero.length === 9 ? `51${numero}` : numero
}

/** wa.me con un texto ya armado (el que se editó en "Cambiar estado"). */
export function enlaceWhatsappCon (orden, texto) {
  const numero = numeroWhatsappOrden(orden)
  return numero ? `https://wa.me/${numero}?text=${encodeURIComponent(texto)}` : null
}

/** wa.me con el mensaje del estado actual de la orden. */
export function enlaceWhatsapp (orden) {
  return enlaceWhatsappCon(orden, mensajeWhatsapp(orden))
}

/** "2026-10-03" → "03/10/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}
