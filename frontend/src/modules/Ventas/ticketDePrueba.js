import { desglosarIgv } from '@/utils/igv'

/**
 * Un "pedido" inventado con la misma forma que PedidoResource, para probar
 * la ticketera (ancho, corte, letra) sin registrar una venta. Lleva un nombre
 * largo a propósito: así se ve cómo corta las líneas en 80 mm.
 *
 * @param {{ name?: string, sede?: object|null }} usuario  el que imprime
 */
export function ticketDePrueba (usuario) {
  const items = [
    { id: 1, nombre: 'Cemento Portland Tipo I', presentacion: 'Bolsa 42.5 kg', cantidad: 3, precio: 29.9 },
    { id: 2, nombre: 'Impermeabilizante acrílico para techos de alto tránsito', presentacion: 'Galón 4 L', color: 'Blanco', cantidad: 1, precio: 95 },
    { id: 3, nombre: 'Sellador de silicona', presentacion: 'Cartucho 280 ml', cantidad: 2, precio: 24.9 }
  ].map((i) => ({
    id: i.id,
    cantidad: i.cantidad,
    precio_unitario: i.precio.toFixed(2),
    subtotal: (Math.round(i.cantidad * i.precio * 100) / 100).toFixed(2),
    variante: {
      producto: { nombre: i.nombre },
      presentacion: i.presentacion,
      color: i.color ? { nombre: i.color } : null
    }
  }))

  const total = items.reduce((s, i) => s + Number(i.subtotal), 0)
  const { opGravada, igv } = desglosarIgv(total)
  const recibido = Math.ceil(total / 50) * 50

  return {
    id: 0,
    codigo: 'PRUEBA',
    sede: usuario?.sede ?? null,
    usuario: { name: usuario?.name ?? 'Usuario' },
    cliente: { nombre: 'TICKET DE PRUEBA - NO ES UNA VENTA', tipo_documento: null, numero_documento: null },
    fecha: new Date().toISOString(),
    confirmado_at: new Date().toISOString(),
    items,
    subtotal: total.toFixed(2),
    descuento: '0',
    total: total.toFixed(2),
    op_gravada: opGravada.toFixed(2),
    igv: igv.toFixed(2),
    pagos: [{
      id: 1,
      metodo_label: 'Efectivo',
      monto: total.toFixed(2),
      recibido: recibido.toFixed(2),
      vuelto: (recibido - total).toFixed(2),
      es_devolucion: false
    }]
  }
}
