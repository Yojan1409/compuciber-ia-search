# Documentación técnica — Compuciber AI Search

**Proyecto:** Buscador inteligente multimodal para WordPress y WooCommerce  
**Titular indicado en el requerimiento:** COMPUCIBER S.A.C.  
**Versión declarada por el plugin:** `2.1.3`  
**Base de esta documentación:** código fuente de `compuciber-ia-search-prueba-98.zip`, recibido en noviembre de 2026  
**Fecha de revisión:** 15 de noviembre de 2026  
**Alcance:** arquitectura, datos, procesos, dependencias, interfaces, operación y mantenimiento

> **Naturaleza del documento.** Esta guía describe la implementación observable en el código fuente. No certifica que las funciones externas hayan sido probadas en todas las versiones de PHP, WordPress, WooCommerce, temas o proveedores de IA. La numeración interna de cambios hasta el 98 no equivale a una versión oficial del plugin. La documentación de uso e instalación está en `README.md`; las modificaciones de cada entrega, en `CHANGELOG.md`.

---

## 1. Propósito y decisiones de arquitectura

Compuciber AI Search agrega un motor híbrido de búsqueda de productos a WordPress/WooCommerce. Ofrece consultas de texto y SKU, correcciones y sinónimos, autocompletado, perfiles de catálogo, voz, reconocimiento de fotografías mediante proveedores multimodales y funcionalidades semánticas que utilizan embeddings cuando están disponibles.

La arquitectura distingue tres responsabilidades:

1. **WooCommerce:** fuente autoritativa del producto, su estado de publicación, visibilidad, precio, inventario, variaciones y página de detalle.
2. **Índice local:** copia auxiliar de campos de búsqueda y representaciones semánticas para evaluar consultas y ordenar candidatos sin depender permanentemente de una API externa.
3. **Proveedor de IA:** interpreta lenguaje natural o fotografías y genera embeddings; sus respuestas se convierten en señales de búsqueda, **no en productos inventados**.

Se favorece el funcionamiento local ante una caída, desactivación o agotamiento de cuota de IA, dentro de las capacidades de cada modalidad. Las funciones que requieren analizar una imagen con un modelo multimodal sí dependen de un proveedor disponible.

## 2. Estructura del código y responsabilidades

La carpeta distribuible debe tener **una única raíz** `compuciber-ia-search/`.

| Ruta | Responsabilidad observada |
|---|---|
| `compuciber-ai-search.php` | Punto de entrada; carga módulos, registra recursos, AJAX de imagen, shortcodes, Gutenberg, integraciones frontend y lógica de representación. |
| `includes/configuracion.php` | Menú, registro de opciones, páginas destino, perfiles y configuraciones de administración. |
| `includes/admin-panel.php` | Dashboard, configuración visible, comprobaciones, simulación de relevancia, diagnóstico y mediciones. |
| `includes/resultados.php` | Motor de búsqueda, recuperación de índice, puntuaciones, tratamiento visual y conexión a la consulta final de WooCommerce. |
| `includes/resultado/normalizacion.php` | Normalización de términos. |
| `includes/resultado/puntuacion.php` | Cálculo de señales y puntuaciones de productos. |
| `includes/resultado/consulta.php` | Elegibilidad comercial, filtrado por perfil, preservación de orden y paginación mediante `WP_Query`. |
| `includes/resultado/embeddings.php` | Funciones de apoyo a la comparación semántica en resultados. |
| `includes/sugerencias.php`, `includes/sugerencias-ia.php` | Autocompletado y apoyo opcional de IA para sugerencias. |
| `includes/correcciones.php`, `includes/admin-correcciones.php`, `includes/fuzzy.php`, `includes/sinonimos.php` | Corrección, coincidencia aproximada y administración de sinónimos. |
| `includes/imagenes.php` | Optimización y caché de interpretaciones visuales. |
| `includes/ia.php`, `includes/proveedores/` | Abstracción de proveedor, interfaces y adaptadores Gemini/OpenAI para texto, imágenes y embeddings. |
| `includes/indexacion.php`, `includes/indexacion/` | Tablas, extracción de datos, sincronización de productos, indexación inicial, auditoría, reparación y cola de embeddings. |
| `includes/estadisticas.php`, `includes/exportacion-excel.php` | Registro de búsquedas/selecciones y exportaciones administrativas. |
| `includes/licencias.php`, `includes/licencias/` | Componentes de licenciamiento presentes en el código; se documentan como módulos existentes, sin afirmar que haya una plataforma SaaS independiente incluida. |
| `assets/js/buscador-v59.js`, `assets/css/buscador.css` | Comportamiento del buscador y estilos frontend. |
| `blocks/buscadores/index.js` | Bloque de Gutenberg. |
| `elementor/widget-ai-search.php` | Widget y controles de Elementor. |

