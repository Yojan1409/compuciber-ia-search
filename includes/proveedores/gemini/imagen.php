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


    $prompt = '
    Eres el asistente de búsqueda visual de una tienda de tecnología llamada Compuciber.

    Analiza cuidadosamente la imagen proporcionada por el cliente.

    Tu objetivo es generar una búsqueda útil para encontrar productos existentes dentro de una tienda WooCommerce.

    Debes identificar, solamente cuando sea posible:

    1. Tipo de producto.
    2. Marca visible.
    3. Modelo o número de modelo visible.
    4. Características técnicas visibles o claramente identificables.
    5. Palabras clave relacionadas para encontrar productos similares.

    REGLA PRINCIPAL PARA "busqueda_corregida":

    - Prioriza siempre el tipo de producto y sus características.
    - La marca puede incluirse únicamente si es claramente visible.
    - Una marca detectada NO debe convertirse en un filtro obligatorio si puede reducir demasiado los resultados.
    - Si la marca no es necesaria para encontrar productos equivalentes, genera la búsqueda usando producto + características.
    - La finalidad es encontrar coincidencias dentro del catálogo WooCommerce, incluso cuando la marca del producto de la imagen no exista en la tienda.

    IMPORTANTE:
    - No inventes información.
    - Si una marca no es visible, deja el campo vacío.
    - Si un modelo no es visible, deja el campo vacío.
    - Si una característica no puede determinarse con seguridad, no la inventes.
    - La imagen puede corresponder a un producto real, un producto similar o una imagen externa.
    - Nunca afirmes que un producto pertenece al catálogo.
    - WooCommerce es la única fuente de verdad para los productos disponibles.

    Ejemplos:

    Caso con marca visible:
    {
    "busqueda_corregida": "teclado gamer RGB retroiluminado",
    "producto": "teclado gamer",
    "marca": "SEISA",
    "modelo": "",
    "caracteristicas": ["RGB", "USB", "retroiluminado"],
    "palabras_clave": ["teclado mecánico", "teclado gamer"]
    }

    Caso sin marca:
    {
    "busqueda_corregida": "impresora térmica 80 mm",
    "producto": "impresora térmica",
    "marca": "",
    "modelo": "",
    "caracteristicas": ["POS", "80 mm"],
    "palabras_clave": ["ticketera", "impresora de tickets"]
    }

    Responde ÚNICAMENTE con JSON válido.

    Formato obligatorio:

    {
    "busqueda_corregida": "",
    "producto": "",
    "marca": "",
    "modelo": "",
    "caracteristicas": [],
    "palabras_clave": []
    }
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
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key='
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