/**
 * Forma inicial del form de usuarios. La clave `usuario` es la misma que
 * valida StoreUserRequest (`usuario.name`, `usuario.rolesSelected.*`...).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría roles tildados de un form al
 * siguiente.
 */
export default function formUsuario () {
  return {
    usuario: {
      name: '',
      username: '',
      email: '',
      password: '',
      sede_id: null,
      rolesSelected: [],
      permisosSelected: []
    }
  }
}
