<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * AJAX: obtiene sugerencias de productos.
 */
function compuciber_buscar_sugerencias() {

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

    foreach (
        $palabras_termino as $palabra
    ) {

        if ( empty( $palabra ) ) {
            continue;
        }

        $condiciones[] =
            'i.nombre LIKE %s';

        $valores[] =
            '%'
            . $wpdb->esc_like(
                $palabra
            )
            . '%';
    }

    if ( empty( $condiciones ) ) {
        wp_send_json_success( array() );
    }


    $sql =
        "SELECT
            i.id,
            i.nombre,
            i.url,
            i.imagen
        FROM {$tabla} AS i
        INNER JOIN {$wpdb->posts} AS p
            ON p.ID = i.id
        WHERE
            p.post_type = 'product'
            AND p.post_status = 'publish'
            AND "
            . implode(
                ' AND ',
                $condiciones
            )
            . "
        ORDER BY i.nombre ASC";


    $productos =
        $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $valores
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

        if ( ! $producto_wc ) {
            continue;
        }


        /*
         * ==================================================
         * TÍTULO
         * ==================================================
         */

        $titulo =
            $producto->nombre;

        $titulo_normalizado =
            compuciber_normalizar_texto(
                $titulo
            );


        /*
         * ==================================================
         * COINCIDENCIA
         * ==================================================
         */

        $coincide = true;

        foreach (
            $palabras_termino as $palabra
        ) {

            if ( empty( $palabra ) ) {
                continue;
            }

            $patron =
                '/(?<![a-z0-9])'
                . preg_quote(
                    $palabra,
                    '/'
                )
                . '/i';

            if (
                ! preg_match(
                    $patron,
                    $titulo_normalizado
                )
            ) {

                $coincide =
                    false;

                break;
            }
        }

        if ( ! $coincide ) {
            continue;
        }


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