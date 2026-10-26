<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * ==========================================================
 * MENÚ DEL PLUGIN
 * ==========================================================
 */

function compuciber_ai_search_menu() {

    add_menu_page(
        'AI Product Search',
        'AI Product Search',
        'manage_options',
        'compuciber-ai-search',
        'compuciber_ai_search_dashboard',
        'dashicons-search',
        56
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'compuciber-ai-search',
        'compuciber_ai_search_dashboard'
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Configuración',
        'Configuración',
        'manage_options',
        'compuciber-ai-search-configuracion',
        'compuciber_ai_search_configuracion'
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Sinónimos',
        'Sinónimos',
        'manage_options',
        'compuciber-ai-search-sinonimos',
        'compuciber_pagina_sinonimos'
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Correcciones',
        'Correcciones',
        'manage_options',
        'compuciber-ai-search-correcciones',
        'compuciber_pagina_correcciones'
    );

}

add_action(
    'admin_menu',
    'compuciber_ai_search_menu'
);


/*
 * ==========================================================
 * REGISTRO DE OPCIONES
 * ==========================================================
 */

function compuciber_ai_search_registrar_opciones() {
    /*
     * Registrar los estilos personalizados
     * como un grupo independiente.
     */
    register_setting(
        'compuciber_estilos_perfiles_settings_group',
        'compuciber_estilos_perfiles',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'compuciber_sanitizar_estilos_perfiles',
            'default'           => array(),
        )
    );    

    /*
     * Opciones generales.
     */
    $opciones = array(

        'compuciber_proveedor_ia',

        'compuciber_gemini_api_key',

        'compuciber_openai_api_key',

        /*
         * Modelos independientes para Gemini.
         */
        'compuciber_gemini_modelo_texto',

        'compuciber_gemini_modelo_embeddings',

        'compuciber_gemini_modelo_multimodal',

        /*
         * Modelos independientes para OpenAI.
         */
        'compuciber_openai_modelo_texto',

        'compuciber_openai_modelo_embeddings',

        'compuciber_openai_modelo_multimodal',

        /*
         * Funciones del buscador.
         */
        'compuciber_autocompletado',

        'compuciber_correccion',

        'compuciber_busqueda_semantica',

        'compuciber_busqueda_voz',

        'compuciber_busqueda_imagen',

        'compuciber_estadisticas',

        'compuciber_aprendizaje_ranking',

        /*
         * Licenciamiento.
         */
        'compuciber_clave_licencia',
    );

    register_setting(
        'compuciber_ai_search_settings_group',
        'compuciber_pagina_resultados',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        )
    );

    /*
     * MODO DE PRESENTACIÓN DE RESULTADOS
     *
     * propio: tarjetas actuales del plugin.
     * woocommerce: presentación de la tienda.
     */

    register_setting(
        'compuciber_ai_search_settings_group',
        'compuciber_modo_resultados',
        array(
            'type'    => 'string',
            'default' => 'propio',

            'sanitize_callback' => static function ( $valor ) {

                return in_array(
                    $valor,
                    array(
                        'propio',
                        'woocommerce',
                    ),
                    true
                ) ? $valor : 'propio';
            },
        )
    );    
    
    foreach ( $opciones as $opcion ) {

        register_setting(
            'compuciber_ai_search_settings_group',
            $opcion,
            array(
                'sanitize_callback' =>
                    'sanitize_text_field',
            )
        );
    }
}

add_action(
    'admin_init',
    'compuciber_ai_search_registrar_opciones'
);

/**
 * Crear la página de resultados cuando
 * el administrador lo solicite.
 */
