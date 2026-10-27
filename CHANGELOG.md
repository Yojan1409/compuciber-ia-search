# Historial de cambios — Compuciber AI Search

**Proyecto:** Compuciber AI Search  
**Titular:** COMPUCIBER S.A.C.  
**Versión documentada:** 2.1.1  
**Estado:** desarrollo funcional completados

Este historial reúne las funcionalidades incorporadas a Compuciber AI Search y las mejoras de integración, administración y presentación realizadas durante el desarrollo.

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

**Compuciber AI Search — versión 2.1.1**
