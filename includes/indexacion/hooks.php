<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_indexar_al_guardar_producto( $post_id, $programar_embedding = true ) {

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $post_id = absint( $post_id );

    if (
        ! $post_id
        || get_post_type( $post_id ) !== 'product'
        || wp_is_post_revision( $post_id )
    ) {
        return;
    }

    /*
     * Un producto puede disparar varios hooks al guardarse.
     * Esperamos al final de esta petición para indexar sus
     * últimos datos (incluidas categorías y variaciones).
     */
    static $pendientes = array();

    if ( isset( $pendientes[ $post_id ] ) ) {
        // Si un hook completo llega después del de stock,
        // mantenemos también la programación del embedding.
        $pendientes[ $post_id ] =
            $pendientes[ $post_id ] || $programar_embedding;
        return;
    }

    $pendientes[ $post_id ] = (bool) $programar_embedding;

    add_action(
        'shutdown',
        static function () use ( $post_id, &$pendientes ) {

            if ( get_post_type( $post_id ) !== 'product' ) {
                return;
            }

            compuciber_indexar_producto( $post_id, false );

            if ( ! empty( $pendientes[ $post_id ] ) ) {
                compuciber_programar_embedding_producto( $post_id );
            }
        },
        100
    );
}

add_action(
    'save_post_product',
    'compuciber_indexar_al_guardar_producto',
    20
);


/**
 * Cuando WooCommerce crea un producto.
 */
add_action(
    'woocommerce_new_product',
    'compuciber_indexar_al_guardar_producto',
    20
);


/**
 * Cuando WooCommerce actualiza un producto.
 */
add_action(
    'woocommerce_update_product',
    'compuciber_indexar_al_guardar_producto',
    20
);


/**
 * Cuando se crea una variación.
 */
function compuciber_indexar_variacion(
    $variation_id
) {

    $variation =
        wc_get_product(
            $variation_id
        );

    if ( ! $variation ) {
        return;
    }

    $parent_id =
        $variation->get_parent_id();

    if ( $parent_id ) {

        compuciber_indexar_al_guardar_producto(
            $parent_id
        );
    }
}

add_action(
    'woocommerce_new_product_variation',
    'compuciber_indexar_variacion',
    20
);


/**
 * Cuando se actualiza una variación.
 */
add_action(
    'woocommerce_update_product_variation',
    'compuciber_indexar_variacion',
    20
);

/**
 * Los cambios de stock también pueden producirse durante ventas,
 * sin pasar por la pantalla de edición del producto.
 */
function compuciber_indexar_por_cambio_stock( $producto ) {

    if ( ! ( $producto instanceof WC_Product ) ) {
        return;
    }

    $post_id = $producto->is_type( 'variation' )
        ? $producto->get_parent_id()
        : $producto->get_id();

    if ( $post_id ) {
        // Un cambio de stock no modifica el texto semántico.
        compuciber_indexar_al_guardar_producto( $post_id, false );
    }
}

add_action(
    'woocommerce_product_set_stock',
    'compuciber_indexar_por_cambio_stock',
    20
);

add_action(
    'woocommerce_variation_set_stock',
    'compuciber_indexar_por_cambio_stock',
    20
);


/**
 * Elimina del índice cuando se elimina un producto.
 */
function compuciber_eliminar_producto_indice(
    $post_id
) {

    if (
        get_post_type( $post_id )
        !== 'product'
    ) {
        return;
    }

    global $wpdb;

    $tabla =
        $wpdb->prefix .
        'compuciber_product_index';

    $wpdb->delete(
        $tabla,
        array(
            'id' => $post_id,
        ),
        array(
            '%d',
        )
    );
}

add_action(
    'before_delete_post',
    'compuciber_eliminar_producto_indice'
);


/**
 * Al enviar un producto a papelera.
 */
function compuciber_producto_a_papelera(
    $post_id
) {

    if (
        get_post_type( $post_id )
        !== 'product'
    ) {
        return;
    }

    compuciber_eliminar_producto_indice(
        $post_id
    );
}

add_action(
    'trashed_post',
    'compuciber_producto_a_papelera'
);


/**
 * Al restaurar desde papelera.
 */
function compuciber_producto_restaurado(
    $post_id
) {

    if (
        get_post_type( $post_id )
        !== 'product'
    ) {
        return;
    }

    compuciber_indexar_al_guardar_producto(
        $post_id
    );
}

add_action(
    'untrashed_post',
    'compuciber_producto_restaurado'
);

/**
 * Programa un embedding pendiente sin bloquear
 * el guardado del producto en WooCommerce.
 */
function compuciber_programar_embedding_producto( $product_id ) {

    $product_id = absint( $product_id );

    if (
        ! $product_id
        || get_post_type( $product_id ) !== 'product'
        || get_post_status( $product_id ) !== 'publish'
        || get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) !== '1'
    ) {
        return;
    }

    // WooCommerce incorpora Action Scheduler.
    if (
        ! function_exists( 'as_schedule_single_action' )
        || ! function_exists( 'as_next_scheduled_action' )
        || ! class_exists( 'Action_Scheduler' )
        || ! Action_Scheduler::is_initialized()
    ) {
        return;
    }

    $hook = 'compuciber_embedding_producto_pendiente';
    $grupo = 'compuciber-ai-search';
    $argumentos = array( $product_id );

    // Evitar múltiples tareas pendientes para el mismo producto.
    if (
        as_next_scheduled_action(
            $hook,
            $argumentos,
            $grupo
        )
    ) {
        return;
    }

    as_schedule_single_action(
        time() + 60,
        $hook,
        $argumentos,
        $grupo
    );
}

/**
 * Procesa el embedding cuando llega su turno.
 */
function compuciber_procesar_embedding_producto_pendiente(
    $product_id
) {

    $product_id = absint( $product_id );

    if (
        ! $product_id
        || get_post_type( $product_id ) !== 'product'
        || get_post_status( $product_id ) !== 'publish'
        || get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) !== '1'
    ) {
        return;
    }

    compuciber_indexar_producto(
        $product_id,
        true
    );
}

add_action(
    'compuciber_embedding_producto_pendiente',
    'compuciber_procesar_embedding_producto_pendiente',
    10,
    1
);