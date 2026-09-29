<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'interfaz.php';
require_once plugin_dir_path( __FILE__ ) . 'openai/texto.php';
require_once plugin_dir_path( __FILE__ ) . 'openai/imagen.php';


/**
 * Proveedor OpenAI.
 */
class Compuciber_OpenAIProvider
    implements Compuciber_AIProviderInterface {

    /**
     * Consulta de texto mediante OpenAI.
     */
    public function consultar_texto( $busqueda ) {

        return compuciber_consultar_openai_texto(
            $busqueda
        );
    }

    /**
     * Consulta mediante imagen usando OpenAI.
     */
    public function consultar_imagen(
        $imagen_base64,
        $mime_type
    ) {

        return compuciber_consultar_openai_imagen_api(
            $imagen_base64,
            $mime_type
        );
    }

    /**
     * Generación de embeddings mediante OpenAI.
     *
     * La implementación completa se añadirá
     * al preparar el proveedor OpenAI.
     */
    public function generar_embedding(
        $texto,
        $tipo = 'producto'
    ) {

        return false;
    }    
}