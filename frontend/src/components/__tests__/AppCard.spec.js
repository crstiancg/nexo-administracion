import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QCard } from 'quasar'
import AppCard, { VARIANTS } from '@/components/AppCard.vue'

describe('AppCard — superficie, no estructura', () => {
  it('el default es surface', () => {
    expect(AppCard.props.variant.default).toBe('surface')
    expect(mount(AppCard).classes()).toContain('app-card--surface')
  })

  it.each(VARIANTS)('la variante %s aplica su clase', (variant) => {
    expect(mount(AppCard, { props: { variant } }).classes()).toContain(`app-card--${variant}`)
  })

  it('no expone prop de padding: el padding es composición', () => {
    // Varía según el uso, así que lo compone quien la usa con q-pa-*.
    expect(AppCard.props).not.toHaveProperty('padding')
  })

  it('pasa flat al QCard para matar la elevación default de Quasar', () => {
    expect(mount(AppCard).findComponent(QCard).props('flat')).toBe(true)
  })

  it('renderiza el slot por defecto', () => {
    expect(mount(AppCard, { slots: { default: 'Contenido' } }).text()).toContain('Contenido')
  })

  it('rechaza una variante inventada', () => {
    const { validator } = AppCard.props.variant
    expect(validator('elevated')).toBe(false)
    expect(validator('highlight')).toBe(true)
  })
})
