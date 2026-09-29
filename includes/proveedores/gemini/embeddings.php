<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Genera un embedding mediante Gemini.
 *
 * @param string $texto Texto que se desea representar.
 * @param string $tipo  Tipo de contenido: producto o consulta.
 *
 * @return array|false Vector numérico o false si falla.
 */
function compuciber_generar_embedding_gemini(
    $texto,
    $tipo = 'producto'
) {

    $api_key = get_option(
        'compuciber_gemini_api_key',
        ''
    );

    if ( empty( $api_key ) ) {
        return false;
    }

    $texto = trim(
        wp_strip_all_tags(
            (string) $texto
        )
    );

    if ( $texto === '' ) {
        return false;
    }


    /*
     * Gemini Embedding 2 utiliza instrucciones
     * dentro del propio contenido.
     */
    if ( $tipo === 'consulta' ) {

        $contenido_embedding =
            'Representa esta consulta para buscar productos '
            . 'tecnológicos semánticamente relacionados: '
            . $texto;

    } else {

        $contenido_embedding =
            'Representa este producto tecnológico para que '
            . 'pueda ser recuperado mediante búsquedas '
            . 'semánticamente relacionadas: '
            . $texto;
    }


    $response = wp_remote_post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent',
        array(
            'timeout' => 20,

            'headers' => array(
                'Content-Type'  => 'application/json',
                'x-goog-api-key' => $api_key,
            ),

            'body' => wp_json_encode(
                array(
                    'content' => array(
                        'parts' => array(
                            array(
                                'text' =>
                                    $contenido_embedding,
                            ),
                        ),
                    ),

                    'output_dimensionality' => 768,
                )
            ),
        )
    );


    if ( is_wp_error( $response ) ) {
        return false;
    }


    $codigo_http =
        wp_remote_retrieve_response_code(
            $response
        );

    if ( $codigo_http !== 200 ) {
        return false;
    }


    $body = json_decode(
        wp_remote_retrieve_body(
            $response
        ),
        true
    );


    if (
        empty( $body['embedding']['values'] )
        || ! is_array(
            $body['embedding']['values']
        )
    ) {
        return false;
    }


    $embedding = array_map(
        'floatval',
        $body['embedding']['values']
    );


    /*
     * Verificación de dimensionalidad.
     */
    if ( count( $embedding ) !== 768 ) {
        return false;
    }


    return $embedding;
}