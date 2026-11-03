<template>
  <form
    class="producto-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Datos generales ── -->
    <div class="producto-form__row">
      <AppTextField
        v-model="form.producto.nombre"
        label="Nombre"
        icon="inventory_2"
        placeholder="Sikaflex 1A Plus"
        maxlength="120"
        hint="Nombre comercial, como lo busca el vendedor. Sin la presentación (bolsa, galón…): eso va abajo."
        class="producto-form__grow"
        :error="form.errors[`${PATH}.nombre`]"
        autofocus
        @change="form.validate(`${PATH}.nombre`)"
      />

      <div class="producto-form__field producto-form__grow">
        <label
          :id="`${uid}-categoria`"
          class="producto-form__label"
        >Categoría</label>
        <q-select
          ref="categoriaSelectRef"
          v-model="form.producto.categoria_id"
          :options="categoriasFiltradas"
          :aria-labelledby="`${uid}-categoria`"
          :loading="cargando"
          :error="Boolean(form.errors[`${PATH}.categoria_id`])"
          :error-message="form.errors[`${PATH}.categoria_id`]"
          placeholder="Elegí una categoría"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          use-input
          fill-input
          hide-selected
          input-debounce="0"
          class="producto-form__control"
          @filter="filtrarCategorias"
          @update:model-value="form.validate(`${PATH}.categoria_id`)"
        >
          <template #prepend>
            <q-icon
              name="category"
              class="producto-form__icon"
            />
          </template>
          <template #no-option>
            <q-item>
              <q-item-section class="text-grey">
                No hay categorías que coincidan.
              </q-item-section>
            </q-item>
            <q-item
              v-if="puedeCrearCategoria"
              clickable
              @click="crearCategoria"
            >
              <q-item-section avatar>
                <q-icon
                  name="add"
                  color="primary"
                />
              </q-item-section>
              <q-item-section class="text-primary">
                Crear categoría “{{ busquedaCategoria.trim() }}”
              </q-item-section>
            </q-item>
          </template>
          <!-- Con coincidencias parciales ("Pintura" encuentra "Pinturas
               látex") también se ofrece crear la que se tipeó. -->
          <template
            v-if="puedeCrearCategoria && categoriasFiltradas.length"
            #after-options
          >
            <q-separator />
            <q-item
              clickable
              @click="crearCategoria"
            >
              <q-item-section avatar>
                <q-icon
                  name="add"
                  color="primary"
                />
              </q-item-section>
              <q-item-section class="text-primary">
                Crear categoría “{{ busquedaCategoria.trim() }}”
              </q-item-section>
            </q-item>
          </template>
        </q-select>
        <p class="producto-form__hint">
          Agrupa el catálogo: el POS y los reportes filtran por acá.
        </p>
      </div>
    </div>

    <div class="producto-form__row producto-form__row--end">
      <div class="producto-form__field producto-form__grow">
        <label
          :id="`${uid}-marca`"
          class="producto-form__label"
        >Marca</label>
        <q-select
          v-model="form.producto.marca_id"
          :options="opcionesMarcas"
          :aria-labelledby="`${uid}-marca`"
          :loading="cargando"
          :error="Boolean(form.errors[`${PATH}.marca_id`])"
          :error-message="form.errors[`${PATH}.marca_id`]"
          placeholder="Elegí una marca"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="producto-form__control"
          @update:model-value="form.validate(`${PATH}.marca_id`)"
        >
          <template #prepend>
            <q-icon
              name="verified"
              class="producto-form__icon"
            />
          </template>
        </q-select>
        <p class="producto-form__hint">
          El fabricante (Sika, Z Aditivos…).
        </p>
      </div>

      <AppTextField
        v-model="form.producto.precio"
        label="Precio base (S/)"
        icon="sell"
        type="number"
        min="0"
        step="0.01"
        placeholder="0.00"
        hint="Precio de venta al público, con IGV. Lo usan las presentaciones que no tengan precio propio."
        class="producto-form__precio"
        :error="form.errors[`${PATH}.precio`]"
        @change="form.validate(`${PATH}.precio`)"
      />

      <q-toggle
        v-model="form.producto.activo"
        label="Activo (se puede vender)"
        color="primary"
        class="producto-form__toggle"
      />

      <!-- Prenderlo con stock se puede (ese stock queda "sin lote" y la ficha
           pide asignarlo); apagarlo con stock no: el backend lo rechaza y
           acá se avisa antes. -->
      <q-toggle
        v-model="form.producto.maneja_lotes"
        label="Maneja lotes y vencimiento"
        color="primary"
        class="producto-form__toggle"
        :disable="noSePuedeApagarLotes"
        @update:model-value="form.validate(`${PATH}.maneja_lotes`)"
      >
        <q-tooltip v-if="noSePuedeApagarLotes">
          Sólo se puede desactivar con el producto sin stock
        </q-tooltip>
      </q-toggle>
    </div>

    <ul class="producto-form__leyenda">
      <li><strong>Activo:</strong> apagado, el producto no aparece en el punto de venta ni en los buscadores (no se borra).</li>
      <li><strong>Maneja lotes y vencimiento:</strong> para productos que vencen. Cada entrada de mercadería pide n° de lote y fecha de vencimiento, y las ventas sacan primero lo que vence antes.</li>
    </ul>

    <p
      v-if="form.errors[`${PATH}.maneja_lotes`]"
      class="producto-form__error"
      role="alert"
    >
      {{ form.errors[`${PATH}.maneja_lotes`] }}
    </p>

    <!-- Texto enriquecido (QEditor). El backend guarda el HTML limpio
         (App\Support\HtmlSeguro) y el POS lo muestra tal cual. -->
    <div class="producto-form__field">
      <span
        :id="`${uid}-descripcion`"
        class="producto-form__label"
      >Descripción (opcional)</span>
      <q-editor
        v-model="form.producto.descripcion"
        :toolbar="BARRA_EDITOR"
        :definitions="TEXTOS_EDITOR"
        :aria-labelledby="`${uid}-descripcion`"
        placeholder="Usos, rendimiento por m², tiempo de secado, recomendaciones…"
        min-height="7rem"
        max-height="18rem"
        :class="['producto-form__editor', { 'producto-form__editor--error': form.errors[`${PATH}.descripcion`] }]"
        @blur="form.validate(`${PATH}.descripcion`)"
      />
      <p
        v-if="form.errors[`${PATH}.descripcion`]"
        class="producto-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.descripcion`] }}
      </p>
      <p
        v-else
        class="producto-form__hint"
      >
        Usos, rendimiento, recomendaciones. El vendedor la ve en la ficha del producto en el punto de venta.
      </p>
    </div>

    <div class="producto-form__field">
      <span class="producto-form__label">Fotos del producto</span>
      <AppFotos
        v-model="form.producto.archivos"
        label="Fotos del producto"
        :errores="erroresDe(`${PATH}.archivos`)"
      />
      <p class="producto-form__hint">
        Fotos generales (la primera es la portada). Las de cada presentación van en su fila.
      </p>
    </div>

    <!-- ── Presentaciones ── -->
    <section class="producto-form__seccion">
      <header class="producto-form__seccionHead">
        <div>
          <h3 class="producto-form__title">
            Presentaciones
            <span class="producto-form__count">{{ form.producto.variantes.length }}</span>
          </h3>
          <p class="producto-form__hint">
            Las formas en que se vende el producto: cada una tiene su código, su precio, sus fotos y su propio stock.
          </p>
        </div>
        <AppButton
          label="Agregar presentación"
          icon="add"
          @click="agregarPresentacion"
        />
      </header>

      <p
        v-if="form.errors[`${PATH}.variantes`]"
        class="producto-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.variantes`] }}
      </p>

      <!-- La guía de cada campo: plegada por defecto (ocupa mucho) y se
           recuerda si el usuario la dejó abierta. -->
      <button
        v-if="form.producto.variantes.length"
        type="button"
        class="producto-form__guiaBtn"
        :aria-expanded="String(guiaAbierta)"
        @click="alternarGuia"
      >
        <q-icon
          name="help_outline"
          size="16px"
        />
        {{ guiaAbierta ? 'Ocultar la ayuda' : '¿Qué va en cada campo?' }}
        <q-icon
          :name="guiaAbierta ? 'expand_less' : 'expand_more'"
          size="16px"
        />
      </button>
      <q-slide-transition>
        <dl
          v-if="form.producto.variantes.length && guiaAbierta"
          class="producto-form__columnas"
        >
          <dt>Presentación</dt>
          <dd>Cómo se vende: “Bolsa 25 kg”, “Galón 4 L”, “Cartucho 300 ml”.</dd>
          <dt>Unidad</dt>
          <dd>En qué se cuenta el stock. Si la unidad acepta decimales (kg, metro) se puede vender 2.5.</dd>
          <dt>Color</dt>
          <dd>Opcional: sólo si la presentación viene en colores.</dd>
          <dt>SKU</dt>
          <dd>Código interno; se arma solo con el nombre, la presentación y el color. Podés cambiarlo.</dd>
          <dt>Código de barras</dt>
          <dd>
            Obligatorio: el que trae el envase o uno propio. Escanealo con la lectora o escribilo. Es el que lee el punto de venta
            y el que se imprime en las etiquetas.
          </dd>
          <dt>Precio</dt>
          <dd>De venta de esta presentación, con IGV. Vacío = usa el precio base.</dd>
          <dt>Precio por mayor</dt>
          <dd>
            Opcional: el que pagan las empresas marcadas como “Cliente mayorista” (en Clientes). Es el mismo en
            todas las sedes. Vacío = a las empresas también se les cobra el precio normal.
          </dd>
          <dt>Fotos</dt>
          <dd>De esta presentación; si no tiene, se muestran las del producto.</dd>
          <dt>{{ hayNuevas ? 'Stock inicial' : 'Stock' }}</dt>
          <dd v-if="hayNuevas">
            Unidades que ya tenés hoy en {{ userStore.sede?.nombre ?? 'tu sede' }} (sólo al crear la presentación). Si no tenés, dejalo vacío.
          </dd>
          <dd v-else>
            Lo que hay hoy (pasá el mouse para ver cada sede). Se mueve desde Inventario, no desde acá.
          </dd>
        </dl>
      </q-slide-transition>

      <!-- Una tarjeta por presentación, con cada dato rotulado: en vez de
           una tabla de 9 columnas con scroll horizontal (las últimas no se
           veían), los campos se acomodan en filas según el ancho. -->
      <div
        v-if="form.producto.variantes.length"
        class="producto-form__tarjetas"
      >
        <template
          v-for="(variante, i) in form.producto.variantes"
          :key="variante.uid"
        >
          <article class="producto-form__tarjeta">
            <header class="producto-form__tarjetaHead">
              <span class="producto-form__tarjetaNumero">{{ i + 1 }}</span>
              <span class="producto-form__tarjetaTitulo">{{ variante.presentacion || 'Nueva presentación' }}</span>
              <!-- Con stock o con historial de inventario no se quita: se
                   perdería mercadería o su trazabilidad (el backend también
                   lo rechaza). -->
              <q-btn
                flat
                dense
                round
                icon="close"
                size="sm"
                color="grey-7"
                :disable="variante.stock !== 0 || variante.con_movimientos"
                :aria-label="`Quitar presentación ${i + 1}`"
                @click="quitar(i)"
              >
                <q-tooltip v-if="variante.stock !== 0 || variante.con_movimientos">
                  {{ variante.stock !== 0 ? 'Tiene stock' : 'Tiene historial de inventario' }}: no se puede quitar
                </q-tooltip>
              </q-btn>
            </header>
            <div class="producto-form__campos">
              <div class="producto-form__campo producto-form__campo--ancho">
                <span class="producto-form__campoLabel">Presentación</span>
                <q-input
                  v-model="variante.presentacion"
                  :aria-label="`Presentación ${i + 1}`"
                  :error="Boolean(errorDe(i, 'presentacion'))"
                  :error-message="errorDe(i, 'presentacion')"
                  placeholder="Balde 4 gl"
                  maxlength="60"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  class="producto-form__control"
                  @update:model-value="refrescarSku(variante)"
                  @change="form.validate(`${PATH}.variantes.${i}.presentacion`)"
                />
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">Unidad</span>
                <q-select
                  v-model="variante.unidad_id"
                  :options="opcionesUnidades"
                  :aria-label="`Unidad de la presentación ${i + 1}`"
                  :error="Boolean(errorDe(i, 'unidad_id'))"
                  :error-message="errorDe(i, 'unidad_id')"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  emit-value
                  map-options
                  class="producto-form__control"
                  @update:model-value="form.validate(`${PATH}.variantes.${i}.unidad_id`)"
                />
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">Color (opcional)</span>
                <q-select
                  v-model="variante.color_id"
                  :options="opcionesColores"
                  :aria-label="`Color de la presentación ${i + 1}`"
                  :error="Boolean(errorDe(i, 'color_id'))"
                  :error-message="errorDe(i, 'color_id')"
                  placeholder="—"
                  clearable
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  emit-value
                  map-options
                  class="producto-form__control"
                  @update:model-value="cambioColor(variante, i)"
                >
                  <template
                    v-if="variante.color_id"
                    #prepend
                  >
                    <span
                      class="producto-form__swatch"
                      :style="{ background: colorPorId.get(variante.color_id)?.hexadecimal ?? 'transparent' }"
                    />
                  </template>
                  <template #option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item-section side>
                        <span
                          class="producto-form__swatch"
                          :style="{ background: scope.opt.hexadecimal }"
                        />
                      </q-item-section>
                      <q-item-section>{{ scope.opt.label }}</q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">SKU</span>
                <q-input
                  :model-value="variante.sku"
                  :aria-label="`SKU de la presentación ${i + 1}`"
                  :error="Boolean(errorDe(i, 'sku'))"
                  :error-message="errorDe(i, 'sku')"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  maxlength="40"
                  class="producto-form__control producto-form__sku"
                  @update:model-value="escribirSku(variante, $event)"
                  @change="form.validate(`${PATH}.variantes.${i}.sku`)"
                >
                  <template
                    v-if="variante.skuManual"
                    #append
                  >
                    <q-btn
                      flat
                      dense
                      round
                      size="xs"
                      icon="autorenew"
                      tabindex="-1"
                      :aria-label="`Volver al SKU sugerido en la presentación ${i + 1}`"
                      @click="restaurarSku(variante, i)"
                    >
                      <q-tooltip>Volver al SKU sugerido</q-tooltip>
                    </q-btn>
                  </template>
                </q-input>
              </div>
              <div class="producto-form__campo producto-form__campo--ancho">
                <span class="producto-form__campoLabel">Código de barras</span>
                <!-- La lectora escribe el código y manda Enter: sin el .prevent,
                     ese Enter guardaría todo el formulario. -->
                <q-input
                  v-model="variante.codigo_barras"
                  :aria-label="`Código de barras de la presentación ${i + 1}`"
                  :error="Boolean(errorDe(i, 'codigo_barras'))"
                  :error-message="errorDe(i, 'codigo_barras')"
                  placeholder="7751234567890"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  class="producto-form__control producto-form__barras"
                  @keydown.enter.prevent="form.validate(`${PATH}.variantes.${i}.codigo_barras`)"
                  @change="form.validate(`${PATH}.variantes.${i}.codigo_barras`)"
                >
                  <template #prepend>
                    <q-icon
                      name="mdi-barcode-scan"
                      size="16px"
                    />
                  </template>
                </q-input>
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">Precio (S/)</span>
                <q-input
                  v-model="variante.precio"
                  :aria-label="`Precio de la presentación ${i + 1}`"
                  :placeholder="formatearPrecio(form.producto.precio) || 'Base'"
                  :error="Boolean(errorDe(i, 'precio'))"
                  :error-message="errorDe(i, 'precio')"
                  type="number"
                  min="0"
                  step="0.01"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  class="producto-form__control"
                  @change="form.validate(`${PATH}.variantes.${i}.precio`)"
                />
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">Precio por mayor (S/)</span>
                <q-input
                  v-model="variante.precio_mayor"
                  :aria-label="`Precio por mayor de la presentación ${i + 1}`"
                  placeholder="—"
                  :error="Boolean(errorDe(i, 'precio_mayor'))"
                  :error-message="errorDe(i, 'precio_mayor')"
                  type="number"
                  min="0"
                  step="0.01"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  class="producto-form__control"
                  @change="form.validate(`${PATH}.variantes.${i}.precio_mayor`)"
                >
                  <q-tooltip v-if="variante.precio_mayor !== '' && Number(variante.precio_mayor) >= Number(variante.precio || form.producto.precio)">
                    Es igual o mayor que el precio normal: al mayorista se le cobra el normal
                  </q-tooltip>
                </q-input>
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">{{ variante.id ? 'Stock' : 'Stock inicial' }}</span>
                <!-- Existente: su stock real (se mueve desde Inventario). Nueva:
                     las unidades con que entra (su costo, lote y vencimiento se
                     completan en "Stock inicial", debajo). -->
                <span
                  v-if="variante.id"
                  class="producto-form__stock text-mono"
                >
                  {{ formatearCantidad(variante.stock) }}
                  <q-tooltip v-if="variante.stocks.some((s) => s.cantidad)">
                    <div
                      v-for="s in variante.stocks.filter((s) => s.cantidad)"
                      :key="s.sede_id"
                    >
                      {{ s.sede }}: {{ formatearCantidad(s.cantidad) }}
                    </div>
                  </q-tooltip>
                </span>
                <div
                  v-else
                  class="producto-form__inicial"
                >
                  <q-input
                    v-model="variante.stock_inicial"
                    :aria-label="`Stock inicial de la presentación ${i + 1}`"
                    placeholder="0"
                    type="number"
                    min="0"
                    :step="unidadPorId.get(variante.unidad_id)?.fraccionable ? '0.001' : '1'"
                    dense
                    outlined
                    hide-bottom-space
                    no-error-icon
                    :error="Boolean(errorDe(i, 'stock_inicial'))"
                    class="producto-form__control"
                  />
                </div>
              </div>
              <div class="producto-form__campo">
                <span class="producto-form__campoLabel">Fotos</span>
                <!-- Miniatura + cantidad; abre el panel de fotos debajo de la fila. -->
                <button
                  type="button"
                  :class="['producto-form__fotosBtn', {
                    'producto-form__fotosBtn--abierto': abierta === variante.uid,
                    'producto-form__fotosBtn--error': erroresDe(`${PATH}.variantes.${i}.archivos`).length
                  }]"
                  :aria-expanded="String(abierta === variante.uid)"
                  :aria-label="`Fotos de la presentación ${i + 1} (${variante.archivos.length})`"
                  @click="abierta = abierta === variante.uid ? null : variante.uid"
                >
                  <img
                    v-if="variante.archivos.length"
                    :src="variante.archivos[0].miniatura_url ?? variante.archivos[0].url"
                    alt=""
                    width="40"
                    height="40"
                    class="producto-form__fotosThumb"
                  >
                  <q-icon
                    v-else
                    name="add_a_photo"
                    size="16px"
                  />
                  <span
                    v-if="variante.archivos.length"
                    class="producto-form__fotosCount"
                  >{{ variante.archivos.length }}</span>
                </button>
              </div>
            </div>

            <p
              v-if="errorDe(i, 'stock_inicial')"
              class="producto-form__error"
            >
              {{ errorDe(i, 'stock_inicial') }}
            </p>

            <div
              v-if="abierta === variante.uid"
              class="producto-form__panelFotos"
            >
              <AppFotos
                v-model="variante.archivos"
                :label="`Fotos de ${etiquetaDe(variante)}`"
                :errores="erroresDe(`${PATH}.variantes.${i}.archivos`)"
              />
              <AppButton
                v-if="variante.archivos.length && form.producto.variantes.length > 1"
                variant="tertiary"
                icon="content_copy"
                :label="`Usar estas fotos en las otras ${form.producto.variantes.length - 1} presentaciones`"
                @click="copiarFotosATodas(variante)"
              />
            </div>
          </article>
        </template>
      </div>

      <!-- ── Venta por sede ── -->
      <div
        v-if="sedes.length && form.producto.variantes.length"
        class="producto-form__sedes"
      >
        <div class="producto-form__stockTitulo">
          <q-icon
            name="storefront"
            size="16px"
          />
          Venta por sede
        </div>
        <ul class="producto-form__leyenda">
          <li><strong>Casilla:</strong> marcada = esa sede vende la presentación (aparece en su punto de venta).</li>
          <li><strong>Precio:</strong> sólo si esa sede la vende a otro precio. Vacío = el precio de la presentación (o el base).</li>
          <li><strong>Stock mín.:</strong> cuando el stock de la sede baje de este número, aparece en “Por reponer” y en las alertas. 0 = no avisar.</li>
          <li><strong>Stock:</strong> lo que hay hoy en esa sede (sólo lectura).</li>
        </ul>

        <div
          v-for="sede in sedes"
          :key="sede.id"
          class="producto-form__sede"
        >
          <div class="producto-form__sedeHead">
            <q-checkbox
              :model-value="estadoSede(sede.id)"
              toggle-indeterminate
              dense
              :label="sede.nombre"
              class="producto-form__sedeNombre"
              @update:model-value="venderTodoEn(sede.id, $event)"
            />
            <span
              v-if="sede.id === userStore.sedeId"
              class="producto-form__hint"
            >(tu sede)</span>
          </div>

          <div class="producto-form__sedeFilas">
            <div class="producto-form__sedeFila producto-form__sedeFila--head">
              <span>Presentación</span>
              <span>Precio</span>
              <span>Stock mín.</span>
              <span class="text-right">Stock</span>
            </div>
            <div
              v-for="(variante, i) in form.producto.variantes"
              :key="variante.uid"
              class="producto-form__sedeFila"
            >
              <q-checkbox
                v-model="configDe(variante, sede.id).activo"
                dense
                :label="etiquetaDe(variante)"
                :aria-label="`Vender ${etiquetaDe(variante)} en ${sede.nombre}`"
              />
              <q-input
                v-model="configDe(variante, sede.id).precio"
                :aria-label="`Precio de ${etiquetaDe(variante)} en ${sede.nombre}`"
                :placeholder="formatearPrecio(variante.precio || form.producto.precio) || 'General'"
                :disable="!configDe(variante, sede.id).activo"
                :error="Boolean(errorSede(i, sede.id, 'precio'))"
                :error-message="errorSede(i, sede.id, 'precio')"
                type="number"
                min="0"
                step="0.01"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
              />
              <q-input
                v-model="configDe(variante, sede.id).stock_minimo"
                :aria-label="`Stock mínimo de ${etiquetaDe(variante)} en ${sede.nombre}`"
                :disable="!configDe(variante, sede.id).activo"
                :error="Boolean(errorSede(i, sede.id, 'stock_minimo'))"
                :error-message="errorSede(i, sede.id, 'stock_minimo')"
                placeholder="0"
                type="number"
                min="0"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
              />
              <span class="text-right text-mono producto-form__sedeStock">
                {{ formatearCantidad(stockEn(variante, sede.id)) ?? 0 }}
              </span>
            </div>
          </div>
        </div>

        <p
          v-for="mensaje in erroresSedes"
          :key="mensaje"
          class="producto-form__error"
          role="alert"
        >
          {{ mensaje }}
        </p>
      </div>

      <div
        v-if="hayNuevas"
        class="producto-form__stockInicial"
      >
        <div class="producto-form__stockTitulo">
          <q-icon
            name="inventory"
            size="16px"
          />
          Stock inicial de las presentaciones nuevas
        </div>
        <p class="producto-form__hint">
          Sólo si ya tenés mercadería de este producto: entra al inventario de
          {{ userStore.sede?.nombre ?? 'tu sede' }} como “Alta de producto”. Si todavía no tenés,
          dejá las cantidades vacías y cargala después con una compra o una entrada.
        </p>
        <div class="producto-form__row">
          <AppTextField
            v-model="form.producto.costo_compra"
            :label="unidadesIniciales ? 'Costo de compra por unidad (S/) — obligatorio' : 'Costo de compra por unidad (S/)'"
            icon="payments"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            hint="Cuánto te costó cada unidad al proveedor, con IGV. NO es el precio de venta: sirve para calcular la ganancia. Vale para todas las presentaciones, salvo que abajo les pongas otro."
            class="producto-form__grow"
            :error="form.errors[`${PATH}.costo_compra`]"
          />
          <AppTextField
            v-model="form.producto.referencia_compra"
            label="Factura o guía (opcional)"
            icon="receipt"
            maxlength="60"
            placeholder="F001-2345"
            hint="El documento con el que entró la mercadería, para encontrarlo en el historial."
            class="producto-form__grow"
            :error="form.errors[`${PATH}.referencia_compra`]"
          />
        </div>
        <div class="producto-form__rellenar">
          <span>Poner</span>
          <q-input
            v-model="cantidadParaTodas"
            aria-label="Unidades para todas las presentaciones nuevas"
            type="number"
            min="0"
            step="1"
            dense
            outlined
            hide-bottom-space
            class="producto-form__control producto-form__rellenarInput"
            @keydown.enter.prevent="ponerATodas"
          />
          <span>unidades a todas las nuevas</span>
          <AppButton
            variant="tertiary"
            label="Aplicar"
            :disable="cantidadParaTodas === ''"
            @click="ponerATodas"
          />
          <span
            v-if="unidadesIniciales"
            class="producto-form__hint"
          >
            Total: {{ unidadesIniciales }} unid.<template v-if="Number(form.producto.costo_compra)">
              · {{ formatearPrecio(costoTotal) }}
            </template>
          </span>
        </div>

        <!-- Una fila por presentación con stock inicial: su costo (si difiere
             del general) y, con lotes, a qué lote entra y cuándo vence. -->
        <div
          v-if="conStockInicial.length"
          :class="['producto-form__lotes', { 'producto-form__lotes--conLote': form.producto.maneja_lotes }]"
        >
          <div class="producto-form__lotesCabecera">
            <span>Presentación</span>
            <span>Costo c/u (opcional)</span>
            <template v-if="form.producto.maneja_lotes">
              <span>N° de lote</span>
              <span>Vence</span>
            </template>
          </div>
          <div
            v-for="{ variante, i } in conStockInicial"
            :key="i"
            class="producto-form__loteFila"
          >
            <span class="producto-form__loteNombre">
              {{ etiquetaDe(variante) }}
              <span class="producto-form__hint">· {{ variante.stock_inicial }} unid.</span>
            </span>
            <q-input
              v-model="variante.costo_unitario"
              :aria-label="`Costo unitario de ${etiquetaDe(variante)}`"
              :placeholder="form.producto.costo_compra ? `${form.producto.costo_compra} (general)` : 'S/ 0.00'"
              type="number"
              min="0"
              step="0.01"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(errorDe(i, 'costo_unitario'))"
              :error-message="errorDe(i, 'costo_unitario')"
              class="producto-form__control"
            />
            <template v-if="form.producto.maneja_lotes">
            <q-input
              v-model="variante.lote"
              :aria-label="`Lote de ${etiquetaDe(variante)}`"
              placeholder="L-2301"
              maxlength="40"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(errorDe(i, 'lote'))"
              :error-message="errorDe(i, 'lote')"
              class="producto-form__control"
            />
            <q-input
              v-model="variante.vence_at"
              :aria-label="`Vencimiento de ${etiquetaDe(variante)}`"
              type="date"
              :min="hoy"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(errorDe(i, 'vence_at'))"
              :error-message="errorDe(i, 'vence_at')"
              class="producto-form__control"
            />
            </template>
          </div>
          <p class="producto-form__hint">
            Costo c/u: sólo si esa presentación costó distinto que el costo general de arriba.
            <template v-if="form.producto.maneja_lotes">
              Lote y vencimiento: los que figuran en el envase o en la guía del proveedor.
            </template>
          </p>
        </div>
      </div>

      <p
        v-else-if="!form.producto.variantes.length"
        class="producto-form__vacio"
      >
        Todavía no hay presentaciones. Tocá “Agregar presentación”.
      </p>
    </section>

    <button
      type="submit"
      hidden
    />

    <!-- Alta rápida de la categoría que no estaba: al guardar queda elegida. -->
    <AppDialog
      v-model="categoriaDialog"
      title="Nueva categoría"
      persistent
    >
      <CategoriasForm
        ref="categoriaFormRef"
        :nombre="nombreNuevaCategoria"
        @save="categoriaCreada"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="categoriaDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="categoriaFormRef?.form.processing"
          @click="categoriaFormRef.submit()"
        />
      </template>
    </AppDialog>
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFotos from '@/components/AppFotos.vue'
import AppTextField from '@/components/AppTextField.vue'
import CategoriaService from '@/services/CategoriaService'
import ColorService from '@/services/ColorService'
import ProductoService from '@/services/ProductoService'
import MarcaService from '@/services/MarcaService'
import SedeService from '@/services/SedeService'
import UnidadService from '@/services/UnidadService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import CategoriasForm from '@/modules/Categorias/CategoriasForm.vue'
import formProducto, { nuevaVariante } from './FormProducto'
import { sugerirSku } from './sku'