**Punto de mantenimiento:** no editar una única copia de `resultados.php` para eliminar verificaciones que aparecen en otros módulos. La búsqueda se forma en varias etapas, y los perfiles, el inventario y la visibilidad se verifican también al construir los resultados finales.

## 3. Dependencias y entorno de ejecución

**Obligatorias:** instalación activa de WordPress, WooCommerce y PHP compatible con ambas. El plugin comprueba la clase `WooCommerce` y muestra un aviso administrativo cuando no está disponible. Utiliza la base de datos y las APIs nativas de WordPress, además de clases y funciones de WooCommerce.

**Componentes usados según funcionalidad:** `WP_Query`, `$wpdb`, Options API, transients, hooks, WP-Cron, editor de imágenes de WordPress (`wp_get_image_editor`), cliente HTTP de WordPress (`wp_remote_post`), `admin-ajax.php` y Action Scheduler aportado por WooCommerce para determinadas tareas individuales de embeddings.

**Opcionales:** Elementor, editor Gutenberg, proveedores Google Gemini y OpenAI. La integración con un tema específico no es un requisito de instalación.

**Frontend:** los archivos JavaScript y CSS necesarios están incluidos en el ZIP. El código revisado no necesita que el administrador ejecute Composer o un proceso de compilación para instalar esta distribución. La disponibilidad de un editor de imágenes adecuado en PHP/servidor es importante para la modalidad visual.

**Versiones mínimas:** el encabezado principal indica `Version: 2.1.3`, pero **no declara formalmente** `Requires PHP` ni `Requires at least`; no debe prometerse una matriz exacta de compatibilidad sin pruebas específicas. Se recomienda validar cada destino con sus versiones activas de WordPress, WooCommerce, PHP y extensiones gráficas.

## 4. Esquema físico de base de datos

Los nombres tienen un prefijo dinámico `$wpdb->prefix`. `wp_` se usa aquí solo como ejemplo. Las tablas se crean/actualizan desde el código con `dbDelta()`: **no requieren importar manualmente un volcado SQL de productos**.

### 4.1. Tabla `{prefijo}compuciber_product_index`

Definición en `includes/indexacion/tabla.php`. Versión interna `COMPUCIBER_INDICE_VERSION = '2.4'`; opción de control `compuciber_indice_version`.

| Campo | Tipo SQL | Uso |
|---|---|---|
| `id` | `bigint(20) unsigned` | ID del producto; clave primaria. |
| `nombre`, `sku`, `marca`, `modelo` | `varchar(255)` | Identificación comercial. |
| `categorias`, `etiquetas` | `text` | Agrupaciones y etiquetas de búsqueda. |
| `atributos`, `contenido`, `variaciones` | `longtext` | Características, texto de producto y variaciones. |
| `precio`, `precio_oferta` | `decimal(20,6)` | Referencias comerciales indexadas. |
| `stock` | `varchar(255)` | Estado o cantidad indexada. |
| `texto_busqueda` | `longtext` | Texto consolidado para búsqueda. |
| `url`, `imagen` | `varchar(2048)` | Enlace de producto y fotografía principal. |
| `galeria`, `metadatos` | `longtext` | Fotografías secundarias y metadatos permitidos. |
| `texto_embedding`, `embedding` | `longtext` | Texto semántico y vector almacenado. |
| `hash_embedding` | `varchar(64)` | Control de vigencia de representación. |
| `embedding_proveedor` | `varchar(50)` | Proveedor utilizado. |
| `embedding_modelo` | `varchar(100)` | Modelo utilizado. |
| `actualizado` | `datetime` | Fecha de actualización del registro. |

Índices declarados: `PRIMARY KEY (id)`, `KEY sku (sku(191))`, `KEY marca (marca(191))`, `KEY modelo (modelo(191))` y `KEY actualizado (actualizado)`.

El índice es auxiliar: un producto indexado no queda autorizado automáticamente para mostrarse. La verificación comercial final se realiza con WooCommerce.

