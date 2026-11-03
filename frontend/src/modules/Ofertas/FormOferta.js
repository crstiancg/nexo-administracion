/**
 * Forma inicial del form de ofertas. La clave `oferta` es la misma que valida
 * StoreOfertaRequest. Las fechas viven acá como ISO con zona (lo que espera
 * la API); el form las muestra en hora local.
 *
 * `productos`: [{ producto_id, variantes: [ids] }] — variantes vacío = el
 * producto completo. `categorias`: [ids].
 *
 * Por defecto: arranca ahora y dura una semana, hasta las 23:59.
 */
export default function formOferta () {
  const inicio = new Date()
  inicio.setSeconds(0, 0)
  const fin = new Date(inicio)
  fin.setDate(fin.getDate() + 7)
  fin.setHours(23, 59, 0, 0)

  return {
    oferta: {
      nombre: '',
      alcance: 'productos',
      productos: [],
      categorias: [],
      incluye_subcategorias: true,
      tipo: 'porcentaje',
      valor: '',
      inicia_at: inicio.toISOString(),
      termina_at: fin.toISOString(),
      activa: true
    }
  }
}

// Mismo mapeo único que el resto: label y status del AppChip juntos.
export const ESTADOS = {
  vigente: { label: 'Vigente', status: 'positive' },
  programada: { label: 'Programada', status: 'info' },
  pausada: { label: 'Pausada', status: 'warning' },
  vencida: { label: 'Vencida', status: 'negative' }
}
