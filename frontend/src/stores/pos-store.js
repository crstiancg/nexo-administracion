import { precioPara } from '@/utils/precios'
import { defineStore } from 'pinia'

/**
 * Estado del punto de venta: el carrito en curso, sus pagos y las ventas en
 * espera. Se guarda en el navegador (una recarga o una pestaña cerrada por
 * error no pierde la venta). Es sólo un borrador: nada de esto es real hasta
 * que el backend registra la venta.
 */
const CLAVE = 'pos.borrador.v1'
const MAX_ESPERA = 5

let ultimoUid = 0
function nuevoPago (metodo = 'efectivo', monto = '0.00') {
  return { uid: ++ultimoUid, metodo, monto, recibido: '', referencia: '', montoManual: false }
}

function redondear (n) {
  return Math.round(n * 100) / 100
}

// Cantidades: hasta 3 decimales (kg, m), sin ruido de flotantes.
function redondearCantidad (n) {
  return Math.round(n * 1000) / 1000
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && valor !== null && Number.isFinite(n) ? n : 0
}

/**
 * Una línea del carrito a partir de una variante del catálogo (con su
 * producto) o de lo que devuelve el escáner (VarianteStockResource).
 */
export function lineaDesdeCatalogo (producto, variante) {
  return {
    variante_id: variante.id,
    producto_id: producto.id,
    sku: variante.sku,
    codigo_barras: variante.codigo_barras ?? null,
    nombre: producto.nombre,
    presentacion: variante.presentacion ?? '',
    unidad: variante.unidad?.abreviatura ?? '',
    fraccionable: Boolean(variante.unidad?.fraccionable),
    color: variante.color ?? null,
    stock: variante.stock,
    // El de hoy (con oferta) y el de lista, para mostrar el ahorro. El precio
    // que se cobra lo fija el carrito según el cliente (por mayor o normal).
    precio_hoy: Number(variante.precio ?? producto.precio).toFixed(2),
    precio_mayor: variante.precio_mayor ?? null,
    precio_unitario: Number(variante.precio ?? producto.precio).toFixed(2),
    precio_lista: Number(variante.precio_lista ?? variante.precio ?? producto.precio).toFixed(2),
    miniatura_url: variante.miniatura_url ?? producto.miniatura_url ?? null
  }
}

export function lineaDesdeEscaner (variante) {
  return {
    variante_id: variante.id,
    producto_id: variante.producto?.id,
    sku: variante.sku,
    codigo_barras: variante.codigo_barras ?? null,
    nombre: variante.producto?.nombre ?? variante.sku,
    presentacion: variante.presentacion ?? '',
    unidad: variante.unidad?.abreviatura ?? '',
    fraccionable: Boolean(variante.unidad?.fraccionable),
    color: variante.color ?? null,
    stock: variante.stock,
    precio_hoy: Number(variante.precio ?? 0).toFixed(2),
    precio_mayor: variante.precio_mayor ?? null,
    precio_unitario: Number(variante.precio ?? 0).toFixed(2),
    precio_lista: Number(variante.precio_lista ?? variante.precio ?? 0).toFixed(2),
    miniatura_url: variante.miniatura_url ?? null
  }
}

export const usePosStore = defineStore('pos', {
  state: () => ({
    items: [],
    cliente: null,
    descuento: '',
    // ticket (sin comprobante SUNAT) | boleta | factura. Se recuerda entre
    // ventas; la factura no (depende del cliente): vuelve a ticket.
    comprobante: 'ticket',
    pagos: [nuevoPago()],
    // variante_id de la línea activa (para + / − / Supr desde el teclado).
    seleccionado: null,
    // El vendedor vende TODO a precio por mayor, sea cual sea el cliente.
    ventaPorMayor: false,
    espera: []
  }),

  getters: {
    // A quién se le "cobra como mayorista": el cliente mayorista o, con el
    // botón Precio por mayor prendido, cualquiera.
    clienteParaPrecio: (s) => (s.ventaPorMayor ? { ...(s.cliente ?? {}), mayorista: true } : s.cliente),

    unidades: (s) => redondearCantidad(s.items.reduce((suma, i) => suma + i.cantidad, 0)),
    subtotal: (s) => redondear(s.items.reduce((suma, i) => suma + i.cantidad * numero(i.precio_unitario), 0)),
    total () {
      return Math.max(0, redondear(this.subtotal - numero(this.descuento)))
    },
    asignado: (s) => redondear(s.pagos.reduce((suma, p) => suma + numero(p.monto), 0)),
    restante () {
      return redondear(this.total - this.asignado)
    },
    // Unidades de un producto en el carrito (sumando sus variantes): el ×N
    // de la tarjeta del catálogo.
    cantidadDeProducto: (s) => (productoId) =>
      s.items.filter((i) => i.producto_id === productoId).reduce((suma, i) => suma + i.cantidad, 0),
    cantidadDeVariante: (s) => (varianteId) => s.items.find((i) => i.variante_id === varianteId)?.cantidad ?? 0
  },

  actions: {
    /**
     * Agrega una unidad. Lo que ya está suma uno. Nunca más que el stock
     * (el backend lo valida igual al cobrar).
     *
     * @returns {'ok'|'sin-stock'}
     */
    agregar (linea) {
      const existente = this.items.find((i) => i.variante_id === linea.variante_id)
      const enCarrito = existente?.cantidad ?? 0

      if (enCarrito + 1 > linea.stock) return 'sin-stock'

      if (existente) {
        existente.cantidad++
        existente.stock = linea.stock
      } else {
        const { precio, porMayor } = precioPara(linea.precio_hoy ?? linea.precio_unitario, linea.precio_mayor, this.clienteParaPrecio)
        this.items.push({ ...linea, cantidad: 1, precio_unitario: precio, precio_auto: precio, por_mayor: porMayor })
      }
      this.seleccionado = linea.variante_id
      return 'ok'
    },

    /** @returns {'ok'|'sin-stock'} */
    cambiarCantidad (varianteId, delta) {
      const item = this.items.find((i) => i.variante_id === varianteId)
      if (!item) return 'ok'

      const nueva = redondearCantidad(item.cantidad + delta)
      if (nueva > item.stock) return 'sin-stock'
      if (nueva <= 0) {
        this.quitar(varianteId)
      } else {
        item.cantidad = nueva
      }
      return 'ok'
    },

    /**
     * Cantidad escrita a mano (2.5 kg). Sólo las unidades fraccionables
     * aceptan decimales; las demás se redondean a entero.
     *
     * @returns {'ok'|'sin-stock'|'invalida'}
     */
    fijarCantidad (varianteId, valor) {
      const item = this.items.find((i) => i.variante_id === varianteId)
      if (!item) return 'ok'

      let nueva = Number(valor)
      if (!Number.isFinite(nueva) || nueva <= 0) return 'invalida'
      nueva = item.fraccionable ? redondearCantidad(nueva) : Math.round(nueva)
      if (nueva <= 0) return 'invalida'
      if (nueva > item.stock) return 'sin-stock'

      item.cantidad = nueva
      return 'ok'
    },

    /**
     * Precios y stock de hoy para las líneas del carrito (`variantes` del
     * buscador de inventario): si cambiaron el precio por mayor o el normal
     * después de agregarlas, el carrito toma el nuevo. Las líneas con un
     * precio puesto a mano por el vendedor conservan ese precio.
     */
    refrescarPrecios (variantes) {
      for (const v of variantes) {
        const item = this.items.find((i) => i.variante_id === v.id)
        if (!item) continue
        const automatico = item.precio_auto !== undefined && item.precio_unitario === item.precio_auto
        item.precio_hoy = Number(v.precio ?? item.precio_hoy).toFixed(2)
        item.precio_lista = Number(v.precio_lista ?? v.precio ?? item.precio_lista).toFixed(2)
        item.precio_mayor = v.precio_mayor ?? null
        item.stock = v.stock
        if (automatico) {
          const { precio, porMayor } = precioPara(item.precio_hoy, item.precio_mayor, this.clienteParaPrecio)
          // Lo elegido línea por línea se respeta: sólo cambia el monto.
          const elegido = item.mayorElegido && item.por_mayor && item.precio_mayor !== null
          item.precio_unitario = elegido ? Number(item.precio_mayor).toFixed(2) : precio
          item.precio_auto = item.precio_unitario
          item.por_mayor = elegido || porMayor
        }
      }
    },

    /**
     * Botón "Precio por mayor": toda la venta pasa al precio por mayor (las
     * presentaciones que lo tienen), o vuelve al normal. Pisa lo que se
     * eligió línea por línea; después se puede seguir ajustando cada una.
     */
    alternarVentaPorMayor () {
      this.ventaPorMayor = !this.ventaPorMayor
      for (const item of this.items) {
        delete item.mayorElegido
        item.precio_auto = item.precio_unitario
      }
      this.aplicarCliente()
    },

    /** Si la presentación tiene un precio por mayor más bajo que el de hoy. */
    tienePrecioMayor (item) {
      const mayor = item.precio_mayor
      return mayor !== null && mayor !== undefined && mayor !== '' && Number(mayor) < Number(item.precio_hoy ?? item.precio_unitario)
    },

    /** La línea se está cobrando al precio por mayor. */
    esPorMayor (item) {
      return this.tienePrecioMayor(item) && Number(item.precio_unitario) === Number(item.precio_mayor)
    },

    /**
     * El vendedor decide: precio por mayor o normal en esa línea, sin
     * importar el cliente. Queda fijo (cambiar de cliente ya no lo toca).
     */
    alternarPorMayor (varianteId) {
      const item = this.items.find((i) => i.variante_id === varianteId)
      if (!item || !this.tienePrecioMayor(item)) return

      const precio = this.esPorMayor(item) ? Number(item.precio_hoy).toFixed(2) : Number(item.precio_mayor).toFixed(2)
      item.precio_unitario = precio
      item.precio_auto = precio
      item.por_mayor = Number(precio) === Number(item.precio_mayor)
      item.mayorElegido = true
    },

    /**
     * Cliente mayorista ↔ no mayorista: las líneas pasan al precio que le
     * toca. Las que el vendedor cambió a mano (o eligió por mayor o normal)
     * no se tocan.
     */
    aplicarCliente () {
      for (const item of this.items) {
        if (item.mayorElegido || item.precio_auto === undefined || item.precio_unitario !== item.precio_auto) continue
        const { precio, porMayor } = precioPara(item.precio_hoy ?? item.precio_unitario, item.precio_mayor, this.clienteParaPrecio)
        item.precio_unitario = precio
        item.precio_auto = precio
        item.por_mayor = porMayor
      }
    },

    quitar (varianteId) {
      const i = this.items.findIndex((item) => item.variante_id === varianteId)
      if (i === -1) return
      this.items.splice(i, 1)
      // La selección pasa a la línea de al lado (seguir con el teclado).
      this.seleccionado = this.items[Math.min(i, this.items.length - 1)]?.variante_id ?? null
    },

    // Con un solo pago el monto es siempre el total (en el carrito no se
    // edita); `montoManual` sólo cuenta en pago mixto.
    sincronizarPagoUnico () {
      if (this.pagos.length === 1) {
        this.pagos[0].monto = this.total.toFixed(2)
      }
    },

    agregarPago () {
      this.pagos[0].montoManual = true
      this.pagos.push(nuevoPago('yape', this.restante > 0 ? this.restante.toFixed(2) : ''))
    },

    quitarPago (j) {
      this.pagos.splice(j, 1)
      // Vuelve a un solo pago: otra vez es el total.
      this.sincronizarPagoUnico()
    },

    limpiar () {
      this.items = []
      this.cliente = null
      if (this.comprobante === 'factura') this.comprobante = 'ticket'
      this.descuento = ''
      this.ventaPorMayor = false
      this.pagos = [nuevoPago()]
      this.seleccionado = null
    },

    // ── Ventas en espera ──
    // "Voy a buscar otro producto al almacén": se aparca el carrito y se atiende a otro.
    aparcar () {
      if (!this.items.length || this.espera.length >= MAX_ESPERA) return false
      this.espera.push({
        id: Date.now(),
        fecha: new Date().toISOString(),
        items: this.items,
        cliente: this.cliente,
        descuento: this.descuento,
        ventaPorMayor: this.ventaPorMayor
      })
      this.limpiar()
      return true
    },

    // Si hay una venta en curso, se aparca antes (intercambio).
    retomar (id) {
      const i = this.espera.findIndex((e) => e.id === id)
      if (i === -1) return
      const [venta] = this.espera.splice(i, 1)
      if (this.items.length) this.aparcar()
      this.items = venta.items
      this.cliente = venta.cliente
      this.descuento = venta.descuento
      this.ventaPorMayor = Boolean(venta.ventaPorMayor)
      this.pagos = [nuevoPago()]
      this.seleccionado = venta.items[0]?.variante_id ?? null
      this.sincronizarPagoUnico()
    },

    descartarEspera (id) {
      this.espera = this.espera.filter((e) => e.id !== id)
    },

    // ── Persistencia (borrador en este navegador) ──
    hidratar () {
      try {
        const guardado = JSON.parse(localStorage.getItem(CLAVE) ?? 'null')
        if (!guardado) return
        this.items = guardado.items ?? []
        this.cliente = guardado.cliente ?? null
        this.descuento = guardado.descuento ?? ''
        this.ventaPorMayor = Boolean(guardado.ventaPorMayor)
        this.espera = guardado.espera ?? []
        this.comprobante = guardado.comprobante ?? 'ticket'
        this.pagos = [nuevoPago()]
        this.sincronizarPagoUnico()
      } catch {
        // Borrador ilegible o sin storage: se arranca limpio.
      }
    },

    persistir () {
      try {
        localStorage.setItem(CLAVE, JSON.stringify({
          items: this.items, cliente: this.cliente, descuento: this.descuento, ventaPorMayor: this.ventaPorMayor, espera: this.espera, comprobante: this.comprobante
        }))
      } catch {
        // Sin storage (modo privado, cuota): la venta sigue, sólo no se recuerda.
      }
    }
  }
})
