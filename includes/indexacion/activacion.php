<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Crea las tablas e indexa productos
 * al activar el plugin.
 */
function compuciber_activar_indexacion() {

    /*
     * Crea la tabla del índice de productos.
     */
    compuciber_crear_tabla_indice_productos();


    /*
     * Crea la tabla de estadísticas
     * de búsquedas.
     */
    compuciber_crear_tabla_estadisticas();


    /*
     * Crea la tabla de productos
     * seleccionados desde el buscador.
     */
    compuciber_crear_tabla_selecciones();


    /*
     * Guarda la versión actual
     * del índice.
     */
    update_option(
        'compuciber_indice_version',
        COMPUCIBER_INDICE_VERSION
    );


    /*
     * Indexación inicial por lotes.
     * No bloquea la activación del plugin.
     */
    update_option(
        'compuciber_indice_lotes_estado',
        array(
            'cursor' => 0,
            'fallos' => array(),
            'ultima' => 0,
            'estado' => 'pendiente',
        ),
        false
    );

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );

    compuciber_programar_indice_113();
}

/**
 * Comprueba la cobertura real del índice.
 *
 * Cuenta solamente productos publicados.
 * Las variaciones se incluyen mediante
 * su producto padre.
 */
function compuciber_resumen_indice_113() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'product'
           AND post_status = 'publish'"
    );

    $pendientes = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$tabla} i ON i.id = p.ID
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           AND i.id IS NULL"
    );

    return array(
        'total' => $total,
        'indexados' => max( 0, $total - $pendientes ),
        'pendientes' => $pendientes,
    );
}

/**
 * Programa el siguiente lote solamente
 * cuando no hay otro pendiente.
 */
function compuciber_programar_indice_113() {

    if (
        ! wp_next_scheduled(
            'compuciber_indice_lote_113'
        )
    ) {

        wp_schedule_single_event(
            time() + 10,
            'compuciber_indice_lote_113'
        );
    }
}

/**
 * Procesa hasta 40 productos pendientes.
 */
function compuciber_procesar_indice_113() {
    global $wpdb;

    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $estado = get_option(
        'compuciber_indice_lotes_estado',
        array()
    );

    if ( ! is_array( $estado ) ) {
        $estado = array();
    }

    if (
        ( $estado['estado'] ?? '' ) === 'errores'
    ) {
        return;
    }

    $cursor = absint( $estado['cursor'] ?? 0 );

    $fallos = (array) (
        $estado['fallos'] ?? array()
    );

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    /*
     * Obtener productos publicados que
     * todavía no tienen registro en el índice.
     */
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$tabla} i ON i.id = p.ID
             WHERE p.post_type = %s
               AND p.post_status = %s
               AND i.id IS NULL
               AND p.ID > %d
             ORDER BY p.ID ASC
             LIMIT %d",
            'product',
            'publish',
            $cursor,
            40
        )
    );

    /*
     * Registrar un error SQL, sin declarar
     * que la indexación terminó.
     */
    if ( $wpdb->last_error ) {

        $estado['estado'] = 'errores';
        $estado['ultima'] = time();

        update_option(
            'compuciber_indice_lotes_estado',
            $estado,
            false
        );

        return;
    }

    foreach ( (array) $ids as $id ) {

        $id = absint( $id );
        $cursor = $id;

        /*
         * Máximo tres intentos por producto
         * durante este proceso.
         */
        if (
            (int) ( $fallos[ $id ] ?? 0 ) >= 3
        ) {
            continue;
        }

        /*
         * Conservar la función original
         * de indexación WooCommerce.
         *
         * false evita generar embeddings
         * durante estos lotes iniciales.
         */
        compuciber_indexar_producto(
            $id,
            false
        );

        /*
         * Confirmar que existe el registro.
         */
        $existe = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$tabla}
                 WHERE id = %d
                 LIMIT 1",
                $id
            )
        );

        if ( $existe ) {

            unset( $fallos[ $id ] );

        } elseif (
            get_post_status( $id ) === 'publish'
        ) {

            $fallos[ $id ] =
                (int) ( $fallos[ $id ] ?? 0 ) + 1;
        }
    }

    $resumen = compuciber_resumen_indice_113();

    $estado['cursor'] = $cursor;
    $estado['fallos'] = $fallos;
    $estado['ultima'] = time();

    if ( $resumen['pendientes'] === 0 ) {

        $estado['estado'] = 'completado';
        $estado['cursor'] = 0;
        $estado['fallos'] = array();

    } elseif ( count( (array) $ids ) < 40 ) {

        /*
         * Terminó una pasada.
         * Comprobar si quedan fallos
         * que todavía puedan reintentarse.
         */
        if (
            count( $fallos ) >=
            $resumen['pendientes']
        ) {

            $todos_agotados = true;

            foreach ( $fallos as $intentos ) {

                if ( (int) $intentos < 3 ) {
                    $todos_agotados = false;
                    break;
                }
            }

        } else {

            $todos_agotados = false;
        }

        if ( $todos_agotados ) {

            $estado['estado'] = 'errores';

        } else {

            $estado['cursor'] = 0;
            $estado['estado'] = 'en_proceso';
        }

    } else {

        $estado['estado'] = 'en_proceso';
    }

    update_option(
        'compuciber_indice_lotes_estado',
        $estado,
        false
    );

    if ( $estado['estado'] === 'en_proceso' ) {
        compuciber_programar_indice_113();
    }
}

add_action(
    'compuciber_indice_lote_113',
    'compuciber_procesar_indice_113'
);

/**
 * Iniciar automáticamente en instalaciones
 * actualizadas si faltan productos.
 */
function compuciber_revisar_indice_113() {

    if (
        ! current_user_can( 'manage_options' )
        || ! class_exists( 'WooCommerce' )
    ) {
        return;
    }

    $resumen = compuciber_resumen_indice_113();

    if ( $resumen['pendientes'] === 0 ) {
        return;
    }

    $estado = get_option(
        'compuciber_indice_lotes_estado',
        array()
    );

    if ( ! is_array( $estado ) ) {
        $estado = array();
    }

    if (
        ( $estado['estado'] ?? '' ) === 'errores'
    ) {
        return;
    }

    if (
        wp_next_scheduled(
            'compuciber_indice_lote_113'
        )
    ) {
        return;
    }

    /*
     * Dar margen a cualquier ejecución
     * que todavía esté terminando.
     */
    if (
        time() -
        (int) ( $estado['ultima'] ?? 0 ) < 90
    ) {
        return;
    }

    compuciber_programar_indice_113();
}

add_action(
    'admin_init',
    'compuciber_revisar_indice_113',
    20
);

/**
 * Permitir reintento desde el Dashboard.
 */
function compuciber_reintentar_indice_113() {

    if (
        ! current_user_can( 'manage_options' )
    ) {
        wp_die( 'No autorizado.' );
    }

    check_admin_referer(
        'compuciber_reintentar_indice_113'
    );

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );

    update_option(
        'compuciber_indice_lotes_estado',
        array(
            'cursor' => 0,
            'fallos' => array(),
            'ultima' => 0,
            'estado' => 'pendiente',
        ),
        false
    );

    compuciber_programar_indice_113();

    wp_safe_redirect(
        admin_url(
            'admin.php?page=compuciber-ai-search'
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_reintentar_indice_113',
    'compuciber_reintentar_indice_113'
);

/**
 * Al desactivar, limpiar las tareas
 * pendientes de indexación inicial.
 * No elimina los productos indexados.
 */
function compuciber_detener_indice_113() {

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );
}

register_deactivation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_detener_indice_113'
);

register_activation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_activar_indexacion'
);