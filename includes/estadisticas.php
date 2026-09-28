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