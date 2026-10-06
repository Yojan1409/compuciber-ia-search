# Compuciber AI Search

**Versión:** 2.0.0  
**Propietario del desarrollo:** COMPUCIBER S.A.C.  
**Plataforma:** WordPress + WooCommerce  
**Formato de entrega:** código fuente + plugin ZIP instalable + documentación + repositorio Git

## 1. Propiedad y alcance de la entrega

El código fuente de Compuciber AI Search se entrega como desarrollo bajo propiedad de **COMPUCIBER S.A.C.**

La entrega debe conservar todos los componentes esenciales necesarios para instalar, mantener y continuar desarrollando el buscador. Ninguna parte esencial del motor de búsqueda debe quedar únicamente en poder del desarrollador.

Los únicos componentes que pueden permanecer fuera del repositorio son servicios externos expresamente utilizados por el plugin, como las APIs de proveedores de inteligencia artificial o un futuro servidor externo de licencias/SaaS. Estos servicios no sustituyen el código fuente del buscador.

## 2. Entregables del proyecto

La entrega del proyecto comprende:

- Código fuente completo del plugin.
- Plugin ZIP instalable.
- Esquema de base de datos utilizado.
- Documentación técnica.
- Documentación de instalación y actualización.
- Relación de dependencias.
- Relación de APIs externas.
- Credenciales y configuración separadas del código fuente.
- Instrucciones de compilación, cuando exista frontend compilado.
- Repositorio Git.
- Historial y versionado del proyecto.

## 3. Descripción técnica

Compuciber AI Search es un plugin de búsqueda inteligente para WooCommerce. Combina un índice local de productos con autocomplete, fuzzy search, corrección, sinónimos, ranking técnico, búsqueda semántica, embeddings, búsqueda por voz, búsqueda por imagen, estadísticas y aprendizaje de ranking basado en interacciones históricas.

WooCommerce y el índice local constituyen la fuente de productos. La inteligencia artificial puede interpretar consultas o imágenes, pero no debe crear productos inexistentes.

## 4. Funcionalidades principales

El plugin incorpora:

- búsqueda por nombre;
- SKU;
- marca;
- modelo;
- categorías;
- atributos;
- etiquetas;
- contenido indexado;
- productos simples y variables;
- autocomplete;
- fuzzy search;
- corrección de errores;
- sinónimos;
- ranking de relevancia;
- coincidencias técnicas;
- búsqueda semántica;
- embeddings;
- búsqueda por voz;
- búsqueda por imagen;
- estadísticas;
- aprendizaje de ranking;
- integración Gutenberg;
- shortcode;
- integración PHP;
- widget nativo de Elementor;
- sistema cliente de licencias;
- identificación de instalaciones;
- preparación para múltiples tiendas;
- preparación para integración SaaS.

## 5. Arquitectura principal

```text
compuciber-ia-search/
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── blocks/
├── elementor/
│   └── widget-ai-search.php
├── includes/
│   ├── indexacion/
│   ├── proveedores/
│   │   ├── gemini/
│   │   ├── openai/
│   │   ├── gemini.php
│   │   ├── openai.php
│   │   └── interfaz.php
│   ├── resultado/
│   ├── licencias/
│   ├── admin-panel.php
│   ├── configuracion.php
│   ├── estadisticas.php
│   ├── fuzzy.php
│   ├── ia.php
│   ├── imagenes.php
│   ├── indexacion.php
│   ├── licencias.php
│   ├── resultados.php
│   ├── sinonimos.php
│   └── sugerencias.php
├── CHANGELOG.md
├── compuciber-ai-search.php
└── README.md
```

## 6. Arquitectura de proveedores de IA

Los proveedores implementan:

`Compuciber_AIProviderInterface`

Contrato principal:

- `consultar_texto()`
- `consultar_imagen()`
- `generar_embedding()`

La selección está centralizada mediante:

`compuciber_obtener_proveedor_ia()`

Proveedores implementados:

- Google Gemini.
- OpenAI.

Cada proveedor conserva opciones independientes para su API key y modelos de texto, embeddings e imagen.

La búsqueda por voz es independiente de estos proveedores y utiliza Web Speech API.

## 7. Funcionamiento sin IA

La indisponibilidad de una API de IA no elimina el motor local. Continúan disponibles, según configuración:

- búsqueda por nombre;
- SKU;
- marca;
- modelo;
- atributos;
- categorías;
- autocomplete;
- fuzzy search;
- corrección;
- ranking local.

Esto evita que el funcionamiento esencial del buscador dependa exclusivamente de un proveedor externo.

## 8. Base de datos y esquema

El plugin utiliza tablas propias dentro de la base de datos de WordPress, empleando el prefijo configurado en cada instalación.

### Índice de productos

Tabla lógica:

`{prefix}compuciber_product_index`

Campos principales del índice:

- `id`
- `nombre`
- `sku`
- `marca`
- `modelo`
- `categorias`
- `atributos`
- `etiquetas`
- `contenido`
- `precio`
- `oferta`
- `stock`
- `variaciones`
- `texto_busqueda`

