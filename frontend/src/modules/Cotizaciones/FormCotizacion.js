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
      condiciones: '',
      descuento: '',
      observacion: '',
      items: []
    }
  }
}
