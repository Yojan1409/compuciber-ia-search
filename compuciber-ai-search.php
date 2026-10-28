<?php
/**
 * Plugin Name: Compuciber AI Search
 * Description: Buscador inteligente de productos para Compuciber.
 * Version: 2.1.2
 * Author: Compuciber
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * ==========================================================
 * INFORMACIÓN DEL PLUGIN
 * ==========================================================
 */

if ( ! defined( 'COMPUCIBER_AI_SEARCH_VERSION' ) ) {
    define(
        'COMPUCIBER_AI_SEARCH_VERSION',
        '2.1.2'
    );
}

function compuciber_verificar_woocommerce() {

    if ( ! class_exists( 'WooCommerce' ) ) {

        add_action( 'admin_notices', function() {

            echo '<div class="notice notice-error">';
            echo '<p>';
            echo '<strong>Compuciber AI Search:</strong> ';
            echo 'WooCommerce debe estar instalado y activo para utilizar este plugin.';
            echo '</p>';
        } );

        return false;
    }

    return true;
}


require_once plugin_dir_path( __FILE__ ) . 'includes/correcciones.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/sugerencias.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/configuracion.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-panel.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/ia.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/sugerencias-ia.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/imagenes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/proveedores/openai.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/proveedores/gemini.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/resultados.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/licencias.php';

/*
 * ==========================================================
 * DESACTIVACIÓN DEL PLUGIN
 * ==========================================================
 */

/**
 * Limpia las tareas programadas del sistema
 * de licencias cuando el plugin se desactiva.
 */
register_deactivation_hook(
    __FILE__,
    'compuciber_limpiar_cron_licencia'
);

require_once plugin_dir_path( __FILE__ ) . 'includes/indexacion.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/fuzzy.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/sinonimos.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-correcciones.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/estadisticas.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/exportacion-excel.php';

/**
 * Verifica WooCommerce después de que WordPress haya cargado los plugins.
 */
add_action( 'plugins_loaded', 'compuciber_verificar_woocommerce', 20 );

/**
 * Registra el widget nativo de Compuciber AI Search
 * cuando Elementor está instalado y activo.
 */
function compuciber_registrar_widget_elementor( $widgets_manager ) {

    $archivo_widget =
        plugin_dir_path( __FILE__ )
        . 'elementor/widget-ai-search.php';

    if ( ! file_exists( $archivo_widget ) ) {
        return;
    }

    require_once $archivo_widget;

    if (
        class_exists(
            'Compuciber_AI_Search_Elementor_Widget'
        )
    ) {
        $widgets_manager->register(
            new Compuciber_AI_Search_Elementor_Widget()
        );
    }
}

/**
 * Conecta Compuciber AI Search con Elementor.
 *
 * Si Elementor no está instalado, este hook
 * simplemente nunca se ejecutará y el buscador
 * continuará funcionando normalmente.
 */
add_action(
    'elementor/widgets/register',
    'compuciber_registrar_widget_elementor'
);

/**
 * Cargar JavaScript del buscador
 */
function compuciber_cargar_buscador_js() {

    /*
     * Versionar automáticamente los archivos
     * cuando se modifica su contenido.
     */
    wp_enqueue_style(
        'compuciber-buscador',
        plugin_dir_url( __FILE__ ) . 'assets/css/buscador.css',
        array(),
        filemtime(
            plugin_dir_path( __FILE__ ) . 'assets/css/buscador.css'
        )
    );

    wp_enqueue_script(
        'compuciber-buscador',
        plugin_dir_url( __FILE__ ) . 'assets/js/buscador.js',
        array(),
        filemtime(
            plugin_dir_path( __FILE__ ) . 'assets/js/buscador.js'
        ),
        true
    );

    wp_localize_script(
        'compuciber-buscador',
        'CompuciberSearch',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),

            'suggestionAiNonce' => wp_create_nonce(
                'compuciber_sugerencia_ia'
            ),

            'suggestionAiEnabled' => (
                get_option(
                    'compuciber_autocompletado',
                    '1'
                ) === '1'
                && get_option(
                    'compuciber_correccion',
                    '1'
                ) === '1'
                && get_option(
                    'compuciber_busqueda_semantica',
                    '0'
                ) === '1'
            ),            

            'imageNonce' => wp_create_nonce(
                'compuciber_busqueda_imagen'
            ),

            'selectionNonce' => wp_create_nonce(
                'compuciber_registrar_seleccion'
            ),
        )
    );
}

add_action(
    'wp_enqueue_scripts',
    'compuciber_cargar_buscador_js'
);

/**
 * Renovar nonces públicos fuera de la caché HTML.
 *
 * No devuelve claves API ni datos privados.
 */
function compuciber_tokens_publicos() {

    nocache_headers();

    wp_send_json_success(
        array(
            'imageNonce' => wp_create_nonce(
                'compuciber_busqueda_imagen'
            ),

            'suggestionAiNonce' => wp_create_nonce(
                'compuciber_sugerencia_ia'
            ),

            'selectionNonce' => wp_create_nonce(
                'compuciber_registrar_seleccion'
            ),
        )
    );
}

add_action(
    'wp_ajax_compuciber_tokens_publicos',
    'compuciber_tokens_publicos'
);

add_action(
    'wp_ajax_nopriv_compuciber_tokens_publicos',
    'compuciber_tokens_publicos'
);

/*
 * ==========================================================
 * AJAX - BÚSQUEDA POR IMAGEN
 * ==========================================================
 */

