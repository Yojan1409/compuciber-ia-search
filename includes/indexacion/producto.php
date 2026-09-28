<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Indexa un producto.
 */
function compuciber_indexar_producto( $product_id ) {

    if ( get_post_type( $product_id ) !== 'product' ) {
        return;
    }

    $producto = wc_get_product( $product_id );

    if ( ! $producto ) {
        return;
    }

    if ( $producto->get_status() !== 'publish' ) {
        return;
    }

    global $wpdb;

    $tabla =
        $wpdb->prefix .
        'compuciber_product_index';


    /*
     * Datos principales.
     */
    $nombre = $producto->get_name();

    $sku = $producto->get_sku();

    $marca =
        compuciber_obtener_marca_producto(
            $product_id
        );

    $modelo =
        compuciber_obtener_modelo_producto(
            $product_id
        );


    /*
     * Categorías.
     */
    $categorias = wp_get_post_terms(
        $product_id,
        'product_cat',
        array(
            'fields' => 'names',
        )
    );

    if (
        is_wp_error( $categorias )
        || empty( $categorias )
    ) {
        $categorias = array();
    }


    /*
     * Atributos.
     */
    $atributos =
        compuciber_obtener_atributos_producto(
            $producto
        );


    /*
     * Etiquetas.
     */
    $etiquetas = wp_get_post_terms(
        $product_id,
        'product_tag',
        array(
            'fields' => 'names',
        )
    );

    if (
        is_wp_error( $etiquetas )
        || empty( $etiquetas )
    ) {
        $etiquetas = array();
    }


    /*
     * Descripción y contenido.
     */
    $descripcion =
        $producto->get_description();

    $descripcion_corta =
        $producto->get_short_description();

    $contenido =
        trim(
            wp_strip_all_tags(
                $descripcion .
                ' ' .
                $descripcion_corta
            )
        );


    /*
     * Precio.
     */
    $precio =
        $producto->get_regular_price();

    $precio_oferta =
        $producto->get_sale_price();


    /*
     * Stock.
     */
    $stock =
        $producto->get_stock_quantity();

    if ( $stock === null ) {
        $stock =
            $producto->get_stock_status();
    }


    /*
     * Variaciones.
     */
    $variaciones =
        compuciber_obtener_variaciones_producto(
            $product_id
        );


    /*
     * Imagen principal.
     */
    $imagen_id =
        $producto->get_image_id();

    $imagen = '';

    if ( $imagen_id ) {

        $imagen =
            wp_get_attachment_url(
                $imagen_id
            );
    }

    /*
    * Galería de imágenes.
    */
    $galeria_ids =
        $producto->get_gallery_image_ids();

    $galeria_urls = array();

    if ( ! empty( $galeria_ids ) ) {

        foreach ( $galeria_ids as $galeria_id ) {

            $galeria_url =
                wp_get_attachment_url(
                    $galeria_id
                );

            if ( $galeria_url ) {
                $galeria_urls[] = $galeria_url;
            }
        }
    }

    $galeria = implode(
        ' | ',
        $galeria_urls
    );


    /*
    * Metadatos seleccionados.
    *
    * Por defecto la lista está vacía.
    * Otros desarrolladores pueden añadir claves
    * mediante el filtro:
    * compuciber_metadatos_indexables
    */
    $metadatos_permitidos = apply_filters(
        'compuciber_metadatos_indexables',
        array(),
        $product_id,
        $producto
    );

    $metadatos_indexados = array();

    if ( is_array( $metadatos_permitidos ) ) {

        foreach ( $metadatos_permitidos as $meta_key ) {

            $meta_key =
                sanitize_key(
                    $meta_key
                );

            if ( $meta_key === '' ) {
                continue;
            }

            $meta_value =
                get_post_meta(
                    $product_id,
                    $meta_key,
                    true
                );

            if (
                $meta_value === ''
                || $meta_value === null
                || is_array( $meta_value )
                || is_object( $meta_value )
            ) {
                continue;
            }

            $metadatos_indexados[] =
                $meta_key . ': ' .
                sanitize_text_field(
                    (string) $meta_value
                );
        }
    }

    $metadatos = implode(
        ' | ',
        $metadatos_indexados
    );    


    /*
     * URL.
     */
    $url =
        get_permalink( $product_id );


    /*
     * Texto utilizado para búsqueda.
     */
    $texto_busqueda = implode(
        ' ',
        array(
            $nombre,
            $sku,
            $marca,
            $modelo,
            implode(
                ' ',
                $categorias
            ),
            $atributos,
            implode(
                ' ',
                $etiquetas
            ),
            $contenido,
            $variaciones,
        )
    );


    /*
     * Guardar / actualizar índice.
     */
    $wpdb->replace(
        $tabla,
        array(
            'id' => $product_id,

            'nombre' => $nombre,

            'sku' => $sku,

            'marca' => $marca,

            'modelo' => $modelo,

            'categorias' => implode(
                ', ',
                $categorias
            ),

            'atributos' => $atributos,

            'etiquetas' => implode(
                ', ',
                $etiquetas
            ),

            'contenido' => $contenido,

            'precio' => $precio !== ''
                ? (float) $precio
                : 0,

            'precio_oferta' => $precio_oferta !== ''
                ? (float) $precio_oferta
                : 0,

            'stock' => (string) $stock,

            'variaciones' => $variaciones,

            'texto_busqueda' => $texto_busqueda,

            'url' => $url,

            'imagen' => $imagen,

            'galeria' => $galeria,

            'metadatos' => $metadatos,

            'actualizado' => current_time(
                'mysql'
            ),
        ),
        array(
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%f',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        )
    );
}