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
        return false;
    }


    if (
        empty( $imagen_base64 )
        || empty( $mime_type )
    ) {
        return false;
    }


    $prompt = '
Eres el asistente de búsqueda visual de una tienda de tecnología llamada Compuciber.

Analiza cuidadosamente la imagen proporcionada por el cliente.

Tu objetivo NO es inventar un producto exacto.

Debes identificar, solamente cuando sea posible:

1. Tipo de producto.
2. Marca visible.
3. Modelo o número de modelo visible.
4. Características técnicas visibles o claramente identificables.
5. Palabras clave útiles para buscar productos similares.

IMPORTANTE:
- No inventes información.
- Si una marca no es visible, deja el campo vacío.
- Si un modelo no es visible, deja el campo vacío.
- Si una característica no puede determinarse con seguridad, no la inventes.
- La imagen puede mostrar un producto real o solamente un producto similar.
- La respuesta será utilizada para buscar productos EXISTENTES dentro de WooCommerce.
- Nunca afirmes que un producto pertenece al catálogo.
- WooCommerce será la fuente de verdad.

Ejemplo:

{
  "busqueda_corregida": "impresora térmica",
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
            return false;
        }


        return $resultado;
    }


    return false;
}