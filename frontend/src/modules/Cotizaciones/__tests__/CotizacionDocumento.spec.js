import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CotizacionDocumento from '@/modules/Cotizaciones/CotizacionDocumento.vue'

const VARIANTE = {
  sku: 'SSD-480',
  presentacion: 'Unidad',
  producto: { nombre: 'Disco SSD 480 GB' },
  unidad: { abreviatura: 'und' }
}

function cotizacion (cambios = {}) {
  return {
    codigo: 'COT-000001',
    tipo: 'normal',
    mostrar_precios: true,
    fecha: '2026-11-04T10:00:00-05:00',
    valida_hasta: '2026-11-11',
    subtotal: '1100.00',
    descuento: '0.00',
    total: '1100.00',
    total_letras: 'SON MIL CIEN CON 00/100 SOLES',
    garantia: '1 año',
    items: [
      { id: 1, componente: 'Procesador', variante: null, descripcion: 'Intel Core i5-14400', unidad: 'und', cantidad: 1, precio_unitario: '950.00', subtotal: '950.00', archivos: [] },
      { id: 2, componente: 'Disco sólido SSD', variante: VARIANTE, descripcion: null, cantidad: 1, precio_unitario: '150.00', subtotal: '150.00', archivos: [] }
    ],
    ...cambios
  }
}

const montar = (cambios) => mount(CotizacionDocumento, { props: { cotizacion: cotizacion(cambios) } })

describe('CotizacionDocumento', () => {
  it('PC armada: cada componente en su casillero, con la franja de garantía', () => {
    const wrapper = montar({ tipo: 'pc' })

    const componentes = wrapper.findAll('tbody .cot-doc__componente').map((td) => td.text())
    expect(componentes).toEqual(['Procesador', 'Disco sólido SSD'])
    expect(wrapper.text()).toContain('Intel Core i5-14400')
    expect(wrapper.find('.cot-doc__franja').text()).toContain('Garantía 1 año')
  })

  it('PC armada sin precios: sólo el total', () => {
    const wrapper = montar({ tipo: 'pc', mostrar_precios: false })

    expect(wrapper.find('.cot-doc__tabla').text()).not.toContain('950')
    expect(wrapper.find('.cot-doc__totales').text()).toContain('1,100.00')
  })

  it('PC para varios equipos: precio por equipo, cuántos y el total de todos', () => {
    const wrapper = montar({ tipo: 'pc', equipos: 10, subtotal: '11000.00', total: '11000.00' })

    const totales = wrapper.find('.cot-doc__totales').text()
    expect(totales).toContain('Precio por equipo')
    expect(totales).toContain('1,100.00')
    expect(totales).toContain('× 10')
    expect(totales).toContain('11,000.00')
    expect(wrapper.find('.cot-doc__franja').text()).toContain('10 equipos')
  })

  it('normal: sin casilleros ni franja, con precios', () => {
    const wrapper = montar()

    expect(wrapper.find('.cot-doc__componente').exists()).toBe(false)
    expect(wrapper.find('.cot-doc__franja').exists()).toBe(false)
    expect(wrapper.find('.cot-doc__tabla').text()).toContain('950')
  })
})
