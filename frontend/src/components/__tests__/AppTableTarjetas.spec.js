import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { h } from 'vue'

// Pantalla de celular: AppTable tiene que mostrar tarjetas.
vi.mock('quasar', async (importOriginal) => ({
  ...(await importOriginal()),
  useQuasar: () => ({ screen: { lt: { sm: true } } })
}))

const { default: AppTable } = await import('@/components/AppTable.vue')

const COLUMNS = [
  { name: 'codigo', label: 'Orden', field: 'codigo', align: 'left' },
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]
const ROWS = [
  { id: 1, codigo: 'OS-000001', cliente: 'Ana Quispe', estado: 'reparado' },
  { id: 2, codigo: 'OS-000002', cliente: 'Juan Mamani', estado: 'recibido' }
]

function montar (props = {}) {
  return mount(AppTable, {
    props: { rows: ROWS, columns: COLUMNS, ...props },
    slots: {
      // Una celda propia de la lista, como las de verdad (<q-td :props>).
      'body-cell-estado': (p) => h('span', { class: 'chip-estado' }, p.row.estado.toUpperCase()),
      'body-cell-acciones': (p) => h('button', { class: 'btn-ver' }, `Ver ${p.row.codigo}`)
    }
  })
}

describe('AppTable — tarjetas en el celular', () => {
  it('cada fila es una tarjeta: título, rótulo: valor y acciones al pie', () => {
    const tarjetas = montar().findAll('.app-table__tarjeta')

    expect(tarjetas).toHaveLength(2)
    expect(tarjetas[0].find('.app-table__tarjetaTitulo').text()).toBe('OS-000001')
    expect(tarjetas[0].find('.app-table__tarjetaDatos').text()).toContain('Cliente')
    expect(tarjetas[0].find('.app-table__tarjetaDatos').text()).toContain('Ana Quispe')
    // El estado sube a la cabecera, no queda entre los datos.
    expect(tarjetas[0].find('.app-table__tarjetaEstado .chip-estado').text()).toBe('REPARADO')
    expect(tarjetas[0].find('.app-table__tarjetaDatos').text()).not.toContain('Estado')
    expect(tarjetas[0].find('.app-table__tarjetaAcciones .btn-ver').text()).toBe('Ver OS-000001')
  })

  it('reusa las celdas que define la lista', () => {
    const tarjeta = montar().findAll('.app-table__tarjeta')[1]

    expect(tarjeta.find('.chip-estado').text()).toBe('RECIBIDO')
  })

  it('con tarjetas-en-movil en false sigue siendo tabla', () => {
    const wrapper = montar({ tarjetasEnMovil: false })

    expect(wrapper.findAll('.app-table__tarjeta')).toHaveLength(0)
    expect(wrapper.find('table').exists()).toBe(true)
  })
})
