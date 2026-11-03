import { api } from '@/boot/axios'

/**
 * Variantes para imprimir etiquetas: por producto (`producto_id`) o por
 * búsqueda (`search`: nombre, SKU o código de barras). Paginado.
 */
class EtiquetaService {
  static async getData (config) {
    return (await api.get('api/etiquetas', config)).data
  }
}

export default EtiquetaService
