<template>
  <!-- Menú de tema (como el de Nuxt UI): color de marca, fuente y modo. -->
  <q-btn
    flat
    dense
    round
    icon="palette"
    aria-label="Cambiar colores del sistema"
    class="tema__btn"
  >
    <q-tooltip>Tema</q-tooltip>
    <q-menu
      anchor="bottom right"
      self="top right"
      :offset="[0, 8]"
      class="tema__menu"
    >
      <div class="tema">
        <section>
          <h3 class="tema__titulo">
            Color principal
          </h3>
          <div
            class="tema__colores"
            role="radiogroup"
            aria-label="Color principal"
          >
            <button
              v-for="c in COLORES"
              :key="c.value"
              type="button"
              role="radio"
              :aria-checked="String(TEMA.color === c.value)"
              :class="['tema__color', { 'tema__color--activo': TEMA.color === c.value }]"
              @click="TEMA.color = c.value"
            >
              <span
                class="tema__punto"
                :style="{ background: c.hex }"
              />
              {{ c.label }}
            </button>
          </div>
        </section>

        <section>
          <h3 class="tema__titulo">
            Fuente
          </h3>
          <div class="tema__opciones">
            <button
              v-for="f in FUENTES"
              :key="f.value"
              type="button"
              :aria-pressed="String(TEMA.fuente === f.value)"
              :class="['tema__opcion', { 'tema__opcion--activa': TEMA.fuente === f.value }]"
              :style="{ fontFamily: f.css }"
              @click="TEMA.fuente = f.value"
            >
              {{ f.label }}
            </button>
          </div>
        </section>

        <section>
          <h3 class="tema__titulo">
            Modo
          </h3>
          <div class="tema__opciones">
            <button
              v-for="m in MODOS"
              :key="m.value"
              type="button"
              :aria-pressed="String(TEMA.modo === m.value)"
              :class="['tema__opcion', { 'tema__opcion--activa': TEMA.modo === m.value }]"
              @click="TEMA.modo = m.value"
            >
              <q-icon
                :name="m.icon"
                size="16px"
              />
              {{ m.label }}
            </button>
          </div>
        </section>

        <button
          type="button"
          class="tema__reset"
          @click="restablecerTema"
        >
          <q-icon
            name="restart_alt"
            size="16px"
          />
          Volver al de Nexo
        </button>
      </div>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { COLORES, FUENTES, MODOS, TEMA, restablecerTema } from '@/config/tema'
</script>

<style lang="scss" scoped>
.tema__btn {
  color: var(--app-ink-2);
}

.tema {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 320px;
  padding: 14px;
  background: var(--app-surface);
  color: var(--app-ink);
}

.tema__titulo {
  margin: 0 0 8px;
  font-size: 11.5px;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.tema__colores {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 2px 6px;
  max-height: 232px;
  overflow-y: auto;
}

.tema__color {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 8px;
  border: 0;
  border-radius: 6px;
  background: none;
  font: inherit;
  font-size: 13.5px;
  color: var(--app-ink);
  text-align: left;
  cursor: pointer;

  &:hover {
    background: var(--app-page);
  }

  &--activo {
    background: var(--app-brand-soft);
    font-weight: 700;
  }
}

.tema__punto {
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.tema__opciones {
  display: flex;
  gap: 6px;
}

.tema__opcion {
  display: inline-flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 8px;
  border: 1px solid var(--app-border-control);
  border-radius: 6px;
  background: var(--app-surface);
  font-size: 12.5px;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &--activa {
    border-color: var(--q-primary);
    background: var(--app-brand-soft);
    font-weight: 700;
    color: var(--app-brand-soft-ink);
  }
}

.tema__reset {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  border: 0;
  border-top: 1px solid var(--app-border-subtle);
  background: none;
  font: inherit;
  font-size: 12.5px;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }
}
</style>
