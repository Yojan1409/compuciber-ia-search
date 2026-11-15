<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'resultado/normalizacion.php';
require_once plugin_dir_path( __FILE__ ) . 'resultado/puntuacion.php';
require_once plugin_dir_path( __FILE__ ) . 'resultado/consulta.php';
require_once plugin_dir_path( __FILE__ ) . 'resultado/embeddings.php';

function compuciber_buscar_productos_inteligente(
    $busqueda,
    $interpretacion
) {

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COMPUCIBER_DEBUG_BUSQUEDA' ) && COMPUCIBER_DEBUG_BUSQUEDA ) error_log( 'RESULTADOS.PHP NUEVO EJECUTADO' );

    /*
    * PRIORIDAD ABSOLUTA:
    * SKU exacto de una variación WooCommerce.
    */

    $sku_buscado = trim( (string) $busqueda );

    if (
        $sku_buscado !== ''
        && function_exists( 'wc_get_product_id_by_sku' )
    ) {

        $id_sku = wc_get_product_id_by_sku(
            $sku_buscado
        );

        $producto_sku = $id_sku
            ? wc_get_product( $id_sku )
            : false;

        if (
            $producto_sku
            && $producto_sku->is_type( 'variation' )
        ) {

            $padre = wc_get_product(
                $producto_sku->get_parent_id()
            );

            if (
                $padre
                && compuciber_producto_visible_en_busqueda( $padre )
                && compuciber_producto_visible_en_busqueda( $producto_sku )
                && $producto_sku->variation_is_visible()
            ) {

                return compuciber_consulta_productos(
                    array( $padre->get_id() )
                );
            }

            return compuciber_consulta_vacia();
        }

        /*
         * BÚSQUEDA RÁPIDA POR SKU EXACTO
         *
         * Para productos que no son variaciones.
         * Evita recorrer el índice completo.
         */

        if (
            $producto_sku
            && ! $producto_sku->is_type( 'variation' )
            && compuciber_producto_visible_en_busqueda( $producto_sku )
        ) {

            return compuciber_consulta_productos(
                array( $producto_sku->get_id() )
            );
        }        
        
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';
 
    /*
    * OPTIMIZACIÓN DEL ÍNDICE
    *
    * Cargar solamente los datos que utiliza
    * el motor de puntuación.
    */

    $columnas_indice = "
        id, nombre, sku, marca, modelo,
        categorias, atributos, precio, precio_oferta,
        variaciones, texto_busqueda
    ";

    /*
    * Los embeddings solamente se cargan cuando
    * la búsqueda semántica está activada.
    */

    if (
        get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) === '1'
    ) {

        $columnas_indice .= ",
            texto_embedding,
            hash_embedding,
            embedding,
            embedding_proveedor,
            embedding_modelo
        ";
    }

    /*
    * Aplicar el filtro del perfil antes
    * de calcular la puntuación de los productos.
    */
    $cis_filtro_resultados_sql = '';

    if (
        isset( $_GET['cis_perfil'] )
        && is_string( $_GET['cis_perfil'] )
    ) {

        $cis_perfil_id = sanitize_key(
            wp_unslash( $_GET['cis_perfil'] )
        );

        if ( $cis_perfil_id !== '' ) {

            $cis_filtro_resultados_sql = function_exists(
                'compuciber_perfil_condicion_sql'
            )
                ? compuciber_perfil_condicion_sql(
                    $cis_perfil_id
                )
                : ' AND 1=0';
        }
    }

    /*
    * Consultar únicamente los productos
    * correspondientes al perfil seleccionado.
    */
    $productos_candidatos = $wpdb->get_results(
        "SELECT {$columnas_indice}
        FROM {$tabla} i
        WHERE 1=1 {$cis_filtro_resultados_sql}"
    );

    if ( empty( $productos_candidatos ) ) {
        return compuciber_consulta_vacia();
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COMPUCIBER_DEBUG_BUSQUEDA' ) && COMPUCIBER_DEBUG_BUSQUEDA ) error_log(
        'INTERPRETACION RECIBIDA: '
        . print_r( $interpretacion, true )
    );    
    
    $datos_ia = compuciber_preparar_datos_ia(
        $interpretacion,
        $busqueda
    );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COMPUCIBER_DEBUG_BUSQUEDA' ) && COMPUCIBER_DEBUG_BUSQUEDA ) error_log(
        'DATOS IA EN RESULTADOS: '
        . print_r( $datos_ia, true )
    );    

    $busqueda_expandida = compuciber_expandir_sinonimos(
        $busqueda
    );

    $palabras_busqueda = compuciber_obtener_palabras(
        $busqueda_expandida
    );


    /*
    * LIMPIEZA DE PALABRAS GENÉRICAS
    * Evita falsos positivos en fuzzy.
    */

    $palabras_ignoradas = array(
        'producto',
        'disponible',
        'incluye',
        'ideal',
        'uso',
        'para',
        'con',
        'nuevo'
    );

    $palabras_busqueda = array_filter(
        $palabras_busqueda,
        function( $palabra ) use ( $palabras_ignoradas ) {

            return ! in_array(
                strtolower( $palabra ),
                $palabras_ignoradas,
                true
            );

        }
    );

    $embedding_consulta = false;

    /*
    * Generar embedding únicamente si:
    * - La búsqueda semántica está activada.
    * - La consulta necesita IA.
    * - La función de embeddings existe.
    */

    if (
        get_option(
            'compuciber_busqueda_semantica',
            '0'
        ) === '1'
        && function_exists(
            'compuciber_busqueda_necesita_ia'
        )
        && compuciber_busqueda_necesita_ia(
            $busqueda
        )
        && function_exists(
            'compuciber_generar_embedding'
        )
    ) {

        $embedding_consulta =
            compuciber_generar_embedding(
                $busqueda,
                'consulta'
            );
    }   

    $configuracion_embeddings =
    compuciber_obtener_configuracion_embeddings();

    $productos_puntuados = array();

    foreach ( $productos_candidatos as $producto ) {

        /*
        * Puntuación del motor tradicional:
        * coincidencias, fuzzy, sinónimos,
        * marca, modelo, características, etc.
        */
        $score =
            compuciber_calcular_puntuacion_producto(
                $producto,
                $busqueda,
                $palabras_busqueda,
                $datos_ia,
                $productos_candidatos
            );

        /*
        * Si el motor estructurado descartó el producto,
        * el embedding no puede volver a introducirlo.
        *
        * Esto conserva filtros fuertes como:
        * producto, categoría, precio, características
        * técnicas, marca/modelo y anclas.
        */
        if ( $score < 0 ) {
            continue;
        }

        /*
        * Puntuación semántica mediante embeddings.
        */
        $similitud_semantica = 0.0;

        if (
            is_array( $embedding_consulta )
            && ! empty( $embedding_consulta )
            && ! empty( $producto->embedding )
            && compuciber_embedding_esta_actualizado(
                    $producto,
                    $configuracion_embeddings
                )            
            && isset( $producto->embedding_proveedor )
            && isset( $producto->embedding_modelo )
        ) {

            $proveedor_actual = get_option(
                'compuciber_proveedor_ia',
                'gemini'
            );

            if ( $proveedor_actual === 'openai' ) {

                $modelo_actual = get_option(
                    'compuciber_openai_modelo_embeddings',
                    'text-embedding-3-small'
                );

            } else {

                $modelo_actual = get_option(
                    'compuciber_gemini_modelo_embeddings',
                    'gemini-embedding-2'
                );
            }

            $modelo_actual = sanitize_text_field(
                (string) $modelo_actual
            );

            $modelo_actual = trim(
                $modelo_actual
            );

            if ( $modelo_actual === '' ) {

                $modelo_actual = (
                    $proveedor_actual === 'openai'
                )
                    ? 'text-embedding-3-small'
                    : 'gemini-embedding-2';
            }


            /*
            * Solo comparar vectores generados
            * por el mismo proveedor y modelo.
            */
            if (
                $producto->embedding_proveedor ===
                    $proveedor_actual
                && $producto->embedding_modelo ===
                    $modelo_actual
            ) {

                $embedding_producto =
                    compuciber_decodificar_embedding(
                        $producto->embedding
                    );

                if ( is_array( $embedding_producto ) ) {

                    $similitud_semantica =
                        compuciber_similitud_coseno(
                            $embedding_consulta,
                            $embedding_producto
                        );
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COMPUCIBER_DEBUG_BUSQUEDA' ) && COMPUCIBER_DEBUG_BUSQUEDA ) error_log(
                        'EMBEDDING DEBUG: '
                        . $producto->nombre
                        . ' | SIMILITUD: '
                        . $similitud_semantica
                    );                       


                    /*
                    * El embedding complementa el
                    * motor existente.
                    *
                    * Una similitud alta puede hacer
                    * aparecer un producto aunque no
                    * comparta exactamente las mismas
                    * palabras de la consulta.
                    */
                    if (
                        $score > 0
                        && $similitud_semantica >= 0.45
                    ) {

                        // Mejorar el ranking de productos ya relevantes.
                        $score += $similitud_semantica * 30;

                    } elseif (
                        $score === 0
                        && $similitud_semantica >= 0.80
                    ) {

                        /*
                        * Recuperación semántica conservadora.
                        *
                        * Solo se aplica a productos que no fueron
                        * rechazados por los filtros obligatorios.
                        */

                        $score = 1 + (
                            $similitud_semantica - 0.80
                        ) * 15;

                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COMPUCIBER_DEBUG_BUSQUEDA' ) && COMPUCIBER_DEBUG_BUSQUEDA ) error_log(
                                'CIS RESCATE SEMANTICO: PRODUCTO_ID='
                                . absint( $producto->id )
                                . ' | SIMILITUD='
                                . round( $similitud_semantica, 3 )
                            );
                        }
                    }
                }
            }
        }


        if ( $score > 0 ) {

            $productos_puntuados[] = array(
                'id' => (int) $producto->id,
                'score' => $score,
            );
        }
    }

    /*
     * CAMBIO 75
     * Prioridad de identidad para búsquedas visuales.
     * Conserva el ranking original para búsquedas normales.
     */

    $cis_prioridades_visuales = array();
    $cis_busqueda_visual_valida = false;

    if (
        isset( $_GET['cis_img_token'] )
        && is_string( $_GET['cis_img_token'] )
    ) {
        $cis_token = sanitize_text_field(
            wp_unslash( $_GET['cis_img_token'] )
        );

        if ( preg_match(
            '/\A[A-Za-z0-9]{40}\z/D',
            $cis_token
        ) ) {
            $cis_datos = get_transient(
                'cis_imagen_' . $cis_token
            );

            $cis_busqueda_visual_valida = (
                is_array( $cis_datos )
                && isset(
                    $cis_datos['interpretacion'],
                    $cis_datos['busqueda']
                )
                && is_array( $cis_datos['interpretacion'] )
                && is_string( $cis_datos['busqueda'] )
                && hash_equals(
                    $cis_datos['busqueda'],
                    (string) $busqueda
                )
            );
        }
    }

    if ( $cis_busqueda_visual_valida ) {

        $cis_tipo = compuciber_normalizar_texto(
            (string) ( $interpretacion['producto'] ?? '' )
        );

        $cis_marca = compuciber_normalizar_texto(
            (string) ( $interpretacion['marca'] ?? '' )
        );

        $cis_modelo = compuciber_normalizar_texto(
            (string) ( $interpretacion['modelo'] ?? '' )
        );

        $cis_palabras_tipo = preg_split(
            '/\s+/u',
            trim( $cis_tipo )
        );

        $cis_tipo_principal = $cis_palabras_tipo[0] ?? '';

        if (
            mb_strlen( $cis_tipo_principal ) >= 3
            && ! in_array(
                $cis_tipo_principal,
                array( 'producto', 'articulo', 'equipo' ),
                true
            )
        ) {
            foreach ( $productos_candidatos as $producto ) {

                $cis_nombre = compuciber_normalizar_texto(
                    (string) $producto->nombre
                );

                $cis_identidad = preg_match(
                    '/^' . preg_quote(
                        $cis_tipo_principal,
                        '/'
                    ) . '\b/u',
                    $cis_nombre
                ) === 1;

                $cis_prioridad = $cis_identidad ? 1 : 0;

                if (
                    $cis_identidad
                    && $cis_marca !== ''
                    && mb_strpos(
                        ' ' . $cis_nombre . ' ',
                        ' ' . $cis_marca . ' '
                    ) !== false
                ) {
                    $cis_prioridad = 2;
                }

                if (
                    $cis_identidad
                    && $cis_modelo !== ''
                    && preg_match(
                        '/(?<![\p{L}\p{N}])'
                        . preg_quote( $cis_modelo, '/' )
                        . '(?![\p{L}\p{N}])/u',
                        $cis_nombre
                    )
                ) {
                    $cis_prioridad += 2;
                }

                $cis_prioridades_visuales[
                    (int) $producto->id
                ] = $cis_prioridad;
            }
        }
    }

    usort(
        $productos_puntuados,
        function ( $a, $b ) use (
            $cis_prioridades_visuales
        ) {

            $cis_a = (int) (
                $cis_prioridades_visuales[$a['id']] ?? 0
            );

            $cis_b = (int) (
                $cis_prioridades_visuales[$b['id']] ?? 0
            );

            if ( $cis_a !== $cis_b ) {
                return $cis_b <=> $cis_a;
            }

            return $b['score'] <=> $a['score'];
        }
    );

    /*
    * Aplicar el filtro del perfil antes de limitar
    * los resultados a los 10 mejores productos.
    */
    $cis_perfil_resultados = '';

    if (
        isset( $_GET['cis_perfil'] )
        && is_string( $_GET['cis_perfil'] )
    ) {
        $cis_perfil_resultados = sanitize_key(
            wp_unslash( $_GET['cis_perfil'] )
        );
    }

    if ( $cis_perfil_resultados !== '' ) {

        $cis_resultados_filtrados = array();

        if (
            function_exists(
                'compuciber_perfil_permite_producto'
            )
        ) {

            foreach ( $productos_puntuados as $cis_resultado ) {

                if (
                    compuciber_perfil_permite_producto(
                        (int) $cis_resultado['id'],
                        $cis_perfil_resultados
                    )
                ) {

                    $cis_resultados_filtrados[] = $cis_resultado;

                }
            }
        }

        $productos_puntuados = $cis_resultados_filtrados;
    }

    /*
     * CAMBIO 66
     * Precisión configurable de los resultados.
     *
     * Amplia: conserva exactamente el comportamiento actual.
     * Equilibrada: 20 % del mejor resultado visible.
     * Alta: 45 % del mejor resultado visible.
     *
     * Se aplica antes de la paginación WooCommerce.
     */

    $cis_precision = get_option(
        'compuciber_precision_resultados',
        'amplia'
    );

    if (
        (
            $cis_precision === 'equilibrada'
            || $cis_precision === 'alta'
        )
        && ! empty( $productos_puntuados )
    ) {

        $cis_factor = (
            $cis_precision === 'alta'
        ) ? 0.45 : 0.20;

        $cis_mejor_visible = 0.0;

        /*
         * Buscar el producto visible mejor puntuado.
         *
         * Los productos están ordenados de mayor
         * a menor puntuación.
         *
         * No detenerse después de 40 agotados:
         * continuar hasta encontrar uno disponible.
         */

        foreach ( $productos_puntuados as $cis_candidato ) {

            $cis_producto_wc = wc_get_product(
                (int) $cis_candidato['id']
            );

            if (
                $cis_producto_wc
                && compuciber_producto_visible_en_busqueda(
                    $cis_producto_wc
                )
            ) {

                $cis_mejor_visible = (float)
                    $cis_candidato['score'];

                break;
            }
        }

        /*
         * Si no encontramos una referencia visible
         * dentro del límite, mantenemos los resultados
         * originales en lugar de arriesgar una
         * búsqueda vacía.
         */

        if ( $cis_mejor_visible > 0 ) {

            $cis_umbral = $cis_mejor_visible
                * $cis_factor;

            $productos_puntuados = array_values(
                array_filter(
                    $productos_puntuados,
                    static function ( $cis_fila ) use (
                        $cis_umbral
                    ) {

                        return (float) $cis_fila['score']
                            >= $cis_umbral;
                    }
                )
            );
        }
    }

    /*
     * CAMBIO 85: comparación visual sobre resultados reales.
     * Solo cuando la casilla está activada y existe una foto temporal.
     * Si no hay coincidencias, no modifica el orden de búsqueda.
     */
    if (
        $cis_busqueda_visual_valida
        && get_option(
            'compuciber_comparacion_visual_catalogo', '0'
        ) === '1'
        && get_option(
            'compuciber_proveedor_ia', 'gemini'
        ) === 'gemini'
        && function_exists(
            'compuciber_comparar_visual_catalogo_gemini'
        )
        && empty( $cis_datos['comparacion_visual_evaluada'] )
        && ! empty( $cis_datos['foto_temporal'] )
        && is_string( $cis_datos['foto_temporal'] )
    ) {
        $cis_ruta_foto = realpath(
            $cis_datos['foto_temporal']
        );

        $cis_directorio_temporal = realpath(
            get_temp_dir()
        );

        if (
            $cis_ruta_foto !== false
            && $cis_directorio_temporal !== false
            && dirname( $cis_ruta_foto ) === $cis_directorio_temporal
            && strpos( basename( $cis_ruta_foto ), 'cisimg_' ) === 0
            && is_file( $cis_ruta_foto )
            && is_readable( $cis_ruta_foto )
            && filesize( $cis_ruta_foto ) <= 6 * 1024 * 1024
        ) {

            /*
             * El ranking ya aplicó perfil y umbral de precisión.
             * Seleccionar candidatos visibles antes de comparar.
             */
            $cis_ids_visibles = array();

            foreach ( $productos_puntuados as $cis_fila ) {

                $cis_id = (int) $cis_fila['id'];
                $cis_wc = wc_get_product( $cis_id );

                if (
                    $cis_wc
                    && compuciber_producto_visible_en_busqueda(
                        $cis_wc
                    )
                ) {
                    $cis_ids_visibles[] = $cis_id;
                }

                if ( count( $cis_ids_visibles ) >= 100 ) {
                    break;
                }
            }

            if ( ! empty( $cis_ids_visibles ) ) {

                $cis_bytes_foto = file_get_contents(
                    $cis_ruta_foto
                );

                $cis_mime_foto = wp_get_image_mime(
                    $cis_ruta_foto
                );

                if (
                    is_string( $cis_bytes_foto )
                    && in_array(
                        $cis_mime_foto,
                        array(
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ),
                        true
                    )
                ) {

                    /*
                     * Reutilizar comparaciones de la misma
                     * fotografía y el mismo grupo de candidatos.
                     */
                    $cis_clave_comparacion = 'cis_vcmp_v2_' . substr(
                        hash(
                            'sha256',
                            hash( 'sha256', $cis_bytes_foto )
                            . '|'
                            . implode( ',', $cis_ids_visibles )
                            . '|'
                            . (string) get_option(
                                'compuciber_gemini_modelo_multimodal', ''
                            )
                        ),
                        0,
                        40
                    );

                    $cis_cache_comparacion = get_transient(
                        $cis_clave_comparacion
                    );

                    if ( is_array( $cis_cache_comparacion ) ) {

                        $cis_coincidencias = $cis_cache_comparacion;

                    } else {

                        $cis_coincidencias =
                            compuciber_comparar_visual_catalogo_gemini(
                                base64_encode( $cis_bytes_foto ),
                                $cis_mime_foto,
                                $interpretacion,
                                $cis_ids_visibles
                            );

                        if ( ! is_array( $cis_coincidencias ) ) {
                            $cis_coincidencias = array();
                        }

                        set_transient(
                            $cis_clave_comparacion,
                            $cis_coincidencias,
                            HOUR_IN_SECONDS
                        );
                    }

                    /*
                     * Guardar solo puntuaciones visuales.
                     * No almacenar la fotografía en Base64.
                     */
                    $cis_datos['coincidencias_visuales'] =
                        $cis_coincidencias;

                    $cis_datos['comparacion_visual_evaluada'] = 1;

                    unset(
                        $cis_datos['foto_temporal'],
                        $cis_datos['mime_type']
                    );

                    if ( set_transient(
                        'cis_imagen_' . $cis_token,
                        $cis_datos,
                        10 * MINUTE_IN_SECONDS
                    ) ) {
                        @unlink( $cis_ruta_foto );
                    }
                }
            }
        }
    }
    
    /*
     * CAMBIO 81: prioridad visual opcional.
     * Solo reordena productos ya encontrados y elegibles.
     * Sin puntuaciones verificadas, conserva el orden actual.
     */
    if (
        $cis_busqueda_visual_valida
        && get_option(
            'compuciber_comparacion_visual_catalogo',
            '0'
        ) === '1'
        && isset( $cis_datos['coincidencias_visuales'] )
        && is_array( $cis_datos['coincidencias_visuales'] )
    ) {
        $cis_coincidencias_verificadas = array();

        foreach ( $cis_datos['coincidencias_visuales'] as $id => $confianza ) {
            $id = absint( $id );
            $confianza = (float) $confianza;

            if (
                $id > 0
                && $confianza >= 0.90
                && $confianza <= 1.0
            ) {
                $cis_coincidencias_verificadas[ $id ] = $confianza;
            }
        }

        if ( ! empty( $cis_coincidencias_verificadas ) ) {
            $cis_posiciones_originales = array();

            foreach ( $productos_puntuados as $posicion => $fila ) {
                $cis_posiciones_originales[ (int) $fila['id'] ] = $posicion;
            }

            usort(
                $productos_puntuados,
                static function ( $a, $b ) use (
                    $cis_coincidencias_verificadas,
                    $cis_prioridades_visuales,
                    $cis_posiciones_originales
                ) {
                    $id_a = (int) $a['id'];
                    $id_b = (int) $b['id'];

                    $grupo_a = (int) (
                        $cis_prioridades_visuales[ $id_a ] ?? 0
                    );

                    $grupo_b = (int) (
                        $cis_prioridades_visuales[ $id_b ] ?? 0
                    );

                    // Respetar el cambio 75: identidad del producto.
                    if ( $grupo_a !== $grupo_b ) {
                        return $grupo_b <=> $grupo_a;
                    }

                    /*
                     * CAMBIO 87
                     * Bonificación visual controlada.
                     *
                     * Máximo 5 % del puntaje original.
                     * No altera la puntuación almacenada.
                     * No prioriza productos sin identidad válida.
                     */

                    $cis_puntaje_a = (float) $a['score'];
                    $cis_puntaje_b = (float) $b['score'];

                    if (
                        $grupo_a > 0
                        && isset(
                            $cis_coincidencias_verificadas[ $id_a ]
                        )
                    ) {
                        $cis_puntaje_a *= 1.05;
                    }

                    if (
                        $grupo_b > 0
                        && isset(
                            $cis_coincidencias_verificadas[ $id_b ]
                        )
                    ) {
                        $cis_puntaje_b *= 1.05;
                    }

                    if ( $cis_puntaje_a !== $cis_puntaje_b ) {
                        return $cis_puntaje_b <=> $cis_puntaje_a;
                    }

                    return $cis_posiciones_originales[ $id_a ]
                        <=> $cis_posiciones_originales[ $id_b ];
                }
            );
        }
    }

    /*
     * Observador opcional del diagnóstico administrativo.
     * No altera resultados ni registra búsquedas.
     */
    if ( has_action( 'compuciber_diagnostico_ranking' ) ) {

        do_action(
            'compuciber_diagnostico_ranking',
            array(
                'candidatos'    => count( $productos_candidatos ),
                'coincidencias' => count( $productos_puntuados ),
                'primeros'     => array_slice(
                    $productos_puntuados,
                    0,
                    20
                ),
            )
        );
    }    

    $ids_resultados = array();

    foreach ( $productos_puntuados as $resultado ) {
        $ids_resultados[] = $resultado['id'];
    }

    return compuciber_consulta_productos(
        $ids_resultados
    );
}