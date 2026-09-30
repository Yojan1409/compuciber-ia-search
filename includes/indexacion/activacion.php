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
     * Obtiene todos los productos
     * publicados de WooCommerce.
     */
    $productos = get_posts(
        array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );


    /*
     * Indexa los productos existentes.
     *
     * false evita generar embeddings
     * de forma síncrona durante
     * la activación.
     */
    foreach ( $productos as $product_id ) {

        compuciber_indexar_producto(
            $product_id,
            false
        );
    }
}


register_activation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_activar_indexacion'
);