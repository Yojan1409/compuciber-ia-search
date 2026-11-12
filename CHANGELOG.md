# Historial de cambios — Compuciber AI Search

**Proyecto:** Compuciber AI Search  
**Titular:** COMPUCIBER S.A.C.  
**Versión documentada:** 2.1.3  
**Fecha de actualización:** 12 de noviembre de 2026  
**Estado:** versión candidata estabilizada; pruebas finales de imagen, IA y embeddings pendientes

Este historial conserva las entradas anteriores y documenta los cambios de estabilización incorporados tras la versión 2.1.1. La versión interna 2.1.2 precedió a esta consolidación; no se inventa una entrada de lanzamiento independiente para esa numeración.

## [2.1.3] — 2026-11-12

### Resultados, inventario y paginación

- Ajustes de visibilidad para respetar publicación, visibilidad de búsqueda y políticas de existencias de WooCommerce.
- **Cambio 56:** exclusión adicional por metadato `_stock_status` de los productos agotados **antes** de calcular la paginación, cuando WooCommerce tiene activada la opción de ocultarlos. Se mantiene el filtrado de visibilidad existente.
- Conservación de las puntuaciones de relevancia y del orden de los productos elegibles; se evita que productos agotados ocupen posiciones que después quedarían vacías.
- Comprobación de las categorías y restricciones de los perfiles durante la selección de resultados.
- Correcciones previas relacionadas con productos no visibles, SKU, variaciones, autocomplete y diagnóstico de páginas.

### Autocompletado y correcciones

- **Cambio 54:** prioridad para nombres de producto que **comienzan** con la consulta frente a menciones secundarias, como cargadores o accesorios.
- **Cambio 57:** aplicación de los filtros de perfil y de inventario antes del límite de 100 candidatos en la consulta de sugerencias; se mantiene el límite para controlar el costo de consulta.
- **Cambio 58:** prioridad para los títulos que comienzan con la expresión corregida en la consulta aproximada (*fuzzy*). Por ejemplo, después de corregir `latop` a `laptop`, las laptops preceden a cables que solo contienen esa palabra.
- Conservación de correcciones personalizadas, sinónimos, evaluación del catálogo e integración condicional con sugerencias por IA, sin imponer llamadas externas por cada pulsación.

### Elementor y recursos JavaScript

- **Cambio 59:** reparación de los enlaces públicos de paginación cuando el HTML final recibido por una página construida con Elementor contiene destinos repetidos. La reparación se ejecuta al cargar la página y en el evento `pageshow`.
- **Cambio 60:** cambio de ruta del JavaScript público a `assets/js/buscador-v59.js` para evitar entregar una versión anterior del recurso en navegadores o capas de caché.
- **Cambio 61:** limpieza del duplicado `assets/js/buscador.js` y actualización de la referencia en el diagnóstico administrativo de recursos para que consulte el archivo activo `buscador-v59.js`.
- La corrección de navegación fue probada con shortcode directo y posteriormente en Elementor, incluyendo navegadores con y sin sesión de administrador.

### Diagnósticos y administración

- **Cambio 53:** recuperación controlada de una auditoría integral activa cuando pierde su tarea programada, evitando reiniciar el recorrido y comprobando los bloqueos existentes.
- **Cambio 55:** incorporación de los campos **Inventario** y **Visibilidad en WooCommerce** al simulador de relevancia para entender descartes de productos.
- Conservación de la traza temporal acotada de búsquedas reales, simuladores y diagnóstico de WooCommerce/índice, sin crear submenús ni registros permanentes innecesarios.
- Herramientas existentes de auditoría integral por lotes, exportación de incidencias y reindexación selectiva.

### Pruebas registradas durante la estabilización

- **LocalWP (~1.015 productos):** pruebas de indexación, relevancia, autocompletado y navegación por páginas de resultados.
- **Tienda de prueba inicial (76 productos):** funcionamiento de tarjetas de WooCommerce/WoodMart, inventario, visibilidad y embeddings previamente existentes.
- **Copia de tienda oficial (3.738 productos):** índice completo y búsqueda textual; tras el cambio 56, la primera página de `laptop` pasó de 1/12 a 12/12 productos aptos en el simulador y el número de páginas calculadas bajó de 66 a 50 en esa prueba.
- Corrección de `latop` y mejora posterior de prioridades del autocompletado; validación de resultados públicos, voz y paginación con el recurso JavaScript actualizado.
- Auditoría integral **3.738/3.738** completada. El reporte exportado identificó 1.671 registros con diferencias: 1.621 solo fecha, 42 solo stock, 7 stock + fecha y 1 nombre + fecha.
- Reindexación selectiva de **50/50** productos afectados, sin fallos reportados; comprobación posterior sin diferencias para el producto ID `49212`.

### Pendientes de validación

- Búsqueda por imagen en la copia del catálogo grande.
- Ejecución del proveedor de IA en casos que lo requieran y seguimiento de límites o errores del servicio externo.
- Estado, avance y validez de la cola de embeddings y búsqueda semántica.
- Prueba final de regresión antes de desplegar en producción.

### Decisiones de alcance

- Se conservan los modos y shortcodes existentes, incluidos **Completo**, **Solo barra** y **Solo resultados**, con perfiles independientes y herencia de `cis_perfil`.
- No se incorpora la integración opcional con el motor nativo del buscador de WoodMart; sí se mantienen las plantillas compatibles de WooCommerce.
- No se incorporan filtros avanzados de búsqueda que puedan alterar la relevancia o el rendimiento.
- Las funciones existentes de licencias/SaaS se mantienen fuera del alcance de esta consolidación.

---

## [2.1.1] — 2026-10-26

### Añadido

- **Perfiles de búsqueda opcionales** con identificador propio y estado activo/inactivo.
- Inclusión y exclusión de categorías WooCommerce, con aplicación a categorías padre y descendientes.
- Perfiles independientes que comparten el mismo motor y la configuración de IA de la tienda.
- Personalización visual por perfil: colores, bordes, redondeo y texto del campo de búsqueda.
- Gestión de perfiles desde Configuración: creación, edición, activación, desactivación y eliminación.
- Presentación de cada perfil en un **desplegable** para mantener el panel administrativo compacto.
- Integración mediante **HTML personalizado** para insertar barras de búsqueda en cabeceras y otros elementos HTML.
- Renderizado de **buscador completo mediante HTML** en bloques HTML nativos compatibles de WordPress.
- Integración de perfiles en el **widget de Elementor**.
- Selector de perfil y de modo **Buscador completo / Solo barra** en el bloque Gutenberg.
- Separación de barra de cabecera y página independiente de resultados, con conservación del perfil seleccionado.
- Configuración de **página de resultados**, con posibilidad de utilizar la página actual cuando corresponda.
- Opción de visualización de resultados con **tarjetas propias** o **plantillas de producto WooCommerce compatibles**.
- Paginación de resultados de búsqueda.
- Documentación de inserción HTML, shortcode, Elementor, Gutenberg y PHP desde un desplegable del Dashboard.

### Mejorado

- Conservación del funcionamiento del **buscador predeterminado sin perfiles**.
- Sugerencias y resultados compatibles con los filtros de los perfiles.
- Tratamiento de SKU exactos, correcciones aproximadas y validación de interpretaciones en búsquedas con perfiles.
- Correcciones con mensaje visible «Interpretamos tu búsqueda…» cuando corresponde.
- Priorización de la búsqueda local y uso condicionado de funciones de IA.
- Comportamiento de las sugerencias al cambiar rápidamente el texto buscado.
- Compatibilidad de múltiples instancias del buscador dentro de una misma página.
- Posicionamiento de las sugerencias para su visualización en cabeceras.
- Diseño administrativo y explicación de los métodos de inserción.
- Versión del JavaScript de Gutenberg asociada a la modificación del archivo para reducir cargas obsoletas por caché.

### Estadísticas y exportaciones

- Protección para evitar registros repetidos de la misma consulta dentro de una petición de resultados.
- Exclusión del registro de nuevas búsquedas al navegar por páginas posteriores de resultados.
- Ajuste de **CSV** y **Excel** para mostrar el total de selecciones de un término una sola vez cuando ese término figura en varios tipos de búsqueda.
- Conservación de indicadores y estadísticas globales aunque la tienda tenga distintos buscadores y perfiles.
- Mantenimiento de la exportación desde el Dashboard, sin un submenú administrativo adicional.

### Compatibilidad

- Shortcode: `[compuciber_ai_search]` y alias `[ai_product_search]`.
- Shortcode en cabeceras: `[compuciber_ai_search solo_barra="si"]`.
- HTML personalizado en modo `solo_barra`.
- Gutenberg con perfiles y modo de visualización.
- Elementor con controles de presentación y perfiles.
- Inserción desde plantillas PHP mediante la función pública del plugin.

### Alcance de la versión

Las funcionalidades de búsqueda, perfiles, integración, presentación y administración correspondientes a esta versión están **desarrolladas**. Las integraciones de temas o constructores externos se adaptan a las capacidades disponibles en cada instalación.

---

## [2.0.0] — 2026-10-06

### Añadido

- Dashboard con estadísticas avanzadas de búsqueda y selección de productos.
- Indicadores de consultas con resultados, sin resultados, tasa de éxito y CTR.
- Consultas populares, productos seleccionados e historial de actividad.
- Aprendizaje de relevancia basado en interacciones históricas.
- Exportación administrativa de estadísticas.
- Widget nativo de Elementor con controles de apariencia.
- Arquitectura de proveedores de IA con configuración independiente para Gemini y OpenAI.
- Selección centralizada del proveedor y de los modelos correspondientes.
- Sistema cliente de licencias: identificación de instalación, validación, caché y periodo de gracia.
- Componentes cliente para integración con servicios externos de licencias y SaaS.

### Mejorado

- Separación de opciones y modelos de los distintos proveedores de IA.
- Estructura modular de indexación, resultados y proveedores.
- Compatibilidad responsive del buscador.
- Gestión de embeddings vinculados al proveedor y modelo configurados.

---

## [1.2.0]

### Añadido

- Búsqueda por imagen.
- Interpretación de fotografías mediante IA.
- Conversión de la interpretación visual en una consulta contra productos existentes en WooCommerce.

---

## [1.1.0]

### Añadido

- Búsqueda por voz mediante Web Speech API en navegadores compatibles.
- Conversión del reconocimiento de voz en consultas del buscador.

---

## [1.0.0]

### Versión inicial

- Plugin instalable en WordPress con WooCommerce.
- Índice local de productos y mecanismos de actualización.
- Búsquedas por nombre, SKU, marca, modelo, categorías y atributos.
- Autocompletado de productos.
- Búsqueda aproximada, sinónimos y correcciones.
- Ordenación por relevancia y coincidencias técnicas.
- Búsqueda semántica y embeddings.
- Integración inicial con proveedores de IA.
- Panel de administración.
- Inserción mediante shortcode, integración PHP y bloque Gutenberg.
- Funcionamiento local sin depender de una API de IA para las consultas ordinarias.

---

## Nota sobre el servicio de licencias/SaaS

El plugin contiene el **cliente y las funciones de conexión** con servicios externos de licencias/SaaS. El backend de un servicio SaaS comercial es independiente del código del plugin y no se considera incluido en este historial como parte del ZIP distribuible.

**Compuciber AI Search — versión 2.1.3**
