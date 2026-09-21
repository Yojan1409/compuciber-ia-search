<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * Consulta de imagen mediante OpenAI.
 *
 * Recibe:
 *
 * $imagen_base64
 * $mime_type
 *
 * Devuelve:
 *
 * busqueda_corregida
 * producto
 * marca
 * modelo
 * caracteristicas
 * palabras_clave
 */

function compuciber_consultar_openai_imagen_api(
    $imagen_base64,
    $mime_type
) {

    $api_key = get_option(
        'compuciber_openai_api_key',
        ''
    );

    if ( empty( $api_key ) ) {

        error_log(
            'OPENAI IMAGEN: API key no configurada.'
        );

        return false;
    }


    if (
        empty( $imagen_base64 )
        || empty( $mime_type )
    ) {

        error_log(
            'OPENAI IMAGEN: datos de imagen vacíos.'
        );

        return false;
    }


    /*
     * Modelo configurable desde el panel.
     */

    $modelo = get_option(
        'compuciber_modelo_multimodal',
        'gpt-5.6'
    );


    /*
     * Instrucciones para interpretar
     * la imagen del producto.
     */

    $instrucciones = '
Eres el sistema de interpretación visual
de un buscador de productos WooCommerce.

Analiza la imagen proporcionada.

Tu trabajo NO es inventar productos.

Debes identificar solamente información
que pueda observarse razonablemente en la imagen
y convertirla en información útil para buscar
productos reales dentro del catálogo WooCommerce.

Devuelve exclusivamente un objeto JSON válido.

Campos obligatorios:

busqueda_corregida:
Una búsqueda corta y útil basada en el producto
observado.

producto:
Tipo de producto observado.

marca:
Marca visible o identificable con suficiente
confianza. Si no puede determinarse, devuelve "".

modelo:
Modelo visible o identificable con suficiente
confianza. Si no puede determinarse, devuelve "".

caracteristicas:
Lista de características visibles o claramente
identificables.

palabras_clave:
Lista de palabras útiles para encontrar productos
similares dentro de WooCommerce.

Ejemplo:

{
  "busqueda_corregida": "laptop lenovo azul",
  "producto": "laptop",
  "marca": "Lenovo",
  "modelo": "",
  "caracteristicas": [
    "Windows 11",
    "teclado numérico",
    "color azul",
    "cámara web integrada"
  ],
  "palabras_clave": [
    "notebook",
    "computadora portátil",
    "laptop lenovo",
    "ideapad"
  ]
}

IMPORTANTE:

No inventes SKU, precios, stock ni productos.

WooCommerce será la fuente de verdad.

La imagen solamente sirve para interpretar
el producto y generar términos de búsqueda.
';


    /*
     * Datos de entrada.
     */

    $imagen_data_url =
        'data:'
        . $mime_type
        . ';base64,'
        . $imagen_base64;


    /*
     * Petición a OpenAI Responses API.
     */

    $respuesta = wp_remote_post(
        'https://api.openai.com/v1/responses',
        array(
            'timeout' => 60,

            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),

            'body' => wp_json_encode(
                array(
                    'model' => $modelo,

                    'instructions' =>
                        $instrucciones,

                    'input' => array(
                        array(
                            'role' => 'user',

                            'content' => array(

                                array(
                                    'type' =>
                                        'input_text',

                                    'text' =>
                                        'Analiza esta imagen '
                                        . 'de producto.',
                                ),

                                array(
                                    'type' =>
                                        'input_image',

                                    'image_url' =>
                                        $imagen_data_url,

                                    'detail' =>
                                        'auto',
                                ),
                            ),
                        ),
                    ),

                    'text' => array(
                        'format' => array(
                            'type' =>
                                'json_object',
                        ),
                    ),

                    'temperature' => 0,
                )
            ),
        )
    );

    if ( is_wp_error( $respuesta ) ) {

        error_log(
            'OPENAI IMAGEN ERROR: '
            . $respuesta->get_error_message()
        );

        return false;
    }

    $codigo_http =
        wp_remote_retrieve_response_code(
            $respuesta
        );

    $cuerpo =
        wp_remote_retrieve_body(
            $respuesta
        );


    if (
        $codigo_http < 200
        || $codigo_http >= 300
    ) {

        error_log(
            'OPENAI IMAGEN HTTP ERROR '
            . $codigo_http
        );

        error_log(
            'OPENAI IMAGEN RESPONSE: '
            . $cuerpo
        );

        return false;
    }


    /*
     * Se obtiene la respuesta.
     */

    $datos =
        json_decode(
            $cuerpo,
            true
        );

    if (
        ! is_array( $datos )
    ) {

        error_log(
            'OPENAI IMAGEN: respuesta JSON inválida.'
        );

        return false;
    }

    $texto = '';

    if (
        ! empty( $datos['output'] )
        && is_array( $datos['output'] )
    ) {

        foreach (
            $datos['output']
            as $elemento
        ) {

            if (
                empty(
                    $elemento['content']
                )
                || ! is_array(
                    $elemento['content']
                )
            ) {
                continue;
            }

            foreach (
                $elemento['content']
                as $contenido
            ) {

                if (
                    isset(
                        $contenido['type']
                    )
                    && $contenido['type']
                        === 'output_text'
                    && isset(
                        $contenido['text']
                    )
                ) {

                    $texto =
                        $contenido['text'];

                    break 2;
                }
            }
        }
    }


    /*
     * Fallback.
     */

    if (
        empty( $texto )
        && isset(
            $datos['output_text']
        )
    ) {

        $texto =
            $datos['output_text'];
    }


    if (
        empty( $texto )
    ) {

        error_log(
            'OPENAI IMAGEN: '
            . 'no se encontró output_text.'
        );

        return false;
    }


    /*
     * Se limpian posibles bloques Markdown.
     */

    $texto =
        trim( $texto );

    $texto =
        preg_replace(
            '/^```(?:json)?\s*/i',
            '',
            $texto
        );

    $texto =
        preg_replace(
            '/\s*```$/',
            '',
            $texto
        );

    $texto =
        trim( $texto );


    /*
     * Se convierte el JSON a array PHP.
     */

    $resultado =
        json_decode(
            $texto,
            true
        );


    if (
        ! is_array( $resultado )
    ) {

        error_log(
            'OPENAI IMAGEN: '
            . 'JSON inválido.'
        );

        error_log(
            'OPENAI IMAGEN TEXTO: '
            . $texto
        );

        return false;
    }

    $resultado = array_merge(
        array(
            'busqueda_corregida' => '',
            'producto'           => '',
            'marca'              => '',
            'modelo'             => '',
            'caracteristicas'    => array(),
            'palabras_clave'     => array(),
        ),
        $resultado
    );

    if (
        ! is_array(
            $resultado['caracteristicas']
        )
    ) {

        $resultado['caracteristicas'] =
            array(
                (string)
                $resultado['caracteristicas']
            );
    }

    if (
        ! is_array(
            $resultado['palabras_clave']
        )
    ) {

        $resultado['palabras_clave'] =
            array(
                (string)
                $resultado['palabras_clave']
            );
    }


    return $resultado;
}