function compuciber_ajax_busqueda_imagen() {

    if ( ! isset( $_POST['nonce'] ) ) {
        wp_send_json_error(
            'Solicitud no válida.'
        );
    }


    if (
        ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['nonce']
                )
            ),
            'compuciber_busqueda_imagen'
        )
    ) {

        wp_send_json_error(
            'Error de seguridad.'
        );
    }


    if (
        ! get_option(
            'compuciber_busqueda_imagen',
            '0'
        )
    ) {

        wp_send_json_error(
            'La búsqueda por imagen está desactivada.'
        );
    }


    if ( empty( $_FILES['imagen'] ) ) {

        wp_send_json_error(
            'No se recibió ninguna imagen.'
        );
    }


    $archivo = $_FILES['imagen'];


    if ( ! empty( $archivo['error'] ) ) {

        wp_send_json_error(
            'No se pudo recibir la imagen.'
        );
    }


    /*
     * Limita el tamaño de la imagen.
     */

    if (
        isset( $archivo['size'] )
        && $archivo['size'] > 5 * 1024 * 1024
    ) {

        wp_send_json_error(
            'La imagen es demasiado grande. Máximo 5 MB.'
        );
    }


    /*
     * Verifica realmente el tipo de archivo.
     */

    $tipo = wp_check_filetype(
        $archivo['name']
    );


    $tipos_permitidos = array(
        'jpg',
        'jpeg',
        'png',
        'webp',
    );


    if (
        empty( $tipo['ext'] )
        || ! in_array(
            strtolower( $tipo['ext'] ),
            $tipos_permitidos,
            true
        )
    ) {

        wp_send_json_error(
            'Formato de imagen no permitido.'
        );
    }

    $mime_original = wp_get_image_mime(
        $archivo['tmp_name']
    );

    if (
        ! in_array(
            $mime_original,
            array(
                'image/jpeg',
                'image/png',
                'image/webp'
            ),
            true
        )
    ) {
        wp_send_json_error(
            'El archivo no contiene una imagen permitida.'
        );
    }

    /*
    * Optimiza la imagen antes de enviarla a la IA.
    */

    $imagen_optimizada = compuciber_optimizar_imagen_ia(
        $archivo['tmp_name']
    );

    if (
        ! is_string( $imagen_optimizada )
        || ! is_file( $imagen_optimizada )
    ) {
        wp_send_json_error(
            'No se pudo optimizar la imagen.'
        );
    }

    // Limpiar la copia temporal al finalizar la petición.
    register_shutdown_function(
        static function () use ( $imagen_optimizada ) {
            if ( is_file( $imagen_optimizada ) ) {
                @unlink( $imagen_optimizada );
            }
        }
    );

    // Obtener el formato real de la imagen procesada.
    $mime_type = wp_get_image_mime(
        $imagen_optimizada
    );

    if (
        ! in_array(
            $mime_type,
            array(
                'image/jpeg',
                'image/png',
                'image/webp'
            ),
            true
        )
    ) {
        wp_send_json_error(
            'Formato de imagen procesada no permitido.'
        );
    }

    $hash_imagen = compuciber_generar_hash_imagen(
        $imagen_optimizada
    );

    // Identificar proveedor y modelo utilizados.

    $proveedor_cache = sanitize_key(
        (string) get_option(
            'compuciber_proveedor_ia',
            'gemini'
        )
    );

    $opcion_modelo_cache = (
        $proveedor_cache === 'openai'
    )
        ? 'compuciber_openai_modelo_multimodal'
        : 'compuciber_gemini_modelo_multimodal';

    $modelo_cache = trim(
        (string) get_option(
            $opcion_modelo_cache,
            ''
        )
    );

    // Separar las interpretaciones por configuración.

    $hash_imagen = hash(
        'sha256',
        'visual-general-v3|'
        . $proveedor_cache
        . '|'
        . $modelo_cache
        . '|'
        . $hash_imagen
    );    

    $cache_imagen = compuciber_obtener_cache_imagen(
        $hash_imagen
    );

    $imagen = file_get_contents(
        $imagen_optimizada
    );

    if ( $imagen === false ) {
        wp_send_json_error(
            'No se pudo leer la imagen optimizada.'
        );
    }

    $imagen_base64 = base64_encode(
        $imagen
    );


    /*
     * Envia la imagen al proveedor de IA
     * exclusivamente desde el backend.
     */

    if (
        $cache_imagen
    ) {

        $resultado = $cache_imagen;

    } else {

    /*
    * LIMITADOR DE CONSUMO DE IA POR IMAGEN
    *
    * Aplica solo cuando no existe una
    * interpretación guardada en caché.
    */

    $ip_visitante = isset( $_SERVER['REMOTE_ADDR'] )
        && is_string( $_SERVER['REMOTE_ADDR'] )
        ? $_SERVER['REMOTE_ADDR']
        : '';

    if ( $ip_visitante !== '' ) {

        $ventana = 10 * MINUTE_IN_SECONDS;

        $bloque = (int) floor(
            time() / $ventana
        );

        // No guardar la dirección IP original.
        $identificador = hash_hmac(
            'sha256',
            $ip_visitante . '|' . $bloque,
            wp_salt( 'auth' )
        );

        $clave_limite = 'cis_img_lmt_'
            . substr( $identificador, 0, 32 );

        $intentos = (int) get_transient(
            $clave_limite
        );

        // Límite predeterminado: 5 solicitudes.
        $max_intentos = max(
            1,
            (int) apply_filters(
                'compuciber_limite_imagen_por_ventana',
                5
            )
        );

        if ( $intentos >= $max_intentos ) {

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(
                    'CIS LIMITE IMAGEN: solicitud bloqueada antes de IA'
                );
            }

            wp_send_json_error(
                'Has realizado varias búsquedas por imagen. Inténtalo nuevamente en unos minutos.',
                429
            );
        }

        // Registrar el intento antes de llamar a la API.
        set_transient(
            $clave_limite,
            $intentos + 1,
            $ventana
        );
    }

        $resultado = compuciber_consultar_ia_imagen(
            $imagen_base64,
            $mime_type
        );


        if (
            is_array( $resultado )
            && empty( $resultado['error'] )
        ) {

            compuciber_guardar_cache_imagen(
                $hash_imagen,
                $resultado
            );

        }

    }


    if (
        ! is_array( $resultado )
    ) {

        wp_send_json_error(
            'La IA no pudo analizar la imagen.'
        );

    }


    if (
        isset( $resultado['error'] )
        && isset( $resultado['mensaje'] )
    ) {

        wp_send_json_error(
            $resultado['mensaje']
        );

    }

    $busqueda = '';

    if ( ! empty( $resultado['busqueda_corregida'] ) ) {

        $busqueda = sanitize_text_field(
            $resultado['busqueda_corregida']
        );
    }

    /*
     * Si Gemini no devuelve busqueda_corregida,
     * se utiliza el producto + marca como respaldo.
     */
    if ( empty( $busqueda ) ) {

        $terminos_respaldo = array();

        if ( ! empty( $resultado['producto'] ) ) {
            $terminos_respaldo[] =
                $resultado['producto'];
        }

        if ( ! empty( $resultado['marca'] ) ) {
            $terminos_respaldo[] =
                $resultado['marca'];
        }      

        if ( ! empty( $resultado['modelo'] ) ) {
            $terminos_respaldo[] =
                $resultado['modelo'];
        }
    

        $busqueda = implode(
            ' ',
            array_unique(
                array_filter(
                    $terminos_respaldo
                )
            )
        );
    }

    if ( empty( $busqueda ) ) {

        wp_send_json_error(
            'No se pudo identificar un producto en la imagen.'
        );
    }


    $token_imagen = wp_generate_password(
        40,
        false,
        false
    );

    $guardado = set_transient(
        'cis_imagen_' . $token_imagen,
        array(
            'busqueda' => sanitize_text_field(
                $busqueda
            ),
            'interpretacion' => $resultado,
        ),
        10 * MINUTE_IN_SECONDS
    );

    if ( ! $guardado ) {
        wp_send_json_error(
            'No se pudo preparar la búsqueda por imagen.',
            500
        );
    }

    wp_send_json_success(
        array(
            'busqueda' => $busqueda,
            'interpretacion' => $resultado,
            'token' => $token_imagen,
        )
    );
}


add_action(
    'wp_ajax_compuciber_busqueda_imagen',
    'compuciber_ajax_busqueda_imagen'
);


add_action(
    'wp_ajax_nopriv_compuciber_busqueda_imagen',
    'compuciber_ajax_busqueda_imagen'
);

/**
 * Encuentra una variación real a partir de los
 * atributos indicados en la búsqueda.
 *
 * Si faltan datos o hay varias coincidencias,
 * no selecciona ninguna variación.
 */
