// Opciones rápidas de la proforma (se puede escribir otra).
export const FORMAS_PAGO = ['Contado', '50% de adelanto y saldo contra entrega', 'Crédito a 15 días', 'Crédito a 30 días']
export const ENTREGAS = [
  'Con posibilidad de entrega en oficina o domicilio del cliente.',
  'Recojo en tienda.',
  'Entrega inmediata, sujeta a stock.'
]

/**
 * Forma inicial del form de cotizaciones. La clave `cotizacion` es la misma
 * que valida StoreCotizacionRequest. Los totales NO van: los calcula el
 * backend; acá se muestran sólo como vista previa.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 */
export default function formCotizacion () {
  return {
    cotizacion: {
      cliente_id: null,
      // Vacía a propósito: la pone quien cotiza (es obligatoria).
      valida_hasta: '',
      // Salen impresos en la proforma; se proponen los de siempre.
      forma_pago: FORMAS_PAGO[0],
      entrega: ENTREGAS[0],
      condiciones: '',
      descuento: '',
      observacion: '',
      items: []
    }
  }
}
