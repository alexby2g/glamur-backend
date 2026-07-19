# Publicar el backend actualizado en Render

## Antes de desplegar

1. Crea una copia de seguridad de PostgreSQL/Neon.
2. Confirma que las variables reales están configuradas en el panel de Render y no dentro del repositorio.
3. Reemplaza el contenido del repositorio del backend con esta carpeta y realiza el commit.

## Despliegue

El `Dockerfile` ya ejecuta lo siguiente al iniciar:

```bash
php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000
```

Por eso, al desplegar se aplicará automáticamente la migración:

```text
2026_07_19_000000_add_offline_sync_support.php
```

La migración:

- agrega UUID sin borrar los ID existentes;
- completa UUID para todos los registros actuales;
- agrega borrado recuperable a servicios;
- crea el registro de operaciones sincronizadas para evitar duplicados;
- crea sesiones separadas por dispositivo móvil.

## Comprobaciones posteriores

Abre en el navegador:

```text
https://glamur-backend-2.onrender.com/api/configuracion-publica
```

Luego inicia sesión desde Flutter. En la pantalla **Sincronización** debe aparecer “datos sincronizados”.

Los endpoints móviles protegidos son:

```text
GET  /api/sync/pull?since=FECHA_ISO
POST /api/sync/push
```

## Compatibilidad

Las rutas anteriores del frontend Quasar se mantienen. El inicio de sesión web sigue usando el token original; Flutter envía un `device_id` y recibe una sesión propia, por lo que iniciar sesión en el celular no debe cerrar la sesión web.

## Si el backend usa otra dirección

No edites el código para cada compilación. Ejecuta Flutter con:

```bash
flutter run --dart-define=API_URL=https://TU-BACKEND.onrender.com/api
```

Para compilar el APK:

```bash
flutter build apk --release --dart-define=API_URL=https://TU-BACKEND.onrender.com/api
```
