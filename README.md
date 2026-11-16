# Compuciber AI Search

**Versión documentada:** 2.1.3  
**Titular:** COMPUCIBER S.A.C.  
**Plataforma:** WordPress y WooCommerce  
**Estado:** versión candidata para entrega; motor local verificado en el entorno de pruebas; funciones externas sujetas a configuración, cuotas y validaciones específicas  
**Última actualización de documentación:** 15 de noviembre de 2026  
**Revisión interna:** cambios documentados hasta el N.º 98 (estos números no sustituyen la versión oficial)

## 1. Descripción

**Compuciber AI Search** es un plugin de búsqueda de productos para WooCommerce. Utiliza un índice local, coincidencias por características comerciales y técnicas, relevancia, autocompletado, correcciones ortográficas, sinónimos y funciones opcionales de inteligencia artificial. Su objetivo es buscar **productos que realmente existan en el catálogo WooCommerce**, aplicando las reglas de publicación, visibilidad y existencias de la tienda.

El buscador predeterminado funciona **sin crear perfiles**. Los perfiles son opcionales: permiten ofrecer distintas barras y resultados para segmentos del catálogo, manteniendo un único motor de búsqueda y la configuración común de IA.

La integración con el **buscador nativo de WoodMart** como un motor o modo adicional **no forma parte de esta versión**. El plugin sí puede presentar resultados mediante las plantillas de producto compatibles de WooCommerce y del tema activo.

## 2. Funcionalidades

### Búsqueda y relevancia

- Búsqueda por nombre de producto, SKU, modelo, marca, categorías, etiquetas y atributos indexados.
- Tratamiento de coincidencias técnicas y SKU; soporte para productos variables y sus variaciones dentro de las reglas del motor.
- Ordenación de resultados según las puntuaciones de relevancia del plugin.
- Correcciones personalizadas, sinónimos y búsqueda aproximada (*fuzzy*), con mensajes de interpretación cuando procede.
- Autocompletado que prioriza los nombres que comienzan con la consulta y, al corregir una palabra, los productos que comienzan con la expresión corregida.
- Indexación local de productos y actualización de registros procedentes de WooCommerce.
- Paginación que conserva los parámetros de búsqueda y perfil.

### Visibilidad, inventario y perfiles

- Respeto de las restricciones de visibilidad de productos de WooCommerce.
- Cuando WooCommerce tiene activada **«Ocultar artículos agotados del catálogo»**, exclusión de productos con estado de stock agotado **antes de calcular las páginas**, además de las comprobaciones de presentación.
- Filtrado de agotados y de las categorías permitidas por perfil **antes del límite inicial de candidatos del autocompletado**; se conserva el límite de 100 candidatos para esa consulta y el límite de sugerencias visibles del componente.
- Perfiles con categorías incluidas y excluidas, que **abarcan las subcategorías descendientes** de las categorías seleccionadas.
- Conservación automática del perfil de origen mediante `cis_perfil` cuando una barra envía al visitante a una página independiente de resultados.

### Funciones opcionales

- Búsqueda por voz en navegadores compatibles con el reconocimiento de voz utilizado por el plugin.
- Búsqueda por imagen mediante un proveedor de IA configurado; admite JPG, JPEG, PNG y WebP, sujeto a la validación del archivo y a un límite de 5 MB en la carga.
- Embeddings y búsqueda semántica mediante proveedores y modelos compatibles.
- Integración de proveedores **Google Gemini** y **OpenAI**, según las credenciales y funciones activadas.
- Estadísticas de consultas, selecciones y exportación desde el Dashboard.

**Importante:** la búsqueda local no exige tener todos los embeddings generados. La generación de embeddings, el reconocimiento de imagen y las solicitudes de IA pueden depender de cuotas, credenciales y condiciones del servicio externo.

## 3. Requisitos e instalación

Se necesita WordPress con **WooCommerce instalado y activo**. Elementor y WoodMart son opcionales; sus integraciones dependen de las versiones y configuraciones del sitio. Se recomienda HTTPS para las funciones del navegador y los recursos públicos.

Para instalarlo, entra en **Plugins → Añadir nuevo → Subir plugin**, selecciona el ZIP del plugin, instálalo y actívalo. Si ya existe una versión, utiliza el procedimiento de **reemplazar el plugin existente** tras realizar una copia de seguridad. Evita desinstalarlo innecesariamente, porque las configuraciones y los registros se guardan en WordPress.

El ZIP debe contener una sola carpeta raíz, `compuciber-ia-search/`, con el archivo principal `compuciber-ai-search.php` directamente dentro. No debe incluir `.git/`, archivos temporales ni credenciales.

Una vez instalado, entra en **AI Product Search → Dashboard** para comprobar WooCommerce, los productos publicados, los productos indexados, el proveedor de IA y el estado de embeddings. Después revisa la configuración y crea o selecciona la página de resultados.

## 4. Formas de insertar el buscador

### Shortcodes

**Buscador completo (barra y resultados):**

```text
[compuciber_ai_search]
```

**Solo barra, recomendado para cabeceras:**

```text
[compuciber_ai_search solo_barra="si"]
```

**Solo resultados, recomendado para la página de destino:**

```text
[compuciber_ai_search solo_resultados="si"]
```

**Buscador con un perfil existente:**

```text
[compuciber_ai_search perfil="reacondicionados"]
```

**Solo barra con perfil:**

```text
[compuciber_ai_search perfil="reacondicionados" solo_barra="si"]
```

`reacondicionados` es un ejemplo de identificador: utiliza el identificador real del perfil configurado. También existe el alias `[ai_product_search]`.

### HTML personalizado

Para un constructor que admita HTML y donde no se ejecuten shortcodes:

```html
<div class="compuciber-ai-search-embed" data-modo="solo_barra"></div>
```

Con perfil:

```html
<div class="compuciber-ai-search-embed"
     data-perfil="reacondicionados"
     data-modo="solo_barra"></div>
```

El atributo `data-perfil` es opcional. En bloques HTML nativos compatibles también está previsto `data-modo="completo"`; el resultado puede depender del constructor utilizado.

### Gutenberg, Elementor y PHP

El bloque **Compuciber AI Search** para Gutenberg permite seleccionar perfil y modo de visualización: **Completo, Solo barra o Solo resultados**.

El widget **Compuciber AI Search** para Elementor ofrece igualmente selección de perfil, modo y controles de presentación. Alternativamente, Elementor dispone de un widget genérico **Shortcode** que permite insertar cualquiera de los shortcodes anteriores.

En plantillas PHP de WordPress se puede utilizar:

```php
<?php compuciber_ai_search(); ?>
```

O, cuando sean necesarios atributos concretos:

```php
<?php echo do_shortcode( '[compuciber_ai_search solo_barra="si"]' ); ?>
```

## 5. Configuración de una página independiente de resultados

Desde **AI Product Search → Configuración**, selecciona una **página de resultados publicada**. Para una cabecera, la combinación recomendada es la barra mediante `[compuciber_ai_search solo_barra="si"]` y, en esa página publicada, `[compuciber_ai_search solo_resultados="si"]`.

La página de destino decide **dónde se muestran** los resultados; el perfil decide **qué productos están permitidos**. Si la búsqueda procede de un perfil, el parámetro `cis_perfil` se transmite y la página «Solo resultados» lo recupera automáticamente. **No hay que volver a indicar el perfil ni duplicar sus categorías en el shortcode de resultados.**

Cada perfil puede tener una página de destino específica; si no la tiene, utiliza la página general configurada. La opción **«Usar página actual»** resulta apropiada para un buscador completo que deba mostrar sus resultados en la misma página, pero no suele ser la mejor opción para una barra instalada en la cabecera.

Los parámetros de resultados incluyen `cis_q` (consulta), `cis_page` (página) y, cuando corresponde, `cis_perfil` (perfil). No deben eliminarse mediante redirecciones o reglas de caché.

Para que el sitio de pruebas pueda ser revisado sin iniciar sesión como administrador, la **página debe estar publicada y accesible a los visitantes autorizados**. Una copia de producción puede mantenerse protegida mediante una contraseña global o acceso restringido y configurada para que los buscadores externos no la indexen.

## 6. Perfiles y categorías descendientes

