import { describe, expect, it } from 'vitest'
import { precioSugerido, tramoDe } from '../precios'

const TABLA = [
  { hasta: 50, porcentaje: 40 },
  { hasta: 200, porcentaje: 30 },
  { hasta: null, porcentaje: 20 }
]

describe('tramoDe', () => {
  it('toma el primer tramo cuyo tope alcanza al costo (tope incluido)', () => {
    expect(tramoDe(10, TABLA).porcentaje).toBe(40)
    expect(tramoDe(50, TABLA).porcentaje).toBe(40)
    expect(tramoDe(50.01, TABLA).porcentaje).toBe(30)
    expect(tramoDe('200', TABLA).porcentaje).toBe(30)
  })

  it('el último sin tope toma todo lo demás', () => {
    expect(tramoDe(5000, TABLA).porcentaje).toBe(20)
  })

  it('sin tabla, sin costo o pasado el último tope no hay tramo', () => {
    expect(tramoDe(80, [])).toBeNull()
    expect(tramoDe('', TABLA)).toBeNull()
    expect(tramoDe(0, TABLA)).toBeNull()
    expect(tramoDe(500, [{ hasta: 100, porcentaje: 30 }])).toBeNull()
  })
})

describe('precioSugerido', () => {
  it('suma el porcentaje del tramo al costo', () => {
    expect(precioSugerido(80, TABLA)).toEqual({ precio: '104.00', porcentaje: 30 })
    expect(precioSugerido('25.50', TABLA)).toEqual({ precio: '35.70', porcentaje: 40 })
  })

  it('redondea a céntimos', () => {
    expect(precioSugerido(33.33, [{ hasta: null, porcentaje: 15 }]).precio).toBe('38.33')
  })

  it('sin tramo no sugiere nada', () => {
    expect(precioSugerido(80, [])).toBeNull()
  })
})
