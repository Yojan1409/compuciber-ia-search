<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Propone una corrección local usando palabras de productos
 * publicados. No consulta proveedores de IA.
 */
function compuciber_sugerencia_fuzzy_catalogo( $consulta ) {
    global $wpdb;

    $consulta = compuciber_normalizar_texto( $consulta );

    $palabras = array_slice(
        compuciber_obtener_palabras( $consulta ),
        0,
        3
    );

    if ( empty( $palabras ) ) {
        return '';
    }

    /*
     * CACHÉ DE LA CONSULTA
     *
     * Evita repetir cálculos de corrección
     * para la misma palabra.
     */
    $clave_consulta =
        'cis_fuzzy_consulta_v2_' . md5( $consulta );

    $respuesta_cache = get_transient(
        $clave_consulta
    );

    if (
        is_array( $respuesta_cache )
        && isset( $respuesta_cache['propuesta'] )
    ) {
        return (string) $respuesta_cache['propuesta'];
    }

    /*
     * VOCABULARIO REAL DE LA TIENDA
     *
     * Se genera a partir del índice WooCommerce.
     * No utiliza un diccionario fijo de marcas.
     */
    $clave_vocabulario = 'cis_fuzzy_vocabulario_v2';

    $vocabulario = get_transient(
        $clave_vocabulario
    );

    if ( ! is_array( $vocabulario ) ) {

        $tabla =
            $wpdb->prefix . 'compuciber_product_index';

        $filas = $wpdb->get_results(
            "SELECT i.nombre, i.marca, i.categorias
             FROM {$tabla} AS i
             INNER JOIN {$wpdb->posts} AS p
                 ON p.ID = i.id
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'"
        );

        $palabras_catalogo = array();

        foreach ( (array) $filas as $fila ) {

            $texto =
                (string) $fila->nombre . ' '
                . (string) $fila->marca . ' '
                . (string) $fila->categorias;

            foreach (
                compuciber_obtener_palabras( $texto )
                as $palabra
            ) {

                if (
                    preg_match(
                        '/^[a-z]{4,30}$/',
                        $palabra
                    )
                ) {
                    $palabras_catalogo[ $palabra ] = true;
                }
            }
        }

        $vocabulario = array_keys(
            $palabras_catalogo
        );

        set_transient(
            $clave_vocabulario,
            $vocabulario,
            15 * MINUTE_IN_SECONDS
        );
    }

    $vocabulario_exacto = array_fill_keys(
        $vocabulario,
        true
    );

    $propuesta = '';

    /*
     * CORRECCIÓN APROXIMADA
     */
    foreach ( $palabras as $incorrecta ) {

        // No modificar códigos, números ni
        // palabras ya presentes en el catálogo.
        if (
            ! preg_match(
                '/^[a-z]{4,30}$/',
                $incorrecta
            )
            || isset(
                $vocabulario_exacto[ $incorrecta ]
            )
        ) {
            continue;
        }

        $max_distancia =
            strlen( $incorrecta ) <= 5 ? 1 : 2;

        $menor_distancia = $max_distancia + 1;

        $mejor = '';
        $empate = false;

        foreach ( $vocabulario as $candidata ) {

            // Descartar palabras con longitudes
            // demasiado diferentes.
            if (
                abs(
                    strlen( $incorrecta )
                    - strlen( $candidata )
                ) > $max_distancia
            ) {
                continue;
            }

            $distancia = levenshtein(
                $incorrecta,
                $candidata
            );

            if (
                $distancia > 0
                && $distancia < $menor_distancia
            ) {

                $menor_distancia = $distancia;
                $mejor = $candidata;
                $empate = false;

            } elseif (
                $distancia === $menor_distancia
                && $mejor !== $candidata
            ) {

                $empate = true;
            }
        }

        // Evitar correcciones ambiguas.
        if ( $mejor !== '' && ! $empate ) {

            $propuesta = preg_replace(
                '/(?<![a-z0-9])'
                    . preg_quote( $incorrecta, '/' )
                    . '(?![a-z0-9])/',
                $mejor,
                $consulta,
                1
            );

            break;
        }
    }

    /*
     * GUARDAR RESULTADO POR 10 MINUTOS
     */
    set_transient(
        $clave_consulta,
        array(
            'propuesta' => $propuesta
        ),
        10 * MINUTE_IN_SECONDS
    );

    return $propuesta;
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
    * SKU EXACTO DE VARIACIONES
    * Prioridad en el autocompletado.
    */

    $sku_exacto = trim(
        sanitize_text_field(
            wp_unslash( $_GET['term'] )
        )
    );

    $id_sku = function_exists( 'wc_get_product_id_by_sku' )
        ? wc_get_product_id_by_sku( $sku_exacto )
        : 0;

    if ( $id_sku ) {

        $variacion = wc_get_product( $id_sku );

        if (
            $variacion
            && $variacion->is_type( 'variation' )
        ) {

            $padre = wc_get_product(
                $variacion->get_parent_id()
            );

            if (
                ! $padre
                || $padre->get_status() !== 'publish'
                || ! $padre->is_visible()
                || ! $variacion->is_visible()
                || ! $variacion->variation_is_visible()
            ) {
                wp_send_json_success( array() );
            }

            // Imagen propia de la variación o del padre.

            $id_imagen = $variacion->get_image_id();

            if ( ! $id_imagen ) {
                $id_imagen = $padre->get_image_id();
            }

            $imagen = $id_imagen
                ? wp_get_attachment_image_url(
                    $id_imagen,
                    'thumbnail'
                )
                : '';

            if ( ! $imagen ) {
                $imagen = wc_placeholder_img_src(
                    'thumbnail'
                );
            }

            // Atributos específicos de esta variación.

            $atributos = array();

            foreach (
                $variacion->get_attributes()
                as $clave => $valor
            ) {

                if ( $valor === '' ) {
                    continue;
                }

                $nombre = wc_attribute_label( $clave );

                if ( taxonomy_exists( $clave ) ) {

                    $termino_atributo = get_term_by(
                        'slug',
                        $valor,
                        $clave
                    );

                    if (
                        $termino_atributo
                        && ! is_wp_error( $termino_atributo )
                    ) {
                        $valor = $termino_atributo->name;
                    }
                }

                $atributos[] = $nombre . ': ' . $valor;
            }

            // Respuesta compatible con el JavaScript actual.

            wp_send_json_success(
                array(
                    array(
                        'id' => $padre->get_id(),
                        'title' => $variacion->get_name(),
                        'url' => $variacion->get_permalink(),
                        'image' => $imagen,
                        'attributes' => $atributos,
                        'price' => $variacion->get_price_html(),
                        'availability' => $variacion->is_in_stock()
                            ? 'Disponible'
                            : 'Agotado',
                    ),
                )
            );
        }
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

    /*
     * SINÓNIMOS EN AUTOCOMPLETADO
     *
     * Conserva la búsqueda original y añade
     * los sinónimos como alternativas.
     */

    $alternativas = array();

    foreach ( compuciber_obtener_sinonimos() as $principal => $variantes ) {

        $grupo = array_merge(
            array( $principal ),
            is_array( $variantes ) ? $variantes : array()
        );

        $grupo_normalizado = array();

        foreach ( $grupo as $valor_grupo ) {

            $valor = compuciber_normalizar_texto( $valor_grupo );

            if ( $valor !== '' ) {
                $grupo_normalizado[] = $valor;
            }
        }

        $grupo_normalizado = array_unique( $grupo_normalizado );

        foreach ( $grupo_normalizado as $origen ) {

            if (
                ! compuciber_contiene_sinonimo(
                    $termino_corregido,
                    $origen
                )
            ) {
                continue;
            }

            foreach ( $grupo_normalizado as $destino ) {

                if ( $origen === $destino ) {
                    continue;
                }

                $alternativa = preg_replace(
                    '/(?<![a-z0-9])'
                    . preg_quote( $origen, '/' )
                    . '(?![a-z0-9])/u',
                    $destino,
                    $termino_corregido
                );

                if (
                    is_string( $alternativa )
                    && $alternativa !== $termino_corregido
                ) {
                    $alternativas[] = $alternativa;
                }
            }
        }
    }

    // Conservar la búsqueda original.
    $condiciones = array(
        '(' . implode( ' AND ', $condiciones ) . ')'
    );

    // Limitar alternativas para proteger el rendimiento.
    $alternativas = array_slice(
        array_unique( $alternativas ),
        0,
        15
    );

    foreach ( $alternativas as $consulta_alternativa ) {

        $palabras_alternativa = preg_split(
            '/\s+/u',
            $consulta_alternativa,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ( empty( $palabras_alternativa ) ) {
            continue;
        }

        $grupo_sql = array();

        foreach ( $palabras_alternativa as $palabra ) {

            $grupo_sql[] = 'i.texto_busqueda LIKE %s';

            $valores[] = '%'
                . $wpdb->esc_like( $palabra )
                . '%';
        }

        $condiciones[] = '('
            . implode( ' AND ', $grupo_sql )
            . ')';
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
            AND (" . implode( ' OR ', $condiciones ) . ")
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

$consulta_sugerida = '';

    if ( get_option( 'compuciber_correccion', '1' ) === '1' ) {

        // Conservar también las correcciones ya configuradas.
        if (
            compuciber_normalizar_texto( $termino )
            !== $termino_corregido
        ) {
            $consulta_sugerida = $termino_corregido;
        }

        // Intentar fuzzy solo si no hay sugerencias normales.
        if ( empty( $productos ) ) {

            $propuesta = compuciber_sugerencia_fuzzy_catalogo(
                $termino_corregido
            );

            if (
                $propuesta !== ''
                && $propuesta !== $termino_corregido
            ) {

                $filtros = array();
                $parametros = array();

                foreach (
                    compuciber_obtener_palabras( $propuesta )
                    as $palabra
                ) {

                    $filtros[] = 'i.texto_busqueda LIKE %s';

                    $parametros[] = '%'
                        . $wpdb->esc_like( $palabra )
                        . '%';
                }

                if ( ! empty( $filtros ) ) {

                    $productos_fuzzy = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT i.id, i.nombre, i.url, i.imagen
                             FROM {$tabla} AS i
                             INNER JOIN {$wpdb->posts} AS p ON p.ID = i.id
                             WHERE p.post_type = 'product'
                               AND p.post_status = 'publish'
                               AND (" . implode( ' AND ', $filtros ) . ")
                             ORDER BY i.nombre ASC
                             LIMIT 100",
                            $parametros
                        )
                    );

                    if ( ! empty( $productos_fuzzy ) ) {

                        $productos = $productos_fuzzy;

                        $consulta_sugerida = $propuesta;
                    }
                }
            }
        }
    }    

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

    if (
        $consulta_sugerida !== ''
        && ! empty( $resultados )
    ) {
        $resultados[0]['suggested_term'] =
            $consulta_sugerida;
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