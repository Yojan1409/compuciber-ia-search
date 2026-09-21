<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'interfaz.php';
require_once plugin_dir_path( __FILE__ ) . 'gemini/texto.php';
require_once plugin_dir_path( __FILE__ ) . 'gemini/imagen.php';


/**
 * Proveedor Gemini.
 */
class Compuciber_GeminiProvider
    implements Compuciber_AIProviderInterface {

    /**
     * Consulta de texto mediante Gemini.
     */
    public function consultar_texto( $busqueda ) {

        return compuciber_consultar_gemini(
            $busqueda
        );
    }

    /**
     * Consulta mediante imagen usando Gemini.
     */
    public function consultar_imagen(
        $imagen_base64,
        $mime_type
    ) {

        return compuciber_consultar_gemini_imagen(
            $imagen_base64,
            $mime_type
        );
    }
}