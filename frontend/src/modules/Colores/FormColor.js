/**
 * Forma inicial del form de colores. La clave `color` es la misma que valida
 * StoreColorRequest (`color.nombre`, `color.hexadecimal`).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 */
export default function formColor () {
  return {
    color: {
      nombre: '',
      hexadecimal: ''
    }
  }
}
