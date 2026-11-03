import { describe, it, expect } from 'vitest'
import { TIENDA } from '@/config/tienda'
import { formatearPrecio } from '@/utils/moneda'
import { enlaceSeguimiento, enlaceWhatsapp, mensajeWhatsapp, rellenarPlantilla } from '@/modules/OrdenesServicio/constantes'
import { checklistPara } from '@/modules/OrdenesServicio/FormOrden'

const ORDEN = {
  codigo: 'OS-000012',
  estado: 'reparado',
  equipo: 'Laptop Lenovo IdeaPad 3',
  token: 'a'.repeat(32),
  saldo: '180.00',
  cliente: { nombre: 'Ana Quispe Mamani', telefono: '987 654 321' }
}

describe('WhatsApp de órdenes de servicio', () => {
  it('un celular peruano de 9 dígitos lleva el 51 adelante', () => {
    expect(enlaceWhatsapp(ORDEN)).toMatch(/^https:\/\/wa\.me\/51987654321\?text=/)
  })

  it('respeta un número que ya viene con código de país', () => {
    expect(enlaceWhatsapp({ ...ORDEN, cliente: { ...ORDEN.cliente, telefono: '+593 98 394 9624' } }))
      .toMatch(/^https:\/\/wa\.me\/593983949624\?/)
  })

  it('sin teléfono no hay enlace', () => {
    expect(enlaceWhatsapp({ ...ORDEN, cliente: { nombre: 'Ana' } })).toBeNull()
  })

  it('el mensaje saluda por el nombre, dice el estado y lleva el enlace de seguimiento', () => {
    const mensaje = mensajeWhatsapp(ORDEN)
    expect(mensaje).toMatch(/^Hola Ana,/)
    expect(mensaje).toContain('OS-000012')
    expect(mensaje).toContain('listo para recoger')
    expect(mensaje).toContain('Saldo pendiente')
    expect(mensaje).toContain(enlaceSeguimiento(ORDEN.token))
  })

  it('sin saldo no menciona saldo pendiente', () => {
    expect(mensajeWhatsapp({ ...ORDEN, saldo: '0.00' })).not.toContain('Saldo pendiente')
  })
})

describe('plantillas de WhatsApp configurables', () => {
  it('usa la plantilla configurada para ese estado', () => {
    TIENDA.whatsapp = { reparado: '{cliente}: tu {equipo} está listo. Debes {saldo}. {enlace}' }
    try {
      expect(mensajeWhatsapp(ORDEN)).toBe(`Ana: tu Laptop Lenovo IdeaPad 3 está listo. Debes ${formatearPrecio(180)}. ${enlaceSeguimiento(ORDEN.token)}`)
    } finally {
      TIENDA.whatsapp = {}
    }
  })

  it('una variable desconocida queda como está', () => {
    expect(rellenarPlantilla('Hola {cliente} {otra}', { cliente: 'Ana' })).toBe('Hola Ana {otra}')
  })
})

describe('checklistPara', () => {
  it('arma la lista del tipo, todo sin marcar', () => {
    expect(checklistPara(['Enciende', 'Cargador'])).toEqual({ Enciende: false, Cargador: false })
  })

  it('al cambiar de tipo conserva lo marcado en los puntos que se repiten', () => {
    expect(checklistPara(['Enciende', 'Táctil responde'], { Enciende: true, Teclado: true }))
      .toEqual({ Enciende: true, 'Táctil responde': false })
  })

  it('sin tipo, lista vacía', () => {
    expect(checklistPara(null)).toEqual({})
  })
})
