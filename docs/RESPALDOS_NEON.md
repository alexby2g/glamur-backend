# Respaldos y recuperación de AUREA

La base de datos de producción está en Neon. Ningún respaldo debe incluirse en Git ni contener credenciales.

## Política mínima

- Mantener habilitada la recuperación disponible en el plan de Neon.
- Crear un respaldo lógico semanal antes de cambios importantes.
- Conservar al menos cuatro respaldos semanales cifrados.
- Probar la restauración una vez al mes en una base separada de producción.
- Registrar fecha, responsable, resultado y tiempo de recuperación.

## Respaldo lógico

Ejecutar desde un entorno seguro que ya tenga `DATABASE_URL` configurada:

```bash
pg_dump "$DATABASE_URL" --format=custom --no-owner --no-acl --file=aurea-backup.dump
```

No escribir la URL de conexión directamente en comandos compartidos, archivos o capturas.

## Prueba de restauración

Crear primero una base de prueba vacía y configurar su URL en `RESTORE_DATABASE_URL`:

```bash
pg_restore --dbname="$RESTORE_DATABASE_URL" --clean --if-exists --no-owner --no-acl aurea-backup.dump
```

Validar en la base restaurada:

- cantidad de clientes, citas y pagos;
- acceso de administrador;
- relaciones entre citas, servicios y pagos;
- migraciones aplicadas;
- fecha del último registro.

## Antes de una publicación

1. Confirmar que el último respaldo terminó correctamente.
2. Verificar que la restauración de prueba sea legible.
3. Publicar la migración.
4. Probar login y operaciones principales.
5. Revisar la bitácora de auditoría.
