# AUREA Beauty Backend

API Laravel 10 para el sistema web Quasar y la aplicación Flutter offline-first.

## Requisitos locales

- PHP 8.1 o superior
- Composer
- PostgreSQL o MySQL

## Instalación local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configura las variables de base de datos en `.env`. No uses los marcadores incluidos en `.env.example` como credenciales reales.

## Sincronización móvil

La aplicación utiliza `/api/sync/pull` y `/api/sync/push`. Las operaciones llevan un UUID de entidad y un UUID de operación. El servidor registra cada operación aceptada, de modo que un reintento por pérdida de conexión no vuelva a crear el mismo registro.

Consulta `DEPLOY_RENDER.md` para el despliegue de producción.
