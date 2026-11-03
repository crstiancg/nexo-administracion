<template>
  <q-page class="showcase">
    <header class="showcase__head">
      <h1 class="showcase__title">Sistema de diseño</h1>
      <p class="showcase__sub">
        Cada pieza con sus variantes y sus estados, para poder mirarlas juntas.
      </p>
    </header>

    <SwitchDarkMode />

    <!-- ══ BOTONES ══ -->
    <AppCard>
      <q-card-section class="showcase__sectionHead">
        <h2 class="showcase__h2">AppButton</h2>
        <p class="showcase__note">
          El relleno rojo es marca; el destructivo va con contorno, ícono y palabra.
          Nunca relleno.
        </p>
      </q-card-section>

      <q-separator class="showcase__sep" />

      <q-card-section>
        <div class="showcase__row">
          <AppButton
            variant="primary"
            label="Nuevo pedido"
            icon="add"
            @click="register('Nuevo pedido')"
          />
          <AppButton
            label="Cancelar"
            @click="register('Cancelar')"
          />
          <AppButton
            variant="tertiary"
            label="Ver reporte"
            @click="register('Ver reporte')"
          />
          <AppButton
            variant="destructive"
            label="Eliminar"
            icon="delete"
            @click="register('Eliminar')"
          />
        </div>

        <div class="showcase__row showcase__row--spaced">
          <AppButton
            variant="primary"
            label="Guardar"
            disable
          />
          <AppButton
            label="Cancelar"
            disable
          />
          <AppButton
            variant="destructive"
            label="Eliminar"
            disable
          />
          <span class="showcase__note">Deshabilitado va a neutro: pierde la marca, no la atenúa.</span>
        </div>

        <p class="showcase__feedback">
          Último click: <strong>{{ lastAction || '—' }}</strong>
        </p>
      </q-card-section>
    </AppCard>

    <!-- ══ CHIPS ══ -->
    <AppCard>
      <q-card-section class="showcase__sectionHead">
        <h2 class="showcase__h2">AppChip</h2>
        <p class="showcase__note">
          El ícono lo elige el componente y el label es obligatorio: el estado
          nunca se comunica sólo por color.
        </p>
      </q-card-section>

      <q-separator class="showcase__sep" />

      <q-card-section>
        <div class="showcase__row">
          <AppChip
            status="positive"
            label="Completado"
          />
          <AppChip
            status="warning"
            label="Pendiente"
          />
          <AppChip
            status="info"
            label="En proceso"
          />
          <AppChip
            status="negative"
            label="Cancelado"
          />
        </div>
      </q-card-section>
    </AppCard>

    <!-- ══ BADGES ══ -->
    <AppCard>
      <q-card-section class="showcase__sectionHead">
        <h2 class="showcase__h2">AppBadge</h2>
        <p class="showcase__note">
          Ojo con <code>floating</code>: posiciona en absoluto, así que necesita
          un padre posicionado. Suelto en el flujo se va a cualquier lado.
        </p>
      </q-card-section>

      <q-separator class="showcase__sep" />

      <q-card-section>
        <div class="showcase__row">
          <span class="showcase__label">En línea</span>
          <AppBadge>14</AppBadge>
          <AppBadge variant="soft">14</AppBadge>
        </div>

        <div class="showcase__row showcase__row--spaced">
          <span class="showcase__label">Flotante, dentro de un botón</span>

          <q-btn
            flat
            dense
            round
            icon="notifications_none"
            aria-label="Notificaciones"
            class="showcase__iconBtn"
          >
            <AppBadge floating>14</AppBadge>
          </q-btn>

          <q-btn
            flat
            dense
            round
            icon="mail_outline"
            aria-label="Mensajes"
            class="showcase__iconBtn"
          >
            <AppBadge
              dot
              floating
              sr-label="Tenés mensajes sin leer"
            />
          </q-btn>
        </div>
      </q-card-section>
    </AppCard>

    <!-- ══ CARDS ══ -->
    <AppCard>
      <q-card-section class="showcase__sectionHead">
        <h2 class="showcase__h2">AppCard</h2>
        <p class="showcase__note">
          No maneja padding ni cabecera: eso se compone con
          <code>q-card-section</code>, como está armada esta misma card.
        </p>
      </q-card-section>

      <q-separator class="showcase__sep" />

      <q-card-section>
        <div class="showcase__grid">
          <AppCard>
            <q-card-section>
              <h3 class="showcase__h3">Superficie</h3>
              <p class="showcase__note">Fondo blanco, borde y sombra sutil.</p>
            </q-card-section>
          </AppCard>

          <AppCard variant="highlight">
            <q-card-section>
              <h3 class="showcase__h3">Destacada</h3>
              <p class="showcase__note">Tinte de marca y sin sombra: ya se separa por color.</p>
            </q-card-section>
          </AppCard>
        </div>
      </q-card-section>
    </AppCard>

    <!-- ══ STAT TILES ══ -->
    <AppCard>
      <q-card-section class="showcase__sectionHead">
        <h2 class="showcase__h2">AppStatTile</h2>
        <p class="showcase__note">
          Mirá la última: la flecha sube pero el color es rojo. La flecha dice
          hacia dónde se movió el dato, el color dice si eso está bien.
        </p>
      </q-card-section>

      <q-separator class="showcase__sep" />

      <q-card-section>
        <div class="showcase__tiles">
          <AppStatTile
            featured
            label="Ingresos del mes"
            value="$418k"
            delta="+12,4%"
            delta-caption="vs. noviembre"
          />
          <AppStatTile
            label="Pedidos"
            value="1.284"
            delta="+6,1%"
            delta-caption="vs. noviembre"
          />
          <AppStatTile
            label="Tasa de conversión"
            value="3,12%"
            delta="−0,4%"
            trend="down"
            delta-caption="vs. noviembre"
          />
          <AppStatTile
            label="Costo por pedido"
            value="$1.240"
            delta="+8,2%"
            trend="up"
            :trend-is-good="false"
            delta-caption="vs. noviembre"
          />
        </div>
      </q-card-section>
    </AppCard>
  </q-page>