function compuciber_encontrar_variacion_por_atributos(
    $producto_padre,
    $consulta
) {

    if (
        ! is_object( $producto_padre )
        || ! $producto_padre->is_type( 'variable' )
    ) {
        return false;
    }

    // Igualar expresiones como "16 GB" y "16GB".
    $normalizar = static function( $texto ) {

        $texto = compuciber_normalizar_texto(
            $texto
        );

        return preg_replace(
            '/(?<=\d)\s+(?=(?:gb|tb|mb)\b)/',
            '',
            $texto
        );
    };

    $consulta = $normalizar( $consulta );
    $filtros = array();

    if ( $consulta === '' ) {
        return false;
    }

    // Revisar los atributos del producto variable.
    foreach (
        $producto_padre->get_variation_attributes()
        as $atributo => $opciones
    ) {

        if (
            ! is_array( $opciones )
            || empty( $opciones )
        ) {
            continue;
        }

        $detectadas = array();

        foreach ( $opciones as $opcion ) {

            $opcion = (string) $opcion;

            if ( $opcion === '' ) {
                continue;
            }

            $etiquetas = array( $opcion );

            // Reconocer también el nombre del término.
            if ( taxonomy_exists( $atributo ) ) {

                $termino = get_term_by(
                    'slug',
                    $opcion,
                    $atributo
                );

                if (
                    $termino
                    && ! is_wp_error( $termino )
                ) {
                    $etiquetas[] = $termino->name;
                }
            }

            foreach ( $etiquetas as $etiqueta ) {

                $valor = $normalizar( $etiqueta );

                if (
                    $valor !== ''
                    && preg_match(
                        '/(?<![a-z0-9])'
                        . preg_quote( $valor, '/' )
                        . '(?![a-z0-9])/',
                        $consulta
                    )
                ) {

                    $detectadas[ $opcion ] = true;
                    break;
                }
            }
        }

        // No seleccionar si hay valores contradictorios.
        if ( count( $detectadas ) > 1 ) {
            return false;
        }

        if ( count( $detectadas ) === 1 ) {

            $filtros[ $atributo ] = (string)
                array_key_first( $detectadas );

        } elseif ( count( $opciones ) > 1 ) {

            // No adivinar un atributo que el cliente omitió.
            return false;
        }
    }

    if ( empty( $filtros ) ) {
        return false;
    }

    $encontrada = false;

    // Comparar los valores dentro de cada variación real.
    foreach (
        $producto_padre->get_children()
        as $id_variacion
    ) {

        $variacion = wc_get_product(
            $id_variacion
        );

        if (
            ! $variacion
            || ! $variacion->is_type( 'variation' )
            || ! $variacion->is_visible()
            || ! $variacion->variation_is_visible()
        ) {
            continue;
        }

// Normalizar nombres de atributos del producto y la variación.
        $atributos_reales = array();

        foreach ( $variacion->get_attributes() as $nombre_real => $valor_real ) {
            $nombre_real = preg_replace( '/^attribute_/', '', (string) $nombre_real );
            $atributos_reales[ sanitize_title( $nombre_real ) ] = $valor_real;
        }

        foreach ( $filtros as $atributo => $valor_esperado ) {
            $clave_atributo = sanitize_title(
                preg_replace( '/^attribute_/', '', (string) $atributo )
            );

            if (
                ! isset( $atributos_reales[ $clave_atributo ] )
                || $atributos_reales[ $clave_atributo ] === ''
                || $normalizar( $atributos_reales[ $clave_atributo ] )
                    !== $normalizar( $valor_esperado )
            ) {
                continue 2;
            }
        }

        // Si coinciden dos variantes, no elegir al azar.
        if ( $encontrada ) {
            return false;
        }

        $encontrada = $variacion;
    }

    return $encontrada;
}

/**
 * Comprobar si la búsqueda tiene coincidencias
 * textuales directas dentro del catálogo.
 */
/**
 * Comprobar coincidencias directas dentro
 * del catálogo permitido por cada perfil.
 */
function compuciber_coincidencia_directa_catalogo(
    $consulta,
    $perfil_id = ''
) {

    global $wpdb;

    $perfil_id = sanitize_key(
        (string) $perfil_id
    );

    $filtro_perfil_sql = '';

    if ( $perfil_id !== '' ) {

        if ( ! function_exists(
            'compuciber_perfil_condicion_sql'
        ) ) {
            return false;
        }

        $filtro_perfil_sql =
            compuciber_perfil_condicion_sql(
                $perfil_id
            );
    }

    $palabras = array_slice(
        compuciber_obtener_palabras( $consulta ),
        0,
        8
    );

    if ( empty( $palabras ) ) {
        return true;
    }

    $tabla = $wpdb->prefix
        . 'compuciber_product_index';

    $condiciones = array_fill(
        0,
        count( $palabras ),
        'i.texto_busqueda LIKE %s'
    );

    $valores = array();

    foreach ( $palabras as $palabra ) {

        $valores[] = '%'
            . $wpdb->esc_like( $palabra )
            . '%';
    }

    $sql = "SELECT 1
        FROM {$tabla} i
        INNER JOIN {$wpdb->posts} p
            ON p.ID = i.id
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          {$filtro_perfil_sql}
          AND " . implode( ' AND ', $condiciones ) . "
        LIMIT 1";

    return (bool) $wpdb->get_var(
        $wpdb->prepare( $sql, $valores )
    );
}

/**
 * Validar correcciones ortográficas propuestas.
 * Evita cambiar códigos, modelos y números.
 */
function compuciber_validar_correccion_ortografica( $original, $propuesta ) {

    $original = compuciber_normalizar_texto( $original );
    $propuesta = compuciber_normalizar_texto( $propuesta );

    if (
        $original === ''
        || $propuesta === ''
        || $original === $propuesta
    ) {
        return false;
    }

    $antes = explode( ' ', $original );
    $despues = explode( ' ', $propuesta );

    if (
        count( $antes ) !== count( $despues )
        || count( $antes ) > 8
    ) {
        return false;
    }

    $cambios = 0;

    foreach ( $antes as $i => $palabra ) {

        $nueva = $despues[ $i ];

        if ( $palabra === $nueva ) {
            continue;
        }

        // Proteger SKU, modelos y números.
        if (
            preg_match( '/[0-9]/', $palabra )
            || preg_match( '/[0-9]/', $nueva )
            || strlen( $palabra ) < 4
            || strlen( $nueva ) < 4
        ) {
            return false;
        }

        $max_distancia = strlen( $palabra ) <= 5 ? 1 : 2;

        if ( levenshtein( $palabra, $nueva ) > $max_distancia ) {
            return false;
        }

        $cambios++;
    }

    return $cambios > 0;
}

/**
 * Shortcode:
 * [compuciber_ai_search]
 */