const PATH = 'producto'

// ── Editor de la descripción ──
// Sólo lo que deja pasar App\Support\HtmlSeguro del backend.
const BARRA_EDITOR = [
  ['bold', 'italic', 'underline', 'strike'],
  ['unordered', 'ordered'],
  [{ label: 'Formato', icon: 'title', list: 'no-icons', options: ['p', 'h3', 'h4'] }],
  ['link', 'removeFormat'],
  ['undo', 'redo']
]

// Quasar viene en inglés: los tooltips de la barra, en castellano.
const TEXTOS_EDITOR = {
  bold: { tip: 'Negrita' },
  italic: { tip: 'Cursiva' },
  underline: { tip: 'Subrayado' },
  strike: { tip: 'Tachado' },
  unordered: { tip: 'Lista con viñetas' },
  ordered: { tip: 'Lista numerada' },
  p: { label: 'Párrafo' },
  h3: { label: 'Título' },
  h4: { label: 'Subtítulo' },
  link: { tip: 'Enlace' },
  removeFormat: { tip: 'Quitar formato' },
  undo: { tip: 'Deshacer' },
  redo: { tip: 'Rehacer' }
}

// Las descripciones cargadas antes del editor son texto plano: los saltos de
// línea pasan a <br> para que no se peguen en un solo renglón.
function aHtml (texto) {
  if (!texto) return ''
  if (/<[a-z][\s\S]*>/i.test(texto)) return texto
  const escapar = { '&': '&amp;', '<': '&lt;', '>': '&gt;' }
  return texto.replace(/[&<>]/g, (c) => escapar[c]).replace(/\n/g, '<br>')
}

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const uid = `producto-${useId()}`

