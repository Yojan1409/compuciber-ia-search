<?php
/**
 * Plugin Name: Compuciber AI Search
 * Description: Buscador inteligente de productos para Compuciber.
 * Version: 2.0.0
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
        '2.0.0'
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

    wp_enqueue_style(
        'compuciber-buscador',
        plugin_dir_url( __FILE__ ) . 'assets/css/buscador.css',
        array(),
        COMPUCIBER_AI_SEARCH_VERSION
    );

    wp_enqueue_script(
        'compuciber-buscador',
        plugin_dir_url( __FILE__ ) . 'assets/js/buscador.js',
        array(),
        COMPUCIBER_AI_SEARCH_VERSION,
        true
    );

    wp_localize_script(
        'compuciber-buscador',
        'CompuciberSearch',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),

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
        'visual-general-v2|'
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

        error_log(
            'COMPuciber Imagen: resultado obtenido desde caché.'
        );

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

            error_log(
                'COMPuciber Imagen: resultado guardado en caché.'
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
 * Shortcode:
 * [compuciber_ai_search]
 */
function compuciber_ai_search_shortcode( $atts = array() )
{
    $atts = shortcode_atts(
        array( 'solo_barra' => 'no' ),
        is_array( $atts ) ? $atts : array(),
        'ai_product_search'
    );

    $solo_barra = in_array(
        strtolower( (string) $atts['solo_barra'] ),
        array( 'si', '1', 'true', 'yes' ),
        true
    );

    $pagina_resultados_id = absint(
        get_option( 'compuciber_pagina_resultados', 0 )
    );

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


    ob_start();
    ?>

    <div class="compuciber-ai-search">

        <form method="get" action="<?php echo esc_url( $url_resultados ); ?>">

            <div class="compuciber-search-box">

                <input
                    type="search"
                    class="compuciber-search-input"
                    name="cis_q"
                    value="<?php echo esc_attr( $busqueda_original ); ?>"
                    placeholder="¿Qué producto estás buscando?"
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

                <button type="submit">
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
            ></div>           

        </form>

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

                $palabras_tipo = compuciber_obtener_palabras(
                    $interpretacion['producto']
                );

                $tipo_principal = ! empty( $palabras_tipo )
                    ? reset( $palabras_tipo )
                    : '';

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

                                    $ids_similares[] =
                                        $post_similar->ID;

                                    break;
                                }
                            }
                        }
                    }

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

                echo '<p>La identificación por imagen es orientativa. '
                    . 'Verifica marca, modelo y características '
                    . 'en la ficha del producto.</p>';
            }

            if ( $mostrando_similares_imagen ) {

                echo '<p>No encontramos coincidencia con todos '
                    . 'los detalles de la fotografía. '
                    . 'Estas son opciones del mismo tipo.</p>';
            }            

            if ( $productos->have_posts() ) {

                echo '<p>Buscando: <strong>'
                    . esc_html( $busqueda_original )
                    . '</strong></p>';

                if (
                    $busqueda_original !== $busqueda
                ) {

                    echo '<p>Interpretamos tu búsqueda como: <strong>'
                        . esc_html( $busqueda )
                        . '</strong></p>';

                }

                echo '<h3>Resultados</h3>';

                echo '<div class="cis-results-grid">';

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

                        $depurar_lenovo = defined( 'WP_DEBUG' )
                            && WP_DEBUG
                            && stripos(
                                $producto_wc->get_name(),
                                'Lenovo IdeaPad Variación'
                            ) !== false;

                        if ( $depurar_lenovo ) {

                            error_log(
                                'CIS VAR OPCIONES: '
                                . wp_json_encode(
                                    $producto_wc->get_variation_attributes()
                                )
                            );

                            foreach (
                                $producto_wc->get_children()
                                as $id_hijo
                            ) {

                                $hija = wc_get_product( $id_hijo );

                                if (
                                    ! $hija
                                    || ! $hija->is_type( 'variation' )
                                ) {
                                    continue;
                                }

                                error_log(
                                    'CIS VAR HIJA ID=' . $id_hijo
                                    . ' | ATRIBUTOS='
                                    . wp_json_encode( $hija->get_attributes() )
                                    . ' | VISIBLE='
                                    . ( $hija->is_visible() ? 'SI' : 'NO' )
                                    . ' | VAR_VISIBLE='
                                    . ( $hija->variation_is_visible() ? 'SI' : 'NO' )
                                );
                            }
                        }

                        $variacion_detectada =
                            compuciber_encontrar_variacion_por_atributos(
                                $producto_wc,
                                $busqueda_original
                            );

                        if ( $depurar_lenovo ) {

                            error_log(
                                'CIS VAR RESULTADO: '
                                . (
                                    $variacion_detectada
                                        ? $variacion_detectada->get_id()
                                        : 'NO ENCONTRADA'
                                )
                            );
                        }                            

                        if ( $variacion_detectada ) {

                            $producto_mostrado =
                                $variacion_detectada;

                            $enlace_producto =
                                $variacion_detectada->get_permalink();

                            if (
                                defined( 'WP_DEBUG' )
                                && WP_DEBUG
                            ) {
                                error_log(
                                    'CIS VARIACION POR ATRIBUTOS: '
                                    . $variacion_detectada->get_id()
                                    . ' | CONSULTA: '
                                    . $busqueda_original
                                );
                            }
                        }
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

                echo '</div>';

            } else {

                echo '<p>No encontramos productos para "'
                    . esc_html(
                        $busqueda_original
                    )
                    . '".</p>';

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
        '2.0.0',
        true
    );

    register_block_type(
        'compuciber/ai-search',
        array(
            'editor_script'   => 'compuciber-ai-search-block',
            'render_callback' => 'compuciber_ai_search_render_bloque',
        )
    );
}

add_action(
    'init',
    'compuciber_ai_search_registrar_bloque'
);


/**
 * Renderiza el bloque Gutenberg.
 *
 * Utiliza exactamente el mismo buscador
 * que el shortcode.
 */
function compuciber_ai_search_render_bloque() {

    return compuciber_ai_search_shortcode();
}