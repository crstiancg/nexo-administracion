import { api } from '@/boot/axios'

/**
 * Descarga un archivo de la API (Excel, XML…). Va por axios y no con un
 * enlace directo porque la API pide el token.
 *
 * @param {string} url  ruta de la API (p. ej. 'api/productos/exportar')
 * @param {string} nombre  nombre del archivo que se guarda
 * @param {object} [params]  query (los filtros de la pantalla)
 */
export async function descargarArchivo (url, nombre, params = {}) {
  const { data } = await api.get(url, { params, responseType: 'blob' })
  const enlace = document.createElement('a')
  enlace.href = URL.createObjectURL(data)
  enlace.download = nombre
  enlace.click()
  URL.revokeObjectURL(enlace.href)
}
