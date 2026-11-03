<template>
  <q-btn
    flat
    round
    dense
    class="user-menu"
    :aria-label="`Cuenta de ${userStore.name ?? 'usuario'}`"
  >
    <div class="user-menu__avatar">{{ userStore.initials }}</div>

    <q-menu
      anchor="bottom right"
      self="top right"
      :offset="[0, 8]"
    >
      <div class="user-menu__panel">
        <div class="user-menu__head">
          <div class="user-menu__avatar user-menu__avatar--lg">{{ userStore.initials }}</div>
          <div class="user-menu__who">
            <div class="user-menu__name">{{ userStore.name }}</div>
            <div class="user-menu__muted">@{{ userStore.username }}</div>
            <div
              v-if="rol"
              class="user-menu__rol"
            >
              {{ rol }}
            </div>
          </div>
        </div>

        <dl class="user-menu__datos">
          <template v-if="userStore.email">
            <dt>Correo</dt>
            <dd>{{ userStore.email }}</dd>
          </template>
          <dt>Sede</dt>
          <dd>
            <div class="user-menu__sede">
              <q-icon
                name="storefront"
                size="16px"
              />
              {{ userStore.sede?.nombre ?? 'Sin sede asignada' }}
            </div>
            <div
              v-if="userStore.sede?.direccion"
              class="user-menu__muted"
            >
              {{ userStore.sede.direccion }}
            </div>
            <div
              v-if="userStore.sede?.telefono"
              class="user-menu__muted"
            >
              Tel. {{ userStore.sede.telefono }}
            </div>
          </dd>
        </dl>

        <q-separator />

        <q-item
          v-close-popup
          clickable
          class="user-menu__logout"
          @click="onLogout"
        >
          <q-item-section avatar>
            <q-icon name="logout" />
          </q-item-section>
          <q-item-section>Cerrar sesión</q-item-section>
        </q-item>
      </div>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user-store'

const router = useRouter()
const userStore = useUserStore()

const rol = computed(() => userStore.roles?.[0] ?? null)

async function onLogout () {
  await userStore.logout()
  router.replace('/login')
}
</script>

<style lang="scss" scoped>
.user-menu {
  padding: 0;
}

.user-menu__avatar {
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

  &--lg {
    width: 44px;
    height: 44px;
    font-size: 15px;
  }
}

.user-menu__panel {
  width: 280px;
  background: var(--app-surface);
  color: var(--app-ink);
}

.user-menu__head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
}

.user-menu__who {
  min-width: 0;
}

.user-menu__name {
  font-size: 15px;
  font-weight: 700;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.user-menu__muted {
  font-size: 12px;
  color: var(--app-ink-2);
}

.user-menu__rol {
  display: inline-block;
  margin-top: 4px;
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}

.user-menu__datos {
  margin: 0;
  padding: 0 16px 14px;

  dt {
    margin-top: 10px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  dd {
    margin: 2px 0 0;
    font-size: 13px;
    word-break: break-word;
  }
}

.user-menu__sede {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.user-menu__logout {
  color: var(--q-negative);
  font-weight: 600;
}
</style>
