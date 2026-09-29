<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/proveedores/gemini.php';
require_once __DIR__ . '/proveedores/openai.php';

/**
 * Determina si una búsqueda de texto necesita
 * interpretación semántica mediante IA.
 */
function compuciber_busqueda_necesita_ia( $busqueda ) {

    $busqueda = compuciber_normalizar_texto(
        $busqueda
    );

    if ( empty( $busqueda ) ) {
        return false;
    }

    $patrones_semanticos = array(
        '/\bquiero\b/',
        '/\bnecesito\b/',
        '/\bbusco\b/',
        '/\brecomiend\w*\b/',
        '/\bme sirve\b/',
        '/\bme conviene\b/',
        '/\bpara trabajar\b/',
        '/\bpara estudiar\b/',
        '/\bpara jugar\b/',
        '/\bpara oficina\b/',
        '/\bpara diseño\b/',
        '/\bpara diseno\b/',
        '/\bpara casa\b/',
        '/\bpara negocio\b/',
        '/\bpara gaming\b/',
        '/\bque tenga\b/',
        '/\bque sea\b/',
        '/\balgo para\b/',
        '/\bproducto para\b/',
    );

    foreach ( $patrones_semanticos as $patron ) {

        if ( preg_match( $patron, $busqueda ) ) {
            return true;
        }
    }

    return false;
}
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

/**
 * Genera un embedding utilizando
 * el proveedor de IA seleccionado.
 *
 * @param string $texto Texto a representar.
 * @param string $tipo  producto o consulta.
 *
 * @return array|false
 */
function compuciber_generar_embedding(
    $texto,
    $tipo = 'producto'
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

    return $ai_provider->generar_embedding(
        $texto,
        $tipo
    );
}

