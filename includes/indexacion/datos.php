<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Obtiene la marca del producto.
 */
function compuciber_obtener_marca_producto( $product_id ) {

    $taxonomias = array(
        'product_brand',
        'pa_brand',
        'marca',
        'pa_marca',
    );

    foreach ( $taxonomias as $taxonomy ) {

        if ( taxonomy_exists( $taxonomy ) ) {

            $terminos = get_the_terms(
                $product_id,
                $taxonomy
            );

            if (
                ! empty( $terminos )
                && ! is_wp_error( $terminos )
            ) {

                $marcas = array();

                foreach ( $terminos as $termino ) {
                    $marcas[] = $termino->name;
                }

                if ( ! empty( $marcas ) ) {
                    return implode( ', ', $marcas );
                }
            }
        }
    }

    return '';
}


/**
 * Obtiene el modelo del producto.
 */
function compuciber_obtener_modelo_producto( $product_id ) {

    $taxonomias = array(
        'pa_modelo',
        'modelo',
    );

    foreach ( $taxonomias as $taxonomy ) {

        if ( taxonomy_exists( $taxonomy ) ) {

            $terminos = get_the_terms(
                $product_id,
                $taxonomy
            );

            if (
                ! empty( $terminos )
                && ! is_wp_error( $terminos )
            ) {

                $modelos = array();

                foreach ( $terminos as $termino ) {
                    $modelos[] = $termino->name;
                }

                if ( ! empty( $modelos ) ) {
                    return implode( ', ', $modelos );
                }
            }
        }
    }

    return '';
}


/**
 * Obtiene los atributos del producto.
 */
function compuciber_obtener_atributos_producto( $producto ) {

    $atributos = array();

    foreach ( $producto->get_attributes() as $atributo ) {

        $nombre = $atributo->get_name();

        $valores = array();

        if ( $atributo->is_taxonomy() ) {

            $terminos = wp_get_post_terms(
                $producto->get_id(),
                $nombre,
                array(
                    'fields' => 'names',
                )
            );

            if (
                ! is_wp_error( $terminos )
                && ! empty( $terminos )
            ) {
                $valores = $terminos;
            }

        } else {

            $opciones = $atributo->get_options();

            if ( ! empty( $opciones ) ) {
                $valores = $opciones;
            }
        }

        if ( ! empty( $valores ) ) {

            $atributos[] =
                $nombre . ': ' .
                implode( ', ', $valores );
        }
    }

    return implode( ' | ', $atributos );
}


/**
 * Obtiene las variaciones del producto.
 */
function compuciber_obtener_variaciones_producto( $producto_id ) {

    $producto = wc_get_product( $producto_id );

    if (
        ! $producto
        || ! $producto->is_type( 'variable' )
    ) {
        return '';
    }

    $variaciones = array();

    foreach ( $producto->get_children() as $variation_id ) {

        $variacion = wc_get_product( $variation_id );

        if ( ! $variacion ) {
            continue;
        }

        $datos = array();

        $datos[] = 'ID: ' . $variation_id;

        $atributos = $variacion->get_attributes();

        if ( ! empty( $atributos ) ) {

            $lista_atributos = array();

            foreach ( $atributos as $nombre => $valor ) {

                $nombre_limpio = str_replace(
                    'attribute_',
                    '',
                    $nombre
                );

                $lista_atributos[] =
                    $nombre_limpio . ': ' . $valor;
            }

            if ( ! empty( $lista_atributos ) ) {

                $datos[] = implode(
                    ', ',
                    $lista_atributos
                );
            }
        }

        $sku = $variacion->get_sku();

        if ( $sku !== '' ) {
            $datos[] = 'SKU: ' . $sku;
        }

        $precio = $variacion->get_regular_price();

        if ( $precio !== '' ) {
            $datos[] = 'Precio: ' . $precio;
        }

        $precio_oferta = $variacion->get_sale_price();

        if ( $precio_oferta !== '' ) {
            $datos[] = 'Oferta: ' . $precio_oferta;
        }

        $stock = $variacion->get_stock_quantity();

        if ( $stock !== null ) {

            $datos[] = 'Stock: ' . $stock;

        } else {

            $datos[] =
                'Stock: ' .
                $variacion->get_stock_status();
        }

        $variaciones[] = implode(
            ' | ',
            $datos
        );
    }

    return implode(
        ' || ',
        $variaciones
    );
}