import { config } from '@vue/test-utils'
import { Quasar, QBtn, QIcon, QCard, QBadge, QInput, QBtnDropdown, QList, QItem, QItemSection, QTable, QDialog, QSeparator, QSelect, QCheckbox, QToggle } from 'quasar'

// En la app, el plugin de Vite de Quasar auto-importa los componentes. En los
// tests ese plugin no corre, así que hay que registrarlos a mano: si no,
// Vue no resuelve <q-btn> y el componente monta vacío sin fallar fuerte.
config.global.plugins = [Quasar]
config.global.components = {
  QBtn, QIcon, QCard, QBadge, QInput, QBtnDropdown, QList, QItem, QItemSection, QTable, QDialog, QSeparator, QSelect, QCheckbox, QToggle
}