Los perfiles se administran desde **AI Product Search → Configuración**. Admiten nombre, identificador, activación, categorías incluidas y excluidas, estilos y página de destino opcional.

Cuando se incluye o excluye una categoría padre, sus **subcategorías descendientes** se tienen en cuenta. Las **exclusiones tienen prioridad** sobre las inclusiones. Un perfil inexistente o inactivo no debe permitir resultados bajo sus reglas.

El ejemplo **PRODUCTOS REACONDICIONADOS** corresponde a una categoría utilizada durante las pruebas del proyecto: sus subcategorías deben quedar comprendidas por las reglas del perfil configurado. No es necesario crear manualmente una regla para cada subcategoría cuando la categoría padre ya está seleccionada.

## 7. Presentación, paginación y compatibilidad con Elementor

En **Diseño de Resultados** puede seleccionarse la presentación propia de Compuciber o el uso de **plantillas de producto compatibles de WooCommerce**. Esta última opción no equivale a reemplazar el motor de búsqueda de WoodMart ni incorpora por sí sola todos los filtros del archivo nativo de tienda.

La paginación utiliza el parámetro `cis_page`. Durante las pruebas, se detectó que algunas páginas insertadas en Elementor entregaban enlaces de paginación incorrectos después de generar el shortcode. La compatibilidad se estabilizó incorporando una reparación de enlaces en el JavaScript público y utilizando una ruta nueva para ese recurso.

**Archivo JavaScript público activo en la versión 2.1.3:** `assets/js/buscador-v59.js`.

La referencia administrativa del diagnóstico de recursos debe apuntar a ese archivo. El archivo antiguo `assets/js/buscador.js` fue retirado durante la limpieza posterior al cambio 60. No vuelvas a cargar ambos archivos simultáneamente.

Si tras una actualización un navegador recibe un recurso JavaScript obsoleto, revisa la versión efectivamente entregada, las optimizaciones de caché y la purga de recursos estáticos. La disponibilidad de un archivo por URL no garantiza que el navegador haya ejecutado su versión más reciente.

## 8. Caché, LiteSpeed y sitios de prueba

Las páginas de resultados deben conservar los parámetros `cis_q`, `cis_page` y `cis_perfil`. En instalaciones con LiteSpeed Cache u otros sistemas de caché, verifica que no se esté sirviendo el mismo HTML para consultas o páginas diferentes.

Como medida de compatibilidad pueden configurarse exclusiones de URI para la página de resultados y exclusiones de caché para esos parámetros, según las opciones disponibles del sitio. **No existe una regla universal que deba aplicarse a todas las tiendas**: evita exclusiones amplias innecesarias y comprueba el comportamiento tanto con sesión de administrador como sin ella.

Las pruebas sobre una copia de producción deben realizarse con copias de seguridad, sin procesar pedidos reales ni enviar comunicaciones involuntarias a clientes.

## 9. Administración: cinco apartados

```text
AI Product Search
├── Dashboard
├── Configuración
├── Sinónimos
├── Correcciones
└── Diagnóstico y registros
```

**Dashboard:** estado general del plugin, indexación, métricas y exportaciones de estadísticas. Las estadísticas y sus exportaciones CSV/Excel **se encuentran dentro del Dashboard**, no en un sexto submenú.

**Configuración:** proveedor y opciones de IA, búsqueda, apariencia, página de resultados, presentación de tarjetas y perfiles.

**Sinónimos:** relaciones entre términos para ampliar coincidencias.

**Correcciones:** reglas personalizadas y comportamiento de interpretación de consultas.

**Diagnóstico y registros:** comprobaciones de instalación y recursos, simulador de relevancia, comparación WooCommerce/índice, auditoría integral por lotes, incidencias exportables, reparación selectiva, trazas temporales de búsquedas reales y seguimiento de la cola de embeddings.

### Diagnóstico y mantenimiento

La **auditoría integral** revisa por lotes los productos indexados y registra discrepancias. Sus resultados deben interpretarse por tipo: una diferencia de fecha de actualización no tiene el mismo significado que una discrepancia de stock, nombre o precio. El contador de productos «no visibles con índice» es independiente del contador de diferencias; puede incluir productos agotados legítimamente ocultos por WooCommerce.

La **reindexación selectiva** permite actualizar los registros del índice que presentan discrepancias relevantes, sin reparar automáticamente todo el catálogo ni modificar los productos de WooCommerce. Antes de utilizarla, revisa las incidencias y conserva un respaldo.

Las trazas de las últimas búsquedas son **temporales y acotadas** para el diagnóstico; no se recomienda mantener instrumentación intensiva permanentemente.

La **cola automática de embeddings** utiliza tareas programadas de WordPress y lotes controlados. Su avance puede depender de visitas al sitio o de un cron real del servidor, además de las cuotas del proveedor de IA.

## 10. IA, embeddings y seguridad

Los proveedores se configuran desde el administrador, sin incorporar claves privadas en los archivos distribuidos. Las funcionalidades de Gemini u OpenAI dependen del servicio, del modelo seleccionado y de sus límites de uso.

Las correcciones locales y fuzzy pueden resolver errores de escritura sin invocar IA en cada pulsación. La IA de sugerencias actúa conforme a las opciones y condiciones configuradas; no debe asumirse una petición remota por cada tecla.

No es imprescindible completar todos los embeddings para comprobar la búsqueda textual. Si se cambia el proveedor o modelo de embeddings, revisa su estado de validez y el procesamiento pendiente antes de interpretar cifras de «generados».

No incluyas claves API, contraseñas, tokens de acceso ni datos sensibles de clientes en archivos ZIP, documentación distribuible o repositorios.

## 11. Arquitectura orientativa

```text
compuciber-ia-search/
├── compuciber-ai-search.php
├── assets/
│   ├── css/buscador.css
│   └── js/buscador-v59.js
├── blocks/buscadores/
├── elementor/widget-ai-search.php
├── includes/
│   ├── admin-panel.php
│   ├── configuracion.php
│   ├── correcciones.php
│   ├── estadisticas.php
│   ├── indexacion/
│   ├── proveedores/
│   │   ├── gemini/
│   │   └── openai/
│   ├── resultado/
│   ├── resultados.php
│   ├── sugerencias.php
│   └── sugerencias-ia.php
├── README.md
└── CHANGELOG.md
```

El plugin incluye otros módulos PHP de administración, licencias, exportaciones e indexación no enumerados en este árbol abreviado. El cliente de licencias y los componentes de conexión con servicios externos existentes son independientes de la presente actualización documental y **no deben alterarse por este cambio**.

## 12. Pruebas realizadas y estado de cierre

Las siguientes cifras corresponden a entornos y fechas de prueba, **no a requisitos permanentes del plugin**.

| Entorno | Evidencia comprobada |
|---|---|
| LocalWP, aproximadamente 1.015 productos | Indexación completa; resultados y relevancia; paginación hasta la tercera página; avance de auditoría |
| Tienda de prueba inicial, 76 productos | Índice y embeddings existentes; comprobaciones de visibilidad, inventario y relevancia; tarjetas de WooCommerce/WoodMart |
| Copia de tienda oficial, 3.738 productos | Índice completo; correcciones 56–58 de stock y sugerencias; paginación validada posteriormente en Chrome y Opera; voz funcional; auditoría integral completada |

En la copia de la tienda oficial, la auditoría integral registró inicialmente **1.671 productos con diferencias**. El CSV exportado permitió distinguir **1.621 diferencias solo de fecha**, **49 con diferencia de stock** (42 solo stock y 7 stock + fecha) y **1 con diferencia de nombre + fecha**. La reparación selectiva procesó **50 de 50 registros**, sin fallos reportados; se comprobó posteriormente el producto de referencia ID `49212` sin diferencias.

**Estado de cierre actualizado (15/11/2026):** se realizaron pruebas adicionales de búsqueda por imágenes, relevancia, paginación y rendimiento local. En el entorno de prueba se verificó que las búsquedas `laptop` y `mouse Logitech` conservaron respectivamente 204 y 75 resultados contados por WooCommerce; sus primeras páginas tenían 12 de 12 productos aptos. La comparación visual adicional con Gemini permanece como característica opcional y experimental; no debe presentarse como un índice visual global de fotografías. La cuota de IA externa y la cola incompleta de embeddings siguen siendo condicionantes operativos. Las pruebas observadas no equivalen a certificación universal de compatibilidad en todas las tiendas.

La integración adicional que entregaría las puntuaciones de Compuciber al **motor nativo de búsqueda de WoodMart** fue estudiada, pero **se decidió no implementarla en esta versión**. Tampoco se añadieron filtros avanzados que modifiquen la selección de resultados.

## 13. Distribución y actualización

Para generar el ZIP de instalación desde PowerShell, situándose dentro de la carpeta `compuciber-ia-search`:

```powershell
tar -a -cf ..\compuciber-ia-search-2.1.3.zip --exclude=".git" -C .. compuciber-ia-search
```

Comprueba que el archivo principal aparece bajo `compuciber-ia-search/` y que no existe ninguna ruta `.git/` dentro del ZIP. Realiza la actualización en una **copia de prueba** antes de reemplazar el plugin de un sitio productivo.

**Versión declarada en el encabezado y constante del plugin:** 2.1.3. Este README incluye la revisión documental de los cambios internos hasta el N.º 98; su sustitución no modifica código ni actualiza automáticamente una instalación WordPress.

## 14. Actualizaciones recientes del reconocimiento de imágenes (cambios 75–91)

### 13.1. Calidad y reconocimiento inicial

Las fotografías recibidas se optimizan antes de enviarse al proveedor multimodal. La implementación utilizada en las pruebas acepta JPG/JPEG, PNG y WebP, aplica un límite de carga de 5 MB, reduce las dimensiones a un máximo de 1200 × 1200 píxeles y utiliza calidad de salida 85 (cambio 77). No se aumentó el límite de resolución para evitar modificaciones simultáneas difíciles de evaluar bajo la cuota gratuita.

Gemini u OpenAI interpretan el objeto fotografiado —tipo, marca, modelo cuando exista evidencia visual, características y términos de búsqueda— y el motor local ordena los productos reales de WooCommerce. La interpretación de la IA no es una identificación garantizada de SKU ni permite inventar artículos.

### 13.2. Protección del ranking por identidad

Las búsquedas visuales incorporan una prioridad específica para la identidad del producto (cambio 75). Su propósito es evitar, por ejemplo, que un combo teclado + mouse desplace a un mouse individual cuando la fotografía representa únicamente un mouse. Esta protección se mantiene antes de aplicar cualquier bonificación de semejanza visual.

### 13.3. Comparación visual opcional contra el catálogo

En **AI Product Search → Configuración** existe la opción **Comparación visual con catálogo**, desactivada por defecto. Esta segunda etapa funciona con Gemini: toma una selección de candidatos de los resultados locales, procesa hasta seis imágenes principales disponibles y solicita una evaluación de semejanza visual. No recorre de forma exhaustiva todas las fotografías del catálogo ni genera embeddings visuales para ellas.

La selección examina un máximo de 100 IDs elegibles derivados del motor y un subconjunto reducido de fotografías. Se mejoró para considerar títulos que comienzan por el tipo de producto o por su marca, y para descartar paquetes y combinaciones manifiestamente incompatibles con un objeto individual. Si el producto exacto no entra en el conjunto comparado, la similitud visual no puede rescatarlo por sí sola.

Una coincidencia con una puntuación suficientemente alta puede recibir una bonificación de hasta **5 %** sobre el puntaje utilizado en el ordenamiento, **dentro de su grupo de prioridad de identidad**. Se preserva el conjunto original de resultados y las comprobaciones de elegibilidad. La cifra devuelta por Gemini es una apreciación del modelo, no una probabilidad estadística calibrada ni prueba del modelo exacto.

Al activar esta función, puede realizarse una llamada adicional a Gemini y aumentar el tiempo de respuesta. Si la función está desactivada, se conserva el recorrido habitual de reconocimiento de imagen y búsqueda local. La etapa de comparación adicional no está implementada para OpenAI en esta revisión.

### 13.4. Archivos temporales, caché y errores

La interpretación inicial y la comparación visual utilizan mecanismos de caché distintos. La segunda etapa evita almacenar la fotografía completa en Base64 dentro del token de resultados; en los casos compatibles con el alojamiento, utiliza una copia temporal no pública y guarda puntuaciones pequeñas para la ordenación. Las tareas de limpieza dependen del servidor y de la ejecución de WP-Cron; debe verificarse que los permisos y el directorio temporal permiten operar con seguridad.

Las respuestas fallidas del proveedor y las respuestas JSON inválidas disponen de tratamiento diferenciado para evitar confundir indisponibilidad técnica con ausencia válida de coincidencias. Hay una caché más corta para ciertos errores y una caché más larga para evaluaciones reutilizables. Estos mecanismos reducen llamadas duplicadas, pero no sustituyen el control de cuotas de la API.

## 15. Ajustes de la interfaz de búsqueda por imagen (cambios 95–97)

Durante el reconocimiento de una fotografía, la cámara permanece **fija** y temporalmente deshabilitada; ya no se hace girar el icono completo. Un indicador independiente muestra el estado **«Analizando imagen con IA...»**.

El texto tiene grosor seminegrita. Los colores del texto y del punto animado pueden personalizarse mediante las variables CSS `--compuciber-status-text-color` y `--compuciber-status-dot-color`. En el widget de Elementor hay controles específicos para estos colores, lo que permite adaptar el mensaje a encabezados claros y oscuros. Esta modificación es solamente de presentación y no interviene en Gemini ni en la relevancia.

## 16. Diagnóstico ampliado y tiempos reales (cambios 78, 92 y 94)

El apartado **Diagnóstico y registros → Diagnóstico de búsquedas y relevancia** permite ejecutar consultas locales, inspeccionar la puntuación de candidatos y comprobar el resultado final de paginación y elegibilidad. Muestra también si la opción WooCommerce **Ocultar productos agotados** está activada.

El diagnóstico presenta **Tiempo de búsqueda local (ms)** y **Consultas SQL** del procesamiento instrumentado. La petición de este diagnóstico no utiliza Gemini/OpenAI ni genera embeddings. Las mediciones de descarga de CSS, JS o renovación de tokens pertenecen a pruebas diferentes y no deben confundirse con la latencia del motor de búsqueda.

Se añadió actualización dinámica del panel: el formulario recupera una nueva respuesta administrativa y reemplaza el contenido de diagnóstico sin recargar visualmente toda la página. El sistema conserva una navegación normal de respaldo cuando falla JavaScript o la solicitud. Esta mejora de interfaz no supone, por sí misma, una aceleración del PHP que evalúa candidatos.

## 17. Optimización de consultas SQL sin cambiar la relevancia (cambio 93)

Se incorporó **precarga de cachés de publicaciones de WordPress** para conjuntos grandes de IDs antes de realizar comprobaciones individuales con WooCommerce. La optimización intenta evitar consultas repetidas por producto, sin eliminar validaciones de publicación, stock, visibilidad, categorías, puntuación o paginación.

Resultados observados en el entorno de pruebas con catálogo general:

| Consulta | Antes: ms / SQL | Después: ms / SQL | Resultados contados por WooCommerce |
|---|---:|---:|---:|
| `laptop` | 1.353,0 / 2.043 | 738,6 / 61 | 204 |
| `mouse Logitech` | 496,3 / 385 | 443,0 / 37 | 75 |
| `impresora Epson` | 409,0 / 149 | 402,3 / 40 | 15 en la prueba posterior |
| SKU `A200SI5` | 3,8 / 7 | 3,0 / 7 | 1 |

Estas son mediciones puntuales de la instalación de prueba; la caché, el servidor, la concurrencia y el catálogo pueden alterar los resultados. En las comprobaciones mostradas, los productos y el orden de las primeras páginas de `laptop` y `mouse Logitech` permanecieron estables, pero esto **no constituye una prueba exhaustiva de todas las consultas**.

## 18. Embeddings y limitaciones operativas

La búsqueda **local** funciona aunque no estén completos los embeddings semánticos. Su generación depende de los modelos elegidos, la configuración de la cola y las cuotas del proveedor externo. En esta revisión el lote de embeddings permanece limitado a **cinco productos por ejecución**; pagar una API no aumenta automáticamente ese máximo: sería necesario reconfigurar y validar el procesamiento.

Una posible mejora futura sería crear un índice de embeddings **visuales** de fotografías de WooCommerce. **No forma parte de la funcionalidad actual**: requeriría indexación progresiva, gestión de imágenes y almacenamiento de vectores, además de pruebas de precisión, consumo y mantenimiento. Esta posibilidad no impide entregar el buscador actual.

## 19. Esquema y conservación de datos

El plugin define sus tablas mediante los mecanismos de instalación/actualización de WordPress, utilizando el prefijo de base de datos de cada sitio. Entre las tablas se encuentran:

| Tabla (sin prefijo) | Uso principal |
|---|---|
| `compuciber_product_index` | Productos indexados, título, SKU, marca, modelo, categorías, textos, imágenes, metadatos y embeddings cuando existan |
| `compuciber_search_stats` | Información agregada o registrada de consultas, conforme a la configuración de estadísticas |
| `compuciber_search_clicks` | Selecciones de productos para métricas y señales relacionadas |

La definición ejecutable del esquema se conserva en los módulos PHP del plugin; el prefijo real **no tiene por qué ser `wp_`**. No cambies versiones de tablas para hacerlas coincidir con el número comercial `2.1.3`.

El índice es auxiliar y no reemplaza los datos oficiales de WooCommerce. El inventario, publicación, elegibilidad, precio y URL comercial deben respetar los registros reales del sistema.

## 20. Seguridad, privacidad y registro de errores

Las claves de Gemini/OpenAI se configuran exclusivamente en el backend. No deben distribuirse en este README, el ZIP, el código frontend, capturas públicas ni repositorios compartidos. Las fotografías subidas deben cumplir sus validaciones y, cuando se usan temporalmente para la comparación, eliminarse según el flujo previsto.

El módulo de imagen de OpenAI incorpora diagnóstico de fallos de transporte y HTTP con registros limitados para no inundar los logs. Los registros se consultan mediante las herramientas de WooCommerce cuando estén disponibles; no deben contener fotografías ni API keys.

Las trazas y auditorías administrativas están destinadas al análisis de problemas concretos. Evita mantener depuración intensiva o exportar datos de visitantes sin necesidad. Antes de publicar la versión instalable, verifica que no existan credenciales reales ni carpetas `.git` dentro del ZIP.

## 21. Comprobaciones y alcance de la entrega

El documento original del proyecto define pruebas de aceptación con consultas como `hp g10`, `hp 250r`, `D2DX8AT`, `laptp hp`, `impresora termica`, `inpresora termica`, `ticketera`, `lector dni`, `laptop para oficina` e `impresora para restaurante`. Los productos concretos dependen del catálogo realmente instalado y de sus reglas de stock/visibilidad.

En la prueba final del catálogo se comprobaron directamente `laptop`, `mouse Logitech`, `impresora Epson` y un SKU exacto; se registraron tiempos, número de SQL, total de resultados y productos aptos. La comparación de imágenes con Gemini se ha tratado como **experimental**, y los embeddings incompletos limitan algunas capacidades semánticas, no la búsqueda local.

El material de entrega incluye código fuente y ZIP instalable, documentación de instalación, arquitectura y base de datos, dependencias y proveedores externos, y un `CHANGELOG.md` coherente con el estado final. El requerimiento también solicita **repositorio Git e historial de versionado**; estos deben entregarse por separado si están disponibles. Nunca se debe fabricar un historial de commits inexistente.

## 22. Propiedad y evolución

El proyecto pertenece a **COMPUCIBER S.A.C.** de acuerdo con las condiciones de desarrollo. El código principal se distribuye dentro del plugin; los servicios externos de IA no se incluyen en el paquete y están sujetos a las condiciones de sus proveedores.

No se implementó la integración como **motor alternativo nativo de WoodMart**. Tampoco se añadieron filtros avanzados que modificasen la relevancia. Las propuestas futuras deben evaluarse contra necesidades concretas y no incorporarse a producción sin justificación.

---

**COMPUCIBER S.A.C. — Compuciber AI Search 2.1.3**  
**README actualizado al 15 de noviembre de 2026 — revisión interna hasta el cambio N.º 98**
