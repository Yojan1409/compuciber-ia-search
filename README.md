# Compuciber AI Search

**Versión:** 2.1.1  
**Estado del desarrollo funcional:** completado  
**Titular del proyecto:** COMPUCIBER S.A.C.  
**Plataforma:** WordPress + WooCommerce  
**Tipo de producto:** plugin de búsqueda inteligente para tiendas en línea

## Descripción

**Compuciber AI Search** es un buscador de productos para WooCommerce que combina búsqueda local, autocompletado, tolerancia a errores, reglas de corrección, sinónimos, relevancia, búsqueda semántica y capacidades de inteligencia artificial. Está diseñado para trabajar con los productos reales de cada tienda, sin depender de un catálogo, marca o sector comercial específico.

El **buscador predeterminado** puede utilizarse inmediatamente, **sin crear perfiles**. Los perfiles son opcionales y permiten incorporar buscadores con diferentes conjuntos de productos y estilos, conservando el mismo motor de búsqueda y la configuración de IA de la tienda.

## Funcionalidades

### Búsqueda de productos

- Consultas por nombre, SKU, marca, modelo, categoría, etiquetas y atributos de WooCommerce.
- Prioridad de coincidencias exactas de SKU y reconocimiento de variaciones.
- Autocompletado con sugerencias de productos.
- Búsqueda aproximada (*fuzzy search*), sinónimos y correcciones personalizadas.
- Mensajes de interpretación cuando se aplica una corrección confirmada.
- Ordenación por relevancia y coincidencias técnicas.
- Búsqueda semántica y embeddings, según las opciones habilitadas.
- Búsqueda por voz mediante las capacidades del navegador compatibles.
- Búsqueda por imagen con interpretación de IA y búsqueda sobre el catálogo real.
- Paginación de resultados y conservación de los parámetros de búsqueda.
- Indexación y actualización de información procedente de WooCommerce.

### Perfiles de buscador

Desde **AI Product Search → Configuración** se pueden crear perfiles opcionales con:

- Nombre e identificador propios.
- Activación o desactivación.
- Inclusión y exclusión de categorías de productos.
- Aplicación de reglas de categorías padre a sus subcategorías.
- Colores, bordes, redondeo y texto de búsqueda personalizados.
- Edición y eliminación administrativa de perfiles.
- Presentación compacta de los perfiles en secciones desplegables.

Una tienda puede usar, por ejemplo, un buscador general y otro limitado a productos reacondicionados. **No es necesario crear un perfil llamado «General»** para utilizar el buscador predeterminado.

### Presentación de resultados

La configuración **Diseño de Resultados** ofrece dos alternativas:

1. **Tarjetas propias del plugin:** presentación de productos con el diseño integrado de Compuciber AI Search.
2. **Plantilla WooCommerce de la tienda:** utilización de las plantillas de producto compatibles de WooCommerce y del tema activo.

La segunda opción reutiliza las **tarjetas de producto compatibles**; no implica copiar automáticamente todos los filtros laterales ni el diseño completo del archivo de tienda de un tema.

Los buscadores pueden presentarse como **buscador completo** o **solo barra**, según el método de inserción. Para una cabecera se recomienda **solo barra** y una **página independiente de resultados**, para evitar que los productos se muestren dentro del encabezado.

## Instalación

1. Asegurarse de que **WooCommerce** esté instalado y activo.
2. Entrar en **WordPress → Plugins → Añadir plugin → Subir plugin**.
3. Seleccionar el archivo ZIP de Compuciber AI Search e instalarlo.
4. Activar el plugin.
5. Entrar en **AI Product Search → Configuración** y ajustar las opciones deseadas.
6. Verificar la indexación de productos de WooCommerce.
7. Insertar el buscador mediante cualquiera de los métodos descritos a continuación.

La conexión a un proveedor de IA es opcional para las funciones de búsqueda local. Las funciones que requieren IA externa utilizan la configuración y credenciales del proveedor elegido.

## Formas de insertar el buscador

### 1. Shortcode

**Buscador predeterminado, completo:**

```text
[compuciber_ai_search]
```

**Buscador predeterminado, solo barra:**

```text
[compuciber_ai_search solo_barra="si"]
```

**Buscador con perfil personalizado:**

```text
[compuciber_ai_search perfil="reacondicionados"]
```

**Perfil personalizado en modo solo barra:**

```text
[compuciber_ai_search perfil="reacondicionados" solo_barra="si"]
```

También está disponible el alias `[ai_product_search]`.

### 2. HTML personalizado

Este formato facilita la inserción en elementos HTML de páginas y constructores de cabecera, incluso cuando el editor no interpreta shortcodes.

**Buscador predeterminado para cabeceras:**

```html
<div class="compuciber-ai-search-embed"
     data-modo="solo_barra">
</div>
```

**Barra asociada a un perfil:**

```html
<div class="compuciber-ai-search-embed"
     data-perfil="reacondicionados"
     data-modo="solo_barra">
</div>
```

El atributo `data-perfil` es **opcional** y debe contener el identificador de un perfil activo.

En los **bloques HTML nativos de Gutenberg** también se dispone de un mecanismo de renderizado para `data-modo="completo"`:

```html
<div class="compuciber-ai-search-embed"
     data-modo="completo">
</div>
```

La integración **solo barra** mediante HTML está pensada para constructores externos. El renderizado de HTML completo puede depender de cómo cada constructor procese el contenido.

### 3. Gutenberg

Insertar el bloque **Compuciber AI Search** desde el editor de WordPress. El bloque dispone de:

- **Perfil de búsqueda:** predeterminado o perfil activo.
- **Modo de visualización:** buscador completo o solo barra.

Los bloques sin configuración especial utilizan el buscador predeterminado.

### 4. Elementor

Insertar el widget **Compuciber AI Search**. Permite seleccionar el perfil de búsqueda, activar la presentación **solo barra** y ajustar sus controles visuales. Elementor es una integración opcional: el plugin no necesita Elementor para que funcione el shortcode.

### 5. Plantillas PHP

Los desarrolladores pueden insertar el buscador predeterminado desde una plantilla de WordPress:

```php
<?php compuciber_ai_search(); ?>
```

También puede utilizarse `echo do_shortcode( '[compuciber_ai_search solo_barra="si"]' );` cuando se necesiten atributos de shortcode.

## Página de resultados y cabeceras

Para separar la barra del encabezado y los resultados completos:

1. En **AI Product Search → Configuración**, seleccionar una **página de resultados** publicada.
2. Colocar en esa página el buscador completo, por ejemplo `[compuciber_ai_search]`.
3. Insertar en la cabecera el shortcode **solo barra** o el elemento HTML correspondiente.
4. Utilizar las opciones de **Diseño de Resultados** para elegir entre tarjetas propias y las plantillas compatibles de WooCommerce.

Esta separación funciona sin modificar la configuración general HTTP/HTTPS de WordPress ni adaptar el motor de búsqueda a un tema específico.

## Inteligencia artificial

La integración de IA dispone de una arquitectura de proveedores. El código incluye conectores para **Google Gemini** y **OpenAI**, con configuración independiente de credenciales y modelos.

La búsqueda local y las coincidencias directas tienen prioridad cuando pueden resolver la consulta. La IA se utiliza cuando corresponde a las opciones habilitadas, por ejemplo para interpretación semántica o análisis de imágenes. Los resultados se relacionan con productos existentes en WooCommerce.

**Todos los buscadores de una misma tienda comparten la configuración del proveedor y su API key.** Crear varios perfiles no crea nuevas API keys ni genera llamadas a IA por el solo hecho de existir. Las peticiones dependen del uso efectivo del buscador y de las funciones que las necesiten.

No se deben incluir API keys, contraseñas ni otros secretos en el código fuente, archivos ZIP o repositorios públicos.

## Administración

El menú administrativo conserva la estructura:

```text
AI Product Search
├── Dashboard
├── Configuración
├── Sinónimos
└── Correcciones
```

El **Dashboard** reúne información de uso, indicadores, historial y exportaciones, además de un apartado desplegable con instrucciones de inserción. **Configuración** contiene las opciones de búsqueda, IA, destinos, apariencia, perfiles y diseño de resultados. **Sinónimos** y **Correcciones** administran las reglas correspondientes.

## Estadísticas y exportación

El plugin registra actividad de búsqueda, búsquedas con/sin resultados y selecciones, según la configuración administrativa, y presenta indicadores como consultas populares, productos seleccionados, tasa de éxito y CTR.

- Exportación en **CSV**.
- Exportación en **Excel (.xlsx)**.
- Consolidación de las búsquedas de distintos perfiles en las estadísticas globales de la tienda.
- Presentación no repetida del total global de selecciones por término en las exportaciones, aunque una consulta figure en distintos canales de búsqueda.

Las estadísticas reflejan **actividad registrada**, no una identificación universal de visitantes o sesiones únicas.

## Compatibilidad y rendimiento

- Diseñado para instalaciones de WordPress con WooCommerce.
- Compatible con el buscador predeterminado sin perfiles.
- Integraciones disponibles para shortcode, HTML, Gutenberg, Elementor y PHP.
- El motor de búsqueda es compartido entre las distintas formas de inserción.
- Indexación, coincidencias locales, caché y llamadas condicionadas a IA contribuyen a reducir trabajo innecesario.
- Los estilos y filtros de perfiles son opcionales.
- La apariencia con plantillas WooCommerce depende de la compatibilidad del tema activo.

Las opciones y ajustes de caché del sitio (incluido LiteSpeed cuando esté instalado) se administran desde WordPress y el propio sistema de caché.

## Arquitectura del código

```text
compuciber-ia-search/
├── assets/
│   ├── css/
│   └── js/
├── blocks/buscadores/
├── elementor/
├── includes/
│   ├── indexacion/
│   ├── proveedores/
│   │   ├── gemini/
│   │   └── openai/
│   ├── resultado/
│   ├── admin-panel.php
│   ├── configuracion.php
│   ├── estadisticas.php
│   ├── exportacion-excel.php
│   ├── resultados.php
│   ├── sugerencias.php
│   ├── correcciones.php
│   ├── sinonimos.php
│   └── ...
├── compuciber-ai-search.php
├── README.md
└── CHANGELOG.md
```

Los archivos JavaScript y CSS se incluyen como recursos del plugin y no requieren un proceso obligatorio de compilación para su uso normal.

## Licencias e integración SaaS

El código incorpora un **cliente de licencias por instalación** y componentes de integración con un servicio remoto configurable: identificación de instalación, dominio, validación, caché, periodo de gracia y tareas programadas.

**Alcance:** la presencia del cliente y la integración SaaS **no significa que el plugin incluya dentro de su ZIP un servidor comercial SaaS de licencias**. Cualquier servicio remoto es un componente independiente que se configura según su propia infraestructura.

## Actualizaciones y distribución

Para actualizar una instalación existente, usar el procedimiento de reemplazo del plugin en WordPress, conservando los datos y ajustes de la tienda. Se recomienda una copia de seguridad antes de efectuar cambios en el sitio.

El paquete distribuible se compone del código necesario para ejecutar el plugin. Los metadatos internos de Git (`.git/`), archivos temporales y credenciales privadas no forman parte del paquete instalable.

## Estado del proyecto

**Desarrollo funcional de Compuciber AI Search 2.1.1 completado**, con motor local, integraciones de IA, perfiles opcionales, distintos métodos de inserción, modos de visualización, compatibilidad con las tarjetas WooCommerce, administración y estadísticas.

La documentación describe las capacidades implementadas; la compatibilidad concreta con cada tema, constructor o entorno de alojamiento depende de las características de esa instalación. Todo en regla

---

**COMPUCIBER S.A.C. — Compuciber AI Search 2.1.1**
