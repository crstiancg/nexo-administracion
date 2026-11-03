/**
 * Tipos de movimiento: lo que ve el usuario y a qué endpoint va cada uno.
 * Mismo mapeo único que Pedidos/Productos: label, status del AppChip e ícono
 * nacen juntos, así nunca se desincronizan.
 */
export const TIPOS = {
  entrada: {
    label: 'Entrada',
    titulo: 'Registrar entrada',
    ayuda: 'Compra o reposición en tu sede. El costo recalcula el costo promedio de cada presentación.',
    endpoint: 'entradas',
    permiso: 'inventario.entradas',
    status: 'positive',
    icon: 'add_box'
  },
  salida: {
    label: 'Salida',
    titulo: 'Registrar salida',
    ayuda: 'Merma, daño, regalo… en tu sede. Las ventas descuentan solas desde Pedidos y el punto de venta.',
    endpoint: 'salidas',
    permiso: 'inventario.salidas',
    status: 'negative',
    icon: 'indeterminate_check_box'
  },
  ajuste: {
    label: 'Ajuste',
    titulo: 'Ajuste por conteo',
    ayuda: 'Escribí el stock que contaste en tu sede: el sistema registra la diferencia. Las presentaciones que coinciden no generan movimiento.',
    endpoint: 'ajustes',
    permiso: 'inventario.ajustes',
    status: 'info',
    icon: 'fact_check'
  },
  // Sólo acción: en el libro queda como una salida en el origen y una
  // entrada en el destino (motivos traslado_salida / traslado_entrada).
  traslado: {
    label: 'Traslado',
    titulo: 'Trasladar a otra sede',
    ayuda: 'Envía mercadería de tu sede a otra. Sale de tu stock y entra en el de la sede de destino.',
    endpoint: 'traslados',
    permiso: 'inventario.traslados',
    status: 'warning',
    icon: 'local_shipping',
    soloAccion: true
  }
}

// Mismas claves que MovimientoInventario::MOTIVOS_SALIDA del backend.
export const MOTIVOS_SALIDA = [
  { value: 'merma', label: 'Merma' },
  { value: 'danado', label: 'Dañado' },
  { value: 'regalo', label: 'Regalo / promoción' },
  { value: 'devolucion_proveedor', label: 'Devolución al proveedor' },
  { value: 'uso_interno', label: 'Uso interno' },
  { value: 'otro', label: 'Otro' }
]