### 4.2. Tabla `{prefijo}compuciber_search_stats`

Definición en `includes/estadisticas.php`.

| Campo | Tipo SQL | Uso |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Clave primaria. |
| `busqueda` | `VARCHAR(255)` | Consulta registrada. |
| `busqueda_interpretada` | `VARCHAR(255)` | Consulta interpretada cuando corresponde. |
| `tipo` | `VARCHAR(30)` | Modalidad, predeterminada `texto`. |
| `resultados` | `INT UNSIGNED` | Cantidad asociada a la búsqueda. |
| `fecha` | `DATETIME` | Fecha del registro. |

Índices: clave primaria `id`, índice por `busqueda` e índice por `fecha`.

### 4.3. Tabla `{prefijo}compuciber_search_clicks`

Definición en `includes/estadisticas.php`.

| Campo | Tipo SQL | Uso |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Clave primaria. |
| `producto_id` | `BIGINT UNSIGNED` | ID del producto seleccionado. |
| `busqueda` | `VARCHAR(255)` | Consulta asociada. |
| `origen` | `VARCHAR(20)` | Origen de la interacción, predeterminado `desconocido`. |
| `fecha` | `DATETIME` | Fecha del registro. |

Índices: clave primaria `id`, índice por `producto_id` e índice por `fecha`.

La actualización de estadísticas utiliza `COMPUCIBER_ESTADISTICAS_VERSION = '2.2'`, controlada por `compuciber_estadisticas_version`, y contiene una verificación adicional de la columna `origen`.

**Relaciones:** `id`/`producto_id` corresponden lógicamente a productos WooCommerce. El SQL revisado **no declara claves foráneas** entre estas tablas y `wp_posts`.

## 5. Ciclo de vida de la indexación

`includes/indexacion/producto.php` extrae información de `WC_Product` e inserta o actualiza filas del índice. El código lee, entre otros, imagen principal mediante `get_image_id()` y galería mediante `get_gallery_image_ids()`. Las fotografías se referencian por URL; **el índice no almacena los archivos binarios de las imágenes**.

`includes/indexacion/hooks.php` observa eventos como `save_post_product`, `woocommerce_new_product`, `woocommerce_update_product`, cambios en variaciones, cambios de stock, eliminación, papelera y restauración. Para evitar repetir indexaciones durante una misma petición, agrupa operaciones pendientes hasta `shutdown` cuando corresponde.

Los cambios de stock se procesan sin forzar necesariamente una nueva generación semántica: stock y texto de embedding no son la misma información.

La indexación inicial, la auditoría integral y la reparación por lotes están distribuidas en `includes/indexacion/activacion.php`; el panel administrativo permite iniciar o inspeccionar determinadas operaciones. El mantenimiento de un índice debe distinguir un producto realmente eliminado de un producto temporalmente oculto o agotado.

## 6. Cola de embeddings semánticos

El código de `includes/indexacion/embeddings-lotes.php` establece un **máximo de cinco productos por lote** en `compuciber_generar_lote_embeddings()` y maneja una cola programada aproximadamente cada cinco minutos, según la configuración y la actividad del sistema.

Existen controles de bloqueo para evitar duplicación de trabajo y un presupuesto temporal en el procesamiento por lotes. Los embeddings se almacenan con referencia a proveedor, modelo y huella del texto, por lo que una representación desactualizada puede detectarse.

También existen tareas por producto mediante Action Scheduler cuando está disponible e inicializado en WooCommerce (`compuciber_embedding_producto_pendiente`).

El rendimiento de esta cola depende de las cuotas del proveedor, la disponibilidad de la API, las tareas programadas y el servidor. **Una suscripción de IA de pago no aumenta por sí sola el límite de cinco elementos** establecido en el código.

La búsqueda local puede funcionar aun cuando no existan embeddings de todos los productos. El índice visual completo de fotografías **no forma parte** de este esquema.

## 7. Flujo de búsqueda por texto

1. El cliente introduce una consulta mediante shortcode, bloque, widget o formulario frontend.
2. Se normaliza y, si corresponde, se aplican correcciones/sinónimos o interpretación asistida por IA.
3. `compuciber_buscar_productos_inteligente()` verifica primero si la consulta corresponde a un SKU exacto. Para variaciones, comprueba la variación y su producto padre.
4. Si no se resuelve por la vía rápida de SKU, consulta los campos necesarios del índice local. Solo agrega los campos de embeddings cuando está habilitada la búsqueda semántica.
5. Aplica condiciones de perfil y calcula puntuaciones con las funciones de `includes/resultado/puntuacion.php`.
6. Ordena candidatos y prepara identificadores elegibles.
7. `compuciber_consulta_productos()` comprueba de nuevo restricciones de perfil, publicación, visibilidad y stock con WooCommerce y arma la paginación.
8. La presentación utiliza las modalidades y controles configurados para la tienda.

