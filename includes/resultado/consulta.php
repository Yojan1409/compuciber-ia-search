<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_consulta_vacia() {

    return new WP_Query(
        array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'post__in'       => array( 0 ),
        )
    );
}


function compuciber_consulta_productos(
    $ids_resultados
) {

    return new WP_Query(
        array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,

            'post__in' =>
                ! empty( $ids_resultados )
                    ? $ids_resultados
                    : array( 0 ),

            'orderby' => 'post__in',
        )
    );
}