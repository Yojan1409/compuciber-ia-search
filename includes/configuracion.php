<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * ==========================================================
 * MENÚ DEL PLUGIN
 * ==========================================================
 */

function compuciber_ai_search_menu() {

    add_menu_page(
        'AI Product Search',
        'AI Product Search',
        'manage_options',
        'compuciber-ai-search',
        'compuciber_ai_search_dashboard',
        'dashicons-search',
        56
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'compuciber-ai-search',
        'compuciber_ai_search_dashboard'
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Configuración',
        'Configuración',
        'manage_options',
        'compuciber-ai-search-configuracion',
        'compuciber_ai_search_configuracion'
    );

    add_submenu_page(
        'compuciber-ai-search',
        'Sinónimos',
        'Sinónimos',
        'manage_options',
        'compuciber-ai-search-sinonimos',
        'compuciber_pagina_sinonimos'
    );
}

add_action(
    'admin_menu',
    'compuciber_ai_search_menu'
);


/*
 * ==========================================================
 * REGISTRO DE OPCIONES
 * ==========================================================
 */

function compuciber_ai_search_registrar_opciones() {

    $opciones = array(

        'compuciber_proveedor_ia',

        'compuciber_gemini_api_key',

        'compuciber_openai_api_key',

        'compuciber_modelo_ia',

        'compuciber_modelo_embeddings',

        'compuciber_modelo_multimodal',

        'compuciber_modelo_voz',

        'compuciber_autocompletado',

        'compuciber_correccion',

        'compuciber_busqueda_semantica',

        'compuciber_busqueda_voz',

        'compuciber_busqueda_imagen',

        'compuciber_estadisticas',

    );

    foreach ( $opciones as $opcion ) {

        register_setting(
            'compuciber_ai_search_settings_group',
            $opcion,
            array(
                'sanitize_callback' => 'sanitize_text_field',
            )
        );
    }
}

add_action(
    'admin_init',
    'compuciber_ai_search_registrar_opciones'
);