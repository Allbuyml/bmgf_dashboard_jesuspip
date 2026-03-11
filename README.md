# BMGF Calculus Market Dashboard

Plugin de WordPress para visualizar y administrar un dashboard interactivo de mercado de libros de Cálculo (Calc I / Calc II) en instituciones de educación superior de EE. UU.

## ¿Qué hace este proyecto?

Este plugin permite:

- Mostrar un dashboard visual embebible en páginas de WordPress.
- Navegar análisis por secciones (portada, enrollment, institutions, textbooks).
- Servir visualizaciones HTML desde `/charts` vía rutas amigables (`/bmgf-charts/...`).
- Inyectar datos dinámicos desde WordPress a los charts con `window.BMGF_DATA`.
- Administrar y editar datos desde un panel de administración propio.
- Subir archivos `.xlsx` o `.csv` (institutions/courses), validarlos y recalcular métricas.
- Ejecutar modo de revisión con anotaciones de trazabilidad de métricas.

## Stack y contexto técnico

- PHP (plugin WordPress)
- Admin UI en JS/CSS dentro de `admin/`
- Visualizaciones en HTML/JS dentro de `charts/`
- Persistencia principal en `wp_options` (`bmgf_dashboard_data`)
- Flujo de carga de archivos y transformación vía clases internas (parser + mapper)

## Estructura principal

- `bmgf-calculus-dashboard.php`: bootstrap del plugin, shortcodes, rewrite rules, render del dashboard y servir charts.
- `includes/class-bmgf-data-manager.php`: defaults, lectura/escritura de datos en `wp_options`.
- `includes/class-bmgf-admin.php`: menú admin, assets admin, endpoints AJAX, upload/apply/reset.
- `includes/class-bmgf-xlsx-parser.php`: parsing de archivos `.xlsx`/`.csv`.
- `includes/class-bmgf-data-mapper.php`: cálculo de KPIs y secciones a partir de archivos cargados.
- `admin/partials/*`: tabs del panel de configuración.
- `charts/*.html`: páginas y componentes de visualización.
- `assets/`: logos e imágenes; `assets/css/dashboard.css` para estilos compartidos.

## Instalación local

1. Copiar la carpeta del proyecto en `wp-content/plugins/bmgf-calculus-dashboard`.
2. Activar el plugin desde el panel de WordPress.
3. Ir a `Settings > Permalinks` y guardar para refrescar rewrite rules.
4. Crear una página y agregar un shortcode del dashboard.

## Uso (shortcodes)

- Dashboard principal:

```text
[bmgf_dashboard]
```

- Página específica:

```text
[bmgf_dashboard page="cover"]
[bmgf_dashboard page="enrollment"]
[bmgf_dashboard page="institutions"]
[bmgf_dashboard page="textbooks"]
```

- Altura personalizada:

```text
[bmgf_dashboard height="1900px"]
```

- Versión de página completa:

```text
[bmgf_dashboard_page]
```

- Modo revisión con anotaciones de fuente:

```text
[bmgf_dashboard_review]
[bmgf_dashboard_review page="enrollment"]
```

## Rutas y carga de charts

El plugin registra una regla de reescritura para servir archivos HTML de `charts/` en:

- `/bmgf-charts/<archivo>.html`

Durante la respuesta:

- Reescribe rutas de assets/scripts para que funcionen dentro del plugin.
- Inyecta datos dinámicos en el HTML (`window.BMGF_DATA`).
- Activa herramientas de anotación cuando `?annotate=1` (en páginas soportadas).

## Panel de administración

Menú: `BMGF Dashboard` (requiere `manage_options`).

Desde el panel se puede:

- Editar secciones del dashboard manualmente.
- Resetear a defaults.
- Subir archivos de datos (`institutions` y/o `courses`).
- Previsualizar resultados calculados antes de aplicar.
- Aplicar actualización completa o parcial según los archivos cargados.

## Formato de datos esperados

El sistema valida columnas obligatorias antes de computar.

- Institutions (`INSTITUTION_REQUIRED`):
  - `State`, `Region`, `Sector`, `School`, `FTE Enrollment`, `Calc Level`, `Calc I Enrollment`, `Calc II Enrollment`, `Publisher_Norm` (o `Publisher`), `Avg_Price`.

- Courses (`COURSE_REQUIRED`):
  - `State`, `School`, `Period`, `Enrollments`, `Book Title Normalized`, `Calc Level`, `Region`, `Sector`, `Publisher_Normalized`, `Textbook_Price`.

Si faltan columnas requeridas, la carga falla con mensaje de error detallado.

## Flujo de actualización de datos

1. Upload del archivo (institutions/courses).
2. Parseo y validación de columnas.
3. Guardado temporal en `tmp/` del plugin (JSON intermedio).
4. `BMGF_Data_Mapper::compute_all(...)` calcula secciones.
5. En modo parcial, solo se actualizan secciones compatibles; el resto se conserva.
6. Persistencia final en `wp_options` mediante `BMGF_Data_Manager`.

## Datos y secciones que maneja el dashboard

- `kpis`
- `regional_data`
- `sector_data`
- `publishers`
- `top_institutions`
- `top_textbooks`
- `period_data`
- `institution_size_data`
- `region_coverage`
- `filters`
- `state_data`

## Consideraciones para desarrollo

- Al agregar o renombrar charts, validar que se sirvan por `/bmgf-charts/...`.
- Si cambias estructura de datos, actualizar:
  - defaults en `BMGF_Data_Manager`
  - sanitización en `BMGF_Admin`
  - cómputo en `BMGF_Data_Mapper`
  - consumo en `charts/*.html`
- Mantener compatibilidad con datos parciales en cargas de archivos.
- Revisar límites de PHP (`upload_max_filesize`, `post_max_size`, `memory_limit`) para cargas grandes.

## Troubleshooting rápido

- Los charts no cargan: guardar permalinks en WordPress.
- Error de upload: validar extensión (`.xlsx`/`.csv`) y tamaño (máx. 50MB).
- Error de columnas faltantes: comparar headers del archivo contra columnas requeridas.
- Datos no reflejados: confirmar que se aplicó la carga (no solo preview) y limpiar caché de página/CDN si aplica.

## Licencia

MIT
