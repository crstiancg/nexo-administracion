import { TIENDA } from '@/config/tienda'
import { numeroWhatsapp } from '@/modules/Cotizaciones/constantes'
import { formatearPrecio } from '@/utils/moneda'

/** Estados visibles (los calcula el backend según la fecha de hoy). */
export const ESTADOS = {
  programado: { label: 'Programado', status: 'info' },
  abierto: { label: 'Abierto', status: 'positive' },
  por_sortear: { label: 'Por sortear', status: 'warning' },
  sorteando: { label: 'Sorteando', status: 'warning' },
  sorteado: { label: 'Sorteado', status: 'positive' },
  cancelado: { label: 'Cancelado', status: 'negative' }
}

export const PERIODOS = [
  { label: 'Mensual', value: 'mensual' },
  { label: 'Trimestral', value: 'trimestral' },
  { label: 'Otro', value: 'otro' }
]

/** "2026-12-31" → "31/12/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}

/** "1 ticket por compra u OS · ticket a S/ 5.00" */
export function reglaDe (sorteo) {
  const precio = Number(sorteo.precio_ticket) > 0 ? ` · ticket a ${formatearPrecio(sorteo.precio_ticket)}` : ''
  return `1 ticket por compra u OS${precio}`
}

/** Fecha local YYYY-MM-DD corrida `dias` días (no UTC). */
export function hoyLocal (dias = 0) {
  const d = new Date()
  d.setDate(d.getDate() + dias)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** El mes en curso (mensual) o el trimestre en curso (trimestral). */
export function periodoSugerido (periodo) {
  const hoy = new Date()
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  if (periodo === 'trimestral') {
    const inicio = Math.floor(hoy.getMonth() / 3) * 3
    return { inicia_el: iso(new Date(hoy.getFullYear(), inicio, 1)), termina_el: iso(new Date(hoy.getFullYear(), inicio + 3, 0)) }
  }
  return { inicia_el: iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), termina_el: iso(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)) }
}

/**
 * Forma inicial del form. Función y no objeto: useForm guarda estos datos
 * como "originales" para el reset().
 */
export function formSorteo () {
  return {
    sorteo: {
      nombre: '',
      bases: '',
      periodo: 'mensual',
      ...periodoSugerido('mensual'),
      // Sugerido al vender tickets sueltos (en cada venta se puede cambiar).
      precio_ticket: '',
      un_premio_por_cliente: true,
      // Vacío = participan todas las sedes.
      sedes: [],
      premios: [{ descripcion: '' }]
    }
  }
}

/** WhatsApp al participante: cuántos tickets tiene y cuándo se sortea. */
export function enlaceWhatsappParticipante (sorteo, participante) {
  const nombre = participante.nombre?.split(' ')[0] ?? ''
  const tickets = participante.tickets === 1 ? '1 ticket' : `${participante.tickets} tickets`
  const mensaje = `Hola ${nombre}, ya tienes ${tickets} para el ${sorteo.nombre}. ` +
    `El sorteo es el ${fechaCorta(sorteo.termina_el)}: cada compra o servicio técnico te suma otro, y también puedes comprar más tickets en tienda. ¡Mucha suerte!\n— ${TIENDA.nombre}`
  return `https://wa.me/${numeroWhatsapp(participante.telefono) ?? ''}?text=${encodeURIComponent(mensaje)}`
}

/** WhatsApp al ganador. */
export function enlaceWhatsappGanador (sorteo, premio) {
  const cliente = premio.ganador?.cliente
  const nombre = cliente?.nombre?.split(' ')[0] ?? ''
  const mensaje = `¡Felicidades ${nombre}! Tu ticket ${premio.ganador.codigo} ganó el ${premio.orden}.° premio del ${sorteo.nombre}: ` +
    `${premio.descripcion}. Acércate a la tienda con tu documento para recogerlo.\n— ${TIENDA.nombre}`
  return `https://wa.me/${numeroWhatsapp(cliente?.telefono) ?? ''}?text=${encodeURIComponent(mensaje)}`
}