La visibilidad no depende únicamente del estado de publicación. `compuciber_producto_visible_en_busqueda()` exige que el producto esté publicado, con visibilidad `visible` o `search`, respeta la opción `woocommerce_hide_out_of_stock_items` y aplica el filtro `woocommerce_product_is_visible`.

La cantidad por página se obtiene de la configuración de WooCommerce y filtros del tema, con límite máximo de seguridad en el código. Los parámetros de navegación relevantes incluyen `cis_q`, `cis_page` y `cis_perfil`.

**Precisión y orden:** modificar el número de candidatos, los umbrales o las validaciones puede cambiar los resultados. No optimizar a costa de descartar productos que quedarían en páginas posteriores.

## 8. Autocompletado y divergencia con resultados finales

El autocompletado reside en `includes/sugerencias.php` y dispone de acciones AJAX públicas. Puede existir una diferencia en las etapas de selección entre sugerencias y resultados completos; no se deben equiparar mecánicamente sus listas sin revisar visibilidad, perfil, stock y paginación final.

Ante un producto que aparece en sugerencias pero no en resultados, utilizar primero los diagnósticos ya incluidos para comprobar el estado de publicación, elegibilidad, categorías, stock y la opción «Ocultar productos agotados». No eliminar el filtro comercial como solución rápida.

## 9. Flujo de búsqueda por imagen

`compuciber_ajax_busqueda_imagen()` recibe la imagen mediante AJAX, valida un nonce y la configuración de la modalidad, limita el archivo a **5 MB** y acepta JPEG/JPG, PNG y WebP tras verificar formato declarado y MIME real.

La fotografía se optimiza mediante `includes/imagenes.php`. En esta versión, la imagen se ajusta a **1200 × 1200 píxeles como máximo**, sin cambiar la proporción, con calidad **85**. Se calcula una huella para recuperar interpretaciones previas de caché, distinguiendo proveedor, modelo y versión de caché visual.

El adaptador Gemini u OpenAI interpreta producto, marca, modelo y demás características según el modelo elegido. La interpretación alimenta la búsqueda normal contra WooCommerce. Se pasa mediante un token temporal generado para la búsqueda. Las imágenes procesadas se limpian al finalizar la petición correspondiente.

### Comparación visual opcional con el catálogo

La opción `compuciber_comparacion_visual_catalogo` está **desactivada por defecto**. Con Gemini seleccionado, el sistema puede conservar de forma temporal y controlada una copia de la fotografía y, después del ranking inicial, enviar una selección de fotografías principales del catálogo para una comparación adicional.

El código recibe hasta 100 identificadores candidatos de la búsqueda, prepara un subconjunto y envía **hasta seis imágenes candidatas** para comparar. No compara las miles de fotografías de WooCommerce una por una. La selección adicional considera tipo, marca, modelo y el orden de la búsqueda. Las fotografías deben estar disponibles localmente para esta implementación.

Las coincidencias aceptadas se registran como puntuaciones temporales. Su influencia se limita a una bonificación de **hasta 5 %** dentro de los grupos de prioridad definidos para la búsqueda visual; la comparación no debe reemplazar la lógica de stock, perfil y elegibilidad.

El control de errores distingue algunos fallos de Gemini de comparaciones válidas sin coincidencias; la caché evita repetir solicitudes inmediatamente. Una puntuación del modelo **no equivale a una identidad exacta garantizada**. Esta función es experimental y exclusiva de Gemini; el análisis de imágenes de OpenAI no incluye la segunda comparación visual contra catálogo.

**Almacenamiento temporal:** el proceso utiliza archivos temporales con medidas de limpieza y programación WP-Cron. No guardar imágenes completas en las tablas permanentes del plugin ni introducir credenciales en el frontend.

## 10. Integraciones de proveedores de IA

La capa `includes/ia.php` usa implementaciones de `includes/proveedores/interfaz.php`, `gemini.php` y `openai.php`.