function compuciber_crear_pagina_resultados_admin() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para esta acción.' );
    }

    check_admin_referer(
        'compuciber_crear_pagina_resultados'
    );

    $id = absint(
        get_option( 'compuciber_pagina_resultados', 0 )
    );

    $valida = $id > 0
        && get_post_type( $id ) === 'page'
        && get_post_status( $id ) === 'publish';

    if ( ! $valida ) {

        $existente = get_page_by_path(
            'resultados-buscador-ia',
            OBJECT,
            'page'
        );

        if (
            $existente instanceof WP_Post
            && $existente->post_status === 'publish'
            && has_shortcode(
                $existente->post_content,
                'compuciber_ai_search'
            )
        ) {

            $id = (int) $existente->ID;

        } else {

            $id = wp_insert_post(
                array(
                    'post_title'   => 'Resultados de búsqueda',
                    'post_name'    => 'resultados-buscador-ia',
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_content' => '[compuciber_ai_search]',
                ),
                true
            );
        }

        if ( is_wp_error( $id ) || ! $id ) {

            wp_safe_redirect(
                add_query_arg(
                    'cis_resultado',
                    'error',
                    admin_url(
                        'admin.php?page=compuciber-ai-search-configuracion'
                    )
                )
            );

            exit;
        }

        update_option(
            'compuciber_pagina_resultados',
            (int) $id
        );
    }

    wp_safe_redirect(
        add_query_arg(
            'cis_resultado',
            'creada',
            admin_url(
                'admin.php?page=compuciber-ai-search-configuracion'
            )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_crear_pagina_resultados',
    'compuciber_crear_pagina_resultados_admin'
);

/**
 * PERFILES DE BUSCADOR
 * Guarda los perfiles y sus filtros.
 */
function compuciber_sanitizar_perfiles_busqueda( $valor ) {

    if ( ! is_array( $valor ) ) {
        return array();
    }

    $perfiles_limpios = array();

    foreach ( $valor as $id => $datos ) {

        if ( ! is_array( $datos ) ) {
            continue;
        }

        $id = sanitize_key( (string) $id );

        $nombre = sanitize_text_field(
            $datos['nombre'] ?? ''
        );

        if ( $id === '' || $nombre === '' ) {
            continue;
        }

        $incluir = array_map(
            'absint',
            (array) ( $datos['incluir'] ?? array() )
        );

        $excluir = array_map(
            'absint',
            (array) ( $datos['excluir'] ?? array() )
        );

        $perfiles_limpios[ $id ] = array(
            'nombre' => $nombre,

            'activo' => (string) (
                $datos['activo'] ?? '0'
            ) === '1' ? 1 : 0,

            'incluir' => array_values(
                array_unique(
                    array_filter( $incluir )
                )
            ),

            'excluir' => array_values(
                array_unique(
                    array_filter( $excluir )
                )
            ),
        );
    }

    return $perfiles_limpios;
}

/**
 * Registrar los perfiles independientemente
 * de la configuración general del plugin.
 */
function compuciber_registrar_opcion_perfiles() {

    register_setting(
        'compuciber_perfiles_settings_group',
        'compuciber_perfiles_busqueda',
        array(
            'type' => 'array',

            'sanitize_callback' =>
                'compuciber_sanitizar_perfiles_busqueda',

            'default' => array(),
        )
    );
}

add_action(
    'admin_init',
    'compuciber_registrar_opcion_perfiles'
);

/**
 * Crear nuevos perfiles de buscador
 * desde el panel administrativo.
 */
function compuciber_admin_crear_perfil_busqueda() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para crear perfiles.' );
    }

    check_admin_referer(
        'compuciber_crear_perfil_busqueda'
    );

    $nombre = isset( $_POST['cis_nombre_perfil'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['cis_nombre_perfil'] )
        )
        : '';

    $url_volver = admin_url(
        'admin.php?page=compuciber-ai-search-configuracion'
    );

    if ( $nombre === '' ) {

        wp_safe_redirect(
            add_query_arg(
                'cis_perfil',
                'nombre_vacio',
                $url_volver
            )
        );

        exit;
    }

    $perfiles = get_option(
        'compuciber_perfiles_busqueda',
        array()
    );

    if ( ! is_array( $perfiles ) ) {
        $perfiles = array();
    }

    // Crear un identificador único para cada perfil.
    $base = sanitize_key(
        sanitize_title(
            remove_accents( $nombre )
        )
    );

    if ( $base === '' ) {
        $base = 'perfil';
    }

    $id = $base;
    $numero = 2;

    while ( isset( $perfiles[ $id ] ) ) {
        $id = $base . '-' . $numero;
        $numero++;
    }

    $perfiles[ $id ] = array(
        'nombre'  => $nombre,
        'activo'  => 1,
        'incluir' => array(),
        'excluir' => array(),
    );

    update_option(
        'compuciber_perfiles_busqueda',
        compuciber_sanitizar_perfiles_busqueda(
            $perfiles
        )
    );

    wp_safe_redirect(
        add_query_arg(
            'cis_perfil',
            'creado',
            $url_volver
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_crear_perfil_busqueda',
    'compuciber_admin_crear_perfil_busqueda'
);

/**
 * Guardar cambios de un perfil existente.
 */
function compuciber_admin_guardar_perfil_busqueda() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para editar perfiles.' );
    }

    $id = isset( $_POST['cis_perfil_id'] )
        && is_string( $_POST['cis_perfil_id'] )
        ? sanitize_key(
            wp_unslash( $_POST['cis_perfil_id'] )
        )
        : '';

    if ( $id === '' ) {
        wp_die( 'Perfil no válido.' );
    }

    check_admin_referer(
        'compuciber_guardar_perfil_busqueda_' . $id
    );

    $perfiles = get_option(
        'compuciber_perfiles_busqueda',
        array()
    );

    if (
        ! is_array( $perfiles )
        || ! isset( $perfiles[ $id ] )
        || ! is_array( $perfiles[ $id ] )
    ) {
        wp_die( 'No se encontró el perfil.' );
    }

    $nombre = isset( $_POST['cis_nombre_perfil_editar'] )
        && is_string( $_POST['cis_nombre_perfil_editar'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['cis_nombre_perfil_editar'] )
        )
        : '';

    if ( $nombre === '' ) {
        wp_die( 'El nombre del perfil no puede estar vacío.' );
    }

    /*
     * Validar categorías reales de WooCommerce.
     */
    $categorias_validas = static function ( $campo ) {

        $valores = isset( $_POST[ $campo ] )
            && is_array( $_POST[ $campo ] )
            ? wp_unslash( $_POST[ $campo ] )
            : array();

        $ids = array_unique(
            array_filter(
                array_map( 'absint', $valores )
            )
        );

        $validos = array();

        foreach ( $ids as $term_id ) {

            $categoria = get_term(
                $term_id,
                'product_cat'
            );

            if (
                $categoria
                && ! is_wp_error( $categoria )
            ) {
                $validos[] = (int) $term_id;
            }
        }

        return $validos;
    };

    $perfiles[ $id ] = array(
        'nombre' => $nombre,

        'activo' => (
            isset( $_POST['cis_perfil_activo'] )
            && $_POST['cis_perfil_activo'] === '1'
        ) ? 1 : 0,

        'incluir' => $categorias_validas(
            'cis_categorias_incluir'
        ),

        'excluir' => $categorias_validas(
            'cis_categorias_excluir'
        ),
    );

    update_option(
        'compuciber_perfiles_busqueda',
        compuciber_sanitizar_perfiles_busqueda(
            $perfiles
        )
    );

    wp_safe_redirect(
        add_query_arg(
            array(
                'cis_perfil' => 'guardado',
            ),
            admin_url(
                'admin.php?page=compuciber-ai-search-configuracion'
            )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_guardar_perfil_busqueda',
    'compuciber_admin_guardar_perfil_busqueda'
);

/**
 * Eliminar un perfil desde el panel de administración.
 * No afecta al buscador predeterminado.
 */
function compuciber_admin_eliminar_perfil_busqueda() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para eliminar perfiles.' );
    }

    $id = isset( $_POST['cis_perfil_id'] )
        && is_string( $_POST['cis_perfil_id'] )
        ? sanitize_key( wp_unslash( $_POST['cis_perfil_id'] ) )
        : '';

    if ( $id === '' ) {
        wp_die( 'Perfil no válido.' );
    }

    check_admin_referer(
        'compuciber_eliminar_perfil_busqueda_' . $id
    );

    $perfiles = get_option(
        'compuciber_perfiles_busqueda',
        array()
    );

    if ( ! is_array( $perfiles ) || ! isset( $perfiles[ $id ] ) ) {
        wp_die( 'El perfil no existe.' );
    }

    unset( $perfiles[ $id ] );

    update_option(
        'compuciber_perfiles_busqueda',
        $perfiles
    );

    // Eliminar únicamente los estilos del perfil eliminado.
    $estilos = get_option(
        'compuciber_estilos_perfiles',
        array()
    );

    if ( is_array( $estilos ) && isset( $estilos[ $id ] ) ) {
        unset( $estilos[ $id ] );
        update_option(
            'compuciber_estilos_perfiles',
            $estilos
        );
    }

    wp_safe_redirect(
        add_query_arg(
            'cis_perfil',
            'eliminado',
            admin_url(
                'admin.php?page=compuciber-ai-search-configuracion'
            )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_eliminar_perfil_busqueda',
    'compuciber_admin_eliminar_perfil_busqueda'
);

/**
 * Comprobar si un producto puede aparecer
 * en un perfil de buscador.
 */
function compuciber_perfil_permite_producto(
    $producto_id,
    $perfil_id = ''
) {

    $perfil_id = sanitize_key(
        (string) $perfil_id
    );

    // Sin perfil: conservar el buscador original.
    if ( $perfil_id === '' ) {
        return true;
    }

    static $reglas = array();

    if ( ! array_key_exists( $perfil_id, $reglas ) ) {

        $perfiles = get_option(
            'compuciber_perfiles_busqueda',
            array()
        );

        // Un perfil inexistente o inactivo no muestra productos.
        if (
            ! is_array( $perfiles )
            || ! isset( $perfiles[ $perfil_id ] )
            || ! is_array( $perfiles[ $perfil_id ] )
            || empty( $perfiles[ $perfil_id ]['activo'] )
        ) {
            $reglas[ $perfil_id ] = false;

        } else {

            /*
             * Expandir categorías padre
             * incluyendo todas sus subcategorías.
             */
            $expandir = static function ( $ids ) {

                $categorias = array();

                foreach ( (array) $ids as $id ) {

                    $id = absint( $id );

                    if ( ! $id ) {
                        continue;
                    }

                    $termino = get_term(
                        $id,
                        'product_cat'
                    );

                    if (
                        ! $termino
                        || is_wp_error( $termino )
                    ) {
                        continue;
                    }

                    $categorias[] = $id;

                    $hijas = get_term_children(
                        $id,
                        'product_cat'
                    );

                    if (
                        is_array( $hijas )
                        && ! is_wp_error( $hijas )
                    ) {

                        foreach ( $hijas as $hija ) {
                            $categorias[] = absint( $hija );
                        }
                    }
                }

                return array_values(
                    array_unique( $categorias )
                );
            };

            $datos = $perfiles[ $perfil_id ];

            $reglas[ $perfil_id ] = array(
                'incluir' => $expandir(
                    $datos['incluir'] ?? array()
                ),

                'restringir' => ! empty(
                    $datos['incluir']
                ),

                'excluir' => $expandir(
                    $datos['excluir'] ?? array()
                ),
            );
        }
    }

    if ( $reglas[ $perfil_id ] === false ) {
        return false;
    }

    $producto_id = absint( $producto_id );

    if ( ! $producto_id ) {
        return false;
    }

    // Las variaciones utilizan las categorías del padre.
    if (
        get_post_type( $producto_id ) === 'product_variation'
    ) {

        $producto_id = (int) wp_get_post_parent_id(
            $producto_id
        );
    }

    $terminos = get_the_terms(
        $producto_id,
        'product_cat'
    );

    $asignadas = array();

    if ( is_array( $terminos ) ) {

        foreach ( $terminos as $termino ) {
            $asignadas[] = (int) $termino->term_id;
        }
    }

    // Las exclusiones siempre tienen prioridad.
    if (
        array_intersect(
            $asignadas,
            $reglas[ $perfil_id ]['excluir']
        )
    ) {
        return false;
    }

    // Si hay categorías incluidas, exigir coincidencia.
    if (
        $reglas[ $perfil_id ]['restringir']
        && ! array_intersect(
            $asignadas,
            $reglas[ $perfil_id ]['incluir']
        )
    ) {
        return false;
    }

    return true;
}

/**
 * Condición SQL para filtrar productos
 * por categorías del perfil seleccionado.
 */
function compuciber_perfil_condicion_sql( $perfil_id ) {

    global $wpdb;

    $perfil_id = sanitize_key(
        (string) $perfil_id
    );

    if ( $perfil_id === '' ) {
        return '';
    }

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
        return ' AND 1=0';
    }

    /*
     * Obtener categorías padre e hijas.
     */
    $expandir = static function ( $seleccionadas ) {

        $ids = array();

        foreach ( (array) $seleccionadas as $id ) {

            $id = absint( $id );

            $termino = $id
                ? get_term( $id, 'product_cat' )
                : false;

            if (
                ! $termino
                || is_wp_error( $termino )
            ) {
                continue;
            }

            $ids[] = $id;

            $hijas = get_term_children(
                $id,
                'product_cat'
            );

            if (
                is_array( $hijas )
                && ! is_wp_error( $hijas )
            ) {
                foreach ( $hijas as $hija ) {
                    $ids[] = absint( $hija );
                }
            }
        }

        return array_values(
            array_unique(
                array_filter( $ids )
            )
        );
    };

    $perfil = $perfiles[ $perfil_id ];

    $sql = '';

    foreach ( array( 'incluir', 'excluir' ) as $tipo ) {

        $seleccionadas = (array) (
            $perfil[ $tipo ] ?? array()
        );

        if ( empty( $seleccionadas ) ) {
            continue;
        }

        $ids = $expandir( $seleccionadas );

        if ( empty( $ids ) ) {

            if ( $tipo === 'incluir' ) {
                return ' AND 1=0';
            }

            continue;
        }

        $lista = implode(
            ',',
            array_map( 'absint', $ids )
        );

        $sql .= ' AND '
            . ( $tipo === 'excluir' ? 'NOT ' : '' )
            . 'EXISTS ('
            . 'SELECT 1 FROM '
            . $wpdb->term_relationships
            . ' AS cis_rel '
            . 'INNER JOIN '
            . $wpdb->term_taxonomy
            . ' AS cis_tax '
            . 'ON cis_tax.term_taxonomy_id = '
            . 'cis_rel.term_taxonomy_id '
            . 'WHERE cis_rel.object_id = i.id '
            . "AND cis_tax.taxonomy = 'product_cat' "
            . 'AND cis_tax.term_id IN ('
            . $lista
            . ')'
            . ')';
    }

    return $sql;
}

