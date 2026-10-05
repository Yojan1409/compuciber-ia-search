<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/proveedores/gemini.php';
require_once __DIR__ . '/proveedores/openai.php';


/**
 * Obtiene la instancia del proveedor de IA seleccionado.
 *
 * Este es el punto central para registrar proveedores.
 * Los proveedores deben implementar
 * Compuciber_AIProviderInterface.
 *
 * @return Compuciber_AIProviderInterface
 */
function compuciber_obtener_proveedor_ia() {

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    $proveedor = sanitize_key(
        (string) $proveedor
    );

    switch ( $proveedor ) {

        case 'openai':

            return new Compuciber_OpenAIProvider();


        case 'gemini':

        default:

            return new Compuciber_GeminiProvider();
    }
}


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

    $ai_provider =
        compuciber_obtener_proveedor_ia();

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

    $ai_provider =
        compuciber_obtener_proveedor_ia();

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

    $ai_provider =
        compuciber_obtener_proveedor_ia();

    return $ai_provider->generar_embedding(
        $texto,
        $tipo
    );
}