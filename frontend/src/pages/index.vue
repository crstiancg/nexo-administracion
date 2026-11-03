<template>
  <q-layout view="LHh Lpr lFf">
    <!-- ══ TOOLBAR ══ -->
    <q-header class="app-header">
      <q-toolbar class="app-toolbar">
        <q-btn
          flat
          dense
          round
          icon="menu"
          aria-label="Abrir navegación"
          :class="['app-toolbar__menu', { 'lt-md': !pantallaCompleta }]"
          @click="toggleDrawer"
        />

        <AppBusquedaGlobal class="gt-xs" />

        <q-space />

        <AppSedeSelector />
        <!-- Versión que está corriendo (al lado de la sede): para saber si ya
             llegó el último deploy. En celular sólo en el menú lateral. -->
        <span class="app-header__version gt-sm">
          v{{ versionApp.numero }}
          <q-tooltip>
            Nexo v{{ versionApp.numero }}<template v-if="versionApp.fecha">
              · compilado {{ versionApp.fecha }}
            </template><template v-if="versionApp.commit">
              · {{ versionApp.commit }}
            </template>
          </q-tooltip>
        </span>
        <SwitchDarkMode />

        <AppAlertas />

        <div class="app-toolbar__sep" />

        <AppUserMenu />
      </q-toolbar>
    </q-header>

    <!-- ══ DRAWER ══ -->
    <!-- Sin `bordered`: Quasar lo dibuja con rgba(0,0,0,0.12) —negro, que en
         tema oscuro es invisible— y con la misma especificidad que nuestra
         regla, así que quién gana dependía del orden del bundle. El borde
         lo dibujamos nosotros con el token, que sí cambia de tema. -->
    <q-drawer
      v-model="drawerOpen"
      :show-if-above="!pantallaCompleta"
      :overlay="pantallaCompleta"
      :width="248"
      class="app-drawer"
    >
      <div class="app-drawer__inner">
        <div class="app-brand">
          <AppBrandMark :size="32" />
          <span class="app-brand__name">NEXO</span>
        </div>

        <!-- Menú por secciones colapsables. Las entradas salen de MENU (abajo):
             cada una aparece sólo con su permiso, y una sección sin
             entradas visibles no se muestra. -->
        <div class="app-drawer__menu">
          <nav class="app-drawer__nav">
            <AppNavItem
              exact
              to="/"
              icon="dashboard"
              label="Dashboard"
            />
            <AppNavItem
              v-if="userStore.hasPermission('reportes.ventas')"
              to="/reportes"
              icon="insights"
              label="Reportes"
            />
          </nav>

          <section
            v-for="seccion in secciones"
            :key="seccion.id"
            class="app-drawer__grupo"
          >
            <button
              type="button"
              class="app-drawer__section"
              :aria-expanded="String(abierta(seccion.id))"
              :aria-controls="`menu-${seccion.id}`"
              @click="alternar(seccion.id)"
            >
              <q-icon
                :name="seccion.icon"
                size="16px"
                class="app-drawer__sectionIcon"
              />
              <span class="app-drawer__sectionLabel">{{ seccion.label }}</span>
              <!-- Cerrada, avisa igual si adentro hay algo pendiente. -->
              <AppBadge
                v-if="!abierta(seccion.id) && totalBadges(seccion)"
                dot
                :sr-label="`Hay pendientes en ${seccion.label}`"
              />
              <q-icon
                name="expand_more"
                size="18px"
                :class="['app-drawer__chevron', { 'app-drawer__chevron--abierta': abierta(seccion.id) }]"
              />
            </button>

            <q-slide-transition>
              <nav
                v-show="abierta(seccion.id)"
                :id="`menu-${seccion.id}`"
                class="app-drawer__nav"
              >
                <AppNavItem
                  v-for="item in seccion.items"
                  :key="item.to"
                  :to="item.to"
                  :icon="item.icon"
                  :label="item.label"
                >
                  <template
                    v-if="badges[item.badge]"
                    #badge
                  >
                    <AppBadge variant="brand">
                      {{ badges[item.badge] }}
                    </AppBadge>
                  </template>
                </AppNavItem>
              </nav>
            </q-slide-transition>
          </section>
        </div>

        <div class="app-drawer__user">
          <div class="app-avatar">{{ userStore.initials }}</div>
          <div class="app-drawer__userText">
            <div class="app-drawer__userName">{{ userStore.name }}</div>
            <div class="app-drawer__userRole">{{ userStore.roles?.[0] ?? userStore.username }}</div>
          </div>
          <q-btn
            flat
            dense
            round
            icon="logout"
            aria-label="Cerrar sesión"
            class="app-drawer__logout"
            @click="onLogout"
          />
        </div>
        <!-- Qué versión está corriendo: para saber si ya llegó el último deploy. -->
        <div
          class="app-drawer__version"
          :title="`Compilado el ${versionApp.fecha}`"
        >
          Nexo v{{ versionApp.numero }}<template v-if="versionApp.fecha">
            · {{ versionApp.fecha }}
          </template><template v-if="versionApp.commit">
            · {{ versionApp.commit }}
          </template>
        </div>
      </div>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user-store'
