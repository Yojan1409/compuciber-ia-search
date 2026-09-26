<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Optimiza una imagen antes de enviarla a la IA.
 *
 * Reduce dimensiones y calidad para disminuir
 * consumo de recursos sin perder información útil.
 */
function compuciber_optimizar_imagen_ia( $ruta_imagen ) {


    if (
        ! file_exists( $ruta_imagen )
    ) {

        return false;

    }


    $imagen = wp_get_image_editor(
        $ruta_imagen
    );


    if (
        is_wp_error( $imagen )
    ) {

        return false;

    }


    /*
     * Tamaño máximo enviado a la IA.
     */

    $imagen->resize(
        1200,
        1200,
        false
    );


    /*
     * Calidad equilibrada.
     */

    $imagen->set_quality(
        80
    );


    /*
     * Guarda una copia temporal.
     */

    $archivo = wp_tempnam(
        'compuciber-ia'
    );


    $resultado =
        $imagen->save(
            $archivo
        );


    if (
        is_wp_error( $resultado )
    ) {

        return false;

    }


    return $resultado['path'];

}
/**
 * Genera un identificador único para una imagen.
 */
function compuciber_generar_hash_imagen( $ruta_imagen ) {

    if (
        ! file_exists( $ruta_imagen )
    ) {

        return false;

    }


    return md5_file(
        $ruta_imagen
    );

}


/**
 * Obtiene una interpretación guardada de una imagen.
 */
function compuciber_obtener_cache_imagen( $hash ) {

    if ( empty( $hash ) ) {

        return false;

    }


    return get_transient(
        'compuciber_img_' . $hash
    );

}


/**
 * Guarda una interpretación de imagen.
 */
function compuciber_guardar_cache_imagen(
    $hash,
    $resultado
) {

    if (
        empty( $hash )
        || empty( $resultado )
    ) {

        return false;

    }


    return set_transient(
        'compuciber_img_' . $hash,
        $resultado,
        DAY_IN_SECONDS * 30
    );

}