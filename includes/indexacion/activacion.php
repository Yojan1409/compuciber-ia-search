<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Crea la tabla e indexa productos al activar el plugin.
 */
function compuciber_activar_indexacion() {

    compuciber_crear_tabla_indice_productos();

    compuciber_crear_tabla_estadisticas();

    update_option(
        'compuciber_indice_version',
        COMPUCIBER_INDICE_VERSION
    );

    $productos = get_posts(
        array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    foreach ( $productos as $product_id ) {

        compuciber_indexar_producto(
            $product_id
        );
    }
}
register_activation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_activar_indexacion'
);