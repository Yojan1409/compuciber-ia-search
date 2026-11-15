<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function compuciber_consultar_gemini_imagen(
    $imagen_base64,
    $mime_type
) {

    $api_key = get_option(
        'compuciber_gemini_api_key',
        ''
    );

    $modelo = get_option(
        'compuciber_gemini_modelo_multimodal',
        'gemini-3.6-flash'
    );

    $modelo = sanitize_text_field(
        (string) $modelo
    );

    if ( empty( $modelo ) ) {
        $modelo = 'gemini-3.6-flash';
    }    


    if ( empty( $api_key ) ) {

        error_log(
            'COMPuciber Gemini Imagen: API Key no configurada.'
        );

        return array(
            'error' => 'api_key',
            'mensaje' => 'El servicio de inteligencia artificial no está configurado.'
        );

    }


    if (
        empty( $imagen_base64 )
        || empty( $mime_type )
    ) {

        return array(
            'error' => 'imagen',
            'mensaje' => 'No se recibió una imagen válida.'
        );

    }

    $idioma_tienda = sanitize_text_field(
            (string) get_locale()
        );
    $prompt = '
    Eres un interprete visual para el buscador de una tienda WooCommerce.

    Idioma configurado de la tienda: ' . $idioma_tienda . '.

    La tienda puede vender ropa, alimentos, tecnologia, muebles u otros productos.
    No presupongas que el catalogo contiene un producto concreto.

    Analiza solo lo que realmente se aprecia en la imagen.

    Identifica el tipo principal y especifico del producto.

    Utiliza las denominaciones comerciales habituales en Peru
    cuando el idioma de la tienda sea espanol de Peru.

    Por ejemplo, utiliza "mouse" en lugar de "raton",
    "laptop" en lugar de "computadora portatil" y
    "audifonos" en lugar de "auriculares" cuando corresponda
    al vocabulario comercial del producto.

    Aplica estas denominaciones tanto al campo producto
    como al campo busqueda_corregida y palabras_clave.

    No traduzcas marcas, modelos, codigos comerciales ni
    referencias del fabricante.

    No cambies la identidad del producto por adaptar
    su denominacion comercial.

    No mezcles colores ni acabados en el campo producto:
    esos detalles deben ir en caracteristicas.

    Examina cuidadosamente el producto fotografiado para identificar
    su tipo especifico, marca, familia, serie y modelo comercial.

    Busca evidencia visual en logotipos, inscripciones, etiquetas,
    empaques, codigos impresos y textos legibles de la imagen.

    Si existe una referencia de modelo visible, conservala completa
    y exactamente como aparece, incluyendo letras, numeros y guiones.

    Considera tambien detalles visuales distintivos como forma,
    distribucion de botones, acabados y combinaciones de colores
    para reconocer posibles familias o series comerciales.

    Distingue entre una identificacion confirmada y una semejanza
    visual. No conviertas una semejanza en un modelo confirmado.

    Escribe el modelo solamente cuando exista evidencia visual
    suficiente para identificarlo con seguridad. Si hay varios
    modelos visualmente indistinguibles, deja modelo vacio.

    No inventes marcas, modelos ni codigos de producto.

    Construye busqueda_corregida utilizando este orden:
    tipo especifico de producto, marca confirmada, modelo
    confirmado y caracteristicas visibles distintivas.

    Ejemplo de estructura de consulta:
    mouse inalambrico + marca + modelo + color.

    No copies el ejemplo como resultado de la fotografia.
    Utiliza exclusivamente la informacion de la imagen analizada.

    Si el modelo no puede confirmarse, utiliza el tipo de producto,
    la marca identificada y las caracteristicas visibles para
    obtener candidatos relevantes del catalogo.

    Conserva la marca confirmada en la busqueda.
    Los colores y acabados deben complementar la identificacion,
    nunca sustituir el tipo principal de producto.

    No deduzcas tallas, capacidades, compatibilidades ni materiales que no se aprecien.

    Si no es posible identificar el tipo de producto,
    deja producto y busqueda_corregida vacios.

    Nunca afirmes que el producto fotografiado existe en WooCommerce.

    Devuelve unicamente un objeto JSON valido con estos campos y tipos:

    {
    "busqueda_corregida": "",
    "producto": "",
    "marca": "",
    "modelo": "",
    "caracteristicas": [],
    "palabras_clave": []
    }

    busqueda_corregida: texto corto que sirva para buscar en WooCommerce.

    caracteristicas y palabras_clave: arreglos de cadenas de texto.

    No incluyas comentarios, Markdown ni explicaciones fuera del JSON.
    ';


    $request_body = array(
        'contents' => array(
            array(
                'parts' => array(

                    array(
                        'text' => $prompt,
                    ),

                    array(
                        'inline_data' => array(
                            'mime_type' => $mime_type,
                            'data'      => $imagen_base64,
                        ),
                    ),

                ),
            ),
        ),
    );


    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode( $modelo )
        . ':generateContent?key='
        . rawurlencode( $api_key );


    /*
     * ======================================================
     * REINTENTOS
     * ======================================================
     *
     * Gemini puede devolver HTTP 503 cuando el modelo
     * está temporalmente saturado.
     *
     * Se intará el llamado 3 veces.
     */

    $max_intentos = 3;


    for (
        $intento = 1;
        $intento <= $max_intentos;
        $intento++
    ) {


        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(
                    $request_body
                ),
            )
        );

        if ( is_wp_error( $response ) ) {

            error_log(
                'COMPuciber Gemini Imagen - Error WP: '
                . $response->get_error_message()
            );

            return false;
        }


        $codigo =
            wp_remote_retrieve_response_code(
                $response
            );

        if ( $codigo === 503 ) {

            error_log(
                'COMPuciber Gemini Imagen - Intento '
                . $intento
                . ' recibió HTTP 503.'
            );


            if ( $intento < $max_intentos ) {

                sleep( 3 );

                continue;
            }


            error_log(
                'COMPuciber Gemini Imagen - '
                . 'Gemini continúa no disponible después '
                . $max_intentos
                . ' intentos.'
            );

            return false;
        }

        if (
            $codigo < 200
            || $codigo >= 300
        ) {

            error_log(
                'COMPuciber Gemini Imagen - HTTP: '
                . $codigo
            );

            return false;
        }


        /*
         * ==================================================
         * RESPUESTA CORRECTA
         * ==================================================
         */

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

            error_log(
                'COMPuciber Gemini Imagen: Respuesta JSON inválida.'
            );

            return array(
                'error' => 'respuesta',
                'mensaje' => 'La IA no devolvió una respuesta válida.'
            );

        }

        error_log(
            'COMPuciber Imagen IA: ' . print_r($resultado, true)
        );   
        return $resultado;
    }


    return array(
        'error' => 'desconocido',
        'mensaje' => 'No fue posible analizar la imagen.'
    );
}

/*
 * CAMBIO 82: comparación visual opcional.
 * No sustituye la búsqueda original.
 */
function compuciber_comparar_visual_catalogo_gemini(
    $foto_cliente,
    $mime_cliente,
    $interpretacion,
    $ids_resultados = array()
) {
    /*
     * CAMBIO 83:
     * Aceptar solo candidatos del ranking real.
     * No comparar si aún no fueron proporcionados.
     */
    $ids_resultados = array_slice(
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        (array) $ids_resultados
                    )
                )
            )
        ),
        0,
        100
    );

    if ( empty( $ids_resultados ) ) {
        return array();
    }
    if (
        get_option(
            'compuciber_comparacion_visual_catalogo', '0'
        ) !== '1'
        || get_option(
            'compuciber_proveedor_ia', 'gemini'
        ) !== 'gemini'
        || ! is_array( $interpretacion )
        || ! in_array(
            $mime_cliente,
            array( 'image/jpeg', 'image/png', 'image/webp' ),
            true
        )
        || ! is_string( $foto_cliente )
        || $foto_cliente === ''
    ) {
        return array();
    }

    $api_key = trim( (string) get_option(
        'compuciber_gemini_api_key', ''
    ) );

    $modelo_ia = trim( (string) get_option(
        'compuciber_gemini_modelo_multimodal',
        'gemini-3.6-flash'
    ) );

    if ( $api_key === '' || $modelo_ia === '' ) {
        return array();
    }

    $tipo = compuciber_normalizar_texto(
        (string) ( $interpretacion['producto'] ?? '' )
    );

    $palabras_tipo = preg_split(
        '/\s+/u', trim( $tipo )
    );

    $tipo_principal = (string) (
        $palabras_tipo[0] ?? ''
    );

    if ( mb_strlen( $tipo_principal ) < 3 ) {
        return array();
    }

    $marca = compuciber_normalizar_texto(
        (string) ( $interpretacion['marca'] ?? '' )
    );

    $modelo = compuciber_normalizar_texto(
        (string) ( $interpretacion['modelo'] ?? '' )
    );

    global $wpdb;

    $tabla = $wpdb->prefix
        . 'compuciber_product_index';

    /*
     * Consultar únicamente los IDs seleccionados
     * previamente por el motor de búsqueda.
     */
    $marcadores = implode(
        ', ',
        array_fill(
            0,
            count( $ids_resultados ),
            '%d'
        )
    );

    $filas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, nombre, marca, modelo FROM {$tabla} WHERE id IN ({$marcadores})",
            $ids_resultados
        )
    );

    if ( ! is_array( $filas ) ) {
        return array();
    }

    $candidatos = array();

    foreach ( $filas as $fila ) {
        $nombre = compuciber_normalizar_texto(
            (string) $fila->nombre
        );

        // No confundir un mouse con un combo.
        if ( ! preg_match(
            '/^' . preg_quote(
                $tipo_principal, '/'
            ) . '\b/u',
            $nombre
        ) ) {
            continue;
        }

        $texto = ' ' . $nombre . ' '
            . compuciber_normalizar_texto(
                (string) $fila->marca
            ) . ' '
            . compuciber_normalizar_texto(
                (string) $fila->modelo
            ) . ' ';

        $puntos = 1;

        if (
            $marca !== ''
            && mb_strpos( $texto, $marca ) !== false
        ) {
            $puntos += 10;
        }

        if (
            $modelo !== ''
            && mb_strpos( $texto, $modelo ) !== false
        ) {
            $puntos += 30;
        }

        $candidatos[] = array(
            'id' => (int) $fila->id,
            'puntos' => $puntos
        );
    }

    /*
     * CAMBIO 86: respetar la relevancia real.
     *
     * Conservar primero los mejores candidatos
     * del ranking original y permitir rescatar
     * coincidencias de marca/modelo más profundas.
     */

    $cis_posicion_ranking = array_flip(
        $ids_resultados
    );

    foreach ( $candidatos as &$cis_candidato ) {
        $cis_candidato['posicion'] = (int) (
            $cis_posicion_ranking[
                $cis_candidato['id']
            ] ?? PHP_INT_MAX
        );
    }

    unset( $cis_candidato );

    /*
     * Recuperar el orden del motor original.
     */
    usort(
        $candidatos,
        static function ( $a, $b ) {
            return $a['posicion']
                <=> $b['posicion'];
        }
    );

    /*
     * Reservar los primeros cuatro candidatos
     * del ranking original.
     */
    $cis_principales = array_slice(
        $candidatos,
        0,
        4
    );

    /*
     * Examinar también candidatos posteriores
     * que puedan coincidir en marca o modelo.
     */
    $cis_rescate = array_slice(
        $candidatos,
        4
    );

    usort(
        $cis_rescate,
        static function ( $a, $b ) {

            if (
                $a['puntos'] !== $b['puntos']
            ) {
                return $b['puntos']
                    <=> $a['puntos'];
            }

            return $a['posicion']
                <=> $b['posicion'];
        }
    );

    /*
     * Preparar la selección final de imágenes.
     * No modificar el ranking público aquí.
     */
    $candidatos = array_merge(
        $cis_principales,
        $cis_rescate
    );

    $partes = array(
        array(
            'text' =>
                'La primera foto es del cliente. '
                . 'Las siguientes son productos del catálogo '
                . 'identificados por ID. Compara forma, '
                . 'detalles visibles, etiquetas y modelo. '
                . 'La misma marca, categoría o color '
                . 'NO demuestra que sean el mismo producto. '
                . 'Devuelve coincidencias solo si son muy claras. '
                . 'Si hay dudas, devuelve una lista vacía. '
                . 'Responde únicamente JSON: '
                . '{"coincidencias":[{"id":123,"similitud":0.95}]}'
        ),
        array(
            'text' => 'FOTO DEL CLIENTE:'
        ),
        array(
            'inline_data' => array(
                'mime_type' => $mime_cliente,
                'data' => $foto_cliente
            )
        )
    );

    $ids_validos = array();

    foreach (
        array_slice( $candidatos, 0, 35 )
        as $candidato
    ) {
        if ( count( $ids_validos ) >= 6 ) {
            break;
        }

        $id = $candidato['id'];
        $producto = wc_get_product( $id );

        if (
            ! $producto
            || ! compuciber_producto_visible_en_busqueda(
                $producto
            )
            || ! $producto->get_image_id()
        ) {
            continue;
        }

        $ruta = get_attached_file(
            $producto->get_image_id()
        );

        if (
            ! is_string( $ruta )
            || ! is_readable( $ruta )
            || filesize( $ruta ) > 8 * 1024 * 1024
        ) {
            continue;
        }

        $editor = wp_get_image_editor( $ruta );

        if ( is_wp_error( $editor ) ) {
            continue;
        }

        if ( is_wp_error(
            $editor->resize( 480, 480, false )
        ) ) {
            continue;
        }

        $temporal = tempnam(
            get_temp_dir(), 'cisvis_'
        );

        if ( ! $temporal ) {
            continue;
        }

        $guardada = $editor->save(
            $temporal, 'image/jpeg'
        );

        $ruta_guardada = ! is_wp_error( $guardada )
            ? (string) ( $guardada['path'] ?? '' )
            : '';

        $datos = (
            $ruta_guardada !== ''
            && is_readable( $ruta_guardada )
        )
            ? file_get_contents( $ruta_guardada )
            : false;

        @unlink( $temporal );

        if (
            $ruta_guardada !== ''
            && $ruta_guardada !== $temporal
        ) {
            @unlink( $ruta_guardada );
        }

        if (
            ! is_string( $datos )
            || strlen( $datos ) > 450000
        ) {
            continue;
        }

        $ids_validos[ $id ] = true;

        $partes[] = array(
            'text' => 'PRODUCTO CANDIDATO ID ' . $id
        );

        $partes[] = array(
            'inline_data' => array(
                'mime_type' => 'image/jpeg',
                'data' => base64_encode( $datos )
            )
        );
    }

    if ( empty( $ids_validos ) ) {
        return array();
    }

    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode( $modelo_ia )
        . ':generateContent?key='
        . rawurlencode( $api_key );

    $respuesta = wp_remote_post(
        $url,
        array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(
                array(
                    'contents' => array(
                        array( 'parts' => $partes )
                    )
                )
            )
        )
    );

    if (
        is_wp_error( $respuesta )
        || (int) wp_remote_retrieve_response_code(
            $respuesta
        ) !== 200
    ) {
        return array();
    }

    $cuerpo = json_decode(
        wp_remote_retrieve_body( $respuesta ),
        true
    );

    $texto = '';

    foreach (
        (array) (
            $cuerpo['candidates'][0]['content']['parts']
            ?? array()
        ) as $parte
    ) {
        if (
            ! empty( $parte['text'] )
            && empty( $parte['thought'] )
        ) {
            $texto .= $parte['text'];
        }
    }

    $texto = preg_replace(
        '/^```(?:json)?\s*|\s*```$/i',
        '',
        trim( $texto )
    );

    $respuesta_ia = json_decode( $texto, true );

    if (
        ! is_array(
            $respuesta_ia['coincidencias'] ?? null
        )
    ) {
        return array();
    }

    $coincidencias = array();

    foreach (
        $respuesta_ia['coincidencias'] as $fila
    ) {
        if ( ! is_array( $fila ) ) {
            continue;
        }

        $id = absint( $fila['id'] ?? 0 );
        $similitud = $fila['similitud'] ?? null;

        if (
            isset( $ids_validos[ $id ] )
            && is_numeric( $similitud )
            && (float) $similitud >= 0.90
            && (float) $similitud <= 1.0
        ) {
            $coincidencias[ $id ] =
                (float) $similitud;
        }
    }

    return $coincidencias;
}