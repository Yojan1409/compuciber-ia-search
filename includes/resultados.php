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

    error_log( 'RESULTADOS.PHP NUEVO EJECUTADO' );

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $productos_candidatos = $wpdb->get_results(
        "SELECT * FROM {$tabla}"
    );

    if ( empty( $productos_candidatos ) ) {
        return compuciber_consulta_vacia();
    }

    error_log(
        'INTERPRETACION RECIBIDA: '
        . print_r( $interpretacion, true )
    );    
    
    $datos_ia = compuciber_preparar_datos_ia(
        $interpretacion,
        $busqueda
    );

    error_log(
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

    if (
        function_exists(
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
                $datos_ia
            );

        /*
        * Si el motor estructurado descartó el producto,
        * el embedding no puede volver a introducirlo.
        *
        * Esto conserva filtros fuertes como:
        * producto, categoría, precio, características
        * técnicas, marca/modelo y anclas.
        */
        if ( $score <= 0 ) {
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
            && isset( $producto->embedding_proveedor )
            && isset( $producto->embedding_modelo )
        ) {

            $proveedor_actual = get_option(
                'compuciber_proveedor_ia',
                'gemini'
            );

            if ( $proveedor_actual === 'openai' ) {

                $modelo_actual = get_option(
                    'compuciber_modelo_embeddings',
                    'text-embedding-3-small'
                );

            } else {

                $modelo_actual =
                    'gemini-embedding-2';
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
                    error_log(
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
                    if ( $similitud_semantica >= 0.45 ) {

                        $score +=
                            $similitud_semantica * 30;
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