import AppBrandMark from '@/components/AppBrandMark.vue'
import AppNavItem from '@/components/AppNavItem.vue'
import AppBadge from '@/components/AppBadge.vue'
import AppSedeSelector from '@/components/AppSedeSelector.vue'
import AppUserMenu from '@/components/AppUserMenu.vue'
import AppAlertas from '@/components/AppAlertas.vue'
import AppBusquedaGlobal from '@/components/AppBusquedaGlobal.vue'
import SwitchDarkMode from '@/components/SwitchDarkMode.vue'
import InventarioService from '@/services/InventarioService'
import PedidoService from '@/services/PedidoService'

// Los pone quasar.config.js al compilar (versión, fecha y commit). Con
// `typeof`: si el servidor de desarrollo arrancó antes de existir, no rompe.
/* global __APP_VERSION__, __APP_BUILD__, __APP_COMMIT__ */
const versionApp = {
  numero: typeof __APP_VERSION__ !== 'undefined' ? __APP_VERSION__ : '—',
  fecha: typeof __APP_BUILD__ !== 'undefined'
    ? new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(__APP_BUILD__))
    : '',
  commit: typeof __APP_COMMIT__ !== 'undefined' ? __APP_COMMIT__ : ''
}

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

// Pedidos pendientes para el badge del menú. Se recalcula al navegar: basta
// para que se entere al volver de confirmar o cancelar uno.
const pendientes = ref(0)
async function contarPendientes () {
  if (!userStore.hasPermission('pedidos.index')) return
  try {
    const { total } = await PedidoService.getData({ params: { estado: 'pendiente', rowsPerPage: 1 } })
    pendientes.value = total ?? 0
  } catch {
    // Un badge no vale un error en pantalla.
  }
}
watch(() => route.path, contarPendientes, { immediate: true })

// Lotes vencidos + por vencer de la sede: el badge de Vencimientos.
const alertasLotes = ref(0)
async function contarAlertasLotes () {
  if (!userStore.hasPermission('inventario.lotes')) return
  try {
    const [vencidos, porVencer] = await Promise.all(['vencido', 'por_vencer'].map((estado) =>
      InventarioService.lotes({ params: { estado, rowsPerPage: 1 } })))
    alertasLotes.value = (vencidos.total ?? 0) + (porVencer.total ?? 0)
  } catch {
    // Un badge no vale un error en pantalla.
  }
}
watch(() => route.path, contarAlertasLotes, { immediate: true })

