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

Desde 1.0.3 el plugin usa **GitHub Releases** como canal de actualización. WordPress consulta el release más reciente, descarga `release.json`, valida versión, URL y SHA-256 y verifica el paquete antes de instalarlo.

La arquitectura de actualización es propia del componente. No depende de WPVibe, Creative Content, Essentials ni de otro plugin H2O.

> Para que WordPress pueda consultar GitHub Releases sin credenciales, el repositorio de distribución debe ser públicamente legible. El código no contiene credenciales ni secretos.