function compuciber_ai_search_shortcode( $atts = array() )
{
    $atts = shortcode_atts(
        array(
            'solo_barra'      => 'no',
            'solo_resultados' => 'no',
            'perfil'          => '',
        ),
        is_array( $atts ) ? $atts : array(),
        'ai_product_search'
    );

    // Identificar el perfil del buscador.
    $perfil_id = sanitize_key(
        (string) $atts['perfil']
    );

    /*
    * Recuperar el perfil enviado desde otra página
    * solamente en buscadores que muestran resultados.
    *
    * Las barras de cabecera conservan su propio perfil.
    */
    if (
        $perfil_id === ''
        && ! in_array(
            strtolower( (string) $atts['solo_barra'] ),
            array( 'si', '1', 'true', 'yes' ),
            true
        )
        && isset( $_GET['cis_perfil'] )
        && is_string( $_GET['cis_perfil'] )
    ) {
        $perfil_id = sanitize_key(
            wp_unslash( $_GET['cis_perfil'] )
        );
    }

    $solo_barra = in_array(
        strtolower( (string) $atts['solo_barra'] ),
        array( 'si', '1', 'true', 'yes' ),
        true
    );

    /*
    * Modo Solo resultados.
    * Permite mostrar los productos sin repetir
    * la barra que ya existe en la cabecera.
    */
    $solo_resultados = in_array(
        strtolower( (string) $atts['solo_resultados'] ),
        array( 'si', '1', 'true', 'yes' ),
        true
    );

    // Solo resultados tiene prioridad sobre Solo barra.
    if ( $solo_resultados ) {
        $solo_barra = false;
    }    

    /*
    * Página de resultados predeterminada.
    * Se utiliza siempre que no exista
    * una página específica para el perfil.
    */
    $pagina_resultados_id = absint(
        get_option( 'compuciber_pagina_resultados', 0 )
    );

    /*
    * Consultar una página específica únicamente
    * cuando se está utilizando un perfil.
    *
    * El buscador predeterminado permanece intacto.
    */
    if ( $perfil_id !== '' ) {

        $cis_perfiles_destino = get_option(
            'compuciber_perfiles_busqueda',
            array()
        );

        if (
            is_array( $cis_perfiles_destino )
            && isset( $cis_perfiles_destino[ $perfil_id ] )
            && is_array( $cis_perfiles_destino[ $perfil_id ] )
            && ! empty( $cis_perfiles_destino[ $perfil_id ]['activo'] )
        ) {

            $cis_pagina_especifica = absint(
                $cis_perfiles_destino[ $perfil_id ]['pagina_resultados'] ?? 0
            );

            /*
            * Utilizar la página específica solo
            * si existe y está publicada.
            */
            if (
                $cis_pagina_especifica > 0
                && get_post_type( $cis_pagina_especifica ) === 'page'
                && get_post_status( $cis_pagina_especifica ) === 'publish'
            ) {

                $pagina_resultados_id = $cis_pagina_especifica;

            }
        }
    }

    $pagina_configurada =
        $pagina_resultados_id > 0
        && get_post_type( $pagina_resultados_id ) === 'page'
        && get_post_status( $pagina_resultados_id ) === 'publish';

    $url_resultados = $pagina_configurada
        ? get_permalink( $pagina_resultados_id )
        : '';

    $mostrar_resultados = ! $solo_barra && (
        ! $pagina_configurada
        || is_page( $pagina_resultados_id )
    );

    $busqueda_original = isset( $_GET['cis_q'] )
        ? sanitize_text_field(
            wp_unslash(
                $_GET['cis_q']
            )
        )
        : '';

    $interpretacion = array();

    $busqueda = $mostrar_resultados
        ? compuciber_corregir_busqueda( $busqueda_original )
        : $busqueda_original;


    /*
     * ======================================================
     * INTERPRETACIÓN PREVIA DE UNA BÚSQUEDA POR IMAGEN
     * ======================================================
     *
     * El JavaScript guarda aquí la interpretación completa
     * que Gemini obtuvo de la imagen.
     *
     * De esta forma NO volvemos a llamar a Gemini como texto.
     */

    $es_busqueda_imagen = false;

    if (
        $mostrar_resultados
        && isset( $_GET['cis_img_token'] )
        && is_string( $_GET['cis_img_token'] )
    ) {

        $token_imagen = sanitize_text_field(
            wp_unslash(
                $_GET['cis_img_token']
            )
        );

        if (
            preg_match(
                '/\A[A-Za-z0-9]{40}\z/D',
                $token_imagen
            )
        ) {

            $datos_imagen = get_transient(
                'cis_imagen_' . $token_imagen
            );

            if (
                is_array( $datos_imagen )
                && isset(
                    $datos_imagen['busqueda'],
                    $datos_imagen['interpretacion']
                )
                && is_string(
                    $datos_imagen['busqueda']
                )
                && is_array(
                    $datos_imagen['interpretacion']
                )
                && hash_equals(
                    $datos_imagen['busqueda'],
                    $busqueda_original
                )
            ) {

                $interpretacion =
                    $datos_imagen['interpretacion'];

                $es_busqueda_imagen = true;
            }
        }
    }


    if (
        $mostrar_resultados
        && ! empty( $busqueda_original )
        && empty( $interpretacion )
        && get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) === '1'
        && compuciber_busqueda_necesita_ia(
            $busqueda_original
        )
    ) {

            $interpretacion =
                compuciber_consultar_ia(
                    $busqueda_original
                );

            if (
                is_array( $interpretacion )
                && ! empty(
                    $interpretacion['busqueda_corregida']
                )
            ) {

                $busqueda =
                    sanitize_text_field(
                        $interpretacion[
                            'busqueda_corregida'
                        ]
                    );

            }

        }


    /*
     * ======================================================
     * SI LA IMAGEN TRAJO SU PROPIA INTERPRETACIÓN
     * ======================================================
     */

    if (
        ! empty( $interpretacion )
        && ! empty(
            $interpretacion['busqueda_corregida']
        )
    ) {

        $busqueda =
            sanitize_text_field(
                $interpretacion[
                    'busqueda_corregida'
                ]
            );

    }

    /*
    * Estilos visuales independientes por perfil.
    * El buscador predeterminado no se modifica.
    */
    $cis_estilo_input = '';
    $cis_estilo_boton = '';
    $cis_estilo_sugerencias = '';
    $cis_placeholder = '¿Qué producto estás buscando?';

    if ( $perfil_id !== '' ) {

        $cis_estilos = get_option(
            'compuciber_estilos_perfiles',
            array()
        );

        $cis_datos = is_array( $cis_estilos )
            ? ( $cis_estilos[ $perfil_id ] ?? array() )
            : array();

        if ( is_array( $cis_datos ) && ! empty( $cis_datos ) ) {

            $fondo = sanitize_hex_color(
                (string) ( $cis_datos['fondo'] ?? '' )
            ) ?: '#ffffff';

            $texto = sanitize_hex_color(
                (string) ( $cis_datos['texto'] ?? '' )
            ) ?: '#222222';

            $borde = sanitize_hex_color(
                (string) ( $cis_datos['borde'] ?? '' )
            ) ?: '#d1d5db';

            $boton = sanitize_hex_color(
                (string) ( $cis_datos['boton'] ?? '' )
            ) ?: '#1e3a5f';

            $texto_boton = sanitize_hex_color(
                (string) ( $cis_datos['texto_boton'] ?? '' )
            ) ?: '#ffffff';

            $radio = min(
                30,
                absint( $cis_datos['radio'] ?? 8 )
            );

            $cis_estilo_input = sprintf(
                'background-color:%s;color:%s;border:1px solid %s;border-radius:%dpx;',
                $fondo,
                $texto,
                $borde,
                $radio
            );

            $cis_estilo_boton = sprintf(
                'background-color:%s;color:%s;border-radius:%dpx;',
                $boton,
                $texto_boton,
                $radio
            );

            $cis_estilo_sugerencias = sprintf(
                'background-color:%s;color:%s;border-color:%s;border-radius:%dpx;',
                $fondo,
                $texto,
                $borde,
                $radio
            );

            $nuevo_placeholder = sanitize_text_field(
                (string) ( $cis_datos['placeholder'] ?? '' )
            );

            if ( $nuevo_placeholder !== '' ) {
                $cis_placeholder = $nuevo_placeholder;
            }
        }
    }    

    ob_start();
    ?>

    <div class="compuciber-ai-search"
     data-cis-perfil="<?php echo esc_attr( $perfil_id ); ?>">

        <?php if ( ! $solo_resultados ) : ?>
        <form method="get" action="<?php echo esc_url( $url_resultados ); ?>">
        <?php if ( $perfil_id !== '' ) : ?>

            <input
                type="hidden"
                name="cis_perfil"
                value="<?php echo esc_attr( $perfil_id ); ?>"
            >

        <?php endif; ?>

            <div class="compuciber-search-box">

                <input
                    type="search"
                    class="compuciber-search-input"
                    name="cis_q"
                    value="<?php echo esc_attr( $busqueda_original ); ?>"
                    placeholder="<?php echo esc_attr( $cis_placeholder ); ?>"
                    style="<?php echo esc_attr( $cis_estilo_input ); ?>"
                    autocomplete="off"
                >

                <?php if ( get_option( 'compuciber_busqueda_voz', '0' ) === '1' ) : ?>

                    <button
                        type="button"
                        class="compuciber-voice-button"
                        aria-label="Buscar por voz"
                        title="Buscar por voz"
                    >
                        <svg 
                            class="compuciber-mic-svg"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/>
                            <path d="M19 11h-2a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21H8v2h8v-2h-3v-3.08A7 7 0 0 0 19 11z"/>
                        </svg>
                    </button>

                <?php endif; ?>

                <?php if ( get_option( 'compuciber_busqueda_imagen', '0' ) === '1' ) : ?>

                    <button
                        type="button"
                        class="compuciber-image-button"
                        aria-label="Buscar por imagen"
                        title="Buscar por imagen"
                    >
                        <svg
                            class="compuciber-image-svg"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M9 3l-2 3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-3l-2-3H9zm3 14a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
                        </svg>
                    </button>

                    <input
                        type="file"
                        class="compuciber-image-input"
                        accept="image/*"
                        style="display:none;"
                    >

                <?php endif; ?>

                <button type="submit"
                        style="<?php echo esc_attr( $cis_estilo_boton ); ?>">
                    🔎 Buscar
                </button>

            </div>

            <div
                id="compuciber-ai-status"
                class="compuciber-ai-status"
            ></div>            

            <div
                id="compuciber-suggestions"
                class="compuciber-suggestions"
                style="<?php echo esc_attr( $cis_estilo_sugerencias ); ?>"
            ></div>          

        </form>

        <?php endif; ?>

        <?php

        if ( $mostrar_resultados && ! empty( $busqueda ) ) {

            /*
            * ======================================================
            * PRIMERA BÚSQUEDA
            * ======================================================
            *
            * Siempre intentamos primero con el motor local.
            * Esto evita consumir IA cuando WooCommerce ya puede
            * resolver correctamente la consulta.
            */

            $productos =
                compuciber_buscar_productos_inteligente(
                    $busqueda,
                    $interpretacion
                );

            /*
             * Corrección fuzzy en los resultados.
             *
             * Cuando la consulta original devuelve
             * coincidencias aproximadas, comprobar
             * si existe una interpretación mejor.
             *
             * No modificar imágenes ni SKU exactos.
             */
            if (
                $productos instanceof WP_Query
                && $productos->have_posts()
                && $busqueda_original === $busqueda
                && ! $es_busqueda_imagen
                && get_option( 'compuciber_correccion', '1' ) === '1'
                && function_exists( 'compuciber_sugerencia_fuzzy_catalogo' )
                && function_exists( 'wc_get_product_id_by_sku' )
                && ! wc_get_product_id_by_sku(
                    trim( $busqueda_original )
                )
            ) {

                $propuesta_fuzzy =
                    compuciber_sugerencia_fuzzy_catalogo(
                        $busqueda_original,
                        $perfil_id
                    );

            /*
            * Verificar con IA posibles errores que el fuzzy
            * detectó, aunque ya existan resultados aproximados.
            */
            if (
                get_option( 'compuciber_busqueda_semantica', '0' ) === '1'
                && strlen( trim( $busqueda_original ) ) >= 4
                && strlen( $busqueda_original ) <= 100
                && (
                    $propuesta_fuzzy !== ''
                    || ! compuciber_coincidencia_directa_catalogo(
                        $busqueda_original,
                        $perfil_id
                    )
                )
            ) {
                $proveedor = get_option(
                    'compuciber_proveedor_ia', 'gemini'
                );

                // Evitar reutilizar correcciones de IA de otros perfiles.
                $cis_contexto_cache = $proveedor . '|' . $busqueda_original;

                if ( $perfil_id !== '' ) {

                    $cis_perfiles_cache = get_option(
                        'compuciber_perfiles_busqueda',
                        array()
                    );

                    $cis_datos_cache = is_array( $cis_perfiles_cache )
                        ? ( $cis_perfiles_cache[ $perfil_id ] ?? null )
                        : null;

                    $cis_contexto_cache .= '|' . $perfil_id . '|' . md5(
                        wp_json_encode( $cis_datos_cache )
                    );
                }

                $clave = ( $perfil_id === ''
                    ? 'cis_fuzzy_ia_v3_'
                    : 'cis_fuzzy_ia_perfil_v1_'
                ) . md5( $cis_contexto_cache );

                $evaluacion = get_transient( $clave );

                if ( ! is_array( $evaluacion ) ) {

                    $ip = isset( $_SERVER['REMOTE_ADDR'] )
                        ? (string) $_SERVER['REMOTE_ADDR'] : '';

                    $limite = 'cis_fuzzy_lim_' . md5(
                        wp_salt( 'auth' ) . $ip
                    );

                    $usos = (int) get_transient( $limite );

                    if ( $usos < 12 ) {

                        set_transient(
                            $limite,
                            $usos + 1,
                            15 * MINUTE_IN_SECONDS
                        );

                        $respuesta = compuciber_consultar_ia(
                            $busqueda_original
                        );

                        $evaluacion = array(
                            'respondio' => is_array( $respuesta ),
                            'propuesta' => is_array( $respuesta )
                                ? sanitize_text_field(
                                    $respuesta['busqueda_corregida'] ?? ''
                                )
                                : '',
                        );

                        set_transient(
                            $clave,
                            $evaluacion,
                            HOUR_IN_SECONDS
                        );
                    }
                }

                if (
                    is_array( $evaluacion )
                    && ! empty( $evaluacion['respondio'] )
                ) {

                    $candidata = (string) (
                        $evaluacion['propuesta'] ?? ''
                    );

                    // Si la IA no valida el cambio, no
                    // mostramos una corrección especulativa.
                    if (
                        compuciber_validar_correccion_ortografica(
                            $busqueda_original,
                            $candidata
                        )
                    ) {
                        $propuesta_fuzzy = $candidata;

                    } elseif (
                        $candidata !== ''
                        && compuciber_normalizar_texto( $candidata )
                            === compuciber_normalizar_texto( $busqueda_original )
                    ) {
                        $propuesta_fuzzy = '';
                    }
                }
            }                    

                if (
                    is_string( $propuesta_fuzzy )
                    && $propuesta_fuzzy !== ''
                    && compuciber_normalizar_texto( $propuesta_fuzzy )
                        !== compuciber_normalizar_texto( $busqueda_original )
                ) {

                    $productos_corregidos =
                        compuciber_buscar_productos_inteligente(
                            $propuesta_fuzzy,
                            $interpretacion
                        );

                    if (
                        $productos_corregidos instanceof WP_Query
                        && $productos_corregidos->have_posts()
                    ) {

                        $productos = $productos_corregidos;
                        $busqueda = $propuesta_fuzzy;
                    }
                }
            }

            /*
            * ======================================================
            * FALLBACK DE IA
            * ======================================================
            *
            * Si:
            *
            * 1. La búsqueda local no encontró productos.
            * 2. Todavía no existe una interpretación de IA.
            * 3. La búsqueda semántica está activada.
            *
            * Entonces damos una segunda oportunidad a la consulta
            * mediante el proveedor de IA configurado.
            *
            * Esto permite resolver errores no registrados en las
            * correcciones personalizadas sin consumir IA en todas
            * las búsquedas.
            */

            if (
                $productos instanceof WP_Query
                && ! $productos->have_posts()
                && empty( $interpretacion )
                && get_option(
                    'compuciber_busqueda_semantica',
                    '0'
                ) === '1'
            ) {

                $interpretacion_ia =
                    compuciber_consultar_ia(
                        $busqueda_original
                    );

                if (
                    is_array( $interpretacion_ia )
                    && ! empty( $interpretacion_ia )
                ) {

                    $interpretacion =
                        $interpretacion_ia;


                    /*
                    * Si la IA propone una búsqueda corregida,
                    * utilizamos esa versión en el segundo intento.
                    */

                    if (
                        ! empty(
                            $interpretacion[
                                'busqueda_corregida'
                            ]
                        )
                    ) {

                        $busqueda =
                            sanitize_text_field(
                                $interpretacion[
                                    'busqueda_corregida'
                                ]
                            );
                    }


                    /*
                    * Segunda búsqueda utilizando la
                    * interpretación obtenida mediante IA.
                    */

                    $productos =
                        compuciber_buscar_productos_inteligente(
                            $busqueda,
                            $interpretacion
                        );
                }
            }

            /*
            * BÚSQUEDA VISUAL DE PRODUCTOS SIMILARES
            *
            * Solo se ejecuta cuando una búsqueda por imagen
            * validada no ha encontrado resultados.
            *
            * No modifica las búsquedas escritas ni por SKU.
            */

            $mostrando_similares_imagen = false;

            if (
                ! empty( $es_busqueda_imagen )
                && $productos instanceof WP_Query
                && ! $productos->have_posts()
                && ! empty( $interpretacion['producto'] )
                && is_string( $interpretacion['producto'] )
            ) {

                $tipo_visual = compuciber_normalizar_texto(
                    $interpretacion['producto']
                );

                $palabras_tipo = compuciber_obtener_palabras(
                    $tipo_visual
                );

                /*
                 * Primero evaluar el tipo completo.
                 * Después, sus términos de derecha a izquierda.
                 * Utilizar solo los sinónimos existentes.
                 */
                $tipo_principal = $tipo_visual;

                $tipos_a_evaluar = array_merge(
                    array( $tipo_visual ),
                    array_reverse( $palabras_tipo )
                );

                foreach ( $tipos_a_evaluar as $termino_tipo ) {

                    foreach (
                        compuciber_obtener_sinonimos()
                        as $principal => $variantes
                    ) {

                        $grupo_tipo = array_merge(
                            array( $principal ),
                            is_array( $variantes )
                                ? $variantes
                                : array()
                        );

                        $grupo_tipo = array_map(
                            'compuciber_normalizar_texto',
                            $grupo_tipo
                        );

                        if (
                            in_array(
                                $termino_tipo,
                                $grupo_tipo,
                                true
                            )
                        ) {

                            $tipo_principal =
                                compuciber_normalizar_texto(
                                    $principal
                                );

                            break 2;
                        }
                    }
                }

                if (
                    $tipo_principal !== ''
                    && ! in_array(
                        $tipo_principal,
                        array(
                            'producto',
                            'objeto',
                            'articulo',
                            'aparato',
                            'dispositivo'
                        ),
                        true
                    )
                ) {

                    /*
                    * Buscar equivalencias utilizando los sinónimos
                    * que ya tiene configurados el plugin.
                    */

                    $variantes_tipo = array( $tipo_principal );

                    foreach (
                        compuciber_obtener_sinonimos()
                        as $principal => $variantes
                    ) {

                        $grupo = array_merge(
                            array( $principal ),
                            is_array( $variantes )
                                ? $variantes
                                : array()
                        );

                        $grupo_normalizado = array_map(
                            'compuciber_normalizar_texto',
                            $grupo
                        );

                        if (
                            in_array(
                                $tipo_principal,
                                $grupo_normalizado,
                                true
                            )
                        ) {
                            $variantes_tipo = array_merge(
                                $variantes_tipo,
                                $grupo_normalizado
                            );
                        }
                    }

                    /*
                    * Buscar por el tipo principal,
                    * sin exigir detalles visuales que pueden
                    * no estar registrados en el catálogo.
                    */

                    $candidatos_similares =
                        compuciber_buscar_productos_inteligente(
                            $tipo_principal,
                            array()
                        );

                    $ids_similares = array();

                    $ids_misma_marca = array();
                    $ids_otras_marcas = array();

                    $marca_visual =
                        ! empty( $interpretacion['marca'] )
                        && is_string( $interpretacion['marca'] )
                            ? compuciber_normalizar_texto(
                                $interpretacion['marca']
                            )
                            : '';

                    if ( $candidatos_similares instanceof WP_Query ) {

                        foreach (
                            $candidatos_similares->posts
                            as $post_similar
                        ) {

                            $wc_similar = wc_get_product(
                                $post_similar->ID
                            );

                            if (
                                ! $wc_similar
                                || ! $wc_similar->is_visible()
                            ) {
                                continue;
                            }

                            /*
                            * Confirmar el tipo usando nombre
                            * y categorías reales de WooCommerce.
                            */

                            $identidad = $wc_similar->get_name();

                            $categorias_similar = get_the_terms(
                                $post_similar->ID,
                                'product_cat'
                            );

                            if ( is_array( $categorias_similar ) ) {

                                foreach (
                                    $categorias_similar
                                    as $categoria_similar
                                ) {
                                    $identidad .=
                                        ' ' . $categoria_similar->name;
                                }
                            }

                            foreach (
                                array_unique( $variantes_tipo )
                                as $variante_tipo
                            ) {

                                if (
                                    compuciber_contiene_sinonimo(
                                        $identidad,
                                        $variante_tipo
                                    )
                                ) {

                                    if (
                                        $marca_visual !== ''
                                        && compuciber_contiene_sinonimo(
                                            $identidad,
                                            $marca_visual
                                        )
                                    ) {

                                        $ids_misma_marca[] =
                                            $post_similar->ID;

                                    } else {

                                        $ids_otras_marcas[] =
                                            $post_similar->ID;
                                    }

                                    break;
                                }
                            }
                        }
                    }

                    /*
                     * Mostrar primero productos de la marca
                     * reconocida, si están disponibles.
                     *
                     * Los demás siguen siendo alternativas
                     * del mismo tipo comprobado.
                     */
                    $ids_similares = array_merge(
                        $ids_misma_marca,
                        $ids_otras_marcas
                    );

                    if ( ! empty( $ids_similares ) ) {

                        $productos =
                            compuciber_consulta_productos(
                                $ids_similares
                            );

                        $mostrando_similares_imagen = true;
                    }
                }
            }            

            /*
            * ======================================================
            * REGISTRO DE ESTADÍSTICAS
            * ======================================================
            */

                $tipo_busqueda = 'texto';


                if (
                    isset( $_GET['cis_source'] )
                    && sanitize_key(
                        wp_unslash(
                            $_GET['cis_source']
                        )
                    ) === 'voz'
                ) {
                    $tipo_busqueda = 'voz';
                }

                if ( $es_busqueda_imagen ) {
                    $tipo_busqueda = 'imagen';
                }

                /*
                * Cantidad total de productos encontrados.
                */
                $total_resultados = isset( $productos->found_posts )
                    ? (int) $productos->found_posts
                    : 0;

                compuciber_registrar_busqueda(
                    $busqueda_original,
                    $busqueda,
                    $total_resultados,
                    $tipo_busqueda
                );                

            echo '<div class="compuciber-ai-results">';

            if ( ! empty( $es_busqueda_imagen ) ) {

                echo '<div class="cis-search-notice" role="note">';

                if ( $mostrando_similares_imagen ) {

                    echo '<strong class="cis-search-notice-title">'
                        . 'Productos relacionados con tu imagen'
                        . '</strong>';

                    echo '<p>No encontramos una coincidencia precisa. '
                        . 'Mostramos alternativas del tipo identificado. '
                        . 'Verifica sus características en la ficha del producto.</p>';

                } else {

                    echo '<strong class="cis-search-notice-title">'
                        . 'Búsqueda por imagen'
                        . '</strong>';

                    echo '<p>La identificación visual es orientativa. '
                        . 'Verifica marca, modelo y características '
                        . 'antes de elegir.</p>';
                }

                echo '</div>';
            }    

            if ( $productos->have_posts() ) {

                echo '<p class="cis-search-summary">'
                    . 'Resultados para <strong>'
                    . esc_html( $busqueda_original )
                    . '</strong></p>';

                if (
                    $busqueda_original !== $busqueda
                ) {

                    echo '<div class="cis-search-notice" role="note">';

                    echo '<p>Interpretamos tu búsqueda <strong>'
                        . esc_html( $busqueda_original )
                        . '</strong> como <strong>'
                        . esc_html( $busqueda )
                        . '</strong>. Estos son los resultados de esa interpretación.</p>';

                    echo '</div>';
                }

                echo '<h3>Resultados</h3>';

                $modo_woocommerce =
                    get_option(
                        'compuciber_modo_resultados',
                        'propio'
                    ) === 'woocommerce';

                if ( $modo_woocommerce ) {

                    echo '<div class="woocommerce cis-woo-results" data-search="'
                        . esc_attr( $busqueda_original ) . '">';

                    woocommerce_product_loop_start();

                } else {

                    echo '<div class="cis-results-grid">';
                }

                $variacion_sku = false;

                $id_sku = wc_get_product_id_by_sku(
                    trim( (string) $busqueda_original )
                );

                if ( $id_sku ) {

                    $coincidencia_sku = wc_get_product(
                        $id_sku
                    );

                    if (
                        $coincidencia_sku
                        && $coincidencia_sku->is_type( 'variation' )
                        && $coincidencia_sku->is_visible()
                    ) {

                        $variacion_sku = $coincidencia_sku;
                    }
                }                

                while ( $productos->have_posts() ) {
                    $productos->the_post();

                    $producto_id = get_the_ID();
                    $producto_wc = wc_get_product( $producto_id );

                    if ( ! $producto_wc || ! $producto_wc->is_visible() ) {
                        continue;
                    }

                    $producto_mostrado = $producto_wc;

                    $enlace_producto = get_permalink(
                        $producto_id
                    );

                    if (
                        $variacion_sku
                        && (int) $variacion_sku->get_parent_id()
                            === (int) $producto_id
                    ) {

                        $producto_mostrado = $variacion_sku;

                        $enlace_producto =
                            $variacion_sku->get_permalink();
                    }
                    
                    /*
                    * SELECCIÓN POR COMBINACIÓN DE ATRIBUTOS
                    *
                    * Solo para búsquedas escritas o por voz.
                    * No sustituye la prioridad del SKU exacto.
                    */

                    if (
                        ! $variacion_sku
                        && empty( $es_busqueda_imagen )
                        && $producto_wc->is_type( 'variable' )
                    ) {

                        $variacion_detectada =
                            compuciber_encontrar_variacion_por_atributos(
                                $producto_wc,
                                $busqueda_original
                            );

                        if ( $variacion_detectada ) {

                            $producto_mostrado =
                                $variacion_detectada;

                            $enlace_producto =
                                $variacion_detectada->get_permalink();
                        }
                    }
                    if ( $modo_woocommerce ) {

                        /*
                        * Mostrar el producto mediante la plantilla
                        * WooCommerce del tema.
                        *
                        * Si se detectó una variación concreta,
                        * conservar su precio, imagen y enlace.
                        */

                        $producto_global_anterior = isset(
                            $GLOBALS['product']
                        )
                            ? $GLOBALS['product']
                            : null;

                        $GLOBALS['product'] = $producto_mostrado;

                        /*
                        * Respetar el enlace de la variación
                        * cuando la plantilla utiliza el enlace
                        * estándar de WooCommerce.
                        */

                        $filtro_enlace = static function (
                            $url
                        ) use ( $enlace_producto ) {

                            return $enlace_producto;
                        };

                        add_filter(
                            'woocommerce_loop_product_link',
                            $filtro_enlace,
                            999
                        );

                        wc_get_template_part(
                            'content',
                            'product'
                        );

                        remove_filter(
                            'woocommerce_loop_product_link',
                            $filtro_enlace,
                            999
                        );

                        $GLOBALS['product'] =
                            $producto_global_anterior;

                        continue;
                    }
                    
                    echo '<a href="' . esc_url( $enlace_producto ) . '"'
                        . ' class="compuciber-product-result cis-result-card"'
                        . ' data-product-id="' . esc_attr( $producto_id ) . '"'
                        . ' data-search="' . esc_attr( $busqueda_original ) . '">';

                    echo '<span class="cis-result-image">'
                        . wp_kses_post( $producto_mostrado->get_image( 'woocommerce_thumbnail' ) )
                        . '</span>';

                    echo '<span class="cis-result-title">'
                        . esc_html( $producto_mostrado->get_name() )
                        . '</span>';

                    echo '<span class="cis-result-price">'
                        . wp_kses_post( $producto_mostrado->get_price_html() )
                        . '</span>';

                    echo '<span class="cis-result-stock">'
                        . esc_html( $producto_mostrado->is_in_stock() ? 'Disponible' : 'Agotado' )
                        . '</span>';

                    echo '<span class="cis-result-more">Ver producto →</span>';

                    echo '</a>';
                }

                if ( $modo_woocommerce ) {

                    woocommerce_product_loop_end();
                }

                echo '</div>';

                /*
                * Navegación entre páginas de resultados.
                */
                if ( (int) $productos->max_num_pages > 1 ) {

                    $cis_enlaces = paginate_links(
                        array(
                            'base' => add_query_arg(
                                'cis_page',
                                '%#%',
                                remove_query_arg( 'cis_page' )
                            ),

                            'format' => '',

                            'current' => max(
                                1,
                                (int) $productos->get( 'paged' )
                            ),

                            'total' => (int) $productos->max_num_pages,

                            'mid_size' => 1,

                            'prev_text' => '« Anterior',

                            'next_text' => 'Siguiente »',
                        )
                    );

                    if ( $cis_enlaces ) {

                        echo '<nav class="cis-results-pagination"
                            aria-label="Páginas de resultados">';

                        echo wp_kses_post( $cis_enlaces );

                        echo '</nav>';
                    }
                }

                } else {

                echo '<div class="cis-search-notice" role="status">';

                echo '<strong class="cis-search-notice-title">'
                    . 'No encontramos productos'
                    . '</strong>';

                echo '<p>No hay coincidencias para <strong>'
                    . esc_html( $busqueda_original )
                    . '</strong>. Prueba con otra palabra, '
                    . 'marca o categoría.</p>';

                echo '</div>';

            }

            echo '</div>';

            wp_reset_postdata();

        }

        ?>

    </div>

    <?php

    return ob_get_clean();
}


