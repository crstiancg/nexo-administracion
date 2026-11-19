import { api } from '@/boot/axios'
import { descargarArchivo } from '@/utils/descargar'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los tickets tampoco se crean acá: nacen de las ventas.
 */
class SorteoService {
  static async getData (config) {
    return (await api.get('api/sorteos', config)).data
  }

  static async get (id) {
    return (await api.get(`api/sorteos/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/sorteos/${id}`)
  }

  static async cancelar (id) {
    return (await api.post(`api/sorteos/${id}/cancelar`)).data
  }

  static async tickets (id, config) {
    return (await api.get(`api/sorteos/${id}/tickets`, config)).data
  }

  static async participantes (id, config) {
    return (await api.get(`api/sorteos/${id}/participantes`, config)).data
  }

  static async excel (sorteo) {
    return descargarArchivo(`api/sorteos/${sorteo.id}/excel`, `${sorteo.nombre}.xlsx`)
  }

  // Para animar la ruleta: { total, data: [{ codigo, nombre, documento }] }.
  static async candidatos (id) {
    return (await api.get(`api/sorteos/${id}/candidatos`)).data
  }

  // El ganador lo elige el servidor: { premio, sorteo }.
  static async sortear (id, premioId) {
    return (await api.post(`api/sorteos/${id}/premios/${premioId}/sortear`)).data
  }

  static async descartar (id, premioId, motivo) {
    return (await api.post(`api/sorteos/${id}/premios/${premioId}/descartar`, { motivo })).data
  }
}

export default SorteoService