// Editar va por POST + _method=PUT (ver FormProducto.js).
const form = props.id
  ? useForm('post', `api/productos/${props.id}`, () => formProducto(true))
  : useForm('post', 'api/productos', () => formProducto())

// ── Catálogos ──
const categorias = ref([])
const marcas = ref([])
const unidades = ref([])
const colores = ref([])
const cargando = ref(true)

const colorPorId = computed(() => new Map(colores.value.map((c) => [c.id, c])))
const unidadPorId = computed(() => new Map(unidades.value.map((u) => [u.id, u])))
const userStore = useUserStore()

// Todas las categorías, con la ruta completa ("Impermeabilizantes › Techos y cubiertas").
const opcionesCategorias = computed(() => opcionesPadre(categorias.value))
const busquedaCategoria = ref('')
const categoriasFiltradas = computed(() => {
  const termino = busquedaCategoria.value.toLowerCase()
  return termino
    ? opcionesCategorias.value.filter((o) => o.label.toLowerCase().includes(termino))
    : opcionesCategorias.value
})

function filtrarCategorias (valor, update) {
  update(() => { busquedaCategoria.value = valor })
}

// ── Crear la categoría desde el buscador ──
// Se ofrece si se tipeó algo que no es exactamente una que ya existe.
const puedeCrearCategoria = computed(() => {
  const termino = busquedaCategoria.value.trim().toLowerCase()
  return Boolean(termino) &&
    userStore.hasPermission('categorias.store') &&
    !categorias.value.some((c) => c.nombre.toLowerCase() === termino)
})

