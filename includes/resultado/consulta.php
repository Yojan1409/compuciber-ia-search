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


/**
 * Consultar productos respetando los filtros
 * del perfil de buscador seleccionado.
 */
function compuciber_consulta_productos(
    $ids_resultados,
    $perfil_id = ''
) {

    /*
     * Recuperar el perfil enviado desde el
     * buscador hacia la página de resultados.
     */
    if (
        $perfil_id === ''
        && isset( $_GET['cis_perfil'] )
        && is_string( $_GET['cis_perfil'] )
    ) {
        $perfil_id = sanitize_key(
            wp_unslash( $_GET['cis_perfil'] )
        );
    }

    $perfil_id = sanitize_key(
        (string) $perfil_id
    );

    /*
     * Normalizar los identificadores.
     */
    $ids_resultados = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    (array) $ids_resultados
                )
            )
        )
    );

    /*
     * Aplicar filtros solamente cuando
     * exista un perfil seleccionado.
     */
    if ( $perfil_id !== '' ) {

        if (
            ! function_exists(
                'compuciber_perfil_permite_producto'
            )
        ) {
            return compuciber_consulta_vacia();
        }

        $ids_resultados = array_values(
            array_filter(
                $ids_resultados,
                static function ( $id ) use ( $perfil_id ) {

                    return compuciber_perfil_permite_producto(
                        $id,
                        $perfil_id
                    );
                }
            )
        );
    }

    /*
     * Conservar la consulta original
     * y el orden de relevancia.
     */
    /*
    * Preparar la paginación de los resultados.
    */
    $cis_pagina_actual = 1;

    if (
        isset( $_GET['cis_page'] )
        && is_string( $_GET['cis_page'] )
    ) {
        $cis_pagina_actual = max(
            1,
            (int) wp_unslash( $_GET['cis_page'] )
        );
    }

    return new WP_Query(
        array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'paged'          => $cis_pagina_actual,

            'post__in' =>
                ! empty( $ids_resultados )
                    ? $ids_resultados
                    : array( 0 ),

            'orderby' => 'post__in',
        )
    );
}