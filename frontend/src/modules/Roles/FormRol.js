/**
 * Forma inicial del form de roles. La clave `rol` es la misma que valida
 * StoreRolRequest (`rol.name`, `rol.permisosSelected.*` con IDs de permisos).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría el array de permisos tildados
 * de un form al siguiente.
 */
export default function formRol () {
  return {
    rol: {
      name: '',
      permisosSelected: []
    }
  }
}
