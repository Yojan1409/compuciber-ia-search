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
        '2..0'
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

require_once plugin_dir_path( __FILE__ ) . 'includes/estadisticas.php';

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

    /*
    * Optimiza la imagen antes de enviarla a la IA.
    */

    $imagen_optimizada =
        compuciber_optimizar_imagen_ia(
            $archivo['tmp_name']
        );

    $hash_imagen =
        compuciber_generar_hash_imagen(
            $imagen_optimizada
        );


    $cache_imagen =
        compuciber_obtener_cache_imagen(
            $hash_imagen
        );    


    if (
        ! $imagen_optimizada
    ) {

        wp_send_json_error(
            'No se pudo optimizar la imagen.'
        );

    }


    /*
    * Lee la imagen optimizada.
    */

    $imagen = file_get_contents(
        $imagen_optimizada
    );


    if (
        $imagen === false
    ) {

        wp_send_json_error(
            'No se pudo leer la imagen optimizada.'
        );

    }


    $imagen_base64 = base64_encode(
        $imagen
    );


    $mime_type = 'image/jpeg';


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


    wp_send_json_success(
        array(
            'busqueda' => $busqueda,
            'interpretacion' => $resultado,
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
 * Shortcode:
 * [compuciber_ai_search]
 */
function compuciber_ai_search_shortcode()
{

    $busqueda_original = isset( $_GET['cis_q'] )
        ? sanitize_text_field(
            wp_unslash(
                $_GET['cis_q']
            )
        )
        : '';

    $interpretacion = array();

    $busqueda = compuciber_corregir_busqueda(
        $busqueda_original
    );


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

    if ( ! empty( $_GET['cis_ai'] ) ) {

        $interpretacion_json =
            wp_unslash(
                $_GET['cis_ai']
            );

        $interpretacion_decodificada =
            json_decode(
                $interpretacion_json,
                true
            );

        if (
            is_array(
                $interpretacion_decodificada
            )
        ) {

            $interpretacion =
                $interpretacion_decodificada;

        }

    }


    if (
        ! empty( $busqueda_original )
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

        <form method="get">

            <div class="compuciber-search-box">

                <input
                    type="search"
                    id="compuciber-search-input"
                    name="cis_q"
                    value="<?php echo esc_attr( $busqueda_original ); ?>"
                    placeholder="¿Qué producto estás buscando?"
                    autocomplete="off"
                >

                <?php if ( get_option( 'compuciber_busqueda_voz', '0' ) === '1' ) : ?>

                    <button
                        type="button"
                        id="compuciber-voice-button"
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
                        id="compuciber-image-button"
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
                        id="compuciber-image-input"
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

            <?php

            if ( ! empty( $_GET['cis_ai'] ) ) {

                echo '<input type="hidden" name="cis_ai" value="'
                    . esc_attr(
                        wp_unslash(
                            $_GET['cis_ai']
                        )
                    )
                    . '">';

            }

            ?>

        </form>

        <?php

        if ( ! empty( $busqueda ) ) {

            $productos =
                compuciber_buscar_productos_inteligente(
                    $busqueda,
                    $interpretacion
                );
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

                if ( ! empty( $_GET['cis_ai'] ) ) {
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

                echo '<ul>';

                while ( $productos->have_posts() ) {

                    $productos->the_post();

                    echo '<li>';

                    echo '<a href="'
                        . esc_url( get_permalink() )
                        . '" class="compuciber-product-result"'
                        . ' data-product-id="'
                        . esc_attr( get_the_ID() )
                        . '"'
                        . ' data-search="'
                        . esc_attr( $busqueda_original )
                        . '">';

                    echo esc_html(
                        get_the_title()
                    );

                    echo '</a>';

                    echo '</li>';

                }

                echo '</ul>';

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