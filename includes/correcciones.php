<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function compuciber_corregir_busqueda( $busqueda ) {

    $correcciones = array(
        'laptp'       => 'laptop',
        'lapto'       => 'laptop',
        'laptot'      => 'laptop',
        'tecldao'     => 'teclado',
        'tecldo'      => 'teclado',
        'mause'       => 'mouse',
        'maus'        => 'mouse',
        'mou'         => 'mouse',
        'mau'         => 'mouse',
        'logitec'     => 'logitech',
        'logitehc'    => 'logitech',
        'inalambrico' => 'inalámbrico',
        'wireles'     => 'wireless',
        'monior'      => 'monitor',
        'samsumg'     => 'samsung',
    );

    $palabras = preg_split(
        '/\s+/',
        strtolower( trim( $busqueda ) )
    );

    foreach ( $palabras as &$palabra ) {

        if ( isset( $correcciones[ $palabra ] ) ) {
            $palabra = $correcciones[ $palabra ];
        }
    }

    return implode( ' ', $palabras );
}