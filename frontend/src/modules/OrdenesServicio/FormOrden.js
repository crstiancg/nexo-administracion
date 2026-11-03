/**
 * Forma inicial del form de órdenes de servicio. La clave `orden` es la misma
 * que valida StoreOrdenServicioRequest. Los totales NO van: el pedido de cobro
 * lo arma el backend; acá se muestran sólo como vista previa.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 */
export default function formOrden () {
  return {
    orden: {
      cliente_id: null,
      tecnico_id: null,
      tipo_equipo_id: null,
      marca_id: null,
      modelo: '',
      serie: '',
      accesorios: '',
      seguridad_tipo: 'ninguno',
      seguridad_valor: '',
      // { "Enciende": true, … } con los puntos del tipo de equipo.
      checklist: {},
      falla: '',
      diagnostico: '',
      observacion: '',
      es_garantia: false,
      garantia_dias: 30,
      mano_obra: '',
      repuestos: [],
      // Sólo al crear: entra a la caja abierta.
      anticipo: { metodo: 'efectivo', monto: '', referencia: '' }
    }
  }
}

/**
 * La lista de revisión del tipo elegido, conservando lo ya marcado en los
 * puntos que se repiten (cambiar de "Laptop" a "Tablet" no borra "Enciende").
 */
export function checklistPara (puntos, anterior = {}) {
  return Object.fromEntries((puntos ?? []).map((punto) => [punto, Boolean(anterior[punto])]))
}