// ── Menú ──
// `badge`: la clave en `badges` (contadores que se recalculan al navegar).
const MENU = [
  {
    id: 'ventas',
    label: 'Ventas',
    icon: 'storefront',
    items: [
      { to: '/pos', icon: 'point_of_sale', label: 'Punto de venta', permiso: 'ventas.store' },
      { to: '/pedidos', icon: 'receipt_long', label: 'Pedidos', permiso: 'pedidos.index', badge: 'pendientes' },
      { to: '/cotizaciones', icon: 'request_quote', label: 'Cotizaciones', permiso: 'cotizaciones.index' },
      { to: '/clientes', icon: 'groups', label: 'Clientes', permiso: 'clientes.index' },
      { to: '/caja', icon: 'account_balance_wallet', label: 'Caja', permiso: 'cajas.actual' },
      { to: '/ofertas', icon: 'local_offer', label: 'Ofertas', permiso: 'ofertas.index' }
    ]
  },
  {
    id: 'inventario',
    label: 'Inventario',
    icon: 'warehouse',
    items: [
      { to: '/productos', icon: 'inventory_2', label: 'Productos', permiso: 'productos.index' },
      { to: '/inventario', icon: 'swap_vert', label: 'Movimientos', permiso: 'inventario.index' },
      { to: '/reponer', icon: 'production_quantity_limits', label: 'Por reponer', permiso: 'inventario.index' },
      { to: '/vencimientos', icon: 'event_busy', label: 'Vencimientos', permiso: 'inventario.lotes', badge: 'lotes' },
      { to: '/etiquetas', icon: 'mdi-barcode', label: 'Etiquetas', permiso: 'etiquetas.imprimir' }
    ]
  },
  {
    id: 'compras',
    label: 'Compras',
    icon: 'shopping_bag',
    items: [
      { to: '/ordenes-compra', icon: 'assignment', label: 'Pedidos de compra', permiso: 'ordenes-compra.index' },
      { to: '/compras', icon: 'shopping_bag', label: 'Compras', permiso: 'compras.index' },
      { to: '/proveedores', icon: 'local_shipping', label: 'Proveedores', permiso: 'proveedores.index' }
    ]
  },
  {
    id: 'catalogos',
    label: 'Catálogos',
    icon: 'category',
    items: [
      { to: '/categorias', icon: 'category', label: 'Categorías', permiso: 'categorias.index' },
      { to: '/marcas', icon: 'verified', label: 'Marcas', permiso: 'marcas.index' },
      { to: '/unidades', icon: 'straighten', label: 'Unidades de medida', permiso: 'unidades.index' },
      { to: '/colores', icon: 'palette', label: 'Colores', permiso: 'colores.index' }
    ]
  },
  {
    id: 'administracion',
    label: 'Administración',
    icon: 'admin_panel_settings',
    items: [
      { to: '/sedes', icon: 'storefront', label: 'Sedes', permiso: 'sedes.index' },
      { to: '/usuarios', icon: 'group', label: 'Usuarios', permiso: 'usuarios.index' },
      { to: '/roles', icon: 'badge', label: 'Roles', permiso: 'roles.index' },
      { to: '/permisos', icon: 'key', label: 'Permisos', permiso: 'permisos.index' }
    ]
  }
]

const secciones = computed(() => MENU
  .map((seccion) => ({ ...seccion, items: seccion.items.filter((item) => userStore.hasPermission(item.permiso)) }))
  .filter((seccion) => seccion.items.length))

const badges = computed(() => ({ pendientes: pendientes.value, lotes: alertasLotes.value }))

function totalBadges (seccion) {
  return seccion.items.reduce((suma, item) => suma + (badges.value[item.badge] ?? 0), 0)
}

// Secciones abiertas: se recuerdan en este navegador (comodidad, no dato).
const CLAVE_MENU = 'nexo.menu.abiertas'
function leerAbiertas () {
  try {
    const guardadas = JSON.parse(localStorage.getItem(CLAVE_MENU))
    if (Array.isArray(guardadas)) return guardadas
  } catch {
    // Sin storage (modo privado): arranca con la configuración por defecto.
  }
  return ['ventas', 'inventario']
}
const abiertas = ref(leerAbiertas())

function abierta (id) {
  return abiertas.value.includes(id)
}

function alternar (id) {
  abiertas.value = abierta(id) ? abiertas.value.filter((x) => x !== id) : [...abiertas.value, id]
  try {
    localStorage.setItem(CLAVE_MENU, JSON.stringify(abiertas.value))
  } catch {
    // Igual se alterna; sólo no se recuerda.
  }
}

// La sección de la pantalla actual siempre se ve abierta (entrar por un
// enlace a /compras no deja la entrada escondida).
watch(() => route.path, (ruta) => {
  const actual = MENU.find((seccion) => seccion.items.some((item) => ruta === item.to || ruta.startsWith(`${item.to}/`)))
  if (actual && !abierta(actual.id)) abiertas.value = [...abiertas.value, actual.id]
}, { immediate: true })

async function onLogout () {
  await userStore.logout()
  router.replace('/login')
}

