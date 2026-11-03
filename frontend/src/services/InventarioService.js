import { api } from '@/boot/axios'

/**
 * Registrar entradas, salidas y ajustes no pasa por acá: lo hace el form con
 * useForm() de Precognition, que valida y envía contra la misma URL.
 */
class InventarioService {
  // Historial de movimientos (paginado, filtrable).
  static async getData (config) {
    return (await api.get('api/inventario', config)).data
  }

  // Lotes con stock (filtros: sede_id, variante_id, estado, search).
  static async lotes (config) {
    return (await api.get('api/inventario/lotes', config)).data
  }

  // Completa lo que le faltó a una entrada (costo, referencia, lotes).
  static async corregirEntrada (id, entrada) {
    return (await api.patch(`api/inventario/movimientos/${id}`, { entrada })).data
  }

  // Le da lote al stock que quedó sin lote en la sede del usuario.
  static async asignarLote (lote) {
    return (await api.post('api/inventario/lotes/asignar', { lote })).data
  }

  // Buscador de variantes para las líneas de un movimiento.
  static async variantes (config) {
    return (await api.get('api/inventario/variantes', config)).data
  }
}

export default InventarioService
