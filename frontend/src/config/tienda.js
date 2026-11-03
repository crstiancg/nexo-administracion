/**
 * Datos de la empresa para el encabezado del ticket, la proforma y la orden
 * de servicio. Se pueden pisar en el .env del front (VITE_APP_TIENDA_*).
 */
const env = import.meta.env

export const TIENDA = {
  // Razón social: encabeza el ticket y la proforma impresa.
  nombre: env.VITE_APP_TIENDA_NOMBRE || 'NEXO TECHNOLOGY S.R.L.',
  ruc: env.VITE_APP_TIENDA_RUC || '20610596780',
  direccion: env.VITE_APP_TIENDA_DIRECCION || 'Jr. Arequipa 418 - Puno',
  telefono: env.VITE_APP_TIENDA_TELEFONO || '900 551 900 - 900 180 000',
  pie: env.VITE_APP_TIENDA_PIE || '¡Gracias por su compra!',
  // Lo que hace la empresa: va bajo el logo en la proforma.
  lema: env.VITE_APP_TIENDA_LEMA ||
    'Computadoras · Laptops · Impresoras · Suministros · Soporte técnico especializado · Consultoría y asesoramiento en proyectos tecnológicos',
  // Nota de garantía de la proforma.
  garantia: env.VITE_APP_TIENDA_GARANTIA ||
    'La garantía de equipos de cómputo en general se hace efectiva en las oficinas de NEXO TECHNOLOGY Puno, en los horarios de atención establecidos.'
}
