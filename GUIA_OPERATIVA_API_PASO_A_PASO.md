# Guía Operativa Paso a Paso - API Envios Dominicana

## 1) Objetivo de esta guía

Esta guía te lleva de **cero a producción** con foco en:

- estabilidad,
- seguridad,
- caché,
- colas,
- observabilidad,
- operación diaria.

---

## 2) Requisitos previos

### Sistema

- PHP 8.3+
- Composer 2+
- MySQL 8+
- Redis 7+
- Extensiones PHP recomendadas: `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `json`, `curl`

### Opcional (recomendado)

- Docker / Docker Compose
- Supervisor (Linux) para procesos de cola/scheduler

---

## 3) Levantar entorno local

### Paso 1: instalar dependencias

```bash
composer install
```

### Paso 2: crear archivo de entorno

```bash
cp .env.example .env
```

### Paso 3: generar key

```bash
php artisan key:generate
```

### Paso 4: configurar base de datos en `.env`

Ejemplo mínimo:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=envios_dominicana
DB_USERNAME=root
DB_PASSWORD=secret
```

### Paso 5: migraciones

```bash
php artisan migrate
```

### Paso 6: correr pruebas

```bash
./vendor/bin/pest --no-coverage
```

### Paso 7: correr servidor local

```bash
php artisan serve
```

---

## 4) Estrategia de caché (local, staging, producción)

## 4.1 Local (simple y estable)

Usa base de datos para caché:

```env
CACHE_STORE=database
```

Ventaja: fácil de depurar.

## 4.2 Staging / Producción (alto rendimiento)

Usa Redis:

```env
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

Además:

```env
CACHE_PREFIX=envios_dominicana_cache
```

## 4.3 Cache en dashboard

Ya está aplicado cache corto al endpoint de dashboard con TTL configurable:

```env
DASHBOARD_CACHE_TTL_SECONDS=60
```

También se aplica cache a listados de alta lectura:

```env
LIST_CACHE_TTL_SECONDS=60
```

Recomendación:

- 30-60 segundos para operación normal,
- 10-15 segundos si quieres mayor frescura en panel en horario pico,
- 120 segundos si priorizas costo/rendimiento.

## 4.4 Endpoints cacheados actualmente

- `GET /api/v1/dashboard` (namespace `dashboard`)
- `GET /api/v1/stores` (namespace `stores-index`)
- `GET /api/v1/couriers` (namespace `couriers-index`)
- `GET /api/v1/branches` (namespace `branches-index`)

La invalidación se maneja con **versionado de claves** (compatible con `database` y `redis`):

- Cambios en envíos y estados de paradas invalidan `dashboard`.
- Altas/ediciones/bajas de tiendas invalidan `stores-index` y `branches-index`.
- Altas/ediciones/bajas de mensajeros invalidan `couriers-index` y `branches-index`.
- Cambios en sucursales/admins de sucursal invalidan `branches-index`.
- Aprobación de solicitud de acceso (crea tienda) invalida `stores-index` y `branches-index`.

---

## 5) Colas y jobs

## 5.1 Elegir backend de cola

En producción usa Redis:

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
```

## 5.2 Iniciar Horizon

```bash
php artisan horizon
```

## 5.3 Verificar estado

```bash
php artisan horizon:status
```

## 5.4 Programar snapshot de métricas

Ya existe en scheduler:

- `horizon:snapshot` cada 5 minutos.

---

## 6) Scheduler

Ya existen tareas programadas:

- `health:check` cada minuto,
- `horizon:snapshot` cada 5 minutos.

En servidor debes ejecutar un worker de scheduler:

```bash
php artisan schedule:work
```

O via cron (modo clásico):

```cron
* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7) Health checks y observabilidad

## 7.1 Ejecutar chequeos

```bash
php artisan health:check
```

## 7.2 Activar checks avanzados (cuando haya infraestructura)

En `.env`:

```env
HEALTH_ENABLE_QUEUE_CHECK=true
HEALTH_ENABLE_SCHEDULE_CHECK=true
HEALTH_ENABLE_HORIZON_CHECK=true
```

Solo habilita estos 3 cuando:

- Redis esté funcionando,
- Horizon esté corriendo,
- Scheduler esté corriendo.

## 7.3 Notificaciones de fallos

```env
HEALTH_NOTIFICATIONS_ENABLED=true
HEALTH_NOTIFICATION_EMAIL=devops@tudominio.com
```

---

## 8) Seguridad mínima recomendada

Configurar en `.env` de producción:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=info
SANCTUM_TOKEN_EXPIRATION=10080
CORS_ALLOWED_ORIGINS=https://app.tudominio.com,https://admin.tudominio.com
```

Además:

- HTTPS obligatorio,
- rotación de credenciales,
- backups diarios de DB,
- acceso restringido al panel de Horizon.

---

## 9) Despliegue paso a paso (manual)

## 9.1 Antes de desplegar

1. Ejecuta tests localmente.
2. Confirma que migraciones nuevas no rompan datos.
3. Realiza backup de DB.

## 9.2 Deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 9.3 Reiniciar procesos

```bash
php artisan horizon:terminate
```

Luego tu process manager (Supervisor/systemd) levantará de nuevo Horizon.

---

## 10) Validación post-deploy

Correr checklist rápido:

1. `php artisan health:check`
2. `php artisan horizon:status`
3. smoke test de endpoints críticos:
    - login,
    - creación de envío,
    - asignación,
    - cambio de estado de parada,
    - rating.
4. revisar logs de aplicación (`storage/logs`).

---

## 11) Operación diaria

## Comandos útiles

```bash
# Estado de colas/horizon
php artisan horizon:status

# Health
php artisan health:check

# Tests rápidos
./vendor/bin/pest --no-coverage

# Limpiar caches (si hiciste cambios de env/config)
php artisan optimize:clear
php artisan config:cache
```

## Monitorear

- tasa de errores 4xx/5xx,
- tiempo de respuesta en endpoints pesados,
- jobs fallidos,
- uso de disco,
- saturación de Redis.

---

## 12) Plan de rollback

Si algo falla en deploy:

1. Poner app en mantenimiento.
2. Restaurar release anterior.
3. Restaurar backup de DB (si hubo migración destructiva).
4. `php artisan optimize:clear` y recachear.
5. Levantar scheduler + horizon nuevamente.

---

## 13) Mejoras recomendadas siguientes

1. Agregar métrica de latencia por endpoint (p95/p99).
2. Separar Redis por uso: cache / queue / horizon en bases o instancias distintas.
3. Definir pipeline CI/CD con smoke tests automáticos post-deploy.
4. Implementar alertas de errores críticas por Slack/Email.
5. Añadir invalidación selectiva por eventos de dominio (en vez de invalidación por namespace completo).

---

## 14) Estado actual del proyecto

Con la configuración actual:

- test suite en verde,
- health checks base operativos,
- dashboard con caché corta configurable e invalidación por cambios operativos,
- listados `stores/couriers/branches` cacheados con TTL configurable,
- optimización de marcado masivo de notificaciones,
- stack de observabilidad (Health + Horizon + Activitylog) integrado.
