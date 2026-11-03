/**
 * Forma inicial del form de permisos. Sólo la descripción es editable: el
 * nombre es el de la ruta que protege (lo crea `permisos:sync` en el backend)
 * y lo valida UpdatePermisoRequest como `permiso.description`.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 */
export default function formPermiso () {
  return {
    permiso: {
      description: ''
    }
  }
}
