/*
 * Service worker de Nexo: lo que hace falta para instalarlo como app.
 *
 * A propósito NO guarda nada en caché: tras cada deploy se ve la versión
 * nueva al toque (nginx sirve index.html sin caché y los assets con hash).
 * Sólo atiende la navegación: si no hay conexión, muestra un aviso en vez
 * de la pantalla de error del navegador. Lo demás va a la red como siempre.
 */
const SIN_CONEXION = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sin conexión · Nexo</title>
<style>
  body { display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 24px;
         font-family: system-ui, sans-serif; background: #111; color: #eee; text-align: center; }
  h1 { font-size: 20px; margin: 0 0 8px; }
  p { margin: 0 0 20px; color: #aaa; }
  button { padding: 10px 18px; border: 0; border-radius: 10px; background: #e11d48; color: #fff;
           font: inherit; font-weight: 600; cursor: pointer; }
</style>
</head>
<body>
  <div>
    <h1>Sin conexión</h1>
    <p>Nexo necesita internet. Revisa tu conexión e intenta de nuevo.</p>
    <button onclick="location.reload()">Reintentar</button>
  </div>
</body>
</html>`

self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', (evento) => evento.waitUntil(self.clients.claim()))

self.addEventListener('fetch', (evento) => {
  if (evento.request.mode !== 'navigate') return

  evento.respondWith(
    fetch(evento.request).catch(() => new Response(SIN_CONEXION, {
      headers: { 'Content-Type': 'text/html; charset=utf-8' }
    }))
  )
})
