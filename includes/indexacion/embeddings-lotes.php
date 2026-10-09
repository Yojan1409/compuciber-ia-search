<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene el proveedor y modelo de embeddings
 * configurados actualmente.
 *
 * @return array
 */
function compuciber_obtener_configuracion_embeddings() {

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    $proveedor = (
        $proveedor === 'openai'
    ) ? 'openai' : 'gemini';

    $opcion_modelo = (
        $proveedor === 'openai'
    )
        ? 'compuciber_openai_modelo_embeddings'
        : 'compuciber_gemini_modelo_embeddings';

    $modelo_predeterminado = (
        $proveedor === 'openai'
    )
        ? 'text-embedding-3-small'
        : 'gemini-embedding-2';

    $modelo = trim(
        sanitize_text_field(
            (string) get_option(
                $opcion_modelo,
                $modelo_predeterminado
            )
        )
    );

    if ( $modelo === '' ) {
        $modelo = $modelo_predeterminado;
    }

    return array(
        'proveedor' => $proveedor,
        'modelo'    => $modelo,
    );
}


/**
 * Comprueba si un embedding corresponde
 * al proveedor, modelo y texto actuales.
 *
 * @param object $producto Fila del índice.
 * @param array  $configuracion Configuración actual.
 *
 * @return bool
 */
function compuciber_embedding_esta_actualizado(
    $producto,
    $configuracion
) {

    if ( ! is_object( $producto ) ) {
        return false;
    }

    $texto = trim(
        (string) ( $producto->texto_embedding ?? '' )
    );

    if ( $texto === '' ) {
        return false;
    }

    $embedding = (string) (
        $producto->embedding ?? ''
    );

    if ( $embedding === '' ) {
        return false;
    }

    $hash_guardado = (string) (
        $producto->hash_embedding ?? ''
    );

    if ( $hash_guardado === '' ) {
        return false;
    }

    $hash_actual = hash(
        'sha256',
        $texto
    );

    return (
        (string) (
            $producto->embedding_proveedor ?? ''
        ) === (string) $configuracion['proveedor']

        && (string) (
            $producto->embedding_modelo ?? ''
        ) === (string) $configuracion['modelo']

        && hash_equals(
            $hash_guardado,
            $hash_actual
        )
    );
}


/**
 * Obtiene el estado de los embeddings.
 *
 * Los productos sin texto semántico se
 * contabilizan por separado porque no
 * pueden procesarse mediante la API.
 *
 * @return array
 */
function compuciber_obtener_estado_embeddings() {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_product_index';

    $configuracion =
        compuciber_obtener_configuracion_embeddings();

    $productos = $wpdb->get_results(
        "SELECT
            texto_embedding,
            hash_embedding,
            embedding,
            embedding_proveedor,
            embedding_modelo
        FROM {$tabla}"
    );

    $estado = array(
        'total'      => 0,
        'generados'  => 0,
        'pendientes' => 0,
        'sin_texto'  => 0,
    );

    if ( ! is_array( $productos ) ) {
        return $estado;
    }

    foreach ( $productos as $producto ) {

        $estado['total']++;

        $texto = trim(
            (string) $producto->texto_embedding
        );

        if ( $texto === '' ) {
            $estado['sin_texto']++;
            continue;
        }

        if (
            compuciber_embedding_esta_actualizado(
                $producto,
                $configuracion
            )
        ) {
            $estado['generados']++;
        } else {
            $estado['pendientes']++;
        }
    }

    return $estado;
}


/**
 * Genera embeddings pendientes por lotes.
 *
 * Características:
 * - máximo 5 productos por ejecución;
 * - presupuesto de tiempo;
 * - bloqueo contra ejecuciones simultáneas;
 * - manejo de errores por producto;
 * - conservación de embeddings válidos;
 * - recuento actualizado al finalizar.
 *
 * @param int $limite Tamaño solicitado.
 *
 * @return array
 */
function compuciber_generar_lote_embeddings(
    $limite = 5
) {

    global $wpdb;

    $tabla =
        $wpdb->prefix . 'compuciber_product_index';

    $limite = max(
        1,
        min( 5, absint( $limite ) )
    );

    $resultado = array(
        'procesados' => 0,
        'generados'  => 0,
        'fallidos'   => 0,
        'pendientes' => 0,
        'bloqueado'  => false,
        'tiempo_agotado' => false,
    );

    /*
     * Bloqueo atómico mediante una opción
     * de WordPress.
     *
     * Evita que dos peticiones procesen
     * simultáneamente el mismo lote.
     */
    $opcion_bloqueo =
        'compuciber_embeddings_lote_lock';

    $token_bloqueo = wp_generate_uuid4();

    $inicio = microtime( true );

    $bloqueo_existente = get_option(
        $opcion_bloqueo,
        false
    );

    /*
    * Recuperar bloqueos abandonados.
    *
    * No se elimina un bloqueo mientras
    * pueda existir una ejecución activa.
    */
    if ( is_array( $bloqueo_existente ) ) {

        $creado = absint(
            $bloqueo_existente['creado'] ?? 0
        );

        if (
            $creado > 0
            && ( time() - $creado ) > 180
        ) {

            /*
            * Comprobar nuevamente el token
            * antes de intentar recuperarlo.
            */
            $bloqueo_actual = get_option(
                $opcion_bloqueo,
                false
            );

            if (
                is_array( $bloqueo_actual )
                && ( $bloqueo_actual['token'] ?? '' )
                    === ( $bloqueo_existente['token'] ?? '' )
            ) {
                delete_option( $opcion_bloqueo );
            }
        }
    }

    $bloqueo_adquirido = add_option(
        $opcion_bloqueo,
        array(
            'token'  => $token_bloqueo,
            'creado' => time(),
        ),
        '',
        false
    );

    if ( ! $bloqueo_adquirido ) {

        $resultado['bloqueado'] = true;

        $estado =
            compuciber_obtener_estado_embeddings();

        $resultado['pendientes'] =
            $estado['pendientes'];

        return $resultado;
    }

    try {

        $configuracion =
            compuciber_obtener_configuracion_embeddings();

        /*
         * Cada petición HTTP al proveedor
         * puede esperar hasta 20 segundos.
         *
         * Limitamos inicialmente el lote a
         * un presupuesto de 25 segundos.
         *
         * Este presupuesto se comprueba antes
         * de comenzar cada producto; no
         * interrumpe una petición HTTP activa.
         */
        $presupuesto_segundos = 25;

        $max_execution_time = (int) ini_get(
            'max_execution_time'
        );

        if ( $max_execution_time > 0 ) {

            $presupuesto_segundos = min(
                $presupuesto_segundos,
                max(
                    1,
                    $max_execution_time - 5
                )
            );
        }

        $candidatos = $wpdb->get_results(
            "SELECT
                id,
                texto_embedding,
                hash_embedding,
                embedding,
                embedding_proveedor,
                embedding_modelo
            FROM {$tabla}
            ORDER BY id ASC"
        );

        /*
        * Rotar el punto de inicio entre lotes
        * para que un producto fallido no impida
        * procesar otros pendientes.
        */
        $ultimo_id = absint(
            get_option(
                'compuciber_embeddings_ultimo_id',
                0
            )
        );

        $productos_lote = array();

        if ( is_array( $candidatos ) ) {

            foreach ( $candidatos as $producto ) {

                if (
                    trim(
                        (string) $producto->texto_embedding
                    ) === ''
                ) {
                    continue;
                }

                if (
                    compuciber_embedding_esta_actualizado(
                        $producto,
                        $configuracion
                    )
                ) {
                    continue;
                }

                /*
                * Primero se procesan productos posteriores
                * al último ID utilizado.
                */
                if ( absint( $producto->id ) > $ultimo_id ) {

                    $productos_lote[] =
                        absint( $producto->id );
                }

                if (
                    count( $productos_lote ) >= $limite
                ) {
                    break;
                }
            }
        }

        /*
        * Si llegamos al final del índice,
        * volver a comenzar desde el principio.
        */
        if (
            empty( $productos_lote )
            && $ultimo_id > 0
        ) {

            update_option(
                'compuciber_embeddings_ultimo_id',
                0,
                false
            );

            foreach ( $candidatos as $producto ) {

                if (
                    trim(
                        (string) $producto->texto_embedding
                    ) === ''
                ) {
                    continue;
                }

                if (
                    compuciber_embedding_esta_actualizado(
                        $producto,
                        $configuracion
                    )
                ) {
                    continue;
                }

                $productos_lote[] =
                    absint( $producto->id );

                if (
                    count( $productos_lote ) >= $limite
                ) {
                    break;
                }
            }
        }

        foreach ( $productos_lote as $product_id ) {

            /*
             * No comenzar otra generación si
             * el presupuesto ya se consumió.
             */
            if (
                ( microtime( true ) - $inicio )
                >= $presupuesto_segundos
            ) {

                $resultado['tiempo_agotado'] = true;
                break;
            }

            $resultado['procesados']++;

            update_option(
                'compuciber_embeddings_ultimo_id',
                $product_id,
                false
            );            

            try {

                compuciber_indexar_producto(
                    $product_id,
                    true
                );

                $producto_actualizado =
                    $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT
                                texto_embedding,
                                hash_embedding,
                                embedding,
                                embedding_proveedor,
                                embedding_modelo
                            FROM {$tabla}
                            WHERE id = %d",
                            $product_id
                        )
                    );

                if (
                    compuciber_embedding_esta_actualizado(
                        $producto_actualizado,
                        $configuracion
                    )
                ) {

                    $resultado['generados']++;

                } else {

                    $resultado['fallidos']++;
                }

            } catch ( Throwable $error ) {

                $resultado['fallidos']++;

                /*
                 * No registrar claves API ni
                 * contenido semántico del producto.
                 */
                error_log(
                    '[Compuciber AI Search] '
                    . 'Error generando embedding '
                    . 'del producto ID '
                    . absint( $product_id )
                    . '. Tipo: '
                    . get_class( $error )
                );
            }
        }

        $estado =
            compuciber_obtener_estado_embeddings();

        $resultado['pendientes'] =
            $estado['pendientes'];

    } finally {

        /*
         * Liberar únicamente nuestro bloqueo.
         */
        $bloqueo_actual = get_option(
            $opcion_bloqueo,
            false
        );

        if (
            is_array( $bloqueo_actual )
            && (
                $bloqueo_actual['token'] ?? ''
            ) === $token_bloqueo
        ) {
            delete_option( $opcion_bloqueo );
        }
    }

    return $resultado;
}