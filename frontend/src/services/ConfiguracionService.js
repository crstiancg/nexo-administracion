import { api } from '@/boot/axios'

/**
 * Datos de la empresa, textos de los documentos, logo y mensajes de WhatsApp.
 */
class ConfiguracionService {
  static async get () {
    return (await api.get('api/configuracion')).data
  }

  // { empresa: { nombre, ruc, … }, whatsapp: { reparado: '…' } }
  static async guardar (datos) {
    return (await api.put('api/configuracion', datos)).data
  }

  static async subirLogo (archivo) {
    const datos = new FormData()
    datos.append('logo', archivo)
    return (await api.post('api/configuracion/logo', datos)).data
  }

  static async quitarLogo () {
    return (await api.delete('api/configuracion/logo')).data
  }
}

export default ConfiguracionService
