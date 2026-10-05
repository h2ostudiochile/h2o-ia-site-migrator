# h2o IA Site Migrator

Versión actual: **1.0.3**.

Motor WordPress temporal y versionado para migraciones editoriales controladas por H2O.

## Templa Design

La primera migración soportada es **Templa Design: Divi → Salient**.

- `/` permanece intacto durante la migración.
- `/inicio/` es la Home nueva de prueba.
- Las páginas gestionadas son `Inicio`, `Quiénes somos`, `Servicios`, `Portafolio`, `Blog` y `Contacto`.
- Las páginas de migración permanecen `noindex` hasta el cierre del proyecto.
- El plugin no lee ni modifica internals de Divi, Salient ni de otros componentes H2O.

## Actualizaciones

Desde 1.0.3 el plugin usa un **canal de actualización propio alojado en GitHub**. WordPress consulta `bootstrap/release.json`, valida versión, origen y SHA-256, reconstruye el paquete publicado desde fragmentos versionados y verifica el ZIP antes de instalarlo.

La arquitectura de actualización es propia del componente. No depende de WPVibe, Creative Content, Essentials ni de otro plugin H2O.

> El repositorio de distribución es públicamente legible. El código no contiene credenciales ni secretos.
