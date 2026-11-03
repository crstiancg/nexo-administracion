/**
 * Forma inicial del form de clientes. La clave `cliente` es la misma que
 * valida StoreClienteRequest (`cliente.nombre`, `cliente.numero_documento`…).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 */
export default function formCliente () {
  return {
    cliente: {
      tipo_documento: 'DNI',
      numero_documento: '',
      nombre: '',
      telefono: '',
      email: '',
      direccion: '',
      // Empresa con precio por mayor.
      mayorista: false
    }
  }
}

export const TIPOS_DOCUMENTO = [
  { value: 'DNI', label: 'DNI', digitos: 8 },
  { value: 'RUC', label: 'RUC', digitos: 11 },
  { value: 'CE', label: 'Carné de extranjería' },
  { value: null, label: 'Sin documento' }
]
