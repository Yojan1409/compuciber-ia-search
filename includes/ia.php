<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/proveedores/gemini.php';
require_once __DIR__ . '/proveedores/openai.php';


/*
 * ==========================================================
 * IA - BÚSQUEDA DE TEXTO
 * ==========================================================
 */

function compuciber_consultar_ia( $busqueda ) {

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    switch ( $proveedor ) {

        case 'openai':

            $ai_provider =
                new Compuciber_OpenAIProvider();

            break;


        case 'gemini':

        default:

            $ai_provider =
                new Compuciber_GeminiProvider();

            break;
    }

    return $ai_provider->consultar_texto(
        $busqueda
    );
}


/*
 * ==========================================================
 * IA - BÚSQUEDA POR IMAGEN
 * ==========================================================
 */

function compuciber_consultar_ia_imagen(
    $imagen_base64,
    $mime_type
) {

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    switch ( $proveedor ) {

        case 'openai':

            $ai_provider =
                new Compuciber_OpenAIProvider();

            break;


        case 'gemini':

        default:

            $ai_provider =
                new Compuciber_GeminiProvider();

            break;
    }

    return $ai_provider->consultar_imagen(
        $imagen_base64,
        $mime_type
    );
}