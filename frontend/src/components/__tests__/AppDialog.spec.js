import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AppDialog from '@/components/AppDialog.vue'

let wrapper

// QDialog telepora su contenido a un nodo propio en <body> — no es
// descendiente del $el del wrapper aunque se monte con attachTo: body, así
// que hay que buscarlo en el documento, no en el wrapper. Y sólo lo mete en
// el DOM una vez terminada la transición de apertura: sin esperarla, un
// find() de acá adentro llega antes de que exista.
async function mountDialog (props = {}, slots = {}) {
  wrapper = mount(AppDialog, {
    props: { title: 'Nuevo pedido', modelValue: true, ...props },
    slots,
    attachTo: document.body
  })
  await new Promise((resolve) => setTimeout(resolve, 300))
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
})

describe('AppDialog — mismo lenguaje visual en todos lados', () => {
  it('renderiza el título', async () => {
    await mountDialog({ title: 'Editar pedido' })
    expect(document.querySelector('.app-dialog__title').textContent).toBe('Editar pedido')
  })

  it('renderiza el contenido del slot default', async () => {
    await mountDialog({}, { default: '<div class="fake-form">Formulario</div>' })
    expect(document.querySelector('.fake-form')).not.toBeNull()
  })

  it('sin slot actions no renderiza el footer', async () => {
    await mountDialog()
    expect(document.querySelector('.app-dialog__actions')).toBeNull()
  })

  it('con slot actions lo renderiza', async () => {
    await mountDialog({}, { actions: '<button>Guardar</button>' })
    expect(document.querySelector('.app-dialog__actions').textContent).toBe('Guardar')
  })

  it('la X emite update:modelValue en false', async () => {
    await mountDialog()

    document.querySelector('[aria-label="Cerrar"]').dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
  })
})

describe('AppDialog — tamaños', () => {
  it('por default es el tamaño md', async () => {
    await mountDialog()
    expect(document.querySelector('.app-dialog').classList).toContain('app-dialog--md')
  })

  it('size="lg" es para formularios con varias secciones', async () => {
    await mountDialog({ size: 'lg' })
    expect(document.querySelector('.app-dialog').classList).toContain('app-dialog--lg')
  })
})