const categoriaSelectRef = ref(null)
const categoriaFormRef = ref(null)
const categoriaDialog = ref(false)
const nombreNuevaCategoria = ref('')

function crearCategoria () {
  nombreNuevaCategoria.value = busquedaCategoria.value
  categoriaSelectRef.value.hidePopup()
  categoriaDialog.value = true
}

function categoriaCreada (categoria) {
  categorias.value.push(categoria)
  form.producto.categoria_id = categoria.id
  form.validate(`${PATH}.categoria_id`)
  categoriaDialog.value = false
}

// Las marcas inactivas no se ofrecen, salvo la que ya tiene el producto.
const opcionesMarcas = computed(() => marcas.value
  .filter((m) => m.activo || m.id === form.producto.marca_id)
  .map((m) => ({ value: m.id, label: m.nombre })))
const opcionesUnidades = computed(() => unidades.value.map((u) => ({ value: u.id, label: `${u.nombre} (${u.abreviatura})` })))
const opcionesColores = computed(() => colores.value.map((c) => ({ value: c.id, label: c.nombre, hexadecimal: c.hexadecimal })))

// ── Errores ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.variantes.${i}.${campo}`]
}

// Todos los mensajes bajo un prefijo (la galería y cada una de sus fotos).
function erroresDe (prefijo) {
  return [...new Set(Object.entries(form.errors)
    .filter(([clave]) => clave === prefijo || clave.startsWith(`${prefijo}.`))
    .map(([, mensaje]) => mensaje))]
}

