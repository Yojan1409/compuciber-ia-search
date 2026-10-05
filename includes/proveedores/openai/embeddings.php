<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Genera un embedding mediante OpenAI.
 *
 * Utiliza el modelo configurado en WordPress.
 * Para los modelos text-embedding-3-* se solicitan
 * 768 dimensiones para mantener compatibilidad
 * con la arquitectura semántica del plugin.
 *
 * @param string $texto Texto que se desea representar.
 * @param string $tipo  Tipo de contenido: producto o consulta.
 *
 * @return array|false Vector numérico o false si falla.
 */
function compuciber_generar_embedding_openai(
    $texto,
    $tipo = 'producto'
) {

    /*
     * API Key configurada por el propietario
     * de la instalación.
     */
    $api_key = get_option(
        'compuciber_openai_api_key',
        ''
    );

    if ( empty( $api_key ) ) {
        return false;
    }


    /*
     * Modelo configurable desde el panel.
     */
    $modelo = get_option(
        'compuciber_openai_modelo_embeddings',
        'text-embedding-3-small'
    );

    $modelo = trim(
        (string) $modelo
    );

    if ( $modelo === '' ) {
        $modelo = 'text-embedding-3-small';
    }


    /*
     * Limpiar texto.
     */
    $texto = trim(
        wp_strip_all_tags(
            (string) $texto
        )
    );

    if ( $texto === '' ) {
        return false;
    }


    /*
     * Añadimos contexto semántico según
     * el tipo de contenido.
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


    /*
     * Construcción de la petición.
     */
    $body = array(
        'model'           => $modelo,
        'input'           => $contenido_embedding,
        'encoding_format' => 'float',
    );


    /*
     * Los modelos text-embedding-3 permiten
     * controlar la dimensionalidad.
     *
     * Utilizamos 768 dimensiones para mantener
     * consistencia con Gemini Embedding 2.
     */
    if (
        strpos(
            $modelo,
            'text-embedding-3-'
        ) === 0
    ) {

        $body['dimensions'] = 768;
    }


    /*
     * Petición a OpenAI.
     */
    $response = wp_remote_post(
        'https://api.openai.com/v1/embeddings',
        array(
            'timeout' => 20,

            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),

            'body' => wp_json_encode(
                $body
            ),
        )
    );


    /*
     * Error de transporte.
     */
    if ( is_wp_error( $response ) ) {
        return false;
    }


    /*
     * Validar respuesta HTTP.
     */
    $codigo_http =
        wp_remote_retrieve_response_code(
            $response
        );

    if ( $codigo_http !== 200 ) {
        return false;
    }


    /*
     * Decodificar respuesta.
     */
    $respuesta = json_decode(
        wp_remote_retrieve_body(
            $response
        ),
        true
    );


    /*
     * Validar estructura del embedding.
     */
    if (
        empty(
            $respuesta['data'][0]['embedding']
        )
        || ! is_array(
            $respuesta['data'][0]['embedding']
        )
    ) {
        return false;
    }


    $embedding = array_map(
        'floatval',
        $respuesta['data'][0]['embedding']
    );


    /*
     * Nuestra arquitectura semántica utiliza
     * vectores de 768 dimensiones.
     */
    if ( count( $embedding ) !== 768 ) {
        return false;
    }


    return $embedding;
}