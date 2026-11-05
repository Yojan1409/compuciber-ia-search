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
        'catalogo-general-v2|' . $texto
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

        $cis_emb_ids_fallidos = array();
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
                    $cis_emb_ids_fallidos[] = absint( $product_id );
                }

                } catch ( Throwable $error ) {

                $resultado['fallidos']++;
                $cis_emb_ids_fallidos[] = absint( $product_id );

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

    /*
    * Registrar errores de embeddings en WooCommerce.
    * Un registro por lote para proteger el rendimiento.
    */
    if (
        ! empty( $cis_emb_ids_fallidos )
        && function_exists( 'wc_get_logger' )
    ) {

        $cis_emb_mensaje = sprintf(
            'Embeddings fallidos: proveedor=%s | modelo=%s | procesados=%d | fallidos=%d | IDs=%s',
            sanitize_key( $configuracion['proveedor'] ),
            substr(
                sanitize_text_field( $configuracion['modelo'] ),
                0,
                80
            ),
            absint( $resultado['procesados'] ),
            absint( $resultado['fallidos'] ),
            implode( ',', $cis_emb_ids_fallidos )
        );

        $cis_emb_clave =
            'cis_emb_error_' . md5( $cis_emb_mensaje );

        if ( false === get_transient( $cis_emb_clave ) ) {

            wc_get_logger()->error(
                $cis_emb_mensaje,
                array(
                    'source' => 'compuciber-ai-search'
                )
            );

            set_transient(
                $cis_emb_clave,
                1,
                10 * MINUTE_IN_SECONDS
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

/*
 * Cola opcional de embeddings.
 * Ejecuta un lote aproximadamente cada cinco minutos.
 * Solo se inicia desde el panel administrativo.
 */
function compuciber_cola_embeddings_paso() {

    $opcion = 'compuciber_cola_embeddings_estado';

    $cola = get_option( $opcion, array() );

    if (
        ! is_array( $cola )
        || empty( $cola['activo'] )
    ) {
        return;
    }

    /*
     * Detener la cola y conservar su estado.
     */
    $detener = static function ( $estado, $motivo ) use ( $opcion ) {

        $estado['activo'] = false;
        $estado['situacion'] = $motivo;
        $estado['ultima'] = time();

        update_option( $opcion, $estado, false );

        wp_clear_scheduled_hook(
            'compuciber_cola_embeddings_paso'
        );

        if ( function_exists( 'wc_get_logger' ) ) {

            wc_get_logger()->info(
                'Cola de embeddings detenida: '
                . sanitize_key( $motivo ),
                array(
                    'source' => 'compuciber-ai-search'
                )
            );
        }
    };

    /*
     * Verificar configuración.
     */
    $config = compuciber_obtener_configuracion_embeddings();

    $proveedor = $config['proveedor'];

    $opcion_clave = $proveedor === 'openai'
        ? 'compuciber_openai_api_key'
        : 'compuciber_gemini_api_key';

    if (
        get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) !== '1'

        || ! class_exists( 'WooCommerce' )

        || ! trim(
            (string) get_option(
                $opcion_clave,
                ''
            )
        )
    ) {

        $detener(
            $cola,
            'revisar_configuracion'
        );

        return;
    }

    /*
     * Evitar continuar si cambia el proveedor
     * o el modelo mientras la cola está activa.
     */
    if (
        $proveedor !== (
            $cola['proveedor'] ?? ''
        )

        || $config['modelo'] !== (
            $cola['modelo'] ?? ''
        )
    ) {

        $detener(
            $cola,
            'proveedor_o_modelo_cambiado'
        );

        return;
    }

    /*
     * Respetar el máximo autorizado.
     */
    $restantes = max(
        0,
        absint(
            $cola['limite_total'] ?? 0
        )
        - absint(
            $cola['procesados'] ?? 0
        )
    );

    if ( $restantes === 0 ) {

        $detener(
            $cola,
            'limite_alcanzado'
        );

        return;
    }

    $tamano = min(
        5,
        $restantes,
        max(
            1,
            absint(
                $cola['por_lote'] ?? 1
            )
        )
    );

    /*
     * Reutilizar el generador existente.
     * Este ya dispone de bloqueo y límite de tiempo.
     */
    try {

        $resultado =
            compuciber_generar_lote_embeddings(
                $tamano
            );

    } catch ( Throwable $error ) {

        $detener(
            $cola,
            'error_inesperado'
        );

        return;
    }

    /*
     * Respetar una detención realizada
     * mientras se procesaba el lote.
     */
    $estado_actual = get_option(
        $opcion,
        array()
    );

    if (
        ! is_array( $estado_actual )

        || empty(
            $estado_actual['activo']
        )

        || (
            $estado_actual['token'] ?? ''
        ) !== (
            $cola['token'] ?? ''
        )
    ) {
        return;
    }

    $cola = $estado_actual;

    $procesados = absint(
        $resultado['procesados'] ?? 0
    );

    $fallidos = absint(
        $resultado['fallidos'] ?? 0
    );

    /*
     * Acumular resultados.
     */
    $cola['procesados'] =
        absint(
            $cola['procesados'] ?? 0
        ) + $procesados;

    $cola['generados'] =
        absint(
            $cola['generados'] ?? 0
        ) + absint(
            $resultado['generados'] ?? 0
        );

    $cola['fallidos'] =
        absint(
            $cola['fallidos'] ?? 0
        ) + $fallidos;

    $cola['ultima'] = time();

    /*
     * Determinar si debemos continuar.
     */

    if (
        ! empty(
            $resultado['bloqueado']
        )
    ) {

        $cola['situacion'] =
            'esperando_bloqueo';

    } elseif ( $fallidos > 0 ) {

        /*
         * Evitar gastar cuota en reintentos
         * repetidos cuando hay errores.
         */
        $detener(
            $cola,
            'pausada_por_fallos'
        );

        return;

    } elseif (
        absint(
            $resultado['pendientes'] ?? 0
        ) === 0
    ) {

        $detener(
            $cola,
            'completada'
        );

        return;

    } elseif ( $procesados === 0 ) {

        $detener(
            $cola,
            'sin_avance'
        );

        return;

    } elseif (
        $cola['procesados'] >=
        absint(
            $cola['limite_total']
        )
    ) {

        $detener(
            $cola,
            'limite_alcanzado'
        );

        return;

    } else {

        $cola['situacion'] =
            'en_cola';
    }

    update_option(
        $opcion,
        $cola,
        false
    );

    /*
     * Programar únicamente el siguiente lote.
     */
    if (
        ! wp_next_scheduled(
            'compuciber_cola_embeddings_paso'
        )
    ) {

        /*
         * Programar el siguiente lote utilizando
         * el intervalo elegido por el administrador.
         */

        $cis_intervalo_minutos = max(
            1,
            min(
                10,
                absint(
                    $cola['cada_minutos'] ?? 5
                )
            )
        );

        $programado = wp_schedule_single_event(
            time() + (
                $cis_intervalo_minutos
                * MINUTE_IN_SECONDS
            ),
            'compuciber_cola_embeddings_paso',
            array(),
            true
        );

        if (
            is_wp_error( $programado )
            || ! $programado
        ) {

            $detener(
                $cola,
                'error_programacion'
            );
        }
    }
}

add_action(
    'compuciber_cola_embeddings_paso',
    'compuciber_cola_embeddings_paso'
);


/*
 * Limpiar la programación al desactivar el plugin.
 */
function compuciber_cola_embeddings_desactivar() {

    wp_clear_scheduled_hook(
        'compuciber_cola_embeddings_paso'
    );

    $cola = get_option(
        'compuciber_cola_embeddings_estado',
        array()
    );

    if (
        is_array( $cola )
        && ! empty( $cola['activo'] )
    ) {

        $cola['activo'] = false;

        $cola['situacion'] =
            'detenida_por_desactivacion';

        update_option(
            'compuciber_cola_embeddings_estado',
            $cola,
            false
        );
    }
}

register_deactivation_hook(
    dirname( __DIR__, 2 ) . '/compuciber-ai-search.php',
    'compuciber_cola_embeddings_desactivar'
);