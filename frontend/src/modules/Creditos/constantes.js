import { TIENDA } from '@/config/tienda'
import { fechaCorta, numeroWhatsapp } from '@/modules/Cotizaciones/constantes'
import { rellenarPlantilla } from '@/modules/OrdenesServicio/constantes'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Recordatorios de cuotas por WhatsApp (wa.me, desde el WhatsApp de quien
 * cobra): uno antes de que venza y otro cuando ya venció. Se cambian en
 * Configuración; los que no se cambiaron son éstos.
 */
export const PLANTILLAS_WHATSAPP = {
  cuota_por_vencer: 'Hola {cliente}, le recordamos que su cuota {cuota} de {monto} vence el {vence}. Saldo del crédito: {saldo}.\n— {empresa}',
  cuota_vencida: 'Hola {cliente}, le recordamos que su cuota {cuota} de {monto} venció el {vence}. ¿Cuándo podría pasar a pagarla? Saldo del crédito: {saldo}.\n— {empresa}'
}

/** Lo que se puede usar en el mensaje, con un ejemplo para la ayuda. */
export const VARIABLES_WHATSAPP = [
  { clave: 'cliente', ayuda: 'Primer nombre del cliente' },
  { clave: 'cuota', ayuda: 'Número de la cuota (2 de 4)' },
  { clave: 'monto', ayuda: 'Lo que falta de la cuota: S/ 80.00' },
  { clave: 'vence', ayuda: '12/11/2026' },
  { clave: 'saldo', ayuda: 'Lo que debe en total: S/ 240.00' },
  { clave: 'empresa', ayuda: 'Nombre de la empresa' }
]

export function valoresWhatsapp (credito) {
  const cuota = credito.proxima ?? {}
  return {
    cliente: credito.cliente?.nombre?.split(' ')[0] ?? '',
    cuota: cuota.numero ? `${cuota.numero} de ${credito.numero_cuotas}` : '',
    monto: formatearPrecio(cuota.falta ?? 0),
    vence: fechaCorta(cuota.vence),
    saldo: formatearPrecio(credito.saldo ?? 0),
    empresa: TIENDA.nombre
  }
}

/** Según la próxima cuota: vencida o por vencer. */
export function mensajeWhatsapp (credito) {
  const clave = credito.proxima?.estado === 'vencida' ? 'cuota_vencida' : 'cuota_por_vencer'
  return rellenarPlantilla(TIENDA.whatsapp?.[clave] || PLANTILLAS_WHATSAPP[clave], valoresWhatsapp(credito))
}

/** wa.me con el recordatorio de la próxima cuota; null sin celular o sin cuota pendiente. */
export function enlaceWhatsapp (credito) {
  const numero = numeroWhatsapp(credito.cliente?.telefono)
  if (!numero || !credito.proxima) return null
  return `https://wa.me/${numero}?text=${encodeURIComponent(mensajeWhatsapp(credito))}`
}
