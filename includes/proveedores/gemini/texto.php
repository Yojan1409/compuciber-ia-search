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
Eres el asistente de búsqueda de una tienda de tecnología llamada Compuciber.

Analiza la búsqueda escrita por el cliente y determina qué producto está buscando.

Debes:
1. Corregir errores ortográficos o de escritura.
2. Identificar el tipo de producto.
3. Identificar la marca si el cliente la menciona.
4. Identificar características técnicas o preferencias.
5. Identificar un precio máximo o mínimo si el cliente lo menciona.
6. No inventar características que el cliente no haya solicitado.
7. Mantener vacíos los campos que no se puedan determinar.

Ejemplos:

Búsqueda: "laptp lenovo gamer"
Resultado:
{
  "busqueda_corregida": "laptop lenovo gamer",
  "producto": "laptop",
  "marca": "lenovo",
  "caracteristicas": ["gamer"],
  "precio_minimo": "",
  "precio_maximo": ""
}

Búsqueda: "mause logitec inalambrico"
Resultado:
{
  "busqueda_corregida": "mouse logitech inalámbrico",
  "producto": "mouse",
  "marca": "logitech",
  "caracteristicas": ["inalámbrico"],
  "precio_minimo": "",
  "precio_maximo": ""
}

Búsqueda: "laptop gamer lenovo menos de 2500 soles"
Resultado:
{
  "busqueda_corregida": "laptop gamer lenovo menos de 2500 soles",
  "producto": "laptop",
  "marca": "lenovo",
  "caracteristicas": ["gamer"],
  "precio_minimo": "",
  "precio_maximo": "2500"
}

IMPORTANTE:
- Responde únicamente con JSON válido.
- No escribas explicaciones.
- No utilices Markdown.
- Los precios deben ser números sin símbolos de moneda.
- No inventes información.

Formato obligatorio:

{
  "busqueda_corregida": "...",
  "producto": "...",
  "marca": "...",
  "caracteristicas": [],
  "precio_minimo": "",
  "precio_maximo": ""
}

Búsqueda del cliente:
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


    if ( is_wp_error( $response ) ) {
        return false;
    }


    $codigo_http =
        wp_remote_retrieve_response_code(
            $response
        );

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