// ── SKU sugerido ──
function skuSugerido (variante) {
  return sugerirSku(
    form.producto.nombre,
    variante.presentacion,
    colorPorId.value.get(variante.color_id)?.nombre
  )
}

function refrescarSku (variante) {
  if (!variante.skuManual) variante.sku = skuSugerido(variante)
}

// Renombrar el producto arrastra los SKU que no se tocaron a mano.
watch(() => form.producto.nombre, () => form.producto.variantes.forEach(refrescarSku))

function escribirSku (variante, valor) {
  variante.sku = String(valor ?? '').toUpperCase()
  // Borrarlo del todo vuelve al sugerido.
  variante.skuManual = variante.sku !== '' && variante.sku !== skuSugerido(variante)
  if (!variante.sku) refrescarSku(variante)
}

function restaurarSku (variante, i) {
  variante.skuManual = false
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.sku`)
}

function cambioColor (variante, i) {
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.color_id`)
}

// ── Venta por sede ──
const sedes = ref([])

// La fila de la sede en la presentación (completarSedes() asegura que exista).
function configDe (variante, sedeId) {
  return variante.sedes.find((s) => s.sede_id === sedeId) ?? { activo: false, precio: '', stock_minimo: '' }
}

function stockEn (variante, sedeId) {
  return variante.stocks.find((s) => s.sede_id === sedeId)?.cantidad ?? 0
}

