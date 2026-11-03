import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppBrandMark, { MONOGRAM } from '@/components/AppBrandMark.vue'

describe('AppBrandMark — el monograma', () => {
  it('vive en una sola constante', () => {
    // Si mañana son otras letras, se cambia en un lugar y cambia en el drawer
    // y en el acceso a la vez. Este test existe para que nadie lo escriba a
    // mano en una pantalla y lo deje desincronizado en la otra.
    expect(MONOGRAM).toBe('NX')
    expect(mount(AppBrandMark).text()).toBe(MONOGRAM)
  })
})

describe('AppBrandMark — tamaño', () => {
  it('publica el tamaño como custom property, no como ancho suelto', () => {
    // Todo lo de adentro (radio, tipografía, resplandor) se deriva de este
    // número. Si se pasara sólo como width, el radio y la letra quedarían
    // fijos y el mark se vería mal a cualquier tamaño que no sea el default.
    const wrapper = mount(AppBrandMark, { props: { size: 42 } })
    expect(wrapper.attributes('style')).toContain('--mark-size: 42px')
  })

  it('el default es el del drawer', () => {
    expect(AppBrandMark.props.size.default).toBe(32)
  })
})

describe('AppBrandMark — resplandor', () => {
  it('es opt-in', () => {
    // Sobre superficie clara sobra; sólo hace falta sobre oscuro o fotografía.
    expect(AppBrandMark.props.glow.default).toBe(false)
    expect(mount(AppBrandMark).classes()).not.toContain('brand-mark--glow')
  })

  it('se activa con la prop', () => {
    expect(mount(AppBrandMark, { props: { glow: true } }).classes())
      .toContain('brand-mark--glow')
  })
})

describe('AppBrandMark — accesibilidad', () => {
  it('sin label es decorativo', () => {
    // El caso NORMAL: en el drawer y en el acceso el mark va pegado al nombre
    // escrito en texto. Sin aria-hidden, el lector de pantalla anuncia la
    // marca dos veces seguidas.
    const wrapper = mount(AppBrandMark)
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.attributes('role')).toBeUndefined()
  })

  it('con label se anuncia como imagen', () => {
    // Para cuando el mark va solo, sin el nombre al lado.
    const wrapper = mount(AppBrandMark, { props: { label: 'MC For Kids' } })
    expect(wrapper.attributes('role')).toBe('img')
    expect(wrapper.attributes('aria-label')).toBe('MC For Kids')
    expect(wrapper.attributes('aria-hidden')).toBeUndefined()
  })

  it('las letras nunca se deletrean', () => {
    // "M C" leído letra por letra no es el nombre de nada. El nombre lo da el
    // texto de al lado o el aria-label; el span es pintura.
    expect(mount(AppBrandMark, { props: { label: 'MC For Kids' } })
      .find('.brand-mark__letters').attributes('aria-hidden')).toBe('true')
  })
})
