import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppTable from '@/components/AppTable.vue'

const COLUMNS = [{ name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' }]

function buildRows (count) {
  return Array.from({ length: count }, (_, i) => ({ id: i + 1, nombre: `Fila ${i + 1}` }))
}

function mountTable (props = {}) {
  return mount(AppTable, {
    props: { rows: buildRows(20), columns: COLUMNS, ...props }
  })
}

describe('AppTable — paginación propia', () => {
  it('arranca mostrando la página 1', () => {
    expect(mountTable().text()).toContain('Fila 1')
  })

  it('clickear el número "2" muestra la página siguiente', async () => {
    // Regresión: mutar pagination.value.page directamente no le llega a
    // QTable (confirmado en la doc oficial), así que sin reemplazar el
    // objeto entero esta prueba fallaría en silencio mostrando la página 1.
    const wrapper = mountTable()
    const pageNumbers = wrapper.findAll('.app-table__pageNumber')
    const page2 = pageNumbers.find((el) => el.text() === '2')

    await page2.trigger('click')

    expect(wrapper.text()).toContain('Fila 9')
    expect(wrapper.text()).not.toContain('Fila 1 ')
  })

  it('la flecha "siguiente" avanza una página', async () => {
    const wrapper = mountTable()
    const nextBtn = wrapper.findAll('button').find((btn) => btn.attributes('aria-label') === 'Página siguiente')

    await nextBtn.trigger('click')

    expect(wrapper.text()).toContain('Fila 9')
  })

  it('filtrar (menos filas) vuelve a la página 1', async () => {
    const wrapper = mountTable()
    const page2 = wrapper.findAll('.app-table__pageNumber').find((el) => el.text() === '2')
    await page2.trigger('click')
    expect(wrapper.text()).toContain('Fila 9')

    // Menos filas de las que entraban en la página 2: sin volver a la 1
    // quedaría mostrando una página vacía.
    await wrapper.setProps({ rows: buildRows(3) })

    expect(wrapper.text()).toContain('Fila 1')
  })

  it('sin filas no renderiza la barra de paginación', () => {
    expect(mountTable({ rows: [] }).find('.app-table__pagination').exists()).toBe(false)
  })

  it('muestra el rango "Mostrando X–Y de Z"', () => {
    expect(mountTable().find('.app-table__paginationInfo').text()).toContain('1–8')
    expect(mountTable().find('.app-table__paginationInfo').text()).toContain('20')
  })
})

describe('AppTable — paginación con muchas páginas (10.000 filas)', () => {
  // 10.000 filas a 8 por página son 1.250 páginas. Sin ventana, esto
  // renderizaría 1.250 números de golpe.
  function mountBigTable (props = {}) {
    return mount(AppTable, { props: { rows: buildRows(10000), columns: COLUMNS, ...props } })
  }

  it('no renderiza un número de página por cada página', () => {
    // Primera, última, actual ± 1 y dos "…": nunca más de un puñado de nodos.
    expect(mountBigTable().findAll('.app-table__pageNumber').length).toBeLessThan(10)
  })

  it('siempre muestra la primera y la última página', () => {
    const text = mountBigTable().find('.app-table__paginationControls').text()
    expect(text).toContain('1')
    expect(text).toContain('1250')
  })

  it('muestra "…" cuando hay hueco entre la página actual y los extremos', () => {
    expect(mountBigTable().findAll('.app-table__pageEllipsis').length).toBeGreaterThan(0)
  })

  it('al acercarse a la última página, la ventana se corre y sigue acotada', async () => {
    const wrapper = mountBigTable()
    // Página 1250 no es clickeable directo (no está en la ventana inicial),
    // así que llegamos por goToPage a través de la flecha "siguiente" no
    // sirve acá — probamos moviendo el modelo de paginación directamente.
    await wrapper.setProps({ pagination: { page: 1250, rowsPerPage: 8 } })

    expect(wrapper.findAll('.app-table__pageNumber').length).toBeLessThan(10)
    expect(wrapper.text()).toContain('Fila 10000')
  })
})

// Modo servidor: con `rowsNumber` en la paginación (misma convención que
// QTable) las filas son sólo la página actual y paginar/ordenar/buscar se
// le pide al padre con `request`, que va a la API (patrón sistema-botica).
describe('AppTable — modo servidor', () => {
  function mountServer (props = {}) {
    return mount(AppTable, {
      props: {
        rows: buildRows(10),
        columns: COLUMNS,
        pagination: { page: 1, rowsPerPage: 10, rowsNumber: 45, sortBy: 'id', descending: false },
        ...props
      }
    })
  }

  it('el total y las páginas salen de rowsNumber, no de las filas cargadas', () => {
    const wrapper = mountServer()

    expect(wrapper.find('.app-table__paginationInfo').text()).toBe('Mostrando 1–10 de 45')
    expect(wrapper.findAll('.app-table__pageNumber').map((el) => el.text())).toContain('5')
  })

  it('muestra todas las filas recibidas sin recortarlas', () => {
    expect(mountServer().text()).toContain('Fila 10')
  })

  it('cambiar de página pide los datos al padre en vez de paginar local', async () => {
    const wrapper = mountServer({ filter: 'ab' })

    await wrapper.findAll('.app-table__pageNumber').find((el) => el.text() === '2').trigger('click')

    const [payload] = wrapper.emitted('request').at(-1)
    expect(payload.pagination).toMatchObject({ page: 2, rowsPerPage: 10, sortBy: 'id', descending: false })
    expect(payload.filter).toBe('ab')
  })

  it('requestServerInteraction() pide la página actual (para refrescar tras guardar)', () => {
    const wrapper = mountServer()

    wrapper.vm.requestServerInteraction()

    expect(wrapper.emitted('request').at(-1)[0].pagination.page).toBe(1)
  })

  it('cambiar el filtro vuelve a pedir desde la página 1', async () => {
    const wrapper = mountServer({ pagination: { page: 3, rowsPerPage: 10, rowsNumber: 45 } })

    await wrapper.setProps({ filter: 'nuevo' })

    const [payload] = wrapper.emitted('request').at(-1)
    expect(payload.pagination.page).toBe(1)
    expect(payload.filter).toBe('nuevo')
  })

  it('recibir filas nuevas NO resetea la página (sería un bucle de requests)', async () => {
    const wrapper = mountServer({ pagination: { page: 3, rowsPerPage: 10, rowsNumber: 45 } })

    await wrapper.setProps({ rows: buildRows(5) })

    expect(wrapper.emitted('request')).toBeUndefined()
    expect(wrapper.find('.app-table__pageNumber--active').text()).toBe('3')
  })
})
