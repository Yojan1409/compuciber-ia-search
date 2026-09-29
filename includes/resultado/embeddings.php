<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Calcula la similitud coseno entre dos embeddings.
 *
 * El resultado normalmente estará entre -1 y 1.
 * Cuanto más cercano a 1, mayor similitud semántica.
 *
 * @param array $vector_a Primer embedding.
 * @param array $vector_b Segundo embedding.
 *
 * @return float
 */
function compuciber_similitud_coseno(
    $vector_a,
    $vector_b
) {

    if (
        ! is_array( $vector_a )
        || ! is_array( $vector_b )
        || empty( $vector_a )
        || empty( $vector_b )
        || count( $vector_a ) !== count( $vector_b )
    ) {
        return 0.0;
    }


    $producto_punto = 0.0;
    $magnitud_a = 0.0;
    $magnitud_b = 0.0;

    $total = count( $vector_a );


    for ( $i = 0; $i < $total; $i++ ) {

        $a = (float) $vector_a[ $i ];
        $b = (float) $vector_b[ $i ];

        $producto_punto += $a * $b;

        $magnitud_a += $a * $a;
        $magnitud_b += $b * $b;
    }


    if (
        $magnitud_a <= 0
        || $magnitud_b <= 0
    ) {
        return 0.0;
    }


    return $producto_punto / (
        sqrt( $magnitud_a )
        * sqrt( $magnitud_b )
    );
}


/**
 * Convierte el embedding almacenado en MySQL
 * nuevamente en un array numérico.
 *
 * @param string $embedding_json
 *
 * @return array|false
 */
function compuciber_decodificar_embedding(
    $embedding_json
) {

    if ( empty( $embedding_json ) ) {
        return false;
    }


    $embedding = json_decode(
        $embedding_json,
        true
    );


    if (
        ! is_array( $embedding )
        || empty( $embedding )
    ) {
        return false;
    }


    return array_map(
        'floatval',
        $embedding
    );
}