add_shortcode(
    'compuciber_ai_search',
    'compuciber_ai_search_shortcode'
);

add_shortcode(
    'ai_product_search',
    'compuciber_ai_search_shortcode'
);

/**
 * Devuelve el buscador en modo barra para integraciones HTML.
 * Esta ruta es pública y solo genera marcado, sin guardar datos.
 */
function compuciber_renderizar_buscador_html_ajax() {

    $perfil_id = '';

    if ( isset( $_POST['perfil'] ) && is_string( $_POST['perfil'] ) ) {
        $perfil_id = sanitize_key( wp_unslash( $_POST['perfil'] ) );
    }

    if ( $perfil_id !== '' ) {
        $perfiles = get_option( 'compuciber_perfiles_busqueda', array() );

        if (
            ! is_array( $perfiles )
            || ! isset( $perfiles[ $perfil_id ] )
            || empty( $perfiles[ $perfil_id ]['activo'] )
        ) {
            wp_send_json_error(
                array( 'mensaje' => 'El perfil no existe o está inactivo.' ),
                400
            );
        }
    }

    $html = compuciber_ai_search_shortcode(
        array(
            'solo_barra' => 'si',
            'perfil'     => $perfil_id,
        )
    );

    wp_send_json_success( array( 'html' => $html ) );
}

add_action(
    'wp_ajax_compuciber_renderizar_buscador_html',
    'compuciber_renderizar_buscador_html_ajax'
);

add_action(
    'wp_ajax_nopriv_compuciber_renderizar_buscador_html',
    'compuciber_renderizar_buscador_html_ajax'
);

/**
 * Muestra el buscador inteligente mediante PHP.
 *
 * Permite a desarrolladores insertar el buscador
 * directamente desde una plantilla de WordPress.
 */
function compuciber_ai_search() {

    echo compuciber_ai_search_shortcode();
}

/**
 * Registra el bloque Gutenberg del buscador.
 */
function compuciber_ai_search_registrar_bloque() {

    wp_register_script(
        'compuciber-ai-search-block',
        plugin_dir_url( __FILE__ )
        . 'blocks/buscadores/index.js',
        array(
            'wp-blocks',
            'wp-element',
        ),
        filemtime(
            plugin_dir_path( __FILE__ ) . 'blocks/buscadores/index.js'
        ),        
        true
    );

    register_block_type(
        'compuciber/ai-search',
        array(
            'editor_script'   => 'compuciber-ai-search-block',
            'render_callback' => 'compuciber_ai_search_render_bloque',

            /*
            * Perfil opcional.
            * Vacío = buscador predeterminado.
            */
            'attributes' => array(
                'perfil' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'solo_barra' => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
                /*
                * Modo Solo resultados para Gutenberg.
                * false = conservar el comportamiento anterior.
                * true  = mostrar únicamente los resultados.
                */
                'solo_resultados' => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
            ),
        )
    );
}

