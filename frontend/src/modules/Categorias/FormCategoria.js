/**
 * Forma inicial del form de categorías. La clave `categoria` es la misma que
 * valida StoreCategoriaRequest (`categoria.nombre`, `categoria.parent_id`).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 */
export default function formCategoria () {
  return {
    categoria: {
      nombre: '',
      parent_id: null
    }
  }
}
