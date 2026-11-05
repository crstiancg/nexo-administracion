<template>
  <AppCard class="resp">
    <div class="resp__cabecera">
      <div>
        <h2 class="resp__titulo">
          <q-icon name="backup" /> Backups
        </h2>
        <p class="resp__ayuda">
          Copia de la base de datos y de los archivos subidos (fotos, logo, comprobantes). Se hace sola
          todos los días a las 3:00 a. m.; las viejas se van borrando y siempre queda la más nueva.
        </p>
      </div>
      <AppButton
        v-if="userStore.hasPermission('respaldos.store')"
        variant="secondary"
        icon="backup"
        :label="haciendo ? 'Haciendo backup…' : 'Hacer backup ahora'"
        :loading="haciendo"
        @click="hacer"
      />
    </div>

    <div
      v-if="!datos"
      class="resp__centro"
    >
      <q-spinner size="22px" />
    </div>

    <template v-else>
      <p
        :class="['resp__estado', datos.al_dia ? 'resp__estado--ok' : 'resp__estado--mal']"
        role="status"
      >
        <q-icon :name="datos.al_dia ? 'verified_user' : 'report_problem'" />
        <template v-if="datos.ultimo">
          Último backup: {{ formatearFechaHora(datos.ultimo) }}<template v-if="!datos.al_dia">
            · hace más de un día: revisa que el servidor esté corriendo el programado.
          </template>
        </template>
        <template v-else>
          Todavía no hay ningún backup. Haz uno ahora.
        </template>
      </p>

      <p
        v-if="!datos.destinos.some((d) => d.en_la_nube)"
        class="resp__aviso"
      >
        <q-icon name="info" />
        <span>
          Sólo se guardan en el mismo servidor: si el servidor se pierde, se pierden con él. Para tener una copia
          afuera, configura un destino en la nube (S3, Cloudflare R2 o Backblaze) con <code>BACKUP_DISKS=local,backups</code>.
        </span>
      </p>

      <p
        v-if="error"
        class="resp__error"
        role="alert"
      >
        {{ error }}
      </p>

      <section
        v-for="d in datos.destinos"
        :key="d.disco"
        class="resp__destino"
      >
        <h3 class="resp__subtitulo">
          <q-icon :name="d.en_la_nube ? 'cloud' : 'storage'" />
          {{ d.en_la_nube ? 'En la nube' : 'En el servidor' }}
          <span class="resp__muted">· {{ d.backups.length }} · {{ tamano(d.ocupado) }}</span>
        </h3>
        <p
          v-if="!d.alcanzable"
          class="resp__error"
        >
          No se pudo conectar: {{ d.error }}
        </p>
        <ul
          v-else
          class="resp__lista"
        >
          <li
            v-for="b in d.backups.slice(0, MOSTRAR)"
            :key="b.archivo"
          >
            <span>{{ formatearFechaHora(b.fecha) }}</span>
            <span class="text-mono resp__muted">{{ tamano(b.tamano) }}</span>
            <q-btn
              v-if="userStore.hasPermission('respaldos.descargar')"
              flat
              dense
              round
              size="sm"
              icon="download"
              :loading="bajando === `${d.disco}/${b.archivo}`"
              :aria-label="`Descargar el backup del ${formatearFechaHora(b.fecha)}`"
              @click="bajar(d.disco, b.archivo)"
            />
          </li>
          <li
            v-if="!d.backups.length"
            class="resp__muted"
          >
            Ninguno todavía.
          </li>
        </ul>
      </section>
    </template>
  </AppCard>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import { useUserStore } from '@/stores/user-store'
import { descargarArchivo } from '@/utils/descargar'
import { formatearFechaHora } from '@/utils/fechas'

const MOSTRAR = 10

const $q = useQuasar()
const userStore = useUserStore()
const datos = ref(null)
const haciendo = ref(false)
const bajando = ref(null)
const error = ref('')

function tamano (bytes) {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1)} GB`
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}

async function cargar () {
  datos.value = (await api.get('api/respaldos')).data
}

async function hacer () {
  haciendo.value = true
  error.value = ''
  try {
    datos.value = (await api.post('api/respaldos', null, { timeout: 600000 })).data
    $q.notify({ type: 'positive', message: 'Backup hecho.', position: 'top-right', timeout: 1500 })
  } catch (e) {
    error.value = e.response?.status === 429
      ? 'Espera unos minutos antes de hacer otro backup.'
      : Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? e.response?.data?.message ?? 'No se pudo hacer el backup.'
  } finally {
    haciendo.value = false
  }
}

async function bajar (disco, archivo) {
  bajando.value = `${disco}/${archivo}`
  try {
    await descargarArchivo(`api/respaldos/${disco}/${archivo}`, `nexo-backup-${archivo}`)
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudo descargar el backup.', position: 'top-right' })
  } finally {
    bajando.value = null
  }
}

onMounted(cargar)
</script>

<style lang="scss" scoped>
.resp {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
}

.resp__cabecera {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.resp__titulo {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.resp__ayuda,
.resp__aviso {
  margin: 0;
  max-width: 720px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.resp__aviso {
  display: flex;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--q-warning) 12%, transparent);

  .q-icon {
    margin-top: 2px;
    color: var(--q-warning);
  }
}

.resp__centro {
  display: flex;
  justify-content: center;
  padding: 16px;
}

.resp__estado {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 13.5px;
  font-weight: 600;
}

.resp__estado--ok {
  color: var(--q-positive);
}

.resp__estado--mal {
  color: var(--q-negative);
}

.resp__error {
  margin: 0;
  font-size: 13px;
  color: var(--q-negative);
}

.resp__subtitulo {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 6px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--app-ink);
}

.resp__muted {
  font-size: 12px;
  font-weight: 400;
  color: var(--app-ink-2);
}

.resp__lista {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 0;
    border-bottom: 1px solid var(--app-border-subtle);
    font-size: 13px;

    > span:first-child {
      flex: 1;
    }
  }
}
</style>