add_action(
    'init',
    'compuciber_ai_search_registrar_bloque'
);

/**
 * Enviar los perfiles activos al editor Gutenberg.
 * No se ejecuta en las páginas públicas de la tienda.
 */
function compuciber_ai_search_perfiles_gutenberg() {

    $opciones = array(
        array(
            'label' => 'Predeterminado (todos los productos)',
            'value' => '',
        ),
    );

    $perfiles = get_option(
        'compuciber_perfiles_busqueda',
        array()
    );

    if ( is_array( $perfiles ) ) {

        foreach ( $perfiles as $id => $datos ) {

            if (
                ! is_array( $datos ) ||
                empty( $datos['activo'] )
            ) {
                continue;
            }

            $id = sanitize_key( (string) $id );

            if ( $id === '' ) {
                continue;
            }

            $nombre = sanitize_text_field(
                (string) ( $datos['nombre'] ?? $id )
            );

            $opciones[] = array(
                'label' => $nombre !== '' ? $nombre : $id,
                'value' => $id,
            );
        }
    }

    wp_localize_script(
        'compuciber-ai-search-block',
        'CompuciberGutenbergPerfiles',
        array(
            'opciones' => $opciones,
        )
    );
}

add_action(
    'enqueue_block_editor_assets',
    'compuciber_ai_search_perfiles_gutenberg'
);


