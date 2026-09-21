<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * Consulta de texto mediante OpenAI.
 *
 * Devuelve la misma estructura que utiliza Gemini:
 *
 * busqueda_corregida
 * producto
 * marca
 * modelo
 * caracteristicas
 * precio_minimo
 * precio_maximo
 */

function compuciber_consultar_openai_texto(
    $busqueda
) {

    $api_key = get_option(
        'compuciber_openai_api_key',
        ''
    );

    if ( empty( $api_key ) ) {
        error_log(
            'OPENAI: API key no configurada.'
        );

        return false;
    }

    $busqueda = sanitize_text_field(
        $busqueda
    );

    if ( empty( $busqueda ) ) {
        return false;
    }


    /*
     * Modelo configurable desde el panel.
     */

    $modelo = get_option(
        'compuciber_modelo_ia',
        'gpt-5.6'
    );


    /*
     * Instrucciones para OpenAI.
     */

    $instrucciones = '
Eres el motor de interpretación de búsquedas
de una tienda WooCommerce.

Tu trabajo NO es inventar productos.

Debes interpretar la consulta del usuario y
extraer información útil para buscar productos
reales dentro del catálogo de WooCommerce.

Devuelve exclusivamente un objeto JSON.

Campos obligatorios:

busqueda_corregida:
La búsqueda corregida y normalizada.

producto:
Tipo de producto solicitado.

marca:
Marca solicitada, si existe.

modelo:
Modelo solicitado, si existe.

caracteristicas:
Lista de características técnicas o
descriptivas solicitadas.

precio_minimo:
Precio mínimo solicitado.
Si no existe, devuelve "".

precio_maximo:
Precio máximo solicitado.
Si no existe, devuelve "".

Ejemplos:

Consulta:
"laptp lenovo gamer"

Respuesta:
{
  "busqueda_corregida": "laptop lenovo gamer",
  "producto": "laptop",
  "marca": "lenovo",
  "modelo": "",
  "caracteristicas": ["gamer"],
  "precio_minimo": "",
  "precio_maximo": ""
}

Consulta:
"mause logitec inalambrico"

Respuesta:
{
  "busqueda_corregida": "mouse logitech inalámbrico",
  "producto": "mouse",
  "marca": "logitech",
  "modelo": "",
  "caracteristicas": ["inalámbrico"],
  "precio_minimo": "",
  "precio_maximo": ""
}

Consulta:
"laptop gamer lenovo menos de 2500"

Respuesta:
{
  "busqueda_corregida": "laptop gamer lenovo",
  "producto": "laptop",
  "marca": "lenovo",
  "modelo": "",
  "caracteristicas": ["gamer"],
  "precio_minimo": "",
  "precio_maximo": "2500"
}

Consulta:
"laptop con 512GB SSD"

Respuesta:
{
  "busqueda_corregida": "laptop con 512GB SSD",
  "producto": "laptop",
  "marca": "",
  "modelo": "",
  "caracteristicas": ["512GB SSD"],
  "precio_minimo": "",
  "precio_maximo": ""
}

Consulta del usuario:
' . $busqueda;


    /*
     * Petición a la API de OpenAI.
     */

    $respuesta = wp_remote_post(
        'https://api.openai.com/v1/responses',
        array(
            'timeout' => 30,

            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),

            'body' => wp_json_encode(
                array(
                    'model' => $modelo,

                    'instructions' =>
                        'Interpreta consultas de productos
                        para un buscador WooCommerce.
                        Devuelve solamente JSON válido.',

                    'input' => $instrucciones,

                    'text' => array(
                        'format' => array(
                            'type' => 'json_object',
                        ),
                    ),

                    'temperature' => 0,
                )
            ),
        )
    );


    /*
     * Error de WordPress.
     */

    if ( is_wp_error( $respuesta ) ) {

        error_log(
            'OPENAI ERROR: '
            . $respuesta->get_error_message()
        );

        return false;
    }


    /*
     * Código HTTP.
     */

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
            'OPENAI HTTP ERROR '
            . $codigo_http
        );

        error_log(
            'OPENAI RESPONSE: '
            . $cuerpo
        );

        return false;
    }


    /*
     * Se decodifica la respuesta.
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
            'OPENAI: respuesta JSON inválida.'
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
            'OPENAI: no se encontró output_text.'
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
     * Se convierte el JSON generado
     * por OpenAI en un array PHP.
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
            'OPENAI: JSON de interpretación inválido.'
        );

        error_log(
            'OPENAI TEXTO: '
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
            'precio_minimo'      => '',
            'precio_maximo'      => '',
        ),
        $resultado
    );


    /*
     * Normaliza las características.
     */

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


    return $resultado;
}