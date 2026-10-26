<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * IA de respaldo para las sugerencias.
 *
 * Solo interviene cuando la búsqueda local no
 * encontró una corrección útil.
 *
 * La IA no inventa productos: toda propuesta
 * se valida contra el catálogo WooCommerce.
 */
function compuciber_ajax_sugerencia_ia() {

    /*
     * SEGURIDAD DE LA SOLICITUD
     */
    if (
        ! check_ajax_referer(
            'compuciber_sugerencia_ia',
            'nonce',
            false
        )
    ) {
        wp_send_json_error(
            array(
                'message' => 'Solicitud no autorizada.',
            ),
            403
        );
    }

    /*
     * RESPETAR LA CONFIGURACIÓN DEL PLUGIN
     */
    $activa =
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
        ) === '1';

    if ( ! $activa ) {
        wp_send_json_success(
            array( 'suggested_term' => '' )
        );
    }

    /*
     * RECIBIR Y NORMALIZAR LA CONSULTA
     */
    $consulta =
        isset( $_POST['term'] )
        && is_string( $_POST['term'] )
            ? compuciber_normalizar_texto(
                wp_unslash( $_POST['term'] )
            )
            : '';

    if (
        strlen( $consulta ) < 5
        || strlen( $consulta ) > 80
    ) {
        wp_send_json_success(
            array( 'suggested_term' => '' )
        );
    }

    $palabras = compuciber_obtener_palabras(
        $consulta
    );

    if (
        ! $palabras
        || count( $palabras ) > 6
    ) {
        wp_send_json_success(
            array( 'suggested_term' => '' )
        );
    }

    global $wpdb;

    $tabla =
        $wpdb->prefix
        . 'compuciber_product_index';

    /*
    * Perfil utilizado por el buscador.
    */
    $cis_perfil_ia = '';

    if (
        isset( $_POST['cis_perfil'] )
        && is_string( $_POST['cis_perfil'] )
    ) {
        $cis_perfil_ia = sanitize_key(
            wp_unslash( $_POST['cis_perfil'] )
        );
    }

    $cis_filtro_ia_sql = '';

    if ( $cis_perfil_ia !== '' ) {

        if (
            ! function_exists(
                'compuciber_perfil_condicion_sql'
            )
        ) {
            wp_send_json_success(
                array( 'suggested_term' => '' )
            );
        }

        $cis_filtro_ia_sql =
            compuciber_perfil_condicion_sql(
                $cis_perfil_ia
            );
    }

    /*
    * Identificar cambios en las reglas del perfil
    * para no reutilizar correcciones antiguas.
    */
    $cis_perfiles_cache = get_option(
        'compuciber_perfiles_busqueda',
        array()
    );

    $cis_firma_perfil = $cis_perfil_ia !== ''
        ? md5(
            wp_json_encode(
                is_array( $cis_perfiles_cache )
                    ? ( $cis_perfiles_cache[ $cis_perfil_ia ] ?? null )
                    : null
            )
        )
        : '';        

    /*
     * PRIMERO: COMPROBAR EL CATÁLOGO LOCAL.
     *
     * Si ya existen coincidencias directas,
     * no hay necesidad de llamar a la IA.
     */
    $filtros_locales = array_fill(
        0,
        count( $palabras ),
        'i.texto_busqueda LIKE %s'
    );

    $valores_locales = array_map(
        static function ( $palabra ) use ( $wpdb ) {
            return '%'
                . $wpdb->esc_like( $palabra )
                . '%';
        },
        $palabras
    );

    $sql_local =
        "SELECT i.id
         FROM {$tabla} i
         INNER JOIN {$wpdb->posts} p
             ON p.ID = i.id
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           {$cis_filtro_ia_sql}
           AND "
        . implode( ' AND ', $filtros_locales )
        . ' LIMIT 1';

    if (
        $wpdb->get_var(
            $wpdb->prepare(
                $sql_local,
                $valores_locales
            )
        )
    ) {
        wp_send_json_success(
            array( 'suggested_term' => '' )
        );
    }

    /*
     * IDENTIFICAR PROVEEDOR Y MODELO.
     *
     * Se utiliza la configuración de la tienda.
     */
    $proveedor = sanitize_key(
        (string) get_option(
            'compuciber_proveedor_ia',
            'gemini'
        )
    );

    $opcion_modelo =
        $proveedor === 'openai'
            ? 'compuciber_openai_modelo_texto'
            : 'compuciber_gemini_modelo_texto';

    $modelo = (string) get_option(
        $opcion_modelo,
        ''
    );

    /*
     * CACHÉ PARA EVITAR CONSULTAS REPETIDAS
     */
    $clave_cache =
        'cis_sug_ia_v2_'
        . md5(
            $proveedor
            . '|'
            . $modelo
            . '|'
            . $consulta
            . '|'
            . $cis_perfil_ia
            . '|'
            . $cis_firma_perfil
        );

    $cache = get_transient(
        $clave_cache
    );

    if (
        is_array( $cache )
        && isset( $cache['suggested_term'] )
    ) {
        wp_send_json_success(
            $cache
        );
    }

    /*
     * LIMITAR EL CONSUMO DE API.
     *
     * Hasta 12 consultas no almacenadas
     * por visitante en 15 minutos.
     */
    $direccion =
        isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_SERVER['REMOTE_ADDR']
                )
            )
            : '';

    $agente =
        isset( $_SERVER['HTTP_USER_AGENT'] )
            ? substr(
                sanitize_text_field(
                    wp_unslash(
                        $_SERVER['HTTP_USER_AGENT']
                    )
                ),
                0,
                100
            )
            : '';

    $clave_limite =
        'cis_sug_lim_'
        . md5(
            wp_salt( 'auth' )
            . '|'
            . $direccion
            . '|'
            . $agente
        );

    $intentos = (int) get_transient(
        $clave_limite
    );

    if ( $intentos >= 12 ) {
        wp_send_json_success(
            array( 'suggested_term' => '' )
        );
    }

    set_transient(
        $clave_limite,
        $intentos + 1,
        15 * MINUTE_IN_SECONDS
    );

    /*
     * CONSULTAR GEMINI U OPENAI.
     *
     * Se reutiliza el proveedor existente.
     */
    $respuesta = compuciber_consultar_ia(
        $consulta
    );

    $propuesta =
        is_array( $respuesta )
        && isset(
            $respuesta['busqueda_corregida']
        )
        && is_string(
            $respuesta['busqueda_corregida']
        )
            ? compuciber_normalizar_texto(
                $respuesta['busqueda_corregida']
            )
            : '';

    $resultado = array(
        'suggested_term' => '',
    );

    $palabras_propuesta =
        compuciber_obtener_palabras(
            $propuesta
        );

    /*
     * VALIDAR LA CORRECCIÓN DE LA IA.
     */
    if (
        $propuesta !== ''
        && $propuesta !== $consulta
        && strlen( $propuesta ) <= 100
        && $palabras_propuesta
        && count( $palabras_propuesta ) <= 6
    ) {

        /*
         * Proteger SKU y códigos numéricos.
         */
        $originales = preg_split(
            '/\s+/',
            $consulta,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $corregidas = preg_split(
            '/\s+/',
            $propuesta,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $codigos_intactos = true;

        foreach ( $originales as $token ) {

            if (
                preg_match( '/\d/', $token )
                && ! in_array(
                    $token,
                    $corregidas,
                    true
                )
            ) {
                $codigos_intactos = false;
                break;
            }
        }

        if ( $codigos_intactos ) {

            /*
             * Comprobar si existen productos
             * que coincidan con la propuesta.
             */
            $filtros = array_fill(
                0,
                count( $palabras_propuesta ),
                'i.texto_busqueda LIKE %s'
            );

            $valores = array_map(
                static function ( $palabra ) use ( $wpdb ) {
                    return '%'
                        . $wpdb->esc_like( $palabra )
                        . '%';
                },
                $palabras_propuesta
            );

            $sql =
                "SELECT i.id
                 FROM {$tabla} i
                 INNER JOIN {$wpdb->posts} p
                     ON p.ID = i.id
                 WHERE p.post_type = 'product'
                   AND p.post_status = 'publish'
                   {$cis_filtro_ia_sql}
                   AND "
                . implode( ' AND ', $filtros )
                . ' LIMIT 20';

            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    $sql,
                    $valores
                )
            );

            foreach ( (array) $ids as $id ) {

                $producto = wc_get_product(
                    (int) $id
                );

                if (
                    $producto
                    && $producto->is_visible()
                ) {
                    $resultado['suggested_term'] =
                        $propuesta;

                    break;
                }
            }
        }
    }

    /*
     * ALMACENAR LA RESPUESTA TEMPORALMENTE.
     */
    set_transient(
        $clave_cache,
        $resultado,
        $resultado['suggested_term'] !== ''
            ? 12 * HOUR_IN_SECONDS
            : 20 * MINUTE_IN_SECONDS
    );

    wp_send_json_success(
        $resultado
    );
}

/*
 * AJAX PARA USUARIOS CON Y SIN SESIÓN.
 */
add_action(
    'wp_ajax_compuciber_sugerencia_ia',
    'compuciber_ajax_sugerencia_ia'
);

add_action(
    'wp_ajax_nopriv_compuciber_sugerencia_ia',
    'compuciber_ajax_sugerencia_ia'
);