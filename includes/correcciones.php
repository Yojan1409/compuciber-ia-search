<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene las correcciones ortográficas disponibles.
 *
 * Combina las correcciones predeterminadas del plugin
 * con las correcciones personalizadas creadas desde
 * el panel de WordPress.
 */
function compuciber_obtener_correcciones() {

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

    $personalizadas = get_option(
        'compuciber_correcciones_personalizadas',
        array()
    );

    if ( ! is_array( $personalizadas ) ) {
        $personalizadas = array();
    }

    foreach (
        $personalizadas
        as $incorrecta => $correcta
    ) {

        $incorrecta = sanitize_text_field(
            $incorrecta
        );

        $correcta = sanitize_text_field(
            $correcta
        );

        $incorrecta = trim(
            mb_strtolower( $incorrecta )
        );

        $correcta = trim(
            $correcta
        );

        if (
            $incorrecta === ''
            || $correcta === ''
        ) {
            continue;
        }

        /*
         * Las correcciones personalizadas tienen prioridad
         * sobre las predeterminadas.
         */
        $correcciones[ $incorrecta ] =
            $correcta;
    }

    return $correcciones;
}


/**
 * Corrige palabras conocidas dentro de una búsqueda.
 */
function compuciber_corregir_busqueda( $busqueda ) {

    $correcciones =
        compuciber_obtener_correcciones();

    $palabras = preg_split(
        '/\s+/u',
        mb_strtolower(
            trim( $busqueda )
        )
    );

    if ( ! is_array( $palabras ) ) {
        return $busqueda;
    }

    foreach ( $palabras as &$palabra ) {

        if (
            isset(
                $correcciones[ $palabra ]
            )
        ) {

            $palabra =
                $correcciones[ $palabra ];
        }
    }

    unset( $palabra );

    return implode(
        ' ',
        $palabras
    );
}


/**
 * Guarda una corrección personalizada.
 */
function compuciber_guardar_correccion_personalizada(
    $incorrecta,
    $correcta
) {

    $incorrecta = sanitize_text_field(
        $incorrecta
    );

    $correcta = sanitize_text_field(
        $correcta
    );

    $incorrecta = trim(
        mb_strtolower( $incorrecta )
    );

    $correcta = trim(
        $correcta
    );

    if (
        $incorrecta === ''
        || $correcta === ''
        || $incorrecta === mb_strtolower( $correcta )
    ) {
        return false;
    }

    $correcciones = get_option(
        'compuciber_correcciones_personalizadas',
        array()
    );

    if ( ! is_array( $correcciones ) ) {
        $correcciones = array();
    }

    $correcciones[ $incorrecta ] =
        $correcta;

    update_option(
        'compuciber_correcciones_personalizadas',
        $correcciones
    );

    return true;
}


/**
 * Elimina una corrección personalizada.
 */
function compuciber_eliminar_correccion_personalizada(
    $incorrecta
) {

    $incorrecta = sanitize_text_field(
        $incorrecta
    );

    $incorrecta = trim(
        mb_strtolower( $incorrecta )
    );

    if ( $incorrecta === '' ) {
        return false;
    }

    $correcciones = get_option(
        'compuciber_correcciones_personalizadas',
        array()
    );

    if ( ! is_array( $correcciones ) ) {
        return false;
    }

    if (
        ! isset(
            $correcciones[ $incorrecta ]
        )
    ) {
        return false;
    }

    unset(
        $correcciones[ $incorrecta ]
    );

    update_option(
        'compuciber_correcciones_personalizadas',
        $correcciones
    );

    return true;
}