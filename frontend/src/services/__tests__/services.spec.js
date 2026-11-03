import { describe, it, expect, vi, beforeEach } from 'vitest'

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('@/boot/axios', () => ({ api }))

import PermisoService from '@/services/PermisoService'
import RolService from '@/services/RolService'
import UsuarioService from '@/services/UsuarioService'
import ColorService from '@/services/ColorService'

beforeEach(() => {
  Object.values(api).forEach((fn) => fn.mockReset())
})

// Mismo contrato para cada recurso (patrón botica-api-service).
describe.each([
  ['PermisoService', PermisoService, 'api/permisos'],
  ['RolService', RolService, 'api/roles'],
  ['UsuarioService', UsuarioService, 'api/usuarios'],
  ['ColorService', ColorService, 'api/colores']
])('%s', (_, Service, url) => {
  it('getData pasa los params de la tabla y devuelve el cuerpo', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1 }], total: 1 } })
    const params = { rowsPerPage: 10, page: 1, search: 'x', order_by: '-id' }

    const res = await Service.getData({ params })

    expect(api.get).toHaveBeenCalledWith(url, { params })
    expect(res).toEqual({ data: [{ id: 1 }], total: 1 })
  })

  it('get trae un registro', async () => {
    api.get.mockResolvedValue({ data: { id: 3 } })

    expect(await Service.get(3)).toEqual({ id: 3 })
    expect(api.get).toHaveBeenCalledWith(`${url}/3`)
  })

  it('delete borra por id', async () => {
    api.delete.mockResolvedValue({ status: 204 })

    await Service.delete(3)

    expect(api.delete).toHaveBeenCalledWith(`${url}/3`)
  })
})

describe('UsuarioService — acciones propias', () => {
  it('toggleActive hace PATCH y devuelve el estado nuevo', async () => {
    api.patch.mockResolvedValue({ data: { id: 5, active: false } })

    expect(await UsuarioService.toggleActive(5)).toEqual({ id: 5, active: false })
    expect(api.patch).toHaveBeenCalledWith('api/usuarios/5/toggle-active')
  })

  it('getSesiones lista las sesiones activas del usuario', async () => {
    api.get.mockResolvedValue({ data: [{ id: 'abc' }] })

    expect(await UsuarioService.getSesiones(5)).toEqual([{ id: 'abc' }])
    expect(api.get).toHaveBeenCalledWith('api/usuarios/5/sesiones')
  })

  it('revocarSesion va anidada bajo el usuario dueño del token', async () => {
    api.delete.mockResolvedValue({ status: 204 })

    await UsuarioService.revocarSesion(5, 'abc')

    expect(api.delete).toHaveBeenCalledWith('api/usuarios/5/sesiones/abc')
  })
})

describe('PermisoService — alta desde rutas', () => {
  it('rutasDisponibles trae las rutas sin permiso agrupadas', async () => {
    api.get.mockResolvedValue({ data: [{ recurso: 'ventas', rutas: [] }] })

    expect(await PermisoService.rutasDisponibles()).toEqual([{ recurso: 'ventas', rutas: [] }])
    expect(api.get).toHaveBeenCalledWith('api/permisos/rutas-disponibles')
  })
})