/**
 * Normalizar las opciones visuales de cada perfil.
 * Los estilos se guardan separados de los filtros.
 */
function compuciber_sanitizar_estilos_perfiles( $valor ) {

    if ( ! is_array( $valor ) ) {
        return array();
    }

    $limpios = array();

    $colores = array(
        'fondo'       => '#ffffff',
        'texto'       => '#222222',
        'borde'       => '#d1d5db',
        'boton'       => '#1e3a5f',
        'texto_boton' => '#ffffff',
    );

    foreach ( $valor as $id => $datos ) {

        $id = sanitize_key( (string) $id );

        if ( $id === '' || ! is_array( $datos ) ) {
            continue;
        }

        $limpios[ $id ] = array();

        foreach ( $colores as $campo => $predeterminado ) {

            $color = sanitize_hex_color(
                (string) (
                    $datos[ $campo ] ?? $predeterminado
                )
            );

            $limpios[ $id ][ $campo ] =
                $color ?: $predeterminado;
        }

        $limpios[ $id ]['radio'] = min(
            30,
            absint( $datos['radio'] ?? 8 )
        );

        $limpios[ $id ]['placeholder'] =
            sanitize_text_field(
                (string) (
                    $datos['placeholder'] ?? ''
                )
            );
    }

    return $limpios;
}