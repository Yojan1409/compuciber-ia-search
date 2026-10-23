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
                && $padre->get_status() === 'publish'
                && $padre->is_visible()
                && $producto_sku->is_visible()
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
            && $producto_sku->get_status() === 'publish'
            && $producto_sku->is_visible()
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

    $productos_candidatos = $wpdb->get_results(
        "SELECT {$columnas_indice} FROM {$tabla}"
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

    usort(
        $productos_puntuados,
        function ( $a, $b ) {

            if ( $a['score'] === $b['score'] ) {
                return 0;
            }

            return ( $a['score'] > $b['score'] )
                ? -1
                : 1;
        }
    );

    $productos_puntuados = array_slice(
        $productos_puntuados,
        0,
        10
    );

    $ids_resultados = array();

    foreach ( $productos_puntuados as $resultado ) {
        $ids_resultados[] = $resultado['id'];
    }

    return compuciber_consulta_productos(
        $ids_resultados
    );
}