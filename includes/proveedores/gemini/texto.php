<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_consultar_gemini( $busqueda ) {

    /*
     * API Key configurada desde el panel
     * de administración de WordPress.
     */
    $api_key = get_option(
        'compuciber_gemini_api_key',
        ''
    );
    error_log(
        'CIS GEMINI TEXTO: CLAVE='
        . ( empty( $api_key ) ? 'AUSENTE' : 'PRESENTE' )
    );
    if ( empty( $api_key ) ) {
        return false;
    }


    /*
     * Modelo de texto configurado desde
     * AI Product Search > Configuración.
     *
     * Si no existe una configuración válida,
     * se utiliza el modelo predeterminado.
     */
    $modelo = get_option(
        'compuciber_gemini_modelo_texto',
        'gemini-3.6-flash'
    );

    $modelo = sanitize_text_field(
        (string) $modelo
    );

    if ( empty( $modelo ) ) {
        $modelo = 'gemini-3.6-flash';
    }


$prompt = '
Eres un interprete de consultas para un buscador de productos WooCommerce.
La tienda puede vender cualquier categoria de productos. No presupongas
que es una tienda de tecnologia ni que tiene productos determinados.

Interpreta la consulta del cliente. Debes:
1. Corregir errores ortograficos evidentes respetando la intencion original.
2. Identificar tipo de producto, marca y modelo solo si la consulta lo respalda.
3. Identificar caracteristicas, tallas, colores y finalidades de uso solicitadas.
4. Extraer precios minimos o maximos unicamente si el cliente los menciona.
5. Conservar intactos los SKU, numeros de parte y codigos que puedan ser exactos.
6. No inventar marca, modelo, caracteristicas ni disponibilidad.
7. Dejar campos vacios cuando no tengas suficiente informacion.
8. No asegurar que exista un producto en el catalogo. WooCommerce decidira eso.

Ejemplo de consulta: "camiseta negaa talla M"
Respuesta:
{
  "busqueda_corregida": "camiseta negra talla M",
  "producto": "camiseta",
  "marca": "",
  "modelo": "",
  "caracteristicas": ["negra", "talla M"],
  "precio_minimo": "",
  "precio_maximo": ""
}

IMPORTANTE: responde exclusivamente con JSON valido, sin Markdown.
No agregues texto antes ni despues del JSON.
Los precios se expresan como numeros sin simbolos de moneda.

Formato obligatorio:
{
  "busqueda_corregida": "",
  "producto": "",
  "marca": "",
  "modelo": "",
  "caracteristicas": [],
  "precio_minimo": "",
  "precio_maximo": ""
}

Consulta del cliente:
"' . $busqueda . '"
';


    /*
     * Endpoint dinámico.
     *
     * El modelo ya no está escrito directamente
     * en la URL. Se obtiene de la configuración.
     */
    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode( $modelo )
        . ':generateContent?key='
        . rawurlencode( $api_key );


    $response = wp_remote_post(
        $url,
        array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(
                array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array(
                                    'text' => $prompt,
                                ),
                            ),
                        ),
                    ),
                )
            ),
        )
    );

    // Diagnóstico temporal del proveedor Gemini.

    if ( is_wp_error( $response ) ) {

        error_log(
            'CIS GEMINI TEXTO: WP_ERROR='
            . sanitize_key( $response->get_error_code() )
        );

    } else {

        $http_diagnostico = (int)
            wp_remote_retrieve_response_code( $response );

        $datos_diagnostico = json_decode(
            wp_remote_retrieve_body( $response ),
            true
        );

        $estado_diagnostico = 'SIN_ESTADO';

        if (
            is_array( $datos_diagnostico )
            && isset( $datos_diagnostico['error']['status'] )
            && is_string( $datos_diagnostico['error']['status'] )
        ) {

            $estado_diagnostico = sanitize_text_field(
                $datos_diagnostico['error']['status']
            );
        }

        error_log(
            'CIS GEMINI TEXTO: HTTP='
            . $http_diagnostico
            . ' | ESTADO='
            . $estado_diagnostico
        );
    }

    if ( is_wp_error( $response ) ) {
        return false;
    }


    $codigo_http =
        wp_remote_retrieve_response_code(
            $response
        );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

        $datos_diagnostico = json_decode(
            wp_remote_retrieve_body( $response ),
            true
        );

        $estado_error = 'sin_estado';

        if (
            is_array( $datos_diagnostico )
            && isset(
                $datos_diagnostico['error']['status']
            )
        ) {

            $estado_error = sanitize_text_field(
                (string) $datos_diagnostico['error']['status']
            );
        }

        error_log(
            'CIS GEMINI HTTP: '
            . absint( $codigo_http )
            . ' | ESTADO: '
            . $estado_error
        );
    }        

    if (
        $codigo_http < 200
        || $codigo_http >= 300
    ) {
        return false;
    }


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