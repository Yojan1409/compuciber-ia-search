<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Consulta la API de OpenAI para interpretar
 * una imagen utilizada en la búsqueda visual.
 *
 * La IA únicamente interpreta la imagen.
 * WooCommerce continúa siendo la fuente
 * de verdad para productos, precios y stock.
 *
 * @param string $imagen_base64 Imagen codificada en Base64.
 * @param string $mime_type     Tipo MIME de la imagen.
 *
 * @return array|false
 */
function compuciber_consultar_openai_imagen_api(
    $imagen_base64,
    $mime_type
) {

    /*
     * API Key configurada por el propietario
     * de la instalación.
     */
    $api_key = get_option(
        'compuciber_openai_api_key',
        ''
    );

    if ( empty( $api_key ) ) {
        return false;
    }


    /*
     * Validar entrada.
     */
    $imagen_base64 = trim(
        (string) $imagen_base64
    );

    $mime_type = strtolower(
        trim(
            (string) $mime_type
        )
    );

    if (
        $imagen_base64 === ''
        || $mime_type === ''
    ) {
        return false;
    }


    /*
     * Solo permitimos formatos de imagen
     * compatibles con la búsqueda visual.
     */
    $mime_permitidos = array(
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    );

    if (
        ! in_array(
            $mime_type,
            $mime_permitidos,
            true
        )
    ) {
        return false;
    }


    /*
     * Modelo multimodal configurable.
     *
     * Si el administrador no especifica uno,
     * utilizamos el modelo predeterminado
     * definido por el plugin.
     */
    $modelo = trim(
        (string) get_option(
            'compuciber_modelo_multimodal',
            ''
        )
    );

    if ( $modelo === '' ) {
        $modelo = 'gpt-5.6';
    }


    /*
     * Instrucciones del intérprete visual.
     */
    $instrucciones = '
Eres el sistema de interpretación visual
de un buscador de productos WooCommerce.

Analiza únicamente la imagen proporcionada.

Tu función es interpretar visualmente el producto
para generar información útil para el motor
de búsqueda del catálogo.

No inventes productos ni información que no pueda
deducirse razonablemente de la imagen.

WooCommerce es la fuente de verdad para:
productos, SKU, precios, stock y disponibilidad.

Debes identificar:

- tipo de producto;
- marca, solamente cuando exista suficiente evidencia;
- modelo, solamente cuando exista suficiente evidencia;
- características visibles o razonablemente identificables;
- términos útiles para buscar productos similares.

Si una marca o modelo no puede determinarse
con suficiente confianza, utiliza una cadena vacía.

No inventes especificaciones técnicas que no puedan
observarse o deducirse razonablemente.
';


    /*
     * Construir Data URL.
     */
    $imagen_data_url =
        'data:'
        . $mime_type
        . ';base64,'
        . $imagen_base64;


    /*
     * Esquema estructurado esperado.
     */
    $schema = array(

        'type' => 'object',

        'properties' => array(

            'busqueda_corregida' => array(
                'type' => 'string',
            ),

            'producto' => array(
                'type' => 'string',
            ),

            'marca' => array(
                'type' => 'string',
            ),

            'modelo' => array(
                'type' => 'string',
            ),

            'caracteristicas' => array(
                'type'  => 'array',
                'items' => array(
                    'type' => 'string',
                ),
            ),

            'palabras_clave' => array(
                'type'  => 'array',
                'items' => array(
                    'type' => 'string',
                ),
            ),
        ),

        'required' => array(
            'busqueda_corregida',
            'producto',
            'marca',
            'modelo',
            'caracteristicas',
            'palabras_clave',
        ),

        'additionalProperties' => false,
    );


    /*
     * Petición mediante Responses API.
     */
    $respuesta = wp_remote_post(
        'https://api.openai.com/v1/responses',
        array(

            'timeout' => 60,

            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),

            'body' => wp_json_encode(
                array(

                    'model' =>
                        $modelo,

                    'instructions' =>
                        $instrucciones,

                    'input' => array(

                        array(

                            'role' => 'user',

                            'content' => array(

                                array(
                                    'type' =>
                                        'input_text',

                                    'text' =>
                                        'Analiza esta imagen de producto '
                                        . 'y genera los términos necesarios '
                                        . 'para buscar productos relacionados '
                                        . 'en WooCommerce.',
                                ),

                                array(
                                    'type' =>
                                        'input_image',

                                    'image_url' =>
                                        $imagen_data_url,

                                    'detail' =>
                                        'auto',
                                ),
                            ),
                        ),
                    ),

                    /*
                     * Structured Outputs.
                     */
                    'text' => array(

                        'format' => array(

                            'type' =>
                                'json_schema',

                            'name' =>
                                'compuciber_busqueda_visual',

                            'strict' =>
                                true,

                            'schema' =>
                                $schema,
                        ),
                    ),
                )
            ),
        )
    );


    /*
     * Error de transporte.
     */
    if ( is_wp_error( $respuesta ) ) {
        return false;
    }


    /*
     * Validar respuesta HTTP.
     */
    $codigo_http =
        wp_remote_retrieve_response_code(
            $respuesta
        );

    if (
        $codigo_http < 200
        || $codigo_http >= 300
    ) {
        return false;
    }


    /*
     * Decodificar respuesta.
     */
    $datos = json_decode(
        wp_remote_retrieve_body(
            $respuesta
        ),
        true
    );

    if ( ! is_array( $datos ) ) {
        return false;
    }


    /*
     * Localizar output_text.
     *
     * No dependemos únicamente de una posición
     * concreta dentro del array output.
     */
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
                empty( $elemento['content'] )
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
                    isset( $contenido['type'] )
                    && $contenido['type']
                        === 'output_text'
                    && isset( $contenido['text'] )
                ) {

                    $texto =
                        (string) $contenido['text'];

                    break 2;
                }
            }
        }
    }


    /*
     * Fallback por compatibilidad.
     */
    if (
        $texto === ''
        && ! empty( $datos['output_text'] )
        && is_string(
            $datos['output_text']
        )
    ) {

        $texto =
            $datos['output_text'];
    }


    $texto = trim(
        $texto
    );

    if ( $texto === '' ) {
        return false;
    }


    /*
     * Structured Outputs debería entregar
     * JSON directamente.
     *
     * Conservamos esta limpieza como protección
     * adicional ante respuestas inesperadas.
     */
    $texto = preg_replace(
        '/^```(?:json)?\s*/i',
        '',
        $texto
    );

    $texto = preg_replace(
        '/\s*```$/',
        '',
        $texto
    );

    $texto = trim(
        $texto
    );


    /*
     * Convertir respuesta a array PHP.
     */
    $resultado = json_decode(
        $texto,
        true
    );

    if ( ! is_array( $resultado ) ) {
        return false;
    }


    /*
     * Estructura defensiva.
     */
    $resultado = array_merge(
        array(
            'busqueda_corregida' => '',
            'producto'           => '',
            'marca'              => '',
            'modelo'             => '',
            'caracteristicas'    => array(),
            'palabras_clave'     => array(),
        ),
        $resultado
    );


    /*
     * Normalizar campos simples.
     */
    $resultado['busqueda_corregida'] =
        trim(
            (string)
            $resultado['busqueda_corregida']
        );

    $resultado['producto'] =
        trim(
            (string)
            $resultado['producto']
        );

    $resultado['marca'] =
        trim(
            (string)
            $resultado['marca']
        );

    $resultado['modelo'] =
        trim(
            (string)
            $resultado['modelo']
        );


    /*
     * Normalizar listas.
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


    if (
        ! is_array(
            $resultado['palabras_clave']
        )
    ) {

        $resultado['palabras_clave'] =
            array(
                (string)
                $resultado['palabras_clave']
            );
    }


    /*
     * Limpiar listas.
     */
    $resultado['caracteristicas'] =
        array_values(
            array_filter(
                array_map(
                    'trim',
                    array_map(
                        'strval',
                        $resultado['caracteristicas']
                    )
                )
            )
        );


    $resultado['palabras_clave'] =
        array_values(
            array_filter(
                array_map(
                    'trim',
                    array_map(
                        'strval',
                        $resultado['palabras_clave']
                    )
                )
            )
        );


    return $resultado;
}


/**
 * Wrapper utilizado por el proveedor OpenAI.
 *
 * Mantiene una interfaz uniforme entre
 * Gemini y OpenAI.
 *
 * @param string $imagen_base64
 * @param string $mime_type
 *
 * @return array|false
 */
function compuciber_consultar_imagen_openai(
    $imagen_base64,
    $mime_type
) {

    return compuciber_consultar_openai_imagen_api(
        $imagen_base64,
        $mime_type
    );
}