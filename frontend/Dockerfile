# Frontend (Quasar SPA) para Dokploy: se compila con Node y lo sirve nginx.
#
# Las VITE_APP_* se graban en el JavaScript AL COMPILAR: en Dokploy van como
# "Build Args" (no como variables de entorno del contenedor).

FROM node:24-alpine AS build
WORKDIR /app

# Sin ENV a propósito: una variable definida VACÍA pisa al .env que arma
# Dokploy y el sistema se compilaba con la URL de la API en blanco. Los ARG
# sólo se usan si llegan con valor (ver el RUN del build).
ARG VITE_APP_API_URL
ARG VITE_APP_CLIENT_ID
ARG VITE_APP_SECRET

COPY package.json package-lock.json ./
# Sin scripts: el postinstall (quasar prepare) necesita quasar.config.js, que
# todavía no se copió. Se corre después, ya con el código.
RUN npm ci --ignore-scripts

COPY . .
# La URL de la API puede llegar como Build Arg o en el .env que arma Dokploy
# (Quasar lee ese .env al compilar).
RUN for v in VITE_APP_API_URL VITE_APP_CLIENT_ID VITE_APP_SECRET; do \
      eval "valor=\${$v:-}"; \
      if [ -n "$valor" ]; then export "$v=$valor"; else unset "$v"; fi; \
    done \
    && if [ -z "${VITE_APP_API_URL:-}" ] && ! grep -qs '^VITE_APP_API_URL=.' .env; then \
      echo "Falta VITE_APP_API_URL: ponela en Build-time Arguments (o en Environment)"; \
      ls -la; exit 1; \
    fi \
    && npx quasar prepare \
    && npx quasar build

FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/spa /usr/share/nginx/html
EXPOSE 80
