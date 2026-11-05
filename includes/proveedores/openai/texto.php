<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Interpreta una búsqueda de productos
 * mediante OpenAI.
 *
 * OpenAI únicamente interpreta la intención
 * del usuario. WooCommerce continúa siendo
 * la fuente de verdad para productos,
 * precios, stock y disponibilidad.
 *
 * @param string $busqueda Consulta del usuario.
 *
 * @return array|false
 */
function compuciber_consultar_openai_texto(
    $busqueda
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
     * Limpiar consulta.
     */
    $busqueda = sanitize_text_field(
        (string) $busqueda
    );

    $busqueda = trim(
        $busqueda
    );

    if ( $busqueda === '' ) {
        return false;
    }


    /*
     * Modelo de texto configurable.
     */
    $modelo = trim(
        (string) get_option(
            'compuciber_openai_modelo_texto',
            'gpt-5.6'
        )
    );

    if ( $modelo === '' ) {
        $modelo = 'gpt-5.6';
    }


    /*
     * Instrucciones del intérprete.
     *
     * La IA no decide qué productos existen.
     * Solamente transforma lenguaje natural
     * en filtros útiles para el motor local.
     */
    $instrucciones = '
Eres el sistema de interpretación semántica
de un buscador de productos WooCommerce.

Tu trabajo NO es inventar productos.

Debes interpretar la consulta del usuario y
extraer información útil para buscar productos
reales dentro del catálogo WooCommerce.

WooCommerce es la fuente de verdad para:
productos, SKU, precios, stock y disponibilidad.

Debes:

1. Corregir errores evidentes de escritura.
2. Identificar el tipo de producto solicitado.
3. Identificar la marca solamente cuando
   aparezca o pueda determinarse con suficiente
   confianza a partir de la consulta.
4. Identificar el modelo cuando exista.
5. Extraer características técnicas,
   descriptivas o de uso solicitadas.
6. Extraer restricciones de precio cuando existan.

No inventes marcas, modelos, características,
precios, SKU ni productos.

No conviertas preferencias vagas en
especificaciones técnicas inexistentes.

Ejemplos:

Consulta:
"laptp lenovo gamer"

Interpretación esperada:

busqueda_corregida:
"laptop lenovo gamer"

producto:
"laptop"

marca:
"lenovo"

modelo:
""

caracteristicas:
["gamer"]

precio_minimo:
""

precio_maximo:
""


Consulta:
"mause logitec inalambrico"

Interpretación esperada:

busqueda_corregida:
"mouse logitech inalámbrico"

producto:
"mouse"

marca:
"logitech"

modelo:
""

caracteristicas:
["inalámbrico"]

precio_minimo:
""

precio_maximo:
""


Consulta:
"laptop gamer lenovo menos de 2500"

Interpretación esperada:

busqueda_corregida:
"laptop gamer lenovo"

producto:
"laptop"

marca:
"lenovo"

modelo:
""

caracteristicas:
["gamer"]

precio_minimo:
""

precio_maximo:
"2500"


Consulta:
"laptop con 512GB SSD"

Interpretación esperada:

busqueda_corregida:
"laptop con 512GB SSD"

producto:
"laptop"

marca:
""

modelo:
""

caracteristicas:
["512GB SSD"]

precio_minimo:
""

precio_maximo:
""
';


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

            'precio_minimo' => array(
                'type' => 'string',
            ),

            'precio_maximo' => array(
                'type' => 'string',
            ),
        ),

        'required' => array(
            'busqueda_corregida',
            'producto',
            'marca',
            'modelo',
            'caracteristicas',
            'precio_minimo',
            'precio_maximo',
        ),

        'additionalProperties' => false,
    );


    /*
     * Petición mediante Responses API.
     */
    $respuesta = wp_remote_post(
        'https://api.openai.com/v1/responses',
        array(

            'timeout' => 30,

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
                                        'Consulta del usuario: '
                                        . $busqueda,
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
                                'compuciber_interpretacion_busqueda',

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
     * Diagnóstico de errores OpenAI.
     * No registra búsquedas, respuestas completas ni claves API.
     */
    $cis_openai_error = '';

    if ( is_wp_error( $respuesta ) ) {
        $cis_openai_error = 'TRANSPORTE: '
            . sanitize_key( $respuesta->get_error_code() );
    } else {
        $cis_openai_http = (int) wp_remote_retrieve_response_code( $respuesta );

        if ( $cis_openai_http < 200 || $cis_openai_http >= 300 ) {
            $cis_openai_error = 'HTTP ' . absint( $cis_openai_http );

            $cis_openai_datos = json_decode(
                wp_remote_retrieve_body( $respuesta ),
                true
            );

            if (
                is_array( $cis_openai_datos )
                && ! empty( $cis_openai_datos['error']['code'] )
                && is_string( $cis_openai_datos['error']['code'] )
            ) {
                $cis_openai_error .= ' | CODIGO: '
                    . sanitize_key( $cis_openai_datos['error']['code'] );
            }
        }
    }

    if ( $cis_openai_error !== '' && function_exists( 'wc_get_logger' ) ) {
        $cis_openai_log_id = 'cis_openai_log_' . md5( $cis_openai_error );

        if ( false === get_transient( $cis_openai_log_id ) ) {
            wc_get_logger()->error(
                'OpenAI texto: ' . $cis_openai_error,
                array( 'source' => 'compuciber-ai-search' )
            );

            set_transient( $cis_openai_log_id, 1, 5 * MINUTE_IN_SECONDS );
        }
    }


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
     * Diagnóstico de respuestas aceptadas
     * por OpenAI que no pueden interpretarse.
     *
     * No almacena consultas ni credenciales.
     */
    $cis_openai_aviso_respuesta = static function ( $motivo ) use ( $modelo, $codigo_http ) {

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $motivo = sanitize_key( $motivo );

        $clave = 'cis_openai_resp_' . md5(
            $modelo . '|' . $motivo
        );

        if ( false !== get_transient( $clave ) ) {
            return;
        }

        wc_get_logger()->warning(
            sprintf(
                'OpenAI texto: respuesta HTTP %d no utilizable | motivo=%s | modelo=%s',
                absint( $codigo_http ),
                $motivo,
                sanitize_text_field( $modelo )
            ),
            array(
                'source' => 'compuciber-ai-search'
            )
        );

        set_transient(
            $clave,
            1,
            10 * MINUTE_IN_SECONDS
        );
    };

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

        $cis_openai_aviso_respuesta(
            'cuerpo_json_invalido'
        );

        return false;
    }


    /*
     * Localizar output_text dentro
     * de la respuesta.
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

        $cis_openai_aviso_respuesta(
            'sin_texto_en_respuesta'
        );

        return false;
    }


    /*
     * Structured Outputs debería devolver
     * directamente JSON.
     *
     * Esta limpieza queda como protección
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
     * Convertir JSON a array PHP.
     */
    $resultado = json_decode(
        $texto,
        true
    );

    if ( ! is_array( $resultado ) ) {

        $cis_openai_aviso_respuesta(
            'interpretacion_json_invalida'
        );

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
            'precio_minimo'      => '',
            'precio_maximo'      => '',
        ),
        $resultado
    );


    /*
     * Normalizar campos de texto.
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

    $resultado['precio_minimo'] =
        trim(
            (string)
            $resultado['precio_minimo']
        );

    $resultado['precio_maximo'] =
        trim(
            (string)
            $resultado['precio_maximo']
        );


    /*
     * Normalizar características.
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


    return $resultado;
}


/**
 * Wrapper utilizado por
 * Compuciber_OpenAIProvider.
 *
 * Mantiene la misma interfaz externa
 * independientemente del proveedor.
 *
 * @param string $busqueda
 *
 * @return array|false
 */
function compuciber_consultar_openai(
    $busqueda
) {

    return compuciber_consultar_openai_texto(
        $busqueda
    );
}