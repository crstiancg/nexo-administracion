import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import LoginPage from '@/pages/login.vue'
import { AuthError } from '@/stores/user-store'

// La página no debe saber qué hay del otro lado del store: acá se cambia la
// acción `login` y se comprueba que reacciona a lo que devuelve.
const login = vi.fn()
vi.mock('@/stores/user-store', async (importOriginal) => {
  const real = await importOriginal()
  return { ...real, useUserStore: () => ({ login: (...args) => login(...args) }) }
})

const replace = vi.fn()
const route = { query: {} }
vi.mock('vue-router', () => ({ useRouter: () => ({ replace }), useRoute: () => route }))

function montar () {
  return mount(LoginPage)
}

async function completar (wrapper, usuario, clave) {
  const [campoUsuario, campoClave] = wrapper.findAll('.app-field input')
  await campoUsuario.setValue(usuario)
  await campoClave.setValue(clave)
}

beforeEach(() => {
  login.mockReset()
  replace.mockReset()
  route.query = {}
})

describe('login — validación antes de salir a la red', () => {
  it('con los campos vacíos no llama al servicio y señala los dos', async () => {
    const wrapper = montar()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Ingresá tu usuario.')
    expect(wrapper.text()).toContain('Ingresá tu contraseña.')
  })

  it('un usuario de puros espacios sigue estando vacío', async () => {
    const wrapper = montar()
    await completar(wrapper, '   ', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Ingresá tu usuario.')
  })

  it('completo, envía las credenciales tal cual', async () => {
    login.mockResolvedValue({ username: 'admin' })
    const wrapper = montar()
    await completar(wrapper, 'admin', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(login).toHaveBeenCalledWith({ username: 'admin', password: 'forkids', remember: false })
  })
})

describe('login — respuesta del servicio', () => {
  it('al entrar reemplaza la ruta en vez de apilarla', async () => {
    // replace y no push: con push, el botón "atrás" del navegador vuelve al
    // formulario de acceso de alguien que ya entró.
    login.mockResolvedValue({ username: 'admin' })
    const wrapper = montar()
    await completar(wrapper, 'admin', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/')
  })

  it('si el guard la mandó al login, al entrar vuelve a donde iba', async () => {
    route.query = { redirectTo: '/pedidos' }
    login.mockResolvedValue()
    const wrapper = montar()
    await completar(wrapper, 'admin', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/pedidos')
  })

  it('no redirige fuera de la app aunque redirectTo lo pida', async () => {
    // "//evil.com" es una URL absoluta sin protocolo: sin este control el
    // login serviría de trampolín para phishing.
    route.query = { redirectTo: '//evil.com' }
    login.mockResolvedValue()
    const wrapper = montar()
    await completar(wrapper, 'admin', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/')
  })

  it('muestra el mensaje del AuthError y no navega', async () => {
    login.mockRejectedValue(new AuthError('Usuario o contraseña incorrectos.'))
    const wrapper = montar()
    await completar(wrapper, 'admin', 'mala')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(replace).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('Usuario o contraseña incorrectos.')
  })

  it('un fallo que NO es de credenciales no muestra su mensaje crudo', async () => {
    // El `message` de una excepción de red o de un bug nuestro no es algo que
    // el usuario pueda accionar — y puede filtrar detalles del sistema.
    login.mockRejectedValue(new TypeError('fetch failed: ECONNREFUSED 127.0.0.1:8080'))
    const wrapper = montar()
    await completar(wrapper, 'admin', 'forkids')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const aviso = wrapper.find('[role="alert"]').text()
    expect(aviso).toContain('No pudimos conectarnos.')
    expect(aviso).not.toContain('ECONNREFUSED')
  })

  it('tras fallar, limpia la contraseña pero conserva el usuario', async () => {
    login.mockRejectedValue(new AuthError('Usuario o contraseña incorrectos.'))
    const wrapper = montar()
    await completar(wrapper, 'admin', 'mala')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const campos = wrapper.findAll('.app-field input')
    expect(campos[0].element.value).toBe('admin')
    expect(campos[1].element.value).toBe('')
  })

  it('el error anterior se borra al reintentar', async () => {
    login.mockRejectedValueOnce(new AuthError('Usuario o contraseña incorrectos.'))
    login.mockResolvedValueOnce({ username: 'admin' })
    const wrapper = montar()
    await completar(wrapper, 'admin', 'mala')

    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)

    await completar(wrapper, 'admin', 'forkids')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})

describe('login — la regla de los dos rojos', () => {
  it('el aviso de error NO va relleno de marca', async () => {
    // Marca = siempre relleno. Error = nunca relleno: tinte + contorno.
    login.mockRejectedValue(new AuthError('Usuario o contraseña incorrectos.'))
    const wrapper = montar()
    await completar(wrapper, 'admin', 'mala')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const aviso = wrapper.find('[role="alert"]')
    expect(aviso.classes()).toContain('auth__error')
    expect(aviso.classes().join(' ')).not.toContain('app-btn--primary')
    expect(aviso.find('.q-icon').exists()).toBe(true)
  })

  it('el único botón relleno de la pantalla es Ingresar', () => {
    const wrapper = montar()
    expect(wrapper.findAll('.app-btn--primary')).toHaveLength(1)
  })
})

describe('login — recuperación de contraseña', () => {
  it('no ofrece un enlace a una ruta que no existe', () => {
    // Hasta que haya flujo de recuperación, la respuesta honesta es decir a
    // quién pedirla, no mandar a un 404.
    const wrapper = montar()
    expect(wrapper.find('.auth__link').element.tagName).toBe('BUTTON')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })

  it('despliega a quién pedirla', async () => {
    const wrapper = montar()
    expect(wrapper.find('.auth__hint').exists()).toBe(false)

    await wrapper.find('.auth__link').trigger('click')
    expect(wrapper.find('.auth__hint').text()).toContain('administrador')
  })
})
