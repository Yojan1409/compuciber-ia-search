# CHANGELOG — Compuciber AI Search

**Propietario del desarrollo:** COMPUCIBER S.A.C.

Este archivo documenta la evolución funcional del código fuente entregado.

## [2.0.0] - 2026-10-06

### Añadido

- Estadísticas avanzadas.
- Total de búsquedas, búsquedas con/sin resultados y selecciones.
- Tasa de éxito y CTR global.
- Estadísticas por tipo de búsqueda.
- Historial diario.
- Búsquedas populares y búsquedas sin resultados.
- Productos seleccionados y consultas que generan selecciones.
- Aprendizaje de ranking basado en selecciones históricas.
- Activación/desactivación del aprendizaje de ranking.
- Sistema cliente de licencias.
- UUID por instalación.
- Identificación de dominio e instalación.
- Estados de licencia.
- Validación manual y periódica.
- Caché y periodo de gracia.
- Soporte del lado cliente para límites de instalaciones.
- Preparación para múltiples tiendas.
- Cliente HTTP y contrato para futuro servicio externo de licencias/SaaS.
- Widget nativo de Elementor.
- Controles de presentación para Elementor.
- Configuración independiente de modelos para Gemini.
- Configuración independiente de modelos para OpenAI.
- Persistencia de configuración al cambiar de proveedor.
- Selector centralizado de proveedor mediante `compuciber_obtener_proveedor_ia()`.

### Cambiado

- Separación completa de opciones de modelos Gemini y OpenAI.
- Actualización de indexación y resultados para reconocer el modelo de embeddings del proveedor activo.
- Centralización de la creación del proveedor de IA.
- Reducción de código duplicado entre texto, imagen y embeddings.
- Preparación de la arquitectura para incorporar proveedores adicionales.
- Mejoras responsive.
- Ampliación de la arquitectura de licencias e instalaciones.
- Versión principal actualizada a 2.0.0.

### Eliminado / limpieza

- Eliminación de referencias activas a las antiguas opciones compartidas:
  - `compuciber_modelo_ia`
  - `compuciber_modelo_embeddings`
  - `compuciber_modelo_multimodal`
  - `compuciber_modelo_voz`
- Eliminación del archivo de desarrollo `includes/resultado/hola.txt`.
- Limpieza progresiva de trazas de depuración para la entrega.

### Verificado

- Gemini después de la separación de modelos.
- Persistencia Gemini → OpenAI → Gemini.
- Búsqueda local sin API de IA.
- Autocomplete.
- Fuzzy search.
- SKU.
- Coincidencias técnicas.
- Búsqueda semántica con Gemini.
- Búsqueda por imagen con Gemini.
- Instalación externa mediante ZIP.
- Funcionamiento con más de un tema.
- Estadísticas avanzadas.
- Aprendizaje de ranking.
- Widget Elementor.
- Arquitectura cliente de licencias.

### Propiedad y entrega

La V2.0.0 se documenta para su entrega a **COMPUCIBER S.A.C.** incluyendo:

- código fuente;
- ZIP instalable;
- esquema/rutinas de base de datos;
- documentación técnica;
- documentación de instalación;
- dependencias;
- APIs externas;
- configuración separada de credenciales;
- repositorio Git;
- historial/versionado.

Los servicios externos no forman parte del código fuente del buscador salvo acuerdo expreso.

### Pendiente antes del cierre de producción

- Finalizar limpieza de `error_log()` de depuración.
- Validar OpenAI con una API key real.
- Revisar migraciones de base de datos para actualización.
- Ejecutar regresión final del ZIP V2.0.0.
- Probar actualización sobre la instalación externa existente.

---

## [1.2.0]

### Añadido

- Búsqueda por imagen.
- Interpretación multimodal.
- Conversión del análisis visual en consulta de productos.
- Integración de imagen con el motor local.

### Mejorado

- Flujo multimodal.
- Integración con IA.
- Presentación responsive del buscador.

---

## [1.1.0]

### Añadido

- Búsqueda por voz.
- Integración con Web Speech API.
- Reconocimiento configurado para `es-PE`.
- Conversión y envío de voz a consulta de búsqueda.

---

## [1.0.0]

### Añadido

- Plugin instalable mediante ZIP.
- Integración WordPress/WooCommerce.
- Índice propio de productos.
- Indexación automática.
- Productos simples y variables.
- Búsqueda por nombre.
- Búsqueda por SKU.
- Marca y modelo.
- Categorías, atributos y etiquetas.
- Autocomplete.
- Fuzzy search.
- Corrección de errores.
- Sinónimos.
- Ranking.
- Coincidencias técnicas.
- Búsqueda semántica.
- Integración inicial con Gemini.
- Arquitectura para OpenAI.
- Embeddings.
- Panel administrativo.
- Configuración de API.
- Shortcode `[compuciber_ai_search]`.
- Integración Gutenberg.
- Integración PHP.
- Funcionamiento local sin IA.

---

## Convención de entrega

El repositorio Git y este historial forman parte de la trazabilidad del proyecto. Las credenciales privadas y secretos no deben incorporarse al historial del repositorio ni a los paquetes ZIP.
