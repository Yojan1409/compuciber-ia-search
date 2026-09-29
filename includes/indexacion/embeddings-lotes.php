<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene el proveedor y modelo de embeddings
 * que deben utilizarse actualmente.
 *
 * @return array
 */
function compuciber_obtener_configuracion_embeddings() {

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    if ( $proveedor === 'openai' ) {

        $modelo = get_option(
            'compuciber_modelo_embeddings',
            'text-embedding-3-small'
        );

    } else {

        $modelo = 'gemini-embedding-2';
    }

    return array(
        'proveedor' => $proveedor,
        'modelo'    => $modelo,
    );
}


/**
 * Obtiene el estado real de los embeddings
 * almacenados en el índice.
 *
 * Un embedding se considera actualizado
 * únicamente cuando:
 *
 * - existe un vector;
 * - pertenece al proveedor actual;
 * - pertenece al modelo actual;
 * - su hash corresponde al texto semántico actual.
 *
 * @return array
 */
function compuciber_obtener_estado_embeddings() {

    global $wpdb;

    $tabla =
        $wpdb->prefix
        . 'compuciber_product_index';

    $configuracion =
        compuciber_obtener_configuracion_embeddings();

    $proveedor_actual =
        $configuracion['proveedor'];

    $modelo_actual =
        $configuracion['modelo'];


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
    );


    if ( empty( $productos ) ) {
        return $estado;
    }


    foreach ( $productos as $producto ) {

        $estado['total']++;

        $texto_embedding = trim(
            (string) $producto->texto_embedding
        );


        /*
         * Sin texto semántico no existe
         * un embedding válido.
         */
        if ( $texto_embedding === '' ) {

            $estado['pendientes']++;

            continue;
        }


        $hash_actual = hash(
            'sha256',
            $texto_embedding
        );


        $embedding_valido =
            ! empty( $producto->embedding )
            && (string) $producto->embedding_proveedor
                === (string) $proveedor_actual
            && (string) $producto->embedding_modelo
                === (string) $modelo_actual
            && hash_equals(
                (string) $producto->hash_embedding,
                $hash_actual
            );


        if ( $embedding_valido ) {

            $estado['generados']++;

        } else {

            $estado['pendientes']++;
        }
    }


    return $estado;
}

/**
 * Genera embeddings para un lote limitado
 * de productos del índice.
 *
 * Procesa productos que:
 * - no tienen embedding;
 * - tienen un embedding de otro proveedor;
 * - tienen un embedding de otro modelo;
 * - tienen un embedding desactualizado.
 *
 * @param int $limite Cantidad máxima por lote.
 *
 * @return array
 */
function compuciber_generar_lote_embeddings(
    $limite = 5
) {

    global $wpdb;

    $tabla =
        $wpdb->prefix
        . 'compuciber_product_index';


    $configuracion =
        compuciber_obtener_configuracion_embeddings();

    $embedding_proveedor =
        $configuracion['proveedor'];

    $embedding_modelo =
        $configuracion['modelo'];


    /*
     * Normalizar tamaño del lote.
     */
    $limite = absint( $limite );

    if ( $limite < 1 ) {
        $limite = 1;
    }

    /*
     * Evitamos lotes excesivamente grandes.
     */
    if ( $limite > 20 ) {
        $limite = 20;
    }


    /*
     * Obtenemos candidatos del índice.
     *
     * La validación del hash se realiza
     * posteriormente en PHP para no depender
     * de funciones SHA de la base de datos.
     */
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


    $productos_pendientes = array();


    /*
     * Determinar qué productos necesitan
     * generar o regenerar su embedding.
     */
    if ( ! empty( $candidatos ) ) {

        foreach ( $candidatos as $producto ) {

            $texto_embedding = trim(
                (string) $producto->texto_embedding
            );

            /*
             * Si no existe texto semántico,
             * no podemos generar un embedding.
             */
            if ( $texto_embedding === '' ) {
                continue;
            }


            $hash_actual = hash(
                'sha256',
                $texto_embedding
            );


            $embedding_vacio =
                empty( $producto->embedding );

            $proveedor_distinto =
                (string) $producto->embedding_proveedor
                !==
                (string) $embedding_proveedor;

            $modelo_distinto =
                (string) $producto->embedding_modelo
                !==
                (string) $embedding_modelo;

            $hash_distinto =
                ! hash_equals(
                    (string) $producto->hash_embedding,
                    $hash_actual
                );


            if (
                $embedding_vacio
                || $proveedor_distinto
                || $modelo_distinto
                || $hash_distinto
            ) {

                $productos_pendientes[] =
                    absint( $producto->id );
            }
        }
    }


    /*
     * Cantidad pendiente antes de procesar
     * este lote.
     */
    $total_pendientes =
        count( $productos_pendientes );


    /*
     * Procesamos únicamente la cantidad
     * correspondiente al lote actual.
     */
    $productos_lote = array_slice(
        $productos_pendientes,
        0,
        $limite
    );


    $resultado = array(
        'procesados' => 0,
        'generados'  => 0,
        'fallidos'   => 0,
        'pendientes' => $total_pendientes,
    );


    if ( ! empty( $productos_lote ) ) {

        foreach ( $productos_lote as $product_id ) {

            $product_id =
                absint( $product_id );

            if ( ! $product_id ) {
                continue;
            }


            $resultado['procesados']++;


            /*
             * El indexador normal se encarga
             * de generar o regenerar el vector.
             */
            compuciber_indexar_producto(
                $product_id,
                true
            );


            /*
             * Volvemos a leer la información
             * después de intentar generar
             * el embedding.
             */
            $embedding_generado =
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


            $generacion_correcta = false;


            if (
                $embedding_generado
                && ! empty(
                    $embedding_generado->embedding
                )
            ) {

                $texto_actual = trim(
                    (string)
                    $embedding_generado->texto_embedding
                );

                $hash_actual = hash(
                    'sha256',
                    $texto_actual
                );


                $hash_correcto =
                    hash_equals(
                        (string)
                        $embedding_generado->hash_embedding,
                        $hash_actual
                    );

                $proveedor_correcto =
                    (string)
                    $embedding_generado->embedding_proveedor
                    ===
                    (string) $embedding_proveedor;

                $modelo_correcto =
                    (string)
                    $embedding_generado->embedding_modelo
                    ===
                    (string) $embedding_modelo;


                if (
                    $hash_correcto
                    && $proveedor_correcto
                    && $modelo_correcto
                ) {

                    $generacion_correcta = true;
                }
            }


            if ( $generacion_correcta ) {

                $resultado['generados']++;

            } else {

                $resultado['fallidos']++;
            }
        }
    }


    /*
     * Recalcular pendientes después
     * de procesar el lote.
     */
    $candidatos_finales = $wpdb->get_results(
        "SELECT
            texto_embedding,
            hash_embedding,
            embedding,
            embedding_proveedor,
            embedding_modelo
        FROM {$tabla}"
    );


    $pendientes_finales = 0;


    if ( ! empty( $candidatos_finales ) ) {

        foreach ( $candidatos_finales as $producto ) {

            $texto_embedding = trim(
                (string) $producto->texto_embedding
            );

            if ( $texto_embedding === '' ) {
                continue;
            }


            $hash_actual = hash(
                'sha256',
                $texto_embedding
            );


            if (
                empty( $producto->embedding )
                || (string) $producto->embedding_proveedor
                    !== (string) $embedding_proveedor
                || (string) $producto->embedding_modelo
                    !== (string) $embedding_modelo
                || ! hash_equals(
                    (string) $producto->hash_embedding,
                    $hash_actual
                )
            ) {

                $pendientes_finales++;
            }
        }
    }


    $resultado['pendientes'] =
        $pendientes_finales;


    return $resultado;
}