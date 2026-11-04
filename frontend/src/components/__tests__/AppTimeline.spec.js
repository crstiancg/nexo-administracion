import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppTimeline from '@/components/AppTimeline.vue'

const ITEMS = [
  { titulo: 'Recibido', fecha: '03/11/2026', estado: 'hecho' },
  { titulo: 'En reparación', descripcion: 'Cambiando la pantalla', estado: 'actual' },
  { titulo: 'Listo para recoger', estado: 'pendiente' }
]

function montar (props = {}) {
  return mount(AppTimeline, { props: { items: ITEMS, ...props } })
}

describe('AppTimeline', () => {
  it('dibuja un paso por ítem, con su título, fecha y descripción', () => {
    const wrapper = montar()
    const pasos = wrapper.findAll('.tl__item')

    expect(pasos).toHaveLength(3)
    expect(pasos[0].text()).toContain('Recibido')
    expect(pasos[0].text()).toContain('03/11/2026')
    expect(pasos[1].text()).toContain('Cambiando la pantalla')
  })

  it('marca el paso actual para lectores de pantalla', () => {
    const pasos = montar().findAll('.tl__item')

    expect(pasos[1].attributes('aria-current')).toBe('step')
    expect(pasos[0].attributes('aria-current')).toBeUndefined()
  })

  it('pinta la línea sólo hasta el paso actual', () => {
    const lineas = montar().findAll('.tl__linea')

    // Una línea menos que pasos: entre Recibido→Reparación (activa) y
    // Reparación→Listo (todavía no).
    expect(lineas).toHaveLength(2)
    expect(lineas[0].classes()).toContain('tl__linea--activa')
    expect(lineas[1].classes()).not.toContain('tl__linea--activa')
  })

  it('sin estado, el paso cuenta como hecho', () => {
    const wrapper = montar({ items: [{ titulo: 'A' }, { titulo: 'B' }] })

    expect(wrapper.findAll('.tl__item--hecho')).toHaveLength(2)
    expect(wrapper.find('.tl__linea').classes()).toContain('tl__linea--activa')
  })

  it('acepta orientación horizontal', () => {
    expect(montar({ orientation: 'horizontal' }).classes()).toContain('tl--horizontal')
  })
})