El proyecto también utiliza persistencia para estadísticas, selecciones/interacciones y datos necesarios para las funciones incorporadas en versiones posteriores.

**Fuente autoritativa del esquema:** las funciones PHP de creación y actualización de tablas incluidas en el código fuente, especialmente los módulos de indexación/activación y las rutinas de base de datos del plugin. Para una entrega formal debe conservarse dicho código junto con el repositorio.

No se requiere entregar una copia con datos comerciales reales de una tienda. El requisito se satisface mediante el esquema y las rutinas reproducibles de creación/migración.

## 9. Indexación

La indexación obtiene información de WooCommerce y mantiene el índice del plugin.

Se contemplan hooks de creación y actualización de productos. El índice permite realizar búsquedas locales incluso cuando no existe una API de IA configurada.

Los embeddings almacenan además la identidad de proveedor/modelo necesaria para determinar su compatibilidad con la configuración activa.

## 10. Estadísticas y aprendizaje de ranking

La V2.0 incorpora:

- total de búsquedas;
- búsquedas con resultados;
- búsquedas sin resultados;
- selecciones;
- tasa de éxito;
- CTR global;
- búsquedas por canal;
- búsquedas populares;
- productos seleccionados;
- consultas que generan selecciones;
- evolución temporal.

El aprendizaje de ranking utiliza interacciones históricas para aplicar un bonus limitado a productos que ya poseen relevancia base. No constituye un modelo de machine learning autónomo.

## 11. Sistema de licencias y múltiples instalaciones

El plugin contiene el código cliente necesario para:

- clave de licencia;
- UUID por instalación;
- identificación del dominio;
- estado de licencia;
- validación remota configurable;
- caché;
- periodo de gracia;
- validación manual;
- validación periódica;
- límites de instalaciones informados por un servidor externo.

El plugin no incluye actualmente un backend comercial de licencias operado por COMPUCIBER. Si posteriormente se contrata o desarrolla ese servicio, debe documentarse como servicio externo o incorporarse su código fuente a la entrega según lo acordado.

## 12. Preparación SaaS

La preparación SaaS de V2.0 corresponde al lado cliente del plugin:

- interfaz/contrato;
- cliente HTTP;
- endpoint configurable;
- datos de instalación;
- normalización de respuestas;
- caché;
- cron;
- periodo de gracia.

No debe interpretarse como la existencia de un backend SaaS completo dentro del plugin.

## 13. Dependencias

Dependencias de plataforma:

- WordPress.
- WooCommerce.

Integraciones opcionales o condicionadas:

- Elementor, para utilizar el widget nativo.
- Gutenberg/Block Editor, para el bloque correspondiente.
- Web Speech API del navegador, para búsqueda por voz.

El plugin utiliza las APIs y funciones nativas de WordPress para solicitudes HTTP, opciones, hooks, cron, administración y base de datos.

## 14. APIs externas

### Google Gemini

Uso:

- interpretación de texto;
- análisis de imágenes;
- embeddings.

La API key se configura desde WordPress y no debe incluirse en el repositorio ni en el ZIP de distribución.

### OpenAI

El código incorpora un proveedor para:

- interpretación de texto;
- análisis de imágenes;
- embeddings.

La API key se configura independientemente y no debe incluirse en el repositorio ni en el ZIP.

### Web Speech API

Se utiliza desde navegadores compatibles para reconocimiento de voz. No corresponde a una credencial almacenada por el plugin.

### Servicio externo de licencias/SaaS

La V2.0 dispone de un cliente configurable, pero el endpoint comercial definitivo debe ser suministrado/configurado cuando exista el servicio correspondiente.

## 15. Credenciales y configuración

Las credenciales deben permanecer separadas del código fuente.

No deben incluirse en Git ni en el ZIP:

- API keys de Gemini;
- API keys de OpenAI;
- credenciales de WordPress;
- credenciales de hosting;
- contraseñas;
- tokens privados;
- claves de servicios futuros.

Las API keys del plugin se administran mediante opciones/configuración de WordPress.

Antes de distribuir un ZIP debe comprobarse que no existan secretos codificados directamente en archivos PHP, JavaScript, README, CHANGELOG u otros archivos.

## 16. Frontend y compilación

En la estructura actual documentada, los recursos frontend se distribuyen como archivos fuente dentro de `assets/`, junto con las integraciones correspondientes.

No se ha definido en esta entrega un proceso obligatorio de compilación con Node.js, npm, Vite, Webpack u otra cadena de build.

Por tanto, mientras la estructura permanezca así, **no existe un paso de compilación obligatorio para instalar el plugin**.

Si posteriormente se introduce frontend compilado, deberán incorporarse al repositorio:

- archivos fuente;
- `package.json`;
- lockfile correspondiente;
- versión de Node requerida;
- comandos de instalación;
- comando de build;
- instrucciones para reproducir los archivos distribuidos.

