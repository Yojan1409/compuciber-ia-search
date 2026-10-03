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

function compuciber_obtener_busquedas_sin_resultados(
    $limite = 10
) {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_stats';

    $limite = absint( $limite );

    if ( $limite < 1 ) {
        $limite = 10;
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                busqueda,
                COUNT(*) AS total,
                MAX(fecha) AS ultima_busqueda
            FROM {$tabla}
            WHERE resultados = 0
            GROUP BY busqueda
            ORDER BY total DESC, ultima_busqueda DESC
            LIMIT %d",
            $limite
        )
    );
}

/*
 * ==========================================================
 * ESTADÍSTICAS AVANZADAS - V2.0
 * ==========================================================
 */

/**
 * Obtiene el resumen general del buscador.
 */
function compuciber_obtener_resumen_estadisticas() {

    global $wpdb;

    $tabla_busquedas =
        $wpdb->prefix . 'compuciber_search_stats';

    $tabla_clicks =
        $wpdb->prefix . 'compuciber_search_clicks';

    $total_busquedas = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$tabla_busquedas}"
    );

    $busquedas_con_resultados = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$tabla_busquedas}
        WHERE resultados > 0"
    );

    $busquedas_sin_resultados = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$tabla_busquedas}
        WHERE resultados = 0"
    );

    $total_clicks = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$tabla_clicks}"
    );

    $tasa_exito = 0;

    if ( $total_busquedas > 0 ) {
        $tasa_exito = (
            $busquedas_con_resultados
            / $total_busquedas
        ) * 100;
    }

    $ctr = 0;

    if ( $total_busquedas > 0 ) {
        $ctr = (
            $total_clicks
            / $total_busquedas
        ) * 100;
    }

    return array(

        'total_busquedas' =>
            $total_busquedas,

        'busquedas_con_resultados' =>
            $busquedas_con_resultados,

        'busquedas_sin_resultados' =>
            $busquedas_sin_resultados,

        'total_clicks' =>
            $total_clicks,

        'tasa_exito' =>
            round( $tasa_exito, 2 ),

        'ctr' =>
            round( $ctr, 2 ),
    );
}


/**
 * Obtiene la cantidad de búsquedas
 * realizadas mediante texto, voz e imagen.
 */
function compuciber_obtener_busquedas_por_tipo() {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_stats';

    $resultados = $wpdb->get_results(
        "SELECT
            tipo,
            COUNT(*) AS total
        FROM {$tabla}
        GROUP BY tipo
        ORDER BY total DESC"
    );

    $tipos = array(
        'texto'  => 0,
        'voz'    => 0,
        'imagen' => 0,
    );

    foreach ( $resultados as $resultado ) {

        if (
            isset(
                $tipos[ $resultado->tipo ]
            )
        ) {
            $tipos[ $resultado->tipo ] =
                (int) $resultado->total;
        }
    }

    return $tipos;
}


/**
 * Obtiene la actividad del buscador
 * agrupada por día.
 */
function compuciber_obtener_busquedas_por_dia(
    $dias = 30
) {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_search_stats';

    $dias = absint( $dias );

    if ( $dias < 1 ) {
        $dias = 30;
    }

    $fecha_inicio = wp_date(
        'Y-m-d H:i:s',
        current_time( 'timestamp' )
        - ( DAY_IN_SECONDS * $dias )
    );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                DATE(fecha) AS fecha,
                COUNT(*) AS total
            FROM {$tabla}
            WHERE fecha >= %s
            GROUP BY DATE(fecha)
            ORDER BY fecha ASC",
            $fecha_inicio
        )
    );
}


/**
 * Obtiene las consultas que más
 * selecciones de productos generan.
 */
function compuciber_obtener_busquedas_con_mas_clicks(
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
                busqueda,
                COUNT(*) AS total
            FROM {$tabla}
            WHERE busqueda <> ''
            GROUP BY busqueda
            ORDER BY total DESC
            LIMIT %d",
            $limite
        )
    );
}

/*
 * ==========================================================
 * APRENDIZAJE DE RANKING - V2.0
 * ==========================================================
 */

/**
 * Obtiene cuántas veces un producto ha sido seleccionado
 * para una consulta determinada.
 *
 * Esta información se utiliza como una señal adicional
 * de relevancia y nunca sustituye las reglas principales
 * del motor de búsqueda.
 */
function compuciber_obtener_selecciones_para_busqueda(
    $producto_id,
    $busqueda
) {

    if (
        get_option(
            'compuciber_estadisticas',
            '0'
        ) !== '1'
    ) {
        return 0;
    }

    $producto_id = absint( $producto_id );

    $busqueda = sanitize_text_field(
        $busqueda
    );

    if (
        $producto_id < 1
        || empty( $busqueda )
    ) {
        return 0;
    }
    
    static $cache = array();

    $clave_busqueda = md5( $busqueda );

    if ( ! isset( $cache[ $clave_busqueda ] ) ) {

        global $wpdb;

        $tabla =
            $wpdb->prefix . 'compuciber_search_clicks';

        $selecciones = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT producto_id, COUNT(*) AS total
                FROM {$tabla}
                WHERE busqueda = %s
                GROUP BY producto_id",
                $busqueda
            )
        );

        $cache[ $clave_busqueda ] = array();

        if ( ! empty( $selecciones ) ) {

            foreach ( $selecciones as $seleccion ) {

                $id = absint(
                    $seleccion->producto_id
                );

                $cache[ $clave_busqueda ][ $id ] =
                    absint( $seleccion->total );
            }
        }
    }

    return isset(
        $cache[ $clave_busqueda ][ $producto_id ]
    )
        ? $cache[ $clave_busqueda ][ $producto_id ]
        : 0;
}