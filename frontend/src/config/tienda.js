import { reactive } from 'vue'

/**
 * Datos de la empresa para el ticket, la proforma, la orden de compra y los
 * mensajes de WhatsApp. Arranca con los valores por defecto y, al iniciar
 * sesión, se completa con lo guardado en Configuración (GET api/configuracion).
 * Es reactivo: los documentos que lo usan se actualizan solos.
 */
const env = import.meta.env

export const TIENDA = reactive({
  // Razón social: encabeza el ticket y la proforma impresa.
  nombre: env.VITE_APP_TIENDA_NOMBRE || 'NEXO TECHNOLOGY S.R.L.',
  ruc: env.VITE_APP_TIENDA_RUC || '20610596780',
  direccion: env.VITE_APP_TIENDA_DIRECCION || 'Jr. Arequipa 418 - Puno',
  telefono: env.VITE_APP_TIENDA_TELEFONO || '900 551 900 - 900 180 000',
  // Pie del ticket de venta.
  pie: env.VITE_APP_TIENDA_PIE || '¡Gracias por su compra!',
  // Lo que hace la empresa: va bajo el logo en la proforma.
  lema: 'Computadoras · Laptops · Impresoras · Suministros · Soporte técnico especializado · Consultoría y asesoramiento en proyectos tecnológicos',
  // Nota de garantía de la proforma.
  garantia: 'La garantía de equipos de cómputo en general se hace efectiva en nuestras oficinas, en los horarios de atención establecidos.',
  terminos_orden: '',
  // null = el logo de Nexo que viene con el sistema.
  logo_url: null,
  // Mensajes de WhatsApp cambiados por estado ({ reparado: '…' }); los que
  // no están usan los de por defecto (modules/OrdenesServicio/constantes.js).
  whatsapp: {},
  // Estados de la orden de servicio (Catálogos > Estados de orden). Vacío =
  // los de fábrica (modules/OrdenesServicio/constantes.js).
  estados_orden: [],
  // Tabla de precios de referencia ({ hasta, porcentaje }): sugiere el precio
  // de venta a partir del costo de compra (utils/precios.js). Vacía = nada.
  margenes: []
})

/** Vuelca lo que devuelve la API (GET/PUT api/configuracion) en TIENDA. */
export function aplicarConfiguracion (datos) {
  if (!datos) return
  const { pie_ticket: pie, whatsapp, estados_orden: estados, ...resto } = datos
  Object.assign(TIENDA, resto, { pie: pie || TIENDA.pie, whatsapp: whatsapp ?? {} })
  if (estados) TIENDA.estados_orden = estados
}
