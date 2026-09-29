<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once plugin_dir_path( __FILE__ )
    . 'interfaz.php';

require_once plugin_dir_path( __FILE__ )
    . 'openai/texto.php';

require_once plugin_dir_path( __FILE__ )
    . 'openai/imagen.php';

require_once plugin_dir_path( __FILE__ )
    . 'openai/embeddings.php';


class Compuciber_OpenAIProvider
    implements Compuciber_AIProviderInterface {

    /**
     * Consulta de texto mediante OpenAI.
     *
     * @param string $busqueda
     *
     * @return mixed
     */
    public function consultar_texto(
        $busqueda
    ) {

        return compuciber_consultar_openai(
            $busqueda
        );
    }


    /**
     * Análisis de imagen mediante OpenAI.
     *
     * @param string $imagen_base64
     * @param string $mime_type
     *
     * @return mixed
     */
    public function consultar_imagen(
        $imagen_base64,
        $mime_type
    ) {

        return compuciber_consultar_imagen_openai(
            $imagen_base64,
            $mime_type
        );
    }


    /**
     * Generación de embeddings mediante OpenAI.
     *
     * @param string $texto
     * @param string $tipo
     *
     * @return array|false
     */
    public function generar_embedding(
        $texto,
        $tipo = 'producto'
    ) {

        return compuciber_generar_embedding_openai(
            $texto,
            $tipo
        );
    }
}