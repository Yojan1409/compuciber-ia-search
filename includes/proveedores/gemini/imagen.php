<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_consultar_gemini_imagen(
    $imagen_base64,
    $mime_type
) {

    $api_key = get_option(
        'compuciber_gemini_api_key',
        ''
    );

    $modelo = get_option(
        'compuciber_gemini_modelo_multimodal',
        'gemini-3.6-flash'
    );

    $modelo = sanitize_text_field(
        (string) $modelo
    );

    if ( empty( $modelo ) ) {
        $modelo = 'gemini-3.6-flash';
    }    


    if ( empty( $api_key ) ) {

        error_log(
            'COMPuciber Gemini Imagen: API Key no configurada.'
        );

        return array(
            'error' => 'api_key',
            'mensaje' => 'El servicio de inteligencia artificial no está configurado.'
        );

    }


    if (
        empty( $imagen_base64 )
        || empty( $mime_type )
    ) {

        return array(
            'error' => 'imagen',
            'mensaje' => 'No se recibió una imagen válida.'
        );

    }

    $idioma_tienda = sanitize_text_field(
            (string) get_locale()
        );
    $prompt = '
    Eres un interprete visual para el buscador de una tienda WooCommerce.

    Idioma configurado de la tienda: ' . $idioma_tienda . '.

    La tienda puede vender ropa, alimentos, tecnologia, muebles u otros productos.
    No presupongas que el catalogo contiene un producto concreto.

    Analiza solo lo que realmente se aprecia en la imagen.

    Identifica el tipo principal y especifico de producto, usando una denominacion
    habitual en el idioma de la tienda. No mezcles color o acabados en producto:
    esos detalles van en caracteristicas.

    Examina logotipos, etiquetas y texto visibles. Solo escribe una marca o modelo
    cuando exista evidencia visual suficiente; de lo contrario, deja el campo vacio.
    Nunca deduzcas una marca por el aspecto general del producto.

    Genera busqueda_corregida priorizando el tipo de producto y, si estan
    identificados con seguridad, la marca y el modelo. Conserva la marca visible
    en la consulta; las alternativas sin esa marca las buscara el motor aparte.
    Los colores y otros rasgos secundarios no deben sustituir al tipo o la marca.

    No deduzcas tallas, capacidades, compatibilidades ni materiales que no se aprecien.

    Si no es posible identificar el tipo de producto,
    deja producto y busqueda_corregida vacios.

    Nunca afirmes que el producto fotografiado existe en WooCommerce.

    Devuelve unicamente un objeto JSON valido con estos campos y tipos:

    {
    "busqueda_corregida": "",
    "producto": "",
    "marca": "",
    "modelo": "",
    "caracteristicas": [],
    "palabras_clave": []
    }

    busqueda_corregida: texto corto que sirva para buscar en WooCommerce.

    caracteristicas y palabras_clave: arreglos de cadenas de texto.

    No incluyas comentarios, Markdown ni explicaciones fuera del JSON.
    ';


    $request_body = array(
        'contents' => array(
            array(
                'parts' => array(

                    array(
                        'text' => $prompt,
                    ),

                    array(
                        'inline_data' => array(
                            'mime_type' => $mime_type,
                            'data'      => $imagen_base64,
                        ),
                    ),

                ),
            ),
        ),
    );


    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode( $modelo )
        . ':generateContent?key='
        . rawurlencode( $api_key );


    /*
     * ======================================================
     * REINTENTOS
     * ======================================================
     *
     * Gemini puede devolver HTTP 503 cuando el modelo
     * está temporalmente saturado.
     *
     * Se intará el llamado 3 veces.
     */

    $max_intentos = 3;


    for (
        $intento = 1;
        $intento <= $max_intentos;
        $intento++
    ) {


        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(
                    $request_body
                ),
            )
        );

        if ( is_wp_error( $response ) ) {

            error_log(
                'COMPuciber Gemini Imagen - Error WP: '
                . $response->get_error_message()
            );

            return false;
        }


        $codigo =
            wp_remote_retrieve_response_code(
                $response
            );

        if ( $codigo === 503 ) {

            error_log(
                'COMPuciber Gemini Imagen - Intento '
                . $intento
                . ' recibió HTTP 503.'
            );


            if ( $intento < $max_intentos ) {

                sleep( 3 );

                continue;
            }


            error_log(
                'COMPuciber Gemini Imagen - '
                . 'Gemini continúa no disponible después '
                . $max_intentos
                . ' intentos.'
            );

            return false;
        }

        if (
            $codigo < 200
            || $codigo >= 300
        ) {

            error_log(
                'COMPuciber Gemini Imagen - HTTP: '
                . $codigo
            );

            return false;
        }


        /*
         * ==================================================
         * RESPUESTA CORRECTA
         * ==================================================
         */

        $body = json_decode(
            wp_remote_retrieve_body( $response ),
            true
        );


        if (
            empty(
                $body['candidates'][0]['content']['parts'][0]['text']
            )
        ) {

            return false;
        }


        $texto =
            $body['candidates'][0]['content']['parts'][0]['text'];


        $texto = trim( $texto );

        $texto = preg_replace(
            '/^```json\s*/i',
            '',
            $texto
        );


        $texto = preg_replace(
            '/\s*```$/',
            '',
            $texto
        );


        $resultado = json_decode(
            $texto,
            true
        );

        if ( ! is_array( $resultado ) ) {

            error_log(
                'COMPuciber Gemini Imagen: Respuesta JSON inválida.'
            );

            return array(
                'error' => 'respuesta',
                'mensaje' => 'La IA no devolvió una respuesta válida.'
            );

        }

        error_log(
            'COMPuciber Imagen IA: ' . print_r($resultado, true)
        );   
        return $resultado;
    }


    return array(
        'error' => 'desconocido',
        'mensaje' => 'No fue posible analizar la imagen.'
    );
}