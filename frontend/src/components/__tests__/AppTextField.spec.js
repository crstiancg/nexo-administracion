import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QInput, QBtn } from 'quasar'
import AppTextField, { SIZES } from '@/components/AppTextField.vue'

/**
 * Bloq Mayús no se consulta con una propiedad del teclado sino preguntándole
 * a un evento de teclado cualquiera.
 *
 * Va con dispatchEvent y defineProperty en lugar de trigger('keyup', {...}):
 * KeyboardEvent YA trae getModifierState en su prototipo, así que pasarlo
 * como opción no lo pisa y el componente termina leyendo el del navegador,
 * que siempre dice false.
 */
async function teclear (wrapper, { capsLock, evento = 'keyup', soportado = true } = {}) {
  // Un Event pelado no tiene getModifierState ni en el prototipo: es la única
  // forma de ejercitar de verdad el guard del componente.
  const event = soportado
    ? new KeyboardEvent(evento, { bubbles: true })
    : new Event(evento, { bubbles: true })

  if (soportado) {
    Object.defineProperty(event, 'getModifierState', {
      value: (tecla) => tecla === 'CapsLock' && capsLock
    })
  }

  wrapper.find('input').element.dispatchEvent(event)
  await wrapper.vm.$nextTick()
}

describe('AppTextField — la etiqueta', () => {
  it('apunta al input nativo que dibuja Quasar adentro', () => {
    // Sin esto el campo se queda sin nombre accesible: la etiqueta está fuera
    // del control, así que el `for` es lo ÚNICO que las une.
    //
    // El selector es .app-field__label y no 'label' porque QField envuelve su
    // propio control en otro <label>: buscando a ciegas se agarra el de
    // Quasar y el test pasaría sin comprobar el nuestro.
    const wrapper = mount(AppTextField, { props: { label: 'Usuario' } })

    const forAttr = wrapper.find('.app-field__label').attributes('for')
    expect(forAttr).toBeTruthy()
    expect(wrapper.find('input').attributes('id')).toBe(forAttr)
  })

  it('da un id distinto a cada campo de la misma pantalla', () => {
    // Dos campos con el mismo id harían que las dos etiquetas apunten al
    // primero, y el segundo quedaría sin nombre.
    //
    // Los monta un padre y no dos mount() sueltos a propósito: el contador de
    // useId() es por aplicación, así que dos mount() arrancan dos apps y los
    // dos ids serían 'v-0' sin que eso signifique nada. Lo que importa es que
    // sean distintos DENTRO de una misma pantalla, que es el caso real.
    //
    // Y el selector es .app-field__label porque QField envuelve su control en
    // otro <label>: con 'label' a secas se comparan los DOS del mismo campo,
    // que por supuesto comparten id.
    const wrapper = mount({
      components: { AppTextField },
      template: `
        <div>
          <AppTextField label="Usuario" />
          <AppTextField label="Contraseña" type="password" />
        </div>
      `
    })

    const [uno, dos] = wrapper.findAll('.app-field__label').map((l) => l.attributes('for'))
    expect(uno).toBeTruthy()
    expect(uno).not.toBe(dos)
  })

  it('NO delega la etiqueta al `label` flotante de Quasar', () => {
    // La flotante se encoge dentro del control al escribir; acá el nombre del
    // campo tiene que seguir legible mientras se tipea.
    const wrapper = mount(AppTextField, { props: { label: 'Usuario' } })
    expect(wrapper.findComponent(QInput).props('label')).toBeUndefined()
  })
})

describe('AppTextField — contraseña', () => {
  it('arranca oculta', () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })
    expect(wrapper.find('input').attributes('type')).toBe('password')
  })

  it('el ojito la revela y la vuelve a ocultar', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await wrapper.findComponent(QBtn).trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('text')

    await wrapper.findComponent(QBtn).trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('password')
  })

  it('el ojito tiene nombre accesible y estado', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })
    const boton = wrapper.findComponent(QBtn)

    expect(boton.attributes('aria-label')).toBe('Mostrar contraseña')
    expect(boton.attributes('aria-pressed')).toBe('false')

    await boton.trigger('click')
    expect(boton.attributes('aria-label')).toBe('Ocultar contraseña')
    expect(boton.attributes('aria-pressed')).toBe('true')
  })

  it('el ojito queda FUERA del recorrido del tabulador', () => {
    // El tab va del usuario a la contraseña y de ahí a Ingresar. El ojito es
    // ayuda visual, no un paso del formulario.
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })
    expect(wrapper.findComponent(QBtn).attributes('tabindex')).toBe('-1')
  })

  it('un campo que no es password no trae ojito', () => {
    const wrapper = mount(AppTextField, { props: { label: 'Usuario' } })
    expect(wrapper.findComponent(QBtn).exists()).toBe(false)
  })
})

describe('AppTextField — Bloq Mayús', () => {
  it('avisa cuando está activado al tipear la contraseña', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true })
    expect(wrapper.find('.app-field__caps').text()).toContain('Bloq Mayús')
  })

  it('también lo detecta antes de soltar la tecla', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true, evento: 'keydown' })
    expect(wrapper.find('.app-field__caps').exists()).toBe(true)
  })

  it('el aviso se va cuando se apaga', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true })
    await teclear(wrapper, { capsLock: false })

    expect(wrapper.find('.app-field__caps').exists()).toBe(false)
  })

  it('el aviso se va al salir del campo', async () => {
    // focusout y no blur: blur no burbujea, así que colgado del contenedor
    // nunca llegaría.
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true })
    await wrapper.find('input').trigger('focusout')

    expect(wrapper.find('.app-field__caps').exists()).toBe(false)
  })

  it('no avisa en un campo que no es contraseña', async () => {
    // En un campo visible el usuario ya ve que está escribiendo en mayúsculas.
    const wrapper = mount(AppTextField, { props: { label: 'Usuario' } })

    await teclear(wrapper, { capsLock: true })
    expect(wrapper.find('.app-field__caps').exists()).toBe(false)
  })

  it('el error le gana al aviso', async () => {
    // Dos mensajes bajo el mismo campo compiten; el que dice que algo FALLÓ
    // manda sobre el que dice que algo podría fallar.
    const wrapper = mount(AppTextField, {
      props: { label: 'Contraseña', type: 'password', error: 'Ingresá tu contraseña.' }
    })

    await teclear(wrapper, { capsLock: true })
    expect(wrapper.find('.app-field__caps').exists()).toBe(false)
    expect(wrapper.text()).toContain('Ingresá tu contraseña.')
  })

  it('es un aviso, no una alerta', async () => {
    // role="alert" interrumpe el dictado del lector de pantalla. Para algo
    // que todavía no falló, eso es peor que el problema.
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true })
    expect(wrapper.find('.app-field__caps').attributes('role')).toBe('status')
  })

  it('no explota con un evento sin getModifierState', async () => {
    // Los eventos sintéticos de algunos navegadores llegan sin él.
    const wrapper = mount(AppTextField, { props: { label: 'Contraseña', type: 'password' } })

    await teclear(wrapper, { capsLock: true, soportado: false })
    expect(wrapper.find('.app-field__caps').exists()).toBe(false)
  })
})

describe('AppTextField — tamaño', () => {
  it('el default es md, así el alto grande es opt-in', () => {
    // El campo del sistema mide 40px; el de 48 es para la pantalla donde el
    // formulario ES la pantalla.
    expect(AppTextField.props.size.default).toBe('md')
    expect(mount(AppTextField, { props: { label: 'Usuario' } }).find('.app-field__control').classes())
      .toContain('app-field__control--md')
  })

  it('rechaza un tamaño que no está en la lista', () => {
    const { validator } = AppTextField.props.size
    expect(SIZES).toEqual(['md', 'lg'])
    expect(validator('xl')).toBe(false)
    expect(validator('lg')).toBe(true)
  })
})

describe('AppTextField — error', () => {
  it('el error es un mensaje, no un booleano', () => {
    // Un campo en rojo sin decir qué pasa obliga a adivinar.
    expect(AppTextField.props.error.type).toBe(String)
  })

  it('vacío significa sin error', () => {
    const wrapper = mount(AppTextField, { props: { label: 'Usuario', error: '' } })
    expect(wrapper.findComponent(QInput).props('error')).toBe(false)
  })

  it('con mensaje marca el estado y lo muestra', () => {
    const wrapper = mount(AppTextField, {
      props: { label: 'Usuario', error: 'Ingresá tu usuario.' }
    })

    const input = wrapper.findComponent(QInput)
    expect(input.props('error')).toBe(true)
    expect(input.props('errorMessage')).toBe('Ingresá tu usuario.')
    expect(wrapper.text()).toContain('Ingresá tu usuario.')
  })

  it('desactiva el ícono de error de Quasar', () => {
    // Quasar lo inyecta en el slot `append`, justo donde va el ojito.
    expect(mount(AppTextField, { props: { label: 'Usuario' } })
      .findComponent(QInput).props('noErrorIcon')).toBe(true)
  })
})

describe('AppTextField — integración', () => {
  it('los atributos sueltos llegan al input nativo, no al div de afuera', () => {
    // Contrato con inheritAttrs:false. Si se pierde, el gestor de contraseñas
    // del navegador deja de reconocer el campo y nadie lo nota.
    const wrapper = mount(AppTextField, {
      props: { label: 'Usuario' },
      attrs: { autocomplete: 'username' }
    })

    expect(wrapper.find('input').attributes('autocomplete')).toBe('username')
    expect(wrapper.attributes('autocomplete')).toBeUndefined()
  })

  it('usa el campo del sistema: dense + outlined', () => {
    const input = mount(AppTextField, { props: { label: 'Usuario' } }).findComponent(QInput)
    expect(input.props('dense')).toBe(true)
    expect(input.props('outlined')).toBe(true)
  })

  it('emite el valor tipeado por v-model', async () => {
    const wrapper = mount(AppTextField, { props: { label: 'Usuario', modelValue: '' } })

    await wrapper.find('input').setValue('cristian')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['cristian'])
  })

  it('el slot `aside` va al lado de la etiqueta', () => {
    const wrapper = mount(AppTextField, {
      props: { label: 'Contraseña' },
      slots: { aside: '<a href="#">¿Olvidaste tu contraseña?</a>' }
    })

    expect(wrapper.find('.app-field__top').text()).toContain('¿Olvidaste tu contraseña?')
  })
})
