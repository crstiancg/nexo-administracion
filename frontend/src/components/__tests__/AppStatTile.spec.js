import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QIcon } from 'quasar'
import AppStatTile from '@/components/AppStatTile.vue'
import AppCard from '@/components/AppCard.vue'

const BASE = { label: 'Ingresos', value: '$418k', delta: '+12,4%' }

function mountTile (props = {}) {
  return mount(AppStatTile, { props: { ...BASE, ...props } })
}

function tone (wrapper) {
  const classes = wrapper.find('[class*="app-stat__delta"]').classes()
  if (classes.includes('app-stat__delta--good')) return 'good'
  if (classes.includes('app-stat__delta--bad')) return 'bad'
  return null
}

function arrow (wrapper) {
  return wrapper.findComponent(QIcon).props('name')
}

describe('AppStatTile — flecha y color son canales separados', () => {
  // Este es el punto entero del componente: la flecha dice hacia dónde se
  // movió el dato, el color dice si eso está bien. Acoplarlos miente.

  it('sube y subir es bueno: flecha arriba, verde', () => {
    const wrapper = mountTile({ trend: 'up', trendIsGood: true })
    expect(arrow(wrapper)).toBe('arrow_upward')
    expect(tone(wrapper)).toBe('good')
  })

  it('sube pero subir es MALO: flecha arriba, rojo', () => {
    // El caso que se rompe siempre: costos que suben.
    const wrapper = mountTile({ trend: 'up', trendIsGood: false })
    expect(arrow(wrapper)).toBe('arrow_upward')
    expect(tone(wrapper)).toBe('bad')
  })

  it('baja y bajar es malo: flecha abajo, rojo', () => {
    const wrapper = mountTile({ trend: 'down', trendIsGood: false })
    expect(arrow(wrapper)).toBe('arrow_downward')
    expect(tone(wrapper)).toBe('bad')
  })

  it('baja pero bajar es BUENO: flecha abajo, verde', () => {
    // El espejo del anterior: costos que bajan.
    const wrapper = mountTile({ trend: 'down', trendIsGood: true })
    expect(arrow(wrapper)).toBe('arrow_downward')
    expect(tone(wrapper)).toBe('good')
  })
})

describe('AppStatTile — jerarquía', () => {
  it('sin delta no renderiza el bloque de delta', () => {
    const wrapper = mount(AppStatTile, { props: { label: 'Pedidos', value: '1.284' } })
    expect(wrapper.find('[class*="app-stat__delta"]').exists()).toBe(false)
  })

  it('featured usa la card destacada', () => {
    const wrapper = mountTile({ featured: true })
    expect(wrapper.findComponent(AppCard).props('variant')).toBe('highlight')
  })

  it('sin featured usa la card de superficie', () => {
    expect(mountTile().findComponent(AppCard).props('variant')).toBe('surface')
  })

  it('el valor se renderiza tal cual: el tile no formatea números', () => {
    const wrapper = mountTile({ value: '3,12%' })
    expect(wrapper.find('[class*="app-stat__value"]').text()).toBe('3,12%')
  })
})