</template>

<script setup>
import { ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppCard from '@/components/AppCard.vue'
import AppBadge from '@/components/AppBadge.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import SwitchDarkMode from '@/components/SwitchDarkMode.vue'

const lastAction = ref('')

function register (action) {
  lastAction.value = action
}
</script>

<style lang="scss" scoped>
.showcase {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1100px;
  padding: 32px;
}

.showcase__head {
  margin-bottom: 4px;
}

.showcase__title {
  margin: 0 0 6px;
  font-size: 25px;
  font-weight: 700;
  letter-spacing: -0.5px;
  line-height: 1.1;
  color: var(--app-ink);
}

.showcase__sub,
.showcase__note {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.showcase__sectionHead {
  padding-bottom: 14px;
}

.showcase__h2 {
  margin: 0 0 4px;
  font-size: 15.5px;
  font-weight: 700;
  letter-spacing: -0.2px;
  line-height: 1.2;
  color: var(--app-ink);
}

.showcase__h3 {
  margin: 0 0 4px;
  font-size: 13.5px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--app-ink);
}

// QSeparator usa su propio color; lo alineamos al token del sistema.
.showcase__sep {
  background: var(--app-border-subtle);
}

.showcase__row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.showcase__row--spaced {
  margin-top: 18px;
}

.showcase__label {
  min-width: 190px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.showcase__iconBtn {
  color: var(--app-ink-2);
}

.showcase__feedback {
  margin: 18px 0 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.showcase__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.showcase__tiles {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

code {
  padding: 1px 5px;
  border-radius: 5px;
  background: var(--app-page);
  border: 1px solid var(--app-border-subtle);
  font-size: 11.5px;
  color: var(--app-ink);
}

@media (max-width: 1023px) {
  .showcase__tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 599px) {
  .showcase {
    padding: 20px 16px;
  }

  .showcase__grid,
  .showcase__tiles {
    grid-template-columns: minmax(0, 1fr);
  }

  .showcase__label {
    min-width: 0;
  }
}
</style>
