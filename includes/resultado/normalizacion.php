<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_normalizar_texto( $texto ) {

    $texto = strtolower(
        wp_strip_all_tags(
            (string) $texto
        )
    );

    $texto = remove_accents( $texto );

    $texto = preg_replace(
        '/[^a-z0-9\s]/',
        ' ',
        $texto
    );

    $texto = preg_replace(
        '/\s+/',
        ' ',
        $texto
    );

    return trim( $texto );
}


function compuciber_obtener_palabras( $texto ) {

    $texto = compuciber_normalizar_texto(
        $texto
    );

    if ( empty( $texto ) ) {
        return array();
    }

    $palabras = preg_split(
        '/\s+/',
        $texto
    );

    $ignoradas = array(
        'de',
        'del',
        'la',
        'el',
        'los',
        'las',
        'un',
        'una',
        'para',
        'con',
        'por',
        'y',
        'en',
        'que',
        'es',
        'busco',
        'quiero',
        'necesito',
        'color',
    );

    $resultado = array();

    foreach ( $palabras as $palabra ) {

        if (
            strlen( $palabra ) >= 2
            && ! in_array(
                $palabra,
                $ignoradas,
                true
            )
        ) {
            $resultado[] = $palabra;
        }
    }

    return array_values(
        array_unique(
            $resultado
        )
    );
}