# Historial de cambios — Compuciber AI Search

**Proyecto:** Compuciber AI Search  
**Titular:** COMPUCIBER S.A.C.  
**Versión oficial del plugin:** 2.1.3  
**Última actualización documental:** 15 de noviembre de 2026  
**Revisión interna registrada:** cambios hasta el N.º 98  
**Estado:** versión candidata para entrega; comparación visual adicional opcional/experimental

> **Nota de versionado:** los números 62–98 identifican cambios internos realizados durante el desarrollo, **no** versiones oficiales ni lanzamientos independientes. El archivo principal del ZIP revisado sigue declarando `2.1.3`. Se conserva íntegramente el historial oficial anterior. Las notas de «pendientes» de las secciones antiguas reflejan el estado en la fecha en que se redactaron, no necesariamente el estado actual.

## Actualización de mantenimiento — 2026-11-15 (cambios internos posteriores a la documentación del 12/11)

### Búsqueda, sinónimos y relevancia (cambios previos al 77)

- **Cambio 64:** canonización complementaria de expresiones compuestas, por ejemplo «ordenador portátil HP» → «laptop HP». La interpretación alternativa puede mejorar la coincidencia sin sustituir a la consulta original ni alterar la prioridad de SKU exactos.
- **Cambio 65:** reconocimiento de sinónimos compuestos en autocompletado, con prioridades de título y conservación de la consulta original.
- **Cambio 66:** niveles configurables de precisión **Amplia**, **Equilibrada** y **Alta**. El filtrado de precisión se aplica antes de la paginación y conserva las verificaciones de WooCommerce.
- **Cambio 70:** tratamiento específico para no priorizar laptops completas cuando se busca un **cargador para laptop**.
- **Cambio 74:** herramienta administrativa para renovar la caché de interpretaciones visuales.
- **Cambio 75:** prioridad de identidad para resultados procedentes de fotografías; busca evitar que un combo de teclado y mouse desplace a un mouse individual. Mantiene el ranking habitual de consultas no visuales.
- **Cambio 76:** respaldo de corrección ortográfica cuando la interpretación de Gemini no es válida, en las condiciones admitidas por el motor.
- Otras mejoras intermedias del motor se mantienen incorporadas en el código fuente; no se asigna una descripción individual a números sin evidencia específica en el historial disponible.

### Calidad de imágenes y configuración de comparación visual (77–80)

- **Cambio 77:** calidad de la imagen optimizada para IA de **80 a 85**. Se mantiene la dimensión máxima configurada de **1200 × 1200 píxeles**, sin incremento generalizado de resolución.
- **Cambio 78:** información de la opción WooCommerce **«Ocultar productos agotados»** en el diagnóstico manual de paginación y elegibilidad.
- **Cambio 79:** casilla **«Comparación visual con catálogo»** en Configuración, **desactivada por defecto** y con advertencia sobre tiempo de respuesta y consumo adicional de IA.
- **Cambio 80:** revisión del almacenamiento temporal de la búsqueda para evitar conservar fotografías completas en Base64 dentro del token de WordPress.

### Motor experimental de comparación visual con catálogo (81–91)

- **Cambio 81:** etapa opcional de prioridad visual sobre productos ya puntuados; no crea productos ni sustituye el motor de relevancia.
- **Cambio 82:** comparación multimodal adicional con **Gemini** y fotografías del catálogo WooCommerce. Selecciona un conjunto pequeño de imágenes principales en lugar de enviar el catálogo completo a la API.
- **Cambio 83:** preparación de la comparación para recibir identificadores proporcionados por el ranking real, en vez de recorrer y comparar independientemente todo el catálogo.
- **Cambio 84:** copia temporal controlada de la fotografía procesada y limpieza programada, evitando almacenar la fotografía completa en el token de resultados.
- **Cambio 85:** conexión de la comparación al flujo de resultados, después del cálculo de candidatos, para obtener puntuaciones visuales reutilizables. Se aplica una caché temporal a la comparación.
- **Cambio 86:** selección de imágenes que considera los candidatos mejor clasificados y también permite rescatar candidatos posteriores por marca/modelo, con límites de cantidad. No modifica directamente el ranking público en esta etapa.
- **Cambio 87:** sustitución de la prioridad visual absoluta por una **bonificación acotada al 5 % del puntaje de ordenación**, preservando los grupos de prioridad del cambio 75.
- **Cambio 88:** separación de errores de transporte/HTTP de una comparación válida sin coincidencias. Las fallas detectadas utilizan una espera de caché más corta para evitar nuevas llamadas inmediatas.
- **Cambio 89:** selección de candidatos cuyo título comienza por el tipo de producto **o por la marca**, con reglas para no favorecer combos de teclado y mouse cuando la foto corresponde a un producto individual.
- **Cambio 90:** posibilidad de aplicar la bonificación visual también a coincidencias cuyos títulos comienzan por la marca, conservando los grupos de identidad y el máximo del 5 %.
- **Cambio 91:** diferenciación de respuestas JSON inválidas de Gemini mediante un error controlado, sin interpretarlas como coincidencias negativas válidas.
- **Alcance y limitaciones:** esta comparación adicional **solo está implementada para Gemini**, es optativa y puede consumir cuota. Se seleccionan hasta 100 identificadores del ranking para preparar candidatos; el procesamiento de fotografías examina una selección acotada y envía **hasta seis imágenes principales**. No equivale a un índice completo de embeddings visuales, ni garantiza identificar modelos físicamente indistinguibles.

### Diagnóstico y rendimiento (92–94)

- **Cambio 92:** medición, en el diagnóstico local de relevancia, del **tiempo de ejecución en milisegundos** y de la **cantidad de consultas SQL**. No se agrega un motor de búsqueda paralelo.
- **Cambio 93:** precarga de cachés de publicaciones, metadatos y términos de WordPress para conjuntos de resultados suficientemente grandes, antes de las comprobaciones de elegibilidad WooCommerce. Se mantienen puntuaciones, filtros, orden y verificación de productos; el SKU exacto conserva su recorrido eficiente.
- **Cambio 94:** actualización dinámica del panel **Diagnóstico de búsquedas y relevancia** mediante JavaScript y petición al mismo administrador, sin recarga visual completa. La interfaz conserva una ruta de navegación tradicional de respaldo; el tiempo de procesamiento PHP sigue existiendo.

#### Mediciones de referencia en tienda de pruebas (15/11/2026)

| Consulta | Antes del cambio 93 | Después del cambio 93 | Resultados WooCommerce observados |
| --- | ---: | ---: | ---: |
| `laptop` | 1.353,0 ms · 2.043 SQL | **738,6 ms · 61 SQL** | 204 |
| `mouse Logitech` | 496,3 ms · 385 SQL | **443,0 ms · 37 SQL** | 75 |
| `impresora Epson` | 409,0 ms · 149 SQL | **402,3 ms · 40 SQL** | 15 |
| SKU exacto `A200SI5` | 3,8 ms · 7 SQL | **3,0 ms · 7 SQL** | 1 |

- Las capturas de diagnóstico muestran **3.738 registros evaluados** para consultas generales; `laptop` mantuvo **648 coincidencias puntuadas**, 204 resultados WooCommerce y 17 páginas. `mouse Logitech` mantuvo 122 coincidencias puntuadas, 75 resultados WooCommerce y siete páginas. En las primeras páginas observadas, los productos recuperados eran aptos para búsqueda.
- Estos números documentan una **prueba concreta**, no una garantía de rendimiento en todos los servidores. Las cachés, el catálogo y el entorno de WordPress pueden modificar los tiempos. Se mantiene la obligación de preservar relevancia y resultados correctos.

### Interfaz de análisis de imagen y personalización (95–97)

- **Cambio 95:** el mensaje **«Analizando imagen con IA...»** utiliza mayor grosor y admite personalización del color del texto y del punto mediante variables CSS (`--compuciber-status-text-color` y `--compuciber-status-dot-color`).
- **Cambio 96:** el botón de cámara permanece fijo y desactivado durante el análisis, eliminando su rotación y sustitución visual por reloj de arena. El indicador inferior comunica el estado del procesamiento.
- **Cambio 97:** controles de color del texto y del punto de estado en la integración de Elementor, configurables por instancia del widget.

### OpenAI y mantenimiento de proveedores (98)

- **Cambio 98:** registro controlado de errores de transporte y HTTP del proveedor **OpenAI para imágenes** en el logger de WooCommerce, sin registrar fotografías ni API keys; mensajes repetidos limitados temporalmente.
- Los proveedores OpenAI y Gemini tienen rutas propias para interpretación de imagen; la **comparación visual secundaria con el catálogo** permanece específica de Gemini. No se declara equivalencia funcional completa entre ambos proveedores.

### Decisiones de cierre y compatibilidad

- No se introdujeron filtros avanzados adicionales que pudieran cambiar inesperadamente los productos devueltos, ni una integración alternativa con el motor nativo de WoodMart.
- Se preservan la búsqueda local, la paginación, los perfiles General/Reacondicionados y demás perfiles configurados, las exclusiones de categoría, la visibilidad y la configuración de agotados de WooCommerce.
- La búsqueda local sigue siendo utilizable sin embeddings semánticos completos. La generación de embeddings continúa sujeta a cuotas de la API y al procesamiento por lotes actualmente configurado.
- Los controles de comparación visual son **opcionales y desactivados por defecto**. Activarlos puede añadir latencia, consumo de Gemini y comportamiento experimental de reordenación.
- Esta revisión de documentación **no cambia** el encabezado de versión del plugin, el esquema de base de datos, las opciones de instalación ni las credenciales.

---

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