## 17. Instalación

1. Realizar una copia de seguridad del sitio.
2. Ingresar a WordPress.
3. Ir a **Plugins → Añadir plugin → Subir plugin**.
4. Seleccionar `compuciber-ia-search.zip`.
5. Instalar.
6. Activar.
7. Abrir la configuración de Compuciber AI Search.
8. Configurar funciones y, opcionalmente, proveedor/API de IA.
9. Ejecutar o verificar la indexación.
10. Insertar el buscador mediante shortcode, Gutenberg, Elementor o integración PHP.
11. Realizar pruebas de búsqueda.

Shortcode:

`[compuciber_ai_search]`

## 18. Actualización desde una versión anterior

No se recomienda desinstalar el plugin para actualizarlo.

Procedimiento:

1. realizar copia de seguridad;
2. subir el nuevo ZIP;
3. permitir que WordPress reemplace la versión instalada;
4. conservar/revisar configuración;
5. comprobar las tablas y el índice;
6. comprobar búsquedas;
7. comprobar estadísticas;
8. comprobar las funciones de IA configuradas.

Las actualizaciones que modifiquen el esquema de base de datos deben acompañarse de rutinas reproducibles de migración.

## 19. Repositorio Git

El repositorio Git forma parte de la entrega.

Debe entregarse a COMPUCIBER S.A.C. con acceso suficiente para conservar y continuar el desarrollo.

El repositorio debe contener:

- código fuente;
- README;
- CHANGELOG;
- historial de commits;
- ramas que formen parte de la entrega, cuando corresponda;
- tags/releases cuando se utilicen;
- archivos necesarios para reproducir el plugin.

No deben incluirse secretos ni credenciales.

## 20. Versionado

Historial funcional documentado:

- **V1.0.0:** base del buscador inteligente, WooCommerce, indexación, autocomplete, fuzzy, corrección, sinónimos, búsqueda semántica y administración.
- **V1.1.0:** búsqueda por voz.
- **V1.2.0:** búsqueda por imagen.
- **V2.0.0:** estadísticas avanzadas, aprendizaje de ranking, licencias, múltiples instalaciones, preparación SaaS, Elementor y arquitectura ampliada de proveedores.

El archivo `CHANGELOG.md` conserva el detalle del historial.

## 21. Entrega del ZIP

El ZIP distribuible debe contener la carpeta completa del plugin y todo el código necesario para su funcionamiento.

No debe depender de archivos esenciales existentes únicamente en el equipo del desarrollador.

Antes de entregar:

- verificar versión;
- eliminar archivos temporales;
- eliminar trazas de debug innecesarias;
- verificar ausencia de credenciales;
- probar instalación/actualización;
- comprobar indexación;
- ejecutar regresión de búsqueda.

## 22. Compatibilidad probada durante el desarrollo

Entorno registrado durante las pruebas:

- WordPress 7.1.2.
- WooCommerce 11.1.2.
- PHP 8.2.29.

También se realizaron pruebas con cambio de tema y una instalación externa de WordPress/WooCommerce.

## 23. Estado de validación

Probado durante el desarrollo:

- instalación mediante ZIP;
- indexación;
- búsqueda local;
- autocomplete;
- SKU;
- fuzzy search;
- coincidencias técnicas;
- Gemini;
- búsqueda semántica con Gemini;
- búsqueda por imagen con Gemini;
- persistencia independiente de modelos Gemini/OpenAI;
- funcionamiento sin API de IA;
- widget Elementor;
- estadísticas;
- aprendizaje de ranking;
- arquitectura cliente de licencias.

Pendiente de validación final antes de considerar una entrega de producción cerrada:

- limpieza final de logs de depuración;
- prueba real de OpenAI con una API key válida;
- revisión final de migraciones de base de datos;
- regresión completa del ZIP V2.0.0;
- prueba final de actualización sobre una instalación anterior.

## 24. Continuidad del desarrollo

La arquitectura se ha organizado para que COMPUCIBER S.A.C. pueda continuar el proyecto desde el código entregado.

Para añadir un proveedor de IA adicional se debe:

1. implementar `Compuciber_AIProviderInterface`;
2. crear sus módulos de texto, imagen y embeddings;
3. registrar sus opciones;
4. incorporarlo al selector central de proveedores;
5. añadir su configuración administrativa;
6. documentar sus dependencias y API.

El motor de búsqueda local no necesita ser reescrito para este proceso.

## 25. Propiedad del desarrollo

El código fuente entregado del plugin Compuciber AI Search queda bajo propiedad de **COMPUCIBER S.A.C.**, conforme a los términos contractuales aplicables al proyecto.

La entrega técnica contempla código fuente, esquema de datos, documentación, configuración reproducible, repositorio Git e historial de versiones.

No deben existir componentes esenciales del buscador cuyo código fuente permanezca únicamente en poder del desarrollador, salvo servicios externos expresamente acordados y documentados.
