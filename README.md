# Compuciber AI Search

Buscador inteligente de productos para tiendas WordPress con
WooCommerce.

**Versión documentada:** 1.0.0\
**Plataforma:** WordPress + WooCommerce\
**Estado:** Versión V1.0

## Descripción

Compuciber AI Search añade un buscador inteligente para productos de
WooCommerce. El plugin utiliza un índice propio de productos y combina
búsqueda tradicional, corrección, tolerancia a errores, sinónimos y,
cuando está habilitada, interpretación mediante un proveedor de IA.

WooCommerce continúa siendo la fuente de verdad de los productos,
precios, stock, SKU y demás datos comerciales.

## Funciones de V1.0

-   Integración con WooCommerce.
-   Indexación automática de productos publicados.
-   Actualización automática del índice cuando los productos cambian.
-   Barra de búsqueda inteligente.
-   Autocompletado.
-   Búsqueda por palabras incompletas.
-   Corrección de errores frecuentes.
-   Fuzzy search.
-   Búsqueda por SKU.
-   Búsqueda por marca.
-   Búsqueda por modelo.
-   Búsqueda por categoría.
-   Búsqueda por atributos.
-   Búsqueda por etiquetas y contenido indexado.
-   Detección de características técnicas como GB, TB, SSD y HDD.
-   Búsqueda por rangos de precio.
-   Sinónimos.
-   Puntuación y ordenamiento por relevancia.
-   Interpretación mediante IA cuando la búsqueda semántica está
    habilitada.
-   Panel administrativo.
-   Shortcode.
-   Bloque Gutenberg.

## Funciones posteriores

Según la hoja de requerimientos:

-   **V1.1:** búsqueda por voz.
-   **V1.2:** búsqueda mediante imagen.
-   **V2.0:** estadísticas avanzadas, evolución del ranking y otras
    funciones de producto.

Estas funciones posteriores no se presentan como funcionalidades
terminadas de V1.0.

## Requisitos

-   WordPress.
-   WooCommerce activo.
-   PHP compatible con la instalación.
-   Para la interpretación mediante IA: proveedor configurado y clave
    API correspondiente.

El buscador base puede realizar sus operaciones principales sin depender
obligatoriamente de una llamada a IA.

## Instalación

1.  Entrar en **Plugins → Añadir nuevo plugin**.
2.  Seleccionar **Subir plugin**.
3.  Seleccionar el ZIP de Compuciber AI Search.
4.  Instalar y activar el plugin.
5.  Verificar que WooCommerce esté activo.
6.  Abrir la configuración del plugin.

## Indexación automática

El plugin crea una tabla de índice propia y registra los productos
publicados de WooCommerce.

El índice almacena información como:

-   nombre;
-   SKU;
-   marca;
-   modelo;
-   categorías;
-   atributos;
-   etiquetas;
-   contenido;
-   precio;
-   precio de oferta;
-   stock;
-   variaciones;
-   URL;
-   imagen;
-   texto de búsqueda;
-   fecha de actualización.

Los cambios de productos se sincronizan mediante los hooks
correspondientes de WordPress y WooCommerce.

## Uso mediante shortcode

La barra puede insertarse mediante:

`[compuciber_ai_search]`

## Uso mediante Gutenberg

El plugin incluye el bloque **Compuciber AI Search** para insertar el
buscador desde Gutenberg.

## Panel administrativo

El panel permite consultar el estado de WooCommerce, la indexación y
configurar funciones como:

-   proveedor de IA;
-   claves API;
-   modelo de IA;
-   modelo de embeddings;
-   modelo multimodal;
-   modelo de voz;
-   autocompletado;
-   corrección;
-   búsqueda semántica;
-   búsqueda por voz;
-   búsqueda por imagen;
-   estadísticas.

Las claves API se mantienen en el backend.

## Motor de búsqueda

El flujo general es:

1.  Recepción de la consulta.
2.  Normalización.
3.  Corrección.
4.  Expansión mediante sinónimos.
5.  Detección de términos y características técnicas.
6.  Comparación con el índice.
7.  Cálculo de puntuaciones.
8.  Ordenamiento.
9.  Obtención de los resultados finales.

La búsqueda local puede funcionar sin depender de la IA.

## Interpretación mediante IA

Cuando la búsqueda semántica está habilitada, el plugin puede enviar la
consulta al proveedor configurado para interpretar la intención y
extraer información útil.

La IA no debe inventar productos. Los resultados finales proceden del
catálogo/indexación de WooCommerce.

## Proveedores

La arquitectura permite seleccionar el proveedor de IA.

Proveedores integrados:

-   Gemini.
-   OpenAI.

La integración está separada mediante módulos de proveedores.

## Arquitectura

``` text
compuciber-ia-search/
├── assets/
│   ├── css/
│   └── js/
├── blocks/
│   └── buscadores/
├── includes/
│   ├── indexacion/
│   ├── proveedores/
│   ├── resultado/
│   ├── admin-panel.php
│   ├── configuracion.php
│   ├── correcciones.php
│   ├── fuzzy.php
│   ├── ia.php
│   ├── indexacion.php
│   ├── resultados.php
│   ├── sinonimos.php
│   └── sugerencias.php
└── compuciber-ai-search.php
```

## Seguridad

-   Comprobaciones de acceso mediante WordPress.
-   Sanitización de entradas.
-   Consultas SQL preparadas cuando corresponde.
-   Nonces para las operaciones AJAX correspondientes.
-   Claves API almacenadas en el backend.

## Catálogos grandes

La búsqueda utiliza una tabla propia de índice para evitar depender
directamente de una consulta compleja sobre todos los metadatos de
WooCommerce.

No existe un límite artificial de 50 productos para el catálogo. El
límite de resultados visibles es independiente del número total de
productos indexados.

La arquitectura está preparada para el escenario de referencia de
**2,500 productos** indicado en los requerimientos. En catálogos
considerablemente mayores podrían realizarse optimizaciones adicionales
de rendimiento.

## Pruebas realizadas

Se verificaron búsquedas relacionadas con:

-   SKU y códigos de producto.
-   Marca y modelo.
-   Términos incompletos.
-   Errores ortográficos.
-   Sinónimos.
-   Categorías.
-   Atributos.
-   Características como `512 GB`, `512 GB SSD` y `SSD`.
-   Rangos de precio.
-   Consultas combinadas.
-   Autocompletado.
-   Consultas sin resultados.

Ejemplos:

`hp g10`\
`hp 250r`\
`D2DX8AT`\
`laptp hp`\
`hp 16gb 512`\
`impresora termica`\
`inpresora termica`\
`laptop lenovo`\
`mouse logitech`\
`512 GB SSD`

## Desarrollo

Esta versión corresponde al desarrollo del proyecto de prácticas de
Senati para Compuciber.

La V1.0 constituye la base funcional del buscador inteligente. Las
siguientes versiones ampliarán las capacidades de voz, imagen,
estadísticas y ranking.

## Licencia

La licencia y las condiciones de distribución deberán definirse antes de
una publicación comercial del plugin.

------------------------------------------------------------------------

## Instalación

1.  Descargar el ZIP del plugin.
2.  Entrar al administrador de WordPress.
3.  Ir a **Plugins → Añadir nuevo plugin**.
4.  Seleccionar **Subir plugin**.
5.  Seleccionar el archivo ZIP.
6.  Instalar y activar **Compuciber AI Search**.
7.  Verificar que WooCommerce esté activo.
8.  Configurar las opciones necesarias desde el panel del plugin.

------------------------------------------------------------------------

## Indexación automática

El plugin utiliza una tabla propia para indexar los productos de
WooCommerce.

La información indexada incluye:

-   Nombre.
-   SKU.
-   Marca.
-   Modelo.
-   Categorías.
-   Atributos.
-   Etiquetas.
-   Contenido.
-   Precio.
-   Precio de oferta.
-   Stock.
-   Variaciones.
-   URL.
-   Imagen.
-   Texto de búsqueda.
-   Fecha de actualización.

Los productos publicados se incorporan automáticamente al índice y los
cambios relevantes se sincronizan mediante hooks de WordPress y
WooCommerce.

------------------------------------------------------------------------

## Búsqueda inteligente

El flujo general de búsqueda es:

1.  Recibir la consulta.
2.  Normalizar el texto.
3.  Aplicar correcciones.
4.  Expandir sinónimos.
5.  Detectar características técnicas.
6.  Comparar con el índice.
7.  Calcular puntuaciones.
8.  Ordenar por relevancia.
9.  Mostrar los resultados.

El sistema permite combinar criterios como producto, marca, modelo, SKU,
categoría, atributos, características técnicas y precio.

------------------------------------------------------------------------

## Fuzzy Search

El plugin incorpora búsqueda tolerante a errores mediante fuzzy search.

Esta función permite encontrar coincidencias aunque el término
introducido no coincida exactamente con el texto almacenado en el
índice.

El fuzzy search complementa el sistema de corrección.

------------------------------------------------------------------------

## Panel administrativo

El plugin incorpora un panel administrativo propio dentro de WordPress.

Permite configurar:

-   Proveedor de IA.
-   Clave API de Gemini.
-   Clave API de OpenAI.
-   Modelo de IA.
-   Modelo de embeddings.
-   Modelo multimodal.
-   Modelo de voz.
-   Autocompletado.
-   Corrección.
-   Búsqueda semántica.
-   Búsqueda por voz.
-   Búsqueda por imagen.
-   Estadísticas.

También muestra información sobre WooCommerce, productos indexados,
última sincronización, proveedor de IA y estado del índice.

------------------------------------------------------------------------

## Shortcode

El buscador puede insertarse mediante:

``` text
[compuciber_ai_search]
```

------------------------------------------------------------------------

## Gutenberg

El plugin incluye un bloque Gutenberg denominado **Compuciber AI
Search**.

La implementación se encuentra en:

``` text
blocks/
└── buscadores/
    └── index.js
```

------------------------------------------------------------------------

## Estructura del plugin

``` text
compuciber-ia-search/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│       └── logo.png
├── blocks/
│   └── buscadores/
│       └── index.js
├── includes/
│   ├── indexacion/
│   ├── proveedores/
│   ├── resultado/
│   ├── admin-panel.php
│   ├── configuracion.php
│   ├── correcciones.php
│   ├── fuzzy.php
│   ├── ia.php
│   ├── indexacion.php
│   ├── resultados.php
│   ├── sinonimos.php
│   └── sugerencias.php
├── README.md
├── CHANGELOG.md
└── compuciber-ai-search.php
```

------------------------------------------------------------------------

## Índice de productos

La tabla utilizada es:

``` text
wp_compuciber_product_index
```

El prefijo `wp_` puede variar según la instalación de WordPress.

Campos principales:

``` text
id
nombre
sku
marca
modelo
categorias
atributos
etiquetas
contenido
precio
precio_oferta
stock
variaciones
texto_busqueda
url
imagen
actualizado
```

El ID del producto de WooCommerce se utiliza como identificador
principal.

------------------------------------------------------------------------

## Catálogos grandes

El plugin no establece un límite artificial de 50 productos para el
catálogo.

La cantidad total de productos indexados es independiente del número de
sugerencias o resultados mostrados al usuario.

La arquitectura está preparada para el escenario de aproximadamente
2,500 productos indicado en los requerimientos.

------------------------------------------------------------------------

## Seguridad

El plugin utiliza mecanismos de seguridad de WordPress, incluyendo:

-   Comprobaciones `ABSPATH`.
-   Sanitización de datos.
-   Consultas SQL preparadas cuando corresponde.
-   Nonces para operaciones AJAX.
-   Validación de solicitudes.
-   Claves API almacenadas en el backend.

Las claves API no se incluyen directamente en el código fuente.

------------------------------------------------------------------------

## Compatibilidad con WooCommerce

WooCommerce es la fuente principal de información de los productos.

El plugin obtiene información directamente de WooCommerce para mantener
actualizado el índice.

------------------------------------------------------------------------

## Pruebas realizadas

La V1.0 fue probada en un entorno local de WordPress + WooCommerce.

Se probaron búsquedas por:

-   SKU.
-   Marca.
-   Modelo.
-   Categoría.
-   Atributos.
-   Palabras incompletas.
-   Corrección.
-   Fuzzy search.
-   Sinónimos.
-   Características técnicas.
-   SSD y HDD.
-   Rangos de precio.
-   Autocompletado.
-   Consultas sin resultados.

Consultas utilizadas:

``` text
hp 250r
hp 16gb 512
hp 512gb
laptop menos de 2000
laptop menos de 1900
laptop más de 2000
laptop más de 3000
laptp hp
laptop hp 250r
D2DX8AT
D2DX8AT laptop
lenovo
laptop lenovo
250R
teclados
16 GB
512 GB SSD
512GB SSD
SSD
512 GB HDD
mouse logitech
teclado
999xyz
```

------------------------------------------------------------------------

## Estado de V1.0

La versión 1.0 contiene las funciones principales definidas para la
primera etapa:

-   WooCommerce.
-   Indexación automática.
-   Barra inteligente.
-   Autocompletado.
-   SKU.
-   Marca.
-   Modelo.
-   Categoría.
-   Atributos.
-   Fuzzy search.
-   Corrección.
-   Sinónimos.
-   Búsqueda mediante IA.
-   Panel administrativo.

------------------------------------------------------------------------

## Próximas versiones

### V1.1 --- Búsqueda por voz

Búsqueda mediante el micrófono del navegador y conversión de voz a
texto.

### V1.2 --- Búsqueda por imagen

Carga de imágenes y análisis mediante inteligencia artificial para
encontrar productos relacionados.

### V2.0 --- Funciones avanzadas

-   Estadísticas avanzadas.
-   Consultas sin resultados.
-   Mejoras del ranking.
-   Funciones administrativas adicionales.
-   Soporte para múltiples tiendas.
-   SaaS y licenciamiento.

------------------------------------------------------------------------

## Versionado

``` text
V1.0.0 → Buscador inteligente y funciones principales
V1.1.0 → Búsqueda por voz
V1.2.0 → Búsqueda por imagen
V2.0.0 → Funciones avanzadas
```

------------------------------------------------------------------------

## Proyecto

**Compuciber AI Search** fue desarrollado como proyecto de prácticas
profesionales de **SENATI** para **Compuciber**.

El objetivo es proporcionar un buscador inteligente para tiendas
WooCommerce manteniendo WooCommerce como fuente principal de
información.

------------------------------------------------------------------------

## Autor

**Compuciber**

Proyecto desarrollado para Compuciber -- Prácticas SENATI.

------------------------------------------------------------------------

## Licencia

Este proyecto pertenece a Compuciber.

El código y los recursos incluidos están destinados al desarrollo del
proyecto Compuciber AI Search.