| Operación | Gemini | OpenAI |
|---|---|---|
| Texto | `includes/proveedores/gemini/texto.php` | `includes/proveedores/openai/texto.php` |
| Embeddings | `includes/proveedores/gemini/embeddings.php` | `includes/proveedores/openai/embeddings.php` |
| Imagen | `includes/proveedores/gemini/imagen.php` | `includes/proveedores/openai/imagen.php` |

El código envía solicitudes HTTP desde PHP usando el cliente HTTP de WordPress. Se utilizan servicios externos de `generativelanguage.googleapis.com` y `api.openai.com` según proveedor y función. La selección de modelos y claves se guarda mediante opciones de WordPress, no en el JavaScript entregado al navegador.

**Operación:** documentar para el administrador el proveedor seleccionado, modelo, límites de cuota, compatibilidad de entrada multimodal y manejo de errores. No registrar claves API ni contenido de fotografías en registros de diagnóstico. Las cuotas y nombres de modelos de los proveedores pueden variar; verificar su validez en el momento de configurarlos.

## 11. Opciones y configuración

`includes/configuracion.php` registra opciones como:

- `compuciber_proveedor_ia`, `compuciber_gemini_api_key`, `compuciber_openai_api_key`.
- Modelos independientes de texto, embeddings y multimodal para Gemini y OpenAI.
- `compuciber_autocompletado`, `compuciber_correccion`, `compuciber_busqueda_semantica`, `compuciber_busqueda_voz`, `compuciber_busqueda_imagen`.
- `compuciber_comparacion_visual_catalogo`, `compuciber_estadisticas`, `compuciber_aprendizaje_ranking`.
- `compuciber_pagina_resultados`, `compuciber_modo_resultados`, `compuciber_precision_resultados`.

Los perfiles se almacenan mediante opciones de WordPress y se administran desde el panel. La modalidad **Solo resultados** hereda `cis_perfil` cuando la consulta procede de una barra vinculada a un perfil. Las categorías permitidas y excluidas no deben perderse durante redirecciones o cambios de plantilla.

Estas opciones son datos de configuración de **cada tienda**: el ZIP no debe incluir las claves de la tienda del desarrollador ni las del cliente.

## 12. Interfaces de integración

**Shortcodes:** `[compuciber_ai_search]` y su alias `[ai_product_search]`. El shortcode principal admite modalidades como `solo_barra`, `solo_resultados` y `perfil`.

**PHP:** función `compuciber_ai_search()` para integración en plantillas; se puede recurrir a `do_shortcode()` cuando hagan falta atributos.

**Gutenberg:** bloque dinámico `compuciber/ai-search`, con atributos de perfil, solo barra y solo resultados, registrado en el archivo principal.

**Elementor:** widget `Compuciber_AI_Search_Elementor_Widget` cargado solamente si Elementor registra sus widgets. El archivo contiene controles visuales, incluidos colores del texto y punto de estado del análisis de imágenes.

**Inserción HTML:** existe una acción AJAX que renderiza la barra para integraciones de tipo HTML, validando que un perfil solicitado exista y esté activo.

**Frontend:** `assets/js/buscador-v59.js` controla interacciones, peticiones AJAX, estados visuales y navegación. `assets/css/buscador.css` aplica la apariencia y permite personalizar el estado con `--compuciber-status-text-color` y `--compuciber-status-dot-color`.

## 13. Interfaces AJAX y seguridad de las solicitudes

Principales acciones identificadas en el código:

| Acción / hook | Uso |
|---|---|
| `compuciber_tokens_publicos` | Renueva nonces públicos, sin entregar claves API. |
| `compuciber_busqueda_imagen` | Recepción, validación e interpretación visual. |
| `compuciber_sugerencias` | Autocompletado público. |
| `compuciber_sugerencia_ia` | Apoyo de IA para sugerencias. |
| `compuciber_registrar_seleccion` | Registro opcional de interacción con un producto. |
| `compuciber_renderizar_buscador_html` | Devuelve marcado de barra para integración HTML. |
| `compuciber_progreso_lotes` | Consulta de progreso administrativo de indexación. |

Otras operaciones administrativas utilizan acciones `admin_post_*` para indexación, auditoría, reparación, exportación, cola de embeddings y configuración. No deben exponerse indiscriminadamente al público: preservar las comprobaciones existentes de permisos, nonces y validación de parámetros al mantenerlas.

## 14. Diagnóstico, estadísticas y observabilidad

El panel **AI Product Search** contiene Dashboard, Configuración, Sinónimos, Correcciones y Diagnóstico y registros. Las estadísticas y sus exportaciones pertenecen al Dashboard, no a un menú independiente de estadísticas.

El diagnóstico de búsquedas y relevancia permite inspeccionar las coincidencias, elegibilidad de WooCommerce, páginas, puntuaciones y estado de la configuración «Ocultar productos agotados». También dispone de una medición local de tiempo y número de consultas SQL (cambio interno 92).

La mejora del cambio 94 actualiza visualmente el panel mediante una petición `fetch()` de la página administrativa y reemplaza el contenido HTML del panel. **No sustituye el algoritmo PHP por una versión AJAX más rápida**: la búsqueda sigue ejecutándose en el servidor y no necesariamente reduce el tiempo de SQL.

Se incluyen otros controles para diagnóstico de recursos JavaScript/CSS y renovación de tokens. Esas métricas no representan por sí solas el tiempo total del motor de resultados ni la experiencia completa de un cliente.

Las trazas diagnósticas deben mantenerse acotadas y no registrar indiscriminadamente contenido personal, claves ni búsquedas de clientes por tiempo indefinido.

## 15. Optimización y rendimiento

El buscador aplica una vía rápida para SKU exacto y restringe las columnas del índice cargadas para puntuar. Los campos de embeddings se incluyen solamente cuando la búsqueda semántica está activada.

En `includes/resultado/consulta.php`, la revisión interna 93 agregó precarga de datos de publicaciones, metadatos y taxonomías mediante `_prime_post_caches()` cuando hay al menos 30 identificadores candidatos. Esta precarga ocurre **sin alterar los IDs ni su orden** y antes de comprobar individualmente visibilidad/stock con WooCommerce.

En las mediciones compartidas del entorno de prueba se registraron, por ejemplo:

| Consulta | Antes de la precarga | Después de la precarga |
|---|---|---|
| `laptop` | 1.353 ms; 2.043 SQL | 738,6 ms; 61 SQL |
| `mouse Logitech` | 496,3 ms; 385 SQL | 443,0 ms; 37 SQL |
| `impresora Epson` | 409,0 ms; 149 SQL | 402,3 ms; 40 SQL |
| SKU exacto de prueba | 3,8 ms; 7 SQL | 3,0 ms; 7 SQL |

**Interpretación:** son mediciones particulares, sensibles al caché y al servidor. No constituyen una garantía de rendimiento en cualquier instalación. Una optimización solo debe conservarse si mantiene la relevancia, los productos elegibles, la paginación y el orden esperado.

## 16. Operación, mantenimiento y tareas programadas

El código emplea WP-Cron para indexación/auditoría/reparación por lotes y limpieza temporal, y Action Scheduler para trabajos específicos de producto cuando está disponible. WP-Cron puede retrasarse si no hay tráfico o si su ejecución está deshabilitada; la regularidad debe evaluarse en cada alojamiento.

Durante el mantenimiento:

- Realizar respaldo de archivos y base de datos antes de actualizar.
- Verificar estado de WooCommerce, índices y caché tras cambios importantes.
- No eliminar tablas para resolver fallos de relevancia sin diagnóstico previo.
- No borrar la configuración ni los embeddings de una tienda por defecto al actualizar.
- Revisar conflictos con temas, filtros de visibilidad, plugins de caché y URLs de resultados.
- No activar funciones experimentales de consumo de IA en producción sin medir cuotas y tiempos.

## 17. Seguridad, privacidad y confidencialidad

Las credenciales de IA se introducen desde la administración y las peticiones al proveedor se realizan desde el backend. La protección depende además de permisos de WordPress, uso de HTTPS, seguridad del alojamiento y de sus copias de respaldo.

No incluir en el paquete de entrega archivos `.env` con secretos, `wp-config.php`, volcados de base de datos con información de clientes, logs de producción, fotografías privadas ni claves API reales.

Cuando se utilice comparación visual, respetar la vida corta de archivos temporales y las tareas de limpieza. Un código de estado HTTP o de error puede servir para diagnosticar sin guardar la imagen enviada ni información confidencial.

**Aviso:** el hecho de que un archivo no contenga una cadena obvia de API key no sustituye una inspección de secretos y de datos personales antes de compartir el repositorio.

## 18. Criterios de regresión antes de publicar futuras versiones

Comparar las mismas búsquedas antes y después de modificar código. Entre los escenarios recomendables están: SKU exacto y de variación, términos generales (`laptop`), marca + tipo (`mouse Logitech`), errores ortográficos, perfiles restringidos, productos agotados/ocultos, saltos de paginación y búsquedas por fotografía.

La modalidad visual debe conservar la prioridad de un producto individual cuando se fotografía un mouse y existen combos teclado + mouse en la tienda. Si la opción experimental está apagada, no debe consumir una segunda llamada visual ni reordenar resultados.

Para un producto que aparece en sugerencias y no en resultados completos, comprobar ambos recorridos sin eliminar verificaciones de elegibilidad. Para rendimiento, comparar **tiempo, SQL y resultados**, no únicamente la cantidad de consultas.

Los escenarios concretos de aceptación del documento original incluyen, entre otros, `hp g10`, `hp 250r`, `D2DX8AT`, `laptp hp`, `ticketera`, `lector dni` e `impresora para restaurante`; requieren que el catálogo correspondiente contenga artículos pertinentes y disponibles para interpretarlos correctamente.

## 19. Versionado y paquete de distribución

El archivo principal declara `Version: 2.1.3` y la constante `COMPUCIBER_AI_SEARCH_VERSION` utiliza el mismo valor. La versión de la base de datos (`2.4` en el índice, `2.2` en estadísticas) es **independiente**; no cambiarla para que coincida numéricamente con la versión pública.

El paquete instalable debe conservar esta estructura en su raíz:

```text
compuciber-ia-search/
├── compuciber-ai-search.php
├── README.md
├── CHANGELOG.md
├── DOCUMENTACION_TECNICA.md
├── assets/
├── blocks/
├── elementor/
└── includes/
```

El nombre y la carpeta del plugin pueden evolucionar únicamente mediante un proceso de versión controlado. No distribuir un ZIP con dos carpetas raíz o con código duplicado. La carpeta `.git/` debe permanecer **fuera** del ZIP instalable, pero conservarse en la copia del proyecto que tiene control de versiones.

**El ZIP instalable y el repositorio Git no son el mismo entregable:** el primero se usa para instalar el plugin; el segundo permite consultar código, commits e historial de desarrollo. Si el repositorio es privado, conceder acceso explícito al responsable de la empresa; no hacerlo público de manera inadvertida.

## 20. Lista de entrega técnica

El requerimiento original solicita: código fuente completo, ZIP instalable, esquema de datos, documentación técnica y de instalación, dependencias, servicios API, credenciales/configuración separadas, instrucciones de compilación si correspondieran, repositorio Git e historial/versionado.

Para esta distribución:

- **Código fuente y ZIP:** los archivos PHP/JS/CSS están en el paquete instalable.
- **Esquema:** está documentado en el apartado 4 y definido ejecutablemente en `includes/indexacion/tabla.php` y `includes/estadisticas.php`.
- **Uso e instalación:** `README.md`.
- **Evolución:** `CHANGELOG.md`.
- **Mantenimiento:** este `DOCUMENTACION_TECNICA.md`.
- **APIs:** Gemini y OpenAI, configurados por la tienda; no compartir claves privadas.
- **Compilación:** el paquete recibido incluye los recursos listos para uso, sin proceso adicional requerido para su instalación.
- **Git:** entregar enlace y acceso al repositorio por separado; verificar que el historial solicitado esté efectivamente allí.

**No es necesario enviar al cliente un archivo SQL con todos los productos de la tienda:** sería una copia de datos comerciales, no el esquema. Solo preparar exportaciones de datos reales si han sido solicitadas y autorizadas.

## 21. Limitaciones y estado de cierre

La documentación se elaboró sobre archivos estáticos, sin acceso administrativo directo a la tienda, sin ejecutar solicitudes a las API de pago y sin certificar compatibilidad universal.

La búsqueda visual adicional compara un número reducido de fotografías, no un índice visual exhaustivo. Los embeddings semánticos pueden seguir pendientes para algunos productos por cuotas o ejecución de la cola. Los resultados dependen del catálogo real y de las reglas de WooCommerce.

El plugin puede entregarse como versión candidata funcional acompañada de estos documentos. La aceptación definitiva del propietario, el acceso al repositorio y las responsabilidades de soporte deben acordarse con la empresa.

---

**COMPUCIBER S.A.C. — Documentación técnica de Compuciber AI Search (2.1.3)**
