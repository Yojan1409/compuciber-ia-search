<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * AJAX: obtiene sugerencias de productos.
 */
function compuciber_buscar_sugerencias() {
    if (
        get_option( 'compuciber_autocompletado', '1' ) !== '1'
    ) {
        wp_send_json_success( array() );
    }

    if ( ! isset( $_GET['term'] ) ) {
        wp_send_json_success( array() );
    }

    $termino = sanitize_text_field(
        wp_unslash( $_GET['term'] )
    );

    $termino = trim(
        strtolower( $termino )
    );

    if ( strlen( $termino ) < 2 ) {
        wp_send_json_success( array() );
    }

    /*
     * ==================================================
     * CORRECCIÓN Y NORMALIZACIÓN
     * ==================================================
     */

    $termino_corregido =
        compuciber_corregir_busqueda(
            $termino
        );

    $termino_corregido =
        compuciber_normalizar_texto(
            $termino_corregido
        );

    $palabras_termino =
        preg_split(
            '/\s+/',
            $termino_corregido
        );

    if ( empty( $palabras_termino ) ) {
        wp_send_json_success( array() );
    }


    /*
     * ==================================================
     * BUSCAR EN EL ÍNDICE DE PRODUCTOS
     * ==================================================
     */

    global $wpdb;

    $tabla =
        $wpdb->prefix
        . 'compuciber_product_index';

    $condiciones = array();
    $valores = array();

    // Buscar cada palabra en todos los datos indexados.
    foreach ( $palabras_termino as $palabra ) {

        if ( $palabra === '' ) {
            continue;
        }

        $condiciones[] = 'i.texto_busqueda LIKE %s';

        $valores[] = '%' . $wpdb->esc_like( $palabra ) . '%';
    }

    if ( empty( $condiciones ) ) {
        wp_send_json_success( array() );
    }

    $termino_like = '%' . $wpdb->esc_like( $termino_corregido ) . '%';

    // Prioridades para ordenar las sugerencias.
    $valores_orden = array(
        $termino_corregido, // SKU exacto.
        $termino_corregido, // Modelo exacto.
        $termino_corregido, // Nombre exacto.
        $termino_like,      // Frase en el nombre.
        $termino_like,      // SKU parcial.
        $termino_like,      // Modelo parcial.
        $termino_like,      // Marca.
    );

    $sql = "SELECT
                i.id, i.nombre, i.url, i.imagen
            FROM {$tabla} AS i
            INNER JOIN {$wpdb->posts} AS p
                ON p.ID = i.id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND " . implode( ' AND ', $condiciones ) . "
            ORDER BY CASE
                WHEN i.sku = %s THEN 0
                WHEN i.modelo = %s THEN 1
                WHEN i.nombre = %s THEN 2
                WHEN i.nombre LIKE %s THEN 3
                WHEN i.sku LIKE %s THEN 4
                WHEN i.modelo LIKE %s THEN 5
                WHEN i.marca LIKE %s THEN 6
                ELSE 7
            END, i.nombre ASC
            LIMIT 100";

    $productos = $wpdb->get_results(
        $wpdb->prepare(
            $sql,
            array_merge( $valores, $valores_orden )
        )
    );


    /*
     * ==================================================
     * RESULTADOS
     * ==================================================
     */

    $resultados = array();


    foreach (
        $productos as $producto
    ) {

        $producto_id =
            (int) $producto->id;

        $producto_wc =
            wc_get_product(
                $producto_id
            );

        if ( ! $producto_wc || ! $producto_wc->is_visible() ) {
            continue;
        }


        /*
         * ==================================================
         * TÍTULO
         * ==================================================
         */

        $titulo =
            $producto->nombre;


        /*
         * ==================================================
         * IMAGEN
         * ==================================================
         */

        $imagen =
            $producto->imagen;

        if ( ! $imagen ) {

            $imagen =
                get_the_post_thumbnail_url(
                    $producto_id,
                    'thumbnail'
                );
        }

        if ( ! $imagen ) {

            $imagen =
                wc_placeholder_img_src(
                    'thumbnail'
                );
        }


        /*
         * ==================================================
         * PRECIO
         * ==================================================
         */

        $precio =
            $producto_wc->get_price_html();


        /*
         * ==================================================
         * DISPONIBILIDAD
         * ==================================================
         */

        if (
            $producto_wc->is_in_stock()
        ) {

            $disponibilidad =
                'Disponible';

        } else {

            $disponibilidad =
                'Agotado';
        }


        /*
         * ==================================================
         * ATRIBUTOS
         * ==================================================
         */

        $atributos = array();

        foreach (
            $producto_wc->get_attributes()
            as $atributo
        ) {

            if (
                ! $atributo->get_visible()
            ) {
                continue;
            }

            $nombre =
                wc_attribute_label(
                    $atributo->get_name()
                );

            $valores = array();

            if (
                $atributo->is_taxonomy()
            ) {

                $valores =
                    wc_get_product_terms(
                        $producto_id,
                        $atributo->get_name(),
                        array(
                            'fields' => 'names',
                        )
                    );

            } else {

                $opciones =
                    $atributo->get_options();

                foreach (
                    $opciones as $opcion
                ) {

                    $valores[] =
                        wp_strip_all_tags(
                            $opcion
                        );
                }
            }

            if (
                empty( $valores )
            ) {
                continue;
            }

            $atributos[] =
                $nombre
                . ': '
                . implode(
                    ', ',
                    $valores
                );

            
            if (
                count( $atributos ) >= 3
            ) {
                break;
            }
        }


        /*
         * ==================================================
         * RESULTADO
         * ==================================================
         */

        $resultados[] = array(

            'id' =>
                $producto_id,        

            'title' =>
                $titulo,

            'url' =>
                $producto->url
                ? $producto->url
                : get_permalink(
                    $producto_id
                ),

            'image' =>
                $imagen,

            'attributes' =>
                $atributos,

            'price' =>
                $precio,

            'availability' =>
                $disponibilidad,
        );


        /*
         * ==================================================
         * LÍMITE VISUAL
         * ==================================================
         */

        if (
            count( $resultados ) >= 5
        ) {
            break;
        }
    }


    wp_send_json_success(
        $resultados
    );
}


add_action(
    'wp_ajax_compuciber_sugerencias',
    'compuciber_buscar_sugerencias'
);


add_action(
    'wp_ajax_nopriv_compuciber_sugerencias',
    'compuciber_buscar_sugerencias'
);