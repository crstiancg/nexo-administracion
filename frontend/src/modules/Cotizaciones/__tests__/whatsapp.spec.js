import { describe, it, expect } from 'vitest'
import { enlaceWhatsapp, mensajeWhatsapp, numeroWhatsapp } from '@/modules/Cotizaciones/constantes'

const COTIZACION = {
  codigo: 'COT-000010',
  total: '1250.00',
  valida_hasta: '2026-11-12',
  token: 'a'.repeat(32),
  cliente: { nombre: 'Ana Quispe', telefono: '987 654 321' }
}

describe('WhatsApp de la cotización', () => {
  it('pasa el celular a formato internacional (y toma el primero si hay varios)', () => {
    expect(numeroWhatsapp('987 654 321')).toBe('51987654321')
    expect(numeroWhatsapp('+51 987-654-321')).toBe('51987654321')
    expect(numeroWhatsapp('900 551 900 - 900 180 000')).toBe('51900551900')
    expect(numeroWhatsapp('(051) 36 1234')).toBeNull()
    expect(numeroWhatsapp(null)).toBeNull()
  })

  it('arma el mensaje con el enlace público de la proforma', () => {
    const mensaje = mensajeWhatsapp(COTIZACION)

    expect(mensaje).toContain('Hola Ana')
    expect(mensaje).toContain('COT-000010')
    expect(mensaje).toContain('12/11/2026')
    expect(mensaje).toContain(`/proforma/${'a'.repeat(32)}`)
  })

  it('sin celular del cliente deja que WhatsApp elija el contacto', () => {
    expect(enlaceWhatsapp(COTIZACION)).toMatch(/^https:\/\/wa\.me\/51987654321\?text=/)
    expect(enlaceWhatsapp({ ...COTIZACION, cliente: null })).toMatch(/^https:\/\/wa\.me\/\?text=Hola%20estimado%20cliente/)
  })
})