// Cada presentación con una fila por sede activa. Una NUEVA se vende de
// entrada en la sede de quien la carga (o en la única que haya); una que ya
// existía y no tenía fila en esa sede, no.
function completarSedes () {
  form.producto.variantes.forEach((variante) => {
    sedes.value.forEach((sede) => {
      if (variante.sedes.some((s) => s.sede_id === sede.id)) return
      variante.sedes.push({
        sede_id: sede.id,
        activo: !variante.id && (sede.id === userStore.sedeId || sedes.value.length === 1),
        precio: '',
        stock_minimo: '0'
      })
    })
  })
}
watch(() => [sedes.value.length, form.producto.variantes.length], completarSedes)

// Casilla de la sede: tildada si vende todas, a medias si algunas.
function estadoSede (sedeId) {
  const activas = form.producto.variantes.filter((v) => configDe(v, sedeId).activo).length
  if (activas === 0) return false
  return activas === form.producto.variantes.length ? true : null
}

function venderTodoEn (sedeId, valor) {
  form.producto.variantes.forEach((v) => { configDe(v, sedeId).activo = valor !== false })
}

function errorSede (i, sedeId, campo) {
  const j = form.producto.variantes[i]?.sedes.findIndex((s) => s.sede_id === sedeId)
  return j >= 0 ? form.errors[`${PATH}.variantes.${i}.sedes.${j}.${campo}`] : undefined
}