/**
 * Renderizar el buscador desde Gutenberg.
 *
 * Admite:
 * - Buscador completo.
 * - Solo barra.
 * - Solo resultados.
 *
 * También conserva los perfiles personalizados.
 */
function compuciber_ai_search_render_bloque( $atributos = array() ) {

    $perfil_id = '';

    if (
        is_array( $atributos )
        && isset( $atributos['perfil'] )
        && is_string( $atributos['perfil'] )
    ) {
        $perfil_id = sanitize_key( $atributos['perfil'] );
    }

    /*
     * Leer el modo de visualización.
     */
    $solo_resultados = (
        is_array( $atributos )
        && ! empty( $atributos['solo_resultados'] )
    );

    $solo_barra = (
        ! $solo_resultados
        && is_array( $atributos )
        && ! empty( $atributos['solo_barra'] )
    );

    /*
     * Sin perfil y en modo completo:
     * conservar exactamente la llamada original.
     */
    if (
        $perfil_id === ''
        && ! $solo_resultados
        && ! $solo_barra
    ) {
        return compuciber_ai_search_shortcode();
    }

    /*
     * Validar únicamente cuando se utiliza
     * un perfil personalizado.
     */
    if ( $perfil_id !== '' ) {

        $perfiles = get_option(
            'compuciber_perfiles_busqueda',
            array()
        );

        if (
            ! is_array( $perfiles )
            || ! isset( $perfiles[ $perfil_id ] )
            || ! is_array( $perfiles[ $perfil_id ] )
            || empty( $perfiles[ $perfil_id ]['activo'] )
        ) {
            return current_user_can( 'edit_posts' )
                ? '<p>El perfil seleccionado no existe o está inactivo.</p>'
                : '';
        }
    }

    /*
     * Preparar los parámetros del buscador.
     */
    $opciones = array();

    if ( $perfil_id !== '' ) {
        $opciones['perfil'] = $perfil_id;
    }

    /*
     * Aplicar únicamente un modo de visualización.
     */
    if ( $solo_resultados ) {

        $opciones['solo_resultados'] = 'si';

    } elseif ( $solo_barra ) {

        $opciones['solo_barra'] = 'si';

    }

    /*
     * Reutilizar el motor original.
     */
    return compuciber_ai_search_shortcode( $opciones );
}

/**
 * Modo completo para los bloques HTML nativos de WordPress.
 * Las barras HTML existentes continúan usando su carga AJAX.
 */
function compuciber_renderizar_html_completo_en_bloques( $contenido ) {

    if (
        strpos( $contenido, 'compuciber-ai-search-embed' ) === false
        || strpos( $contenido, 'data-modo' ) === false
        || ! class_exists( 'WP_HTML_Tag_Processor' )
    ) {
        return $contenido;
    }

    return preg_replace_callback(
        '~<div\b[^>]*>\s*</div>~i',
        function ( $coincidencia ) {

            $etiqueta = new WP_HTML_Tag_Processor( $coincidencia[0] );

            if ( ! $etiqueta->next_tag( 'DIV' ) ) {
                return $coincidencia[0];
            }

            $clases = preg_split(
                '/\s+/',
                trim( (string) $etiqueta->get_attribute( 'class' ) )
            );

            $modo = strtolower(
                trim( (string) $etiqueta->get_attribute( 'data-modo' ) )
            );

            if (
                ! in_array(
                    'compuciber-ai-search-embed',
                    $clases,
                    true
                )
                || $modo !== 'completo'
            ) {
                return $coincidencia[0];
            }

            $perfil = sanitize_key(
                (string) $etiqueta->get_attribute( 'data-perfil' )
            );

            if ( $perfil !== '' ) {

                $perfiles = get_option(
                    'compuciber_perfiles_busqueda',
                    array()
                );

                if (
                    ! is_array( $perfiles )
                    || ! isset( $perfiles[ $perfil ] )
                    || ! is_array( $perfiles[ $perfil ] )
                    || empty( $perfiles[ $perfil ]['activo'] )
                ) {
                    return current_user_can( 'edit_posts' )
                        ? '<p>El perfil seleccionado no existe o está inactivo.</p>'
                        : '';
                }
            }

            return compuciber_ai_search_shortcode(
                array(
                    'perfil'     => $perfil,
                    'solo_barra' => 'no',
                )
            );
        },
        $contenido
    );
}

add_filter(
    'render_block_core/html',
    'compuciber_renderizar_html_completo_en_bloques'
);