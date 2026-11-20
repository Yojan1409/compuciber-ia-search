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

        // LAPTOPS
        'laptp'       => 'laptop',
        'lapto'       => 'laptop',
        'laptot'      => 'laptop',
        'latop'       => 'laptop',
        'lapotp'      => 'laptop',
        'laptpo'      => 'laptop',
        'lapptop'     => 'laptop',
        'latpop'      => 'laptop',

        // IMPRESORAS
        'inpresora'   => 'impresora',
        'impesora'    => 'impresora',
        'impresra'    => 'impresora',
        'impresroa'   => 'impresora',
        'impersora'   => 'impresora',
        'impreosra'   => 'impresora',
        'imprresora'  => 'impresora',
        'imrpesora'   => 'impresora',
        'rimpresora'  => 'impresora',
        'impresoraa'  => 'impresora',

        // MOUSE
        'mause'       => 'mouse',
        'maus'        => 'mouse',
        'mou'         => 'mouse',
        'mau'         => 'mouse',
        'moues'       => 'mouse',
        'mosue'       => 'mouse',
        'muose'       => 'mouse',
        'mose'        => 'mouse',
        'moouse'      => 'mouse',
        'mouce'       => 'mouse',

        // TECLADOS
        'tecldao'     => 'teclado',
        'tecldo'      => 'teclado',
        'tecaldo'     => 'teclado',
        'telcado'     => 'teclado',
        'tecado'      => 'teclado',
        'tecladp'     => 'teclado',
        'tecledo'     => 'teclado',
        'tecladdo'    => 'teclado',

        // MONITORES
        'monior'      => 'monitor',
        'monitr'      => 'monitor',
        'monitro'     => 'monitor',
        'montior'     => 'monitor',
        'monitoor'    => 'monitor',
        'monotor'     => 'monitor',

        // ROUTERS
        'ruter'       => 'router',
        'routter'     => 'router',
        'rotuer'      => 'router',
        'roter'       => 'router',
        'routre'      => 'router',

        // TABLETS
        'tabelt'      => 'tablet',
        'tabet'       => 'tablet',
        'talbet'      => 'tablet',
        'tablett'     => 'tablet',

        // CARGADORES
        'cargdor'     => 'cargador',
        'cargdaor'    => 'cargador',
        'caragdor'    => 'cargador',
        'cargadorr'   => 'cargador',

        // COMPUTADORAS
        'computdora'  => 'computadora',
        'compuadora'  => 'computadora',
        'computadra'  => 'computadora',
        'compuatora'  => 'computadora',

        // CAMARAS WEB
        'webcan'      => 'webcam',
        'wecbam'      => 'webcam',
        'webcma'      => 'webcam',

        // PARLANTES
        'parlnate'    => 'parlante',
        'parlate'     => 'parlante',
        'parlantee'   => 'parlante',

        // PANTALLAS
        'pantala'     => 'pantalla',
        'pantallla'   => 'pantalla',

        // PROYECTORES
        'proyecotr'   => 'proyector',
        'proyetor'    => 'proyector',
        'proyectro'   => 'proyector',

        // ESCANERES
        'escnaer'     => 'escáner',
        'escner'      => 'escáner',

        // CAMARAS
        'camra'       => 'cámara',
        'cammara'     => 'cámara',

        // MARCAS Y TERMINOS GENERALES EXISTENTES
        'logitec'     => 'logitech',
        'logitehc'    => 'logitech',
        'samsumg'     => 'samsung',
        'inalambrico' => 'inalámbrico',
        'wireles'     => 'wireless',

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
    // Respetar la opción de corrección del administrador.
    if ( get_option( 'compuciber_correccion', '1' ) !== '1' ) {
        return $busqueda;
    }

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

// Identificar si realmente hay algo que corregir.
    $hay_correccion = false;

    foreach ( $palabras as $palabra ) {
        if ( isset( $correcciones[ $palabra ] ) ) {
            $hay_correccion = true;
            break;
        }
    }

    if ( ! $hay_correccion ) {
        return $busqueda;
    }

    // Un SKU existente tiene prioridad sobre la corrección.
    if (
        function_exists( 'wc_get_product_id_by_sku' )
        && wc_get_product_id_by_sku(
            trim( (string) $busqueda )
        )
    ) {
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