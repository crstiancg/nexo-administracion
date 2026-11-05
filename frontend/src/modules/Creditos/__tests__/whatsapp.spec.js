import { describe, it, expect, beforeEach } from 'vitest'
import { TIENDA } from '@/config/tienda'
import { enlaceWhatsapp, mensajeWhatsapp } from '@/modules/Creditos/constantes'

const credito = (estado) => ({
  numero_cuotas: 4,
  saldo: '240.00',
  proxima: { numero: 2, falta: '80.00', vence: '2026-11-12', estado },
  cliente: { nombre: 'Ana Quispe', telefono: '987654321' }
})

describe('Recordatorio de cuotas por WhatsApp', () => {
  beforeEach(() => { TIENDA.whatsapp = {} })

  it('usa el mensaje de "por vencer" o el de "vencida" según la cuota', () => {
    expect(mensajeWhatsapp(credito('pendiente'))).toContain('vence el 12/11/2026')
    expect(mensajeWhatsapp(credito('vencida'))).toContain('venció el 12/11/2026')
    expect(mensajeWhatsapp(credito('pendiente'))).toContain('cuota 2 de 4')
  })

  it('usa el mensaje cambiado en Configuración', () => {
    TIENDA.whatsapp = { cuota_vencida: 'Ojo {cliente}: debes {monto}' }

    // formatearPrecio separa "S/" con un espacio duro.
    expect(mensajeWhatsapp(credito('vencida')).replace(/\s/g, ' ')).toBe('Ojo Ana: debes S/ 80.00')
  })

  it('sin celular no hay enlace', () => {
    expect(enlaceWhatsapp(credito('pendiente'))).toMatch(/^https:\/\/wa\.me\/51987654321\?text=/)
    expect(enlaceWhatsapp({ ...credito('pendiente'), cliente: { nombre: 'Ana' } })).toBeNull()
  })
})
