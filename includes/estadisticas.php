<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * ==========================================================
 * ESTADÍSTICAS DE BÚSQUEDAS
 * ==========================================================
 */

/**
 * Crea la tabla donde se almacenarán las búsquedas.
 */
function compuciber_crear_tabla_estadisticas() {

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_search_stats';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$tabla} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        busqueda VARCHAR(255) NOT NULL,
        busqueda_interpretada VARCHAR(255) DEFAULT '',
        tipo VARCHAR(30) DEFAULT 'texto',
        resultados INT UNSIGNED DEFAULT 0,
        fecha DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY busqueda (busqueda),
        KEY fecha (fecha)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( $sql );
}

/**
 * Crea la tabla donde se almacenarán
 * los productos seleccionados desde el buscador.
 */
function compuciber_crear_tabla_selecciones() {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_clicks';

    $charset_collate =
        $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$tabla} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        producto_id BIGINT UNSIGNED NOT NULL,
        busqueda VARCHAR(255) DEFAULT '',
        fecha DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY producto_id (producto_id),
        KEY fecha (fecha)
    ) {$charset_collate};";

    require_once ABSPATH
        . 'wp-admin/includes/upgrade.php';

    dbDelta( $sql );
}

/**
 * Registra una búsqueda.
 */
function compuciber_registrar_busqueda(
    $busqueda,
    $busqueda_interpretada = '',
    $resultados = 0,
    $tipo = 'texto'
) {

    if (
        get_option(
            'compuciber_estadisticas',
            '0'
        ) !== '1'
    ) {
        return;
    }

    if ( empty( $busqueda ) ) {
        return;
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_search_stats';

    $tipos_permitidos = array(
        'texto',
        'voz',
        'imagen'
    );

    if ( ! in_array( $tipo, $tipos_permitidos, true ) ) {
        $tipo = 'texto';
    }

    $wpdb->insert(
        $tabla,
        array(
            'busqueda' => sanitize_text_field( $busqueda ),
            'busqueda_interpretada' => sanitize_text_field(
                $busqueda_interpretada
            ),
            'tipo' => $tipo,
            'resultados' => absint( $resultados ),
            'fecha' => current_time( 'mysql' ),
        ),
        array(
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
        )
    );
}

/**
 * Registra un producto seleccionado
 * desde los resultados del buscador.
 */
function compuciber_registrar_producto_seleccionado(
    $producto_id,
    $busqueda = ''
) {

    if (
        get_option(
            'compuciber_estadisticas',
            '0'
        ) !== '1'
    ) {
        return false;
    }

    $producto_id = absint( $producto_id );

    if (
        $producto_id < 1
        || get_post_type( $producto_id ) !== 'product'
    ) {
        return false;
    }

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_clicks';

    return $wpdb->insert(
        $tabla,
        array(
            'producto_id' => $producto_id,
            'busqueda'    => sanitize_text_field( $busqueda ),
            'fecha'       => current_time( 'mysql' ),
        ),
        array(
            '%d',
            '%s',
            '%s',
        )
    );
}


/**
 * Recibe mediante AJAX la selección
 * de un producto del buscador.
 */
function compuciber_ajax_registrar_producto_seleccionado() {

    if (
        ! isset( $_POST['nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash( $_POST['nonce'] )
            ),
            'compuciber_registrar_seleccion'
        )
    ) {
        wp_send_json_error(
            'Solicitud no válida.',
            403
        );
    }

    $producto_id = isset( $_POST['producto_id'] )
        ? absint( $_POST['producto_id'] )
        : 0;

    $busqueda = isset( $_POST['busqueda'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['busqueda'] )
        )
        : '';

    $registrado =
        compuciber_registrar_producto_seleccionado(
            $producto_id,
            $busqueda
        );

    if ( false === $registrado ) {
        wp_send_json_error(
            'No se pudo registrar la selección.'
        );
    }

    wp_send_json_success();
}


add_action(
    'wp_ajax_compuciber_registrar_seleccion',
    'compuciber_ajax_registrar_producto_seleccionado'
);

add_action(
    'wp_ajax_nopriv_compuciber_registrar_seleccion',
    'compuciber_ajax_registrar_producto_seleccionado'
);

/**
 * Obtiene las búsquedas más realizadas.
 */
function compuciber_obtener_busquedas_populares( $limite = 10 ) {

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_search_stats';

    $limite = absint( $limite );

    if ( $limite < 1 ) {
        $limite = 10;
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                busqueda,
                COUNT(*) AS total
            FROM {$tabla}
            GROUP BY busqueda
            ORDER BY total DESC
            LIMIT %d",
            $limite
        )
    );
}

/**
 * Obtiene los productos más seleccionados
 * desde los resultados del buscador.
 */
function compuciber_obtener_productos_mas_seleccionados(
    $limite = 10
) {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_clicks';

    $limite = absint( $limite );

    if ( $limite < 1 ) {
        $limite = 10;
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                producto_id,
                COUNT(*) AS total
            FROM {$tabla}
            GROUP BY producto_id
            ORDER BY total DESC
            LIMIT %d",
            $limite
        )
    );
}