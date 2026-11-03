import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QBtn } from 'quasar'
import AppButton, { VARIANTS } from '@/components/AppButton.vue'

describe('AppButton — la regla de los dos rojos', () => {
  it('NO existe una variante destructiva rellena', () => {
    // El corazón del sistema: el rojo de marca va relleno, el de error nunca.
    // Si alguien agrega 'destructive-filled' o similar, este test lo frena.
    expect(VARIANTS).toEqual(['primary', 'secondary', 'tertiary', 'destructive'])
    expect(VARIANTS.filter((v) => v.includes('destructive'))).toEqual(['destructive'])
  })

  it('rechaza una variante que no está en la lista', () => {
    const { validator } = AppButton.props.variant
    expect(validator('destructive-filled')).toBe(false)
    expect(validator('danger')).toBe(false)
    expect(validator('primary')).toBe(true)
  })
})

describe('AppButton — jerarquía', () => {
  it('el default es secondary, así el primario es opt-in', () => {
    // Que primary no sea el default es lo que evita dos CTAs rellenos por vista.
    expect(AppButton.props.variant.default).toBe('secondary')
    expect(mount(AppButton).classes()).toContain('app-btn--secondary')
  })

  it.each(VARIANTS)('la variante %s aplica su clase', (variant) => {
    const wrapper = mount(AppButton, { props: { variant } })
    expect(wrapper.classes()).toContain(`app-btn--${variant}`)
  })
})

describe('AppButton — integración con Quasar', () => {
  it('pasa no-caps: Quasar mayusculiza los labels por default', () => {
    expect(mount(AppButton).findComponent(QBtn).props('noCaps')).toBe(true)
  })

  it('NO delega el color a Quasar', () => {
    // Las clases bg-* de Quasar aplican con !important; delegarle el primario
    // obligaba a pisarlo desde app.scss. Las variantes se pintan en el componente.
    expect(mount(AppButton, { props: { variant: 'primary' } }).findComponent(QBtn).props('color')).toBeUndefined()
  })

  it('reenvía el slot por defecto', () => {
    const wrapper = mount(AppButton, { slots: { default: 'Guardar' } })
    expect(wrapper.text()).toContain('Guardar')
  })

  it('reenvía atributos sueltos al QBtn, como label', () => {
    const wrapper = mount(AppButton, { attrs: { label: 'Nuevo pedido' } })
    expect(wrapper.findComponent(QBtn).props('label')).toBe('Nuevo pedido')
  })

  it('con disable emite la clase `disabled`, de la que cuelga nuestro CSS', () => {
    // Contrato con Quasar, no con nuestro código: el estilo de deshabilitado
    // apunta a .app-btn.disabled. Si un upgrade de Quasar renombra esa clase,
    // el botón deshabilitado se pintaría como habilitado y nadie lo notaría.
    expect(mount(AppButton, { attrs: { disable: true } }).classes()).toContain('disabled')
  })
})