const drawerOpen = ref(false)

// Pantallas que necesitan todo el ancho (el punto de venta) lo piden con
// `definePage({ meta: { pantallaCompleta: true } })`: el menú se esconde al
// entrar y vuelve al salir. Se abre igual con el botón, encima del contenido.
const pantallaCompleta = computed(() => Boolean(route.meta.pantallaCompleta))
watch(pantallaCompleta, (completa) => {
  drawerOpen.value = completa ? false : $q.screen.gt.sm
})

// Al elegir otra pantalla desde el menú abierto encima, se cierra solo.
watch(() => route.path, () => {
  if (pantallaCompleta.value) drawerOpen.value = false
})

// En escritorio, QDrawer en modo `overlay` no pone backdrop (solo lo hace en
// móvil), así que un clic fuera no lo cierra. Lo resolvemos a mano. El botón
// del menú queda excluido: ya alterna con su propio @click.
function cerrarAlClicFuera (e) {
  if (!pantallaCompleta.value || !drawerOpen.value) return
  if (e.target.closest?.('.app-drawer, .app-toolbar__menu')) return
  drawerOpen.value = false
}
onMounted(() => document.addEventListener('pointerdown', cerrarAlClicFuera))
onBeforeUnmount(() => document.removeEventListener('pointerdown', cerrarAlClicFuera))

function toggleDrawer () {
  drawerOpen.value = !drawerOpen.value
}
</script>

<style lang="scss" scoped>
// QHeader viene con fondo $primary por default. Acá eso sería una franja
// roja de 64px cruzando la pantalla, justo lo que la paleta no quiere:
// el rojo es acento, no superficie.
.app-header {
  background: var(--app-surface);
  color: var(--app-ink);
  border-bottom: 1px solid var(--app-border-subtle);
  box-shadow: none;
}

.app-toolbar {
  height: 64px;
  padding: 0 32px;
  gap: 16px;
}

.app-toolbar__menu {
  color: var(--app-ink-2);
}

.app-toolbar__sep {
  width: 1px;
  height: 26px;
  background: var(--app-border-subtle);
}

.app-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}

// QDrawer tiene inheritAttrs: false y pasa la clase al <aside> interno, que no
// recibe el atributo de scope: sin :deep() esta regla nunca matchea.
:deep(.app-drawer) {
  background: var(--app-surface);
  border-right: 1px solid var(--app-border-control);
}

// QDrawer scrollea en un hijo, no en su raíz. Sin fondo transparente acá, el
// hijo taparía la superficie que acabamos de definir arriba.
:deep(.app-drawer .q-drawer__content) {
  background: transparent;
}

.app-drawer__inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 22px 16px;
}

.app-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 8px 26px;
}

.app-brand__name {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.2px;
  color: var(--app-ink);
}

.app-drawer__menu {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 6px;
  min-height: 0;
  margin: 0 -6px 12px;
  padding: 0 6px;
  overflow-y: auto;
}

.app-drawer__nav {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.app-drawer__grupo {
  margin-top: 10px;
}

// Encabezado de sección: también es el botón que la abre y cierra.
.app-drawer__section {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 6px 8px;
  border: 0;
  border-radius: 8px;
  background: none;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    background: var(--app-border-subtle);
    color: var(--app-ink);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 1px;
  }
}

.app-drawer__sectionLabel {
  flex: 1;
  text-align: left;
}

.app-drawer__chevron {
  transition: transform 0.2s ease;

  &--abierta {
    transform: rotate(180deg);
  }
}

.app-drawer__grupo .app-drawer__nav {
  padding-top: 4px;
}

.app-drawer__user {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: auto;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--app-border-subtle);
}

.app-header__version {
  padding: 2px 8px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 999px;
  font-family: $font-mono;
  font-size: 11px;
  color: var(--app-ink-2);
  cursor: default;
  white-space: nowrap;
}

.app-drawer__version {
  margin-top: 6px;
  font-size: 11px;
  text-align: center;
  color: var(--app-ink-2);
}

.app-drawer__userText {
  min-width: 0;
}

.app-drawer__userName {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-drawer__userRole {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.app-drawer__logout {
  margin-left: auto;
  color: var(--app-ink-2);
}
</style>