// Los de la lista entera (stock en una sede que se apaga, sede repetida).
const erroresSedes = computed(() => [...new Set(Object.entries(form.errors)
  .filter(([clave]) => /\.variantes\.\d+\.sedes$/.test(clave))
  .map(([, mensaje]) => mensaje))])

function etiquetaDe (variante) {
  const color = colorPorId.value.get(variante.color_id)?.nombre
  return [variante.presentacion || 'la presentación', color].filter(Boolean).join(' · ')
}

// Una fila nueva con la unidad de la última (lo normal es cargar varias
// presentaciones parecidas seguidas).
function agregarPresentacion () {
  const ultima = form.producto.variantes.at(-1)
  const variante = nuevaVariante({ unidad_id: ultima?.unidad_id ?? null })
  refrescarSku(variante)
  form.producto.variantes.push(variante)
}

function quitar (i) {
  if (abierta.value === form.producto.variantes[i].uid) abierta.value = null
  form.producto.variantes.splice(i, 1)
}

watch(() => form.producto.maneja_lotes, (conLotes) => {
  if (conLotes) form.producto.variantes.forEach((v) => { if (!v.id) v.stock_inicial = '' })
})

const tieneStock = computed(() => form.producto.variantes.some((v) => Number(v.stock) !== 0))
// Cómo estaba guardado: con stock se puede prender, pero no apagar.
const lotesGuardado = ref(false)
const noSePuedeApagarLotes = computed(() => tieneStock.value && lotesGuardado.value)

// ── Stock inicial (sólo variantes nuevas) ──
const hayNuevas = computed(() => form.producto.variantes.some((v) => !v.id))

// Guía de los campos de las presentaciones: plegada por defecto; se recuerda
// en este navegador (comodidad, no dato).
const CLAVE_GUIA = 'nexo.producto.guia'
function leerGuia () {
  try {
    return localStorage.getItem(CLAVE_GUIA) === '1'
  } catch {
    return false
  }
}
const guiaAbierta = ref(leerGuia())
function alternarGuia () {
  guiaAbierta.value = !guiaAbierta.value
  try {
    localStorage.setItem(CLAVE_GUIA, guiaAbierta.value ? '1' : '0')
  } catch {
    // Sin storage igual se abre y se cierra; sólo no se recuerda.
  }
}

// Nuevas con stock inicial (con su índice, para los errores por fila).
const conStockInicial = computed(() => form.producto.variantes
  .map((variante, i) => ({ variante, i }))
  .filter(({ variante }) => !variante.id && Number(variante.stock_inicial) > 0))

// Mínimo del selector de fecha, en la fecha local (no la UTC de toISOString).
const hoy = (() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
})()
const cantidadParaTodas = ref('')

function ponerATodas () {
  if (cantidadParaTodas.value === '') return
  form.producto.variantes.filter((v) => !v.id).forEach((v) => { v.stock_inicial = String(cantidadParaTodas.value) })
}

const unidadesIniciales = computed(() =>
  form.producto.variantes.filter((v) => !v.id).reduce((s, v) => s + (Number(v.stock_inicial) || 0), 0))

// Con el costo propio de cada variante o, si no tiene, el general.
const costoTotal = computed(() => form.producto.variantes
  .filter((v) => !v.id && Number(v.stock_inicial) > 0)
  .reduce((s, v) => s + Number(v.stock_inicial) * Number(v.costo_unitario || form.producto.costo_compra || 0), 0))

// ── Fotos por variante ──
// La variante con el panel de fotos abierto (una a la vez).
const abierta = ref(null)

// Una guardada viaja por id (el backend duplica el archivo); una nueva viaja
// como el mismo File otra vez. Objetos nuevos: cada galería edita su lista.
function copiarFotos (archivos) {
  return archivos.map((foto) => ({ ...foto }))
}

function copiarFotosATodas (variante) {
  form.producto.variantes
    .filter((v) => v !== variante)
    .forEach((otra) => { otra.archivos = copiarFotos(variante.archivos) })
}

// ── Carga ──
// Lo que da ArchivoResource; al guardar viaja sólo el id (lo demás se ignora).
function aFoto ({ id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }) {
  return { id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }
}

onMounted(async () => {
  const todos = { params: { rowsPerPage: 0 } }

  const [catalogoCategorias, catalogoMarcas, catalogoUnidades, catalogoColores, catalogoSedes, producto] = await Promise.all([
    CategoriaService.getData(todos),
    MarcaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    UnidadService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    ColorService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    SedeService.activas(),
    props.id ? ProductoService.get(props.id) : null
  ])

  categorias.value = catalogoCategorias.data
  marcas.value = catalogoMarcas.data
  unidades.value = catalogoUnidades.data
  colores.value = catalogoColores.data
  sedes.value = catalogoSedes
  cargando.value = false

  if (!producto) {
    // Con una sola marca (lo normal al empezar), ya viene elegida.
    if (opcionesMarcas.value.length === 1) form.producto.marca_id = opcionesMarcas.value[0].value
    return
  }

  const { nombre, categoria_id: categoriaId, marca_id: marcaId, descripcion, precio, activo, maneja_lotes: manejaLotes, archivos, variantes } = producto
  lotesGuardado.value = manejaLotes

  form.setData({
    _method: 'PUT',
    [PATH]: {
      nombre,
      categoria_id: categoriaId,
      marca_id: marcaId,
      descripcion: aHtml(descripcion),
      precio: String(precio),
      activo,
      maneja_lotes: manejaLotes,
      costo_compra: '',
      referencia_compra: '',
      archivos: archivos.map(aFoto),
      variantes: variantes.map((v) => nuevaVariante({
        id: v.id,
        presentacion: v.presentacion,
        unidad_id: v.unidad_id,
        color_id: v.color_id,
        sku: v.sku,
        codigo_barras: v.codigo_barras ?? '',
        precio: v.precio ?? '',
        precio_mayor: v.precio_mayor ?? '',
        stock: v.stock,
        stocks: v.stocks ?? [],
        // Lo que viaja: cómo la vende cada sede (las que faltan las completa
        // completarSedes(), sin vender).
        sedes: (v.stocks ?? []).map((s) => ({
          sede_id: s.sede_id,
          activo: s.activo,
          precio: s.precio ?? '',
          stock_minimo: formatearCantidad(s.stock_minimo) ?? '0'
        })),
        con_movimientos: v.con_movimientos ?? false,
        archivos: v.archivos.map(aFoto),
        // Si el SKU guardado no es el que se sugeriría, lo escribieron a mano.
        skuManual: v.sku !== sugerirSku(nombre, v.presentacion, v.color?.nombre)
      }))
    }
  })
})

// Las vistas previas de fotos nuevas (blob:) se liberan al cerrar el form.
// Acá y no en AppFotos: la misma foto puede estar copiada en varias galerías.
onBeforeUnmount(() => {
  const todas = [...form.producto.archivos, ...form.producto.variantes.flatMap((v) => v.archivos)]
  new Set(todas.map((f) => f.url).filter((url) => url?.startsWith('blob:')))
    .forEach((url) => URL.revokeObjectURL(url))
})

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.producto-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.producto-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;

  &--end {
    align-items: flex-end;
  }
}

.producto-form__grow {
  flex: 1 1 240px;
  min-width: 0;
}

.producto-form__precio {
  flex: 0 1 200px;
}

.producto-form__toggle {
  padding-bottom: 4px;
  font-size: 13.5px;
  color: var(--app-ink);
}

.producto-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.producto-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.producto-form__icon {
  color: var(--app-ink-2);
}

// Mismo radio, borde y foco que AppTextField.
.producto-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

// ── Secciones ──
.producto-form__seccion {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.producto-form__title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--app-ink);
}

.producto-form__count {
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 12px;
  background: var(--app-border-subtle);
  color: var(--app-ink-2);
}

.producto-form__editor {
  border-color: var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);

  :deep(.q-editor__toolbar) {
    border-bottom-color: var(--app-border-subtle);
  }

  :deep(.q-editor__content) {
    font-size: 14px;
    line-height: 1.55;
  }

  &--error {
    border-color: var(--q-negative);
  }
}

.producto-form__leyenda {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  padding-left: 18px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);

  strong {
    font-weight: 600;
    color: var(--app-ink);
  }
}

.producto-form__guiaBtn {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: $primary;
  }
}

.producto-form__columnas {
  display: grid;
  grid-template-columns: max-content minmax(0, 1fr);
  gap: 3px 12px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-page);
  font-size: 12px;
  line-height: 1.45;

  dt {
    font-weight: 600;
    color: var(--app-ink);
  }

  dd {
    margin: 0;
    color: var(--app-ink-2);
  }
}

.producto-form__hint,
.producto-form__vacio {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.producto-form__vacio {
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  text-align: center;
}

.producto-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}

.producto-form__seccionHead {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

// En pantallas angostas la tabla se desplaza sola en vez de apretarse.
// ── Presentaciones como tarjetas ──
.producto-form__tarjetas {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.producto-form__tarjeta {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);
}

.producto-form__tarjetaHead {
  display: flex;
  align-items: center;
  gap: 8px;
}

.producto-form__tarjetaNumero {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  font-size: 12px;
  font-weight: 700;
  color: var(--app-brand-soft-ink);
}

.producto-form__tarjetaTitulo {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: var(--app-ink);
}

// Los campos se acomodan solos: 4 por fila en pantalla ancha, 1 en el celular.
.producto-form__campos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 10px 12px;
  align-items: start;
}

.producto-form__campo {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;

  &--ancho {
    grid-column: span 2;
  }
}

.producto-form__campoLabel {
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
}

@media (max-width: 599px) {
  .producto-form__campo--ancho {
    grid-column: auto;
  }
}

.producto-form__barras :deep(input) {
  font-family: $font-mono;
  letter-spacing: 0.02em;
}

.producto-form__sku :deep(input) {
  font-family: $font-mono;
  text-transform: uppercase;
}

.producto-form__inicial {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.producto-form__sedes {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
}

.producto-form__sede {
  padding-top: 10px;
  border-top: 1px solid var(--app-border-subtle);
}

.producto-form__sedeHead {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.producto-form__sedeNombre {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.producto-form__sedeFilas {
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow-x: auto;
}

.producto-form__sedeFila {
  display: grid;
  grid-template-columns: minmax(200px, 2fr) 120px 110px 70px;
  align-items: center;
  gap: 8px;
  min-width: 520px;
  padding-left: 22px;

  &--head {
    font-size: 12px;
    font-weight: 600;
    color: var(--app-ink-2);
  }
}

.producto-form__sedeStock {
  color: var(--app-ink-2);
}

.producto-form__stockInicial {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border: 1px dashed rgba($primary, 0.45);
  border-radius: 12px;
  background: rgba($primary, 0.03);
}

.producto-form__stockTitulo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.producto-form__lotes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.producto-form__lotesCabecera,
.producto-form__loteFila {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) 150px;
  align-items: center;
  gap: 10px;

  .producto-form__lotes--conLote & {
    grid-template-columns: minmax(0, 1.4fr) 150px minmax(0, 1fr) 170px;
  }
}

.producto-form__lotesCabecera {
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.producto-form__loteNombre {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

@media (max-width: 599px) {
  .producto-form__lotesCabecera {
    display: none;
  }

  .producto-form__loteFila,
  .producto-form__lotes--conLote .producto-form__loteFila {
    grid-template-columns: 1fr 1fr;

    .producto-form__loteNombre {
      grid-column: 1 / -1;
    }
  }
}

.producto-form__rellenar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.producto-form__rellenarInput {
  width: 80px;
}

.producto-form__stock {
  padding-top: 9px;
  text-align: right;
  color: var(--app-ink-2);
}

.producto-form__swatch {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

// ── Fotos por variante ──
.producto-form__fotosBtn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
  background: var(--app-surface);
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--abierto {
    border-color: $primary;
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }

  &--error {
    border-color: var(--q-negative);
  }
}

.producto-form__fotosThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.producto-form__fotosCount {
  position: absolute;
  right: 2px;
  bottom: 2px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  line-height: 16px;
  background: rgba(0, 0, 0, 0.6);
  color: #FFFFFF;
}

.producto-form__panelFotos {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

</style>
