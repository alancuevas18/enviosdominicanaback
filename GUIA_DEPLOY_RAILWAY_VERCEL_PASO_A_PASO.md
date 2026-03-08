# Guía Paso a Paso: Deploy de API Laravel (Railway) + Cliente Vue (Vercel)

## 1. Arquitectura recomendada

- API Laravel: Railway
- Base de datos MySQL: Railway
- Redis (cache + queue + horizon): Railway
- Frontend Vue: Vercel
- Dominios sugeridos:
  - API: `api.tudominio.com`
  - Cliente: `app.tudominio.com`

---

## 2. Requisitos previos

Antes de iniciar, ten listo:

1. Cuenta en Railway y Vercel.
2. Repositorio API en GitHub.
3. Repositorio Vue en GitHub.
4. Dominio en Cloudflare/Namecheap/GoDaddy (o similar).

---

## 3. Deploy de la API en Railway

## Paso 1: Crear proyecto

1. Entra a Railway.
2. `New Project` → `Deploy from GitHub repo`.
3. Selecciona el repo de la API.
4. En `Settings` del servicio, usa **Nixpacks** como builder (recomendado para este proyecto).

## Paso 2: Crear servicios de datos

Dentro del mismo proyecto en Railway:

1. Agrega plugin MySQL.
2. Agrega plugin Redis.

Guarda las credenciales que Railway genera (host, port, db, user, password).

## Paso 3: Configurar variables de entorno de la API

En el servicio web de la API, agrega estas variables (ajusta valores reales):

```env
APP_NAME="Envios Dominicana"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
APP_TIMEZONE=America/Santo_Domingo

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=<MYSQL_HOST>
DB_PORT=<MYSQL_PORT>
DB_DATABASE=<MYSQL_DB>
DB_USERNAME=<MYSQL_USER>
DB_PASSWORD=<MYSQL_PASSWORD>

CACHE_STORE=redis
CACHE_PREFIX=envios_dominicana_cache

QUEUE_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=<REDIS_HOST>
REDIS_PORT=<REDIS_PORT>
REDIS_PASSWORD=<REDIS_PASSWORD>
REDIS_DB=0
REDIS_CACHE_DB=1

SANCTUM_TOKEN_EXPIRATION=10080
CORS_ALLOWED_ORIGINS=https://app.tudominio.com

DASHBOARD_CACHE_TTL_SECONDS=60
LIST_CACHE_TTL_SECONDS=60

HEALTH_NOTIFICATIONS_ENABLED=true
HEALTH_NOTIFICATION_EMAIL=devops@tudominio.com
HEALTH_ENABLE_QUEUE_CHECK=true
HEALTH_ENABLE_SCHEDULE_CHECK=true
HEALTH_ENABLE_HORIZON_CHECK=true
```

Si usas correos reales (reseteo de contraseña/notificaciones), también configura `MAIL_*`.

## Paso 4: Comando de inicio del servicio web

En el servicio web de Railway, define:

- Build Command:

```bash
composer install --no-dev --optimize-autoloader
```

- Start Command:

```bash
php artisan config:cache && php artisan route:cache && php artisan serve --host=0.0.0.0 --port=$PORT
```

## Paso 5: Migraciones iniciales

Cuando termine el primer deploy:

1. Abre terminal/shell del servicio web en Railway.
2. Ejecuta:

```bash
php artisan key:generate --force
php artisan migrate --force
```

Si necesitas datos base:

```bash
php artisan db:seed --force
```

---

## 4. Crear procesos de Worker y Scheduler en Railway

Para que Horizon y tareas programadas funcionen, crea 2 servicios adicionales (desde el mismo repo, mismo proyecto):

## A) Worker (Horizon)

- Build Command:

```bash
composer install --no-dev --optimize-autoloader
```

- Start Command:

```bash
php artisan horizon
```

Usa las mismas variables de entorno del servicio web.

## B) Scheduler

- Build Command:

```bash
composer install --no-dev --optimize-autoloader
```

- Start Command:

```bash
php artisan schedule:work
```

Usa las mismas variables de entorno del servicio web.

---

## 5. Verificación de API en Railway

Desde la terminal del web service:

```bash
php artisan health:check
php artisan horizon:status
```

Checklist esperado:

- Health check en `OK`.
- Horizon en `running`.
- Endpoints respondiendo por HTTPS.

Prueba rápida:

```bash
curl -I https://api.tudominio.com/api/v1/login
```

---

## 6. Configurar dominio de la API

1. En Railway, servicio web → `Custom Domain`.
2. Agrega `api.tudominio.com`.
3. Crea el CNAME/A record en tu DNS según lo que Railway indique.
4. Espera propagación SSL.

---

## 7. Deploy del cliente Vue en Vercel

## Paso 1: Importar repo

1. En Vercel: `Add New` → `Project`.
2. Selecciona repo Vue.
3. Framework: normalmente `Vite` (Vue 3).

## Paso 2: Build settings

Generalmente:

- Build Command:

```bash
npm run build
```

- Output Directory:

```bash
dist
```

## Paso 3: Variables de entorno del frontend

En Vercel agrega (ajusta al nombre que tu app Vue use):

```env
VITE_API_URL=https://api.tudominio.com
```

Si tu proyecto usa otro nombre (`VITE_API_BASE_URL`, etc.), usa ese exactamente.

## Paso 4: Deploy

1. Haz deploy inicial.
2. Verifica que el frontend consuma la URL correcta de API.

---

## 8. Configurar dominio del cliente Vue

1. En Vercel → proyecto Vue → `Domains`.
2. Agrega `app.tudominio.com`.
3. Crea DNS requerido (CNAME normalmente).
4. Verifica HTTPS activo.

---

## 9. Ajustes cruzados API ↔ Cliente

En la API (Railway):

- `CORS_ALLOWED_ORIGINS=https://app.tudominio.com`

En el cliente (Vercel):

- `VITE_API_URL=https://api.tudominio.com`

Si cambias dominio, actualiza ambos lados y redeploy.

---

## 10. Flujo de deploy recomendado (producción)

Cada vez que publiques:

1. Push a `main`.
2. Railway despliega API.
3. Ejecuta migraciones si hay nuevas (`php artisan migrate --force`).
4. Verifica `health:check` y `horizon:status`.
5. Vercel despliega frontend.
6. Smoke test login + endpoints críticos.

---

## 11. Checklist final de salida en vivo

- API en `https://api.tudominio.com` responde.
- Frontend en `https://app.tudominio.com` carga.
- Login funciona desde frontend.
- CORS sin errores en navegador.
- `health:check` en OK.
- Horizon y scheduler ejecutándose.
- Reset de contraseña y correos funcionando (si aplica).

---

## 12. Comandos útiles de operación

```bash
# Estado general
php artisan health:check

# Estado de colas
php artisan horizon:status

# Limpiar/reconstruir cache de config
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

# Tests rápidos
./vendor/bin/pest --no-coverage
```

---

## 13. Problemas comunes y solución rápida

## Error CORS en frontend

- Revisa `CORS_ALLOWED_ORIGINS` en API.
- Debe coincidir exactamente con dominio Vercel.

## Horizon no corre

- Revisa que el servicio Worker esté arriba.
- Verifica `QUEUE_CONNECTION=redis` y Redis accesible.

## Scheduler no ejecuta tareas

- Verifica que el servicio Scheduler esté arriba.
- Revisa logs de `php artisan schedule:work`.

## Frontend pega a localhost

- Corrige variable en Vercel (`VITE_API_URL`).
- Redeploy del frontend.

---

## 14. Recomendación final de seguridad

Antes de anunciar en producción:

- `APP_DEBUG=false`
- rotar passwords iniciales / credenciales sensibles
- usar proveedor real de email
- monitoreo activo (health + logs + alertas)
