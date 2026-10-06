<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/tecnicos.php';

function compuciber_preparar_datos_ia(
    $interpretacion,
    $busqueda = ''
) {

    $datos = array(
        'producto'          => array(),
        'marca'             => array(),
        'modelo'            => array(),
        'caracteristicas'   => array(),
        'palabras_clave'    => array(),
        'precio_minimo'     => null,
        'precio_maximo'     => null,
    );

    if ( ! is_array( $interpretacion ) ) {
        $interpretacion = array();
    }


    if ( ! empty( $interpretacion['producto'] ) ) {

        $datos['producto'] =
            compuciber_obtener_palabras(
                sanitize_text_field(
                    $interpretacion['producto']
                )
            );
    }


    if ( ! empty( $interpretacion['marca'] ) ) {

        $datos['marca'] =
            compuciber_obtener_palabras(
                sanitize_text_field(
                    $interpretacion['marca']
                )
            );
    }


    if ( ! empty( $interpretacion['modelo'] ) ) {

        $datos['modelo'] =
            compuciber_obtener_palabras(
                sanitize_text_field(
                    $interpretacion['modelo']
                )
            );
    }


    if (
        ! empty( $interpretacion['caracteristicas'] )
        && is_array(
            $interpretacion['caracteristicas']
        )
    ) {

        foreach (
            $interpretacion['caracteristicas']
            as $caracteristica
        ) {

            $caracteristica =
                sanitize_text_field(
                    $caracteristica
                );

            if ( empty( $caracteristica ) ) {
                continue;
            }

            $caracteristica =
                compuciber_normalizar_texto(
                    $caracteristica
                );
         

            /*
            * Las características técnicas con números
            * se deben conservar completas.
            *
            * Ejemplos:
            * 8 GB      → "8 gb"
            * 16 GB     → "16 gb"
            * 1 TB SSD  → "1 tb ssd"
            * 512 GB    → "512 gb"
            */
            if (
                preg_match(
                    '/\b\d+(?:\.\d+)?\s*(gb|tb|mb)(?:\s+(ssd|hdd))?\b/i',
                    $caracteristica
                )
            ) {

                $datos['caracteristicas'][] =
                    $caracteristica;

            } else {

                $datos['caracteristicas'] =
                    array_merge(
                        $datos['caracteristicas'],
                        compuciber_obtener_palabras(
                            $caracteristica
                        )
                    );
            }
        }
    }


    if (
        ! empty( $interpretacion['palabras_clave'] )
        && is_array(
            $interpretacion['palabras_clave']
        )
    ) {

        foreach (
            $interpretacion['palabras_clave']
            as $palabra_clave
        ) {

            $palabra_clave =
                sanitize_text_field(
                    $palabra_clave
                );

            if ( ! empty( $palabra_clave ) ) {

                $datos['palabras_clave'] =
                    array_merge(
                        $datos['palabras_clave'],
                        compuciber_obtener_palabras(
                            $palabra_clave
                        )
                    );
            }
        }
    }


    /*
     * PRECIOS
     */

    if (
        isset( $interpretacion['precio_minimo'] )
        && is_numeric(
            $interpretacion['precio_minimo']
        )
    ) {

        $datos['precio_minimo'] =
            (float) $interpretacion['precio_minimo'];
    }


    if (
        isset( $interpretacion['precio_maximo'] )
        && is_numeric(
            $interpretacion['precio_maximo']
        )
    ) {

        $datos['precio_maximo'] =
            (float) $interpretacion['precio_maximo'];
    }

    /* PRECIO DESDE LA BÚSQUEDA ORIGINAL */

    $texto_precio = strtolower(
        remove_accents(
            sanitize_text_field(
                $busqueda
            )
        )
    );

    $numero_precio = '([0-9]+(?:[.,][0-9]+)?)';

    /* PRECIO MÁXIMO */

    if (
        preg_match(
            '/(?:menos de|menor de|por debajo de|hasta|no mas de|maximo|maximo de)\s*(?:s\/|soles?)?\s*' . $numero_precio . '/i',
            $texto_precio,
            $coincidencia
        )
    ) {

        $precio = str_replace(
            ',',
            '.',
            $coincidencia[1]
        );

        $datos['precio_maximo'] = (float) $precio;
    }

    /* PRECIO MÍNIMO */

    if (
        preg_match(
            '/(?:mas de|mayor de|por encima de|desde|minimo|minimo de)\s*(?:s\/|soles?)?\s*' . $numero_precio . '/i',
            $texto_precio,
            $coincidencia
        )
    ) {

        $precio = str_replace(
            ',',
            '.',
            $coincidencia[1]
        );

        $datos['precio_minimo'] = (float) $precio;
    }
    
    /*
     * ELIMINA DUPLICADOS
     */

    foreach (
        array(
            'producto',
            'marca',
            'modelo',
            'caracteristicas',
            'palabras_clave'
        ) as $tipo
    ) {

        $datos[$tipo] =
            array_values(
                array_unique(
                    $datos[$tipo]
                )
            );
    }


    error_log( 'DATOS PRECIO FINAL: ' . print_r( $datos['precio_maximo'], true ) );
    return $datos;
}

function compuciber_obtener_ancla_categoria( $palabras_busqueda ) {

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $categorias = $wpdb->get_col(
        "SELECT DISTINCT categorias
         FROM {$tabla}
         WHERE categorias <> ''"
    );

    foreach ( $palabras_busqueda as $palabra ) {

        $palabra = compuciber_normalizar_texto( $palabra );

        if (
            empty( $palabra )
            || strlen( $palabra ) < 4
            || is_numeric( $palabra )
        ) {
            continue;
        }

        foreach ( $categorias as $categoria ) {

            $categoria_normalizada =
                compuciber_normalizar_texto(
                    $categoria
                );

            if (
                strpos(
                    $categoria_normalizada,
                    $palabra
                ) !== false
            ) {

                return $palabra;
            }
        }
    }

    return '';
}

function compuciber_contiene_termino(
    $texto,
    $termino
) {

    $termino = compuciber_normalizar_texto(
        $termino
    );

    if ( empty( $termino ) ) {
        return false;
    }

    if (
        is_numeric( $termino )
        || in_array(
            $termino,
            array(
                'gb',
                'tb',
                'mb',
                'ram',
                'ssd',
                'hdd',
                'rgb',
                'ghz',
                'mhz'
            ),
            true
        )
    ) {

        return preg_match(
            '/(?<![a-z0-9])'
            . preg_quote( $termino, '/' )
            . '(?![a-z0-9])/i',
            $texto
        ) === 1;
    }

    return preg_match(
        '/(?<![a-z0-9])'
        . preg_quote(
            $termino,
            '/'
        )
        . '(?![a-z0-9])/i',
        $texto
    ) === 1;
}



function compuciber_calcular_puntuacion_producto(
    $producto,
    $busqueda,
    $palabras_busqueda,
    $datos_ia
) {

    $titulo = compuciber_normalizar_texto(
        $producto->nombre
    );

    $marca = compuciber_normalizar_texto(
        $producto->marca
    );

    $modelo = compuciber_normalizar_texto(
        $producto->modelo
    );

    $categorias = compuciber_normalizar_texto(
        $producto->categorias
    );

    $ancla_categoria =
        compuciber_obtener_ancla_categoria(
            $palabras_busqueda
        );

    if ( ! empty( $ancla_categoria ) ) {

        if (
            strpos(
                $categorias,
                $ancla_categoria
            ) === false
        ) {
            return 0;
        }
    }

    $atributos = compuciber_normalizar_texto(
        $producto->atributos
    );

    $texto = compuciber_normalizar_texto(
        $producto->texto_busqueda
    );

    if (
        strpos(
            $busqueda,
            '512'
        ) !== false
    ) {

        error_log(
            'TECNICO DEBUG: '
            . $producto->nombre
            . ' | TITULO: '
            . $titulo
            . ' | ATRIBUTOS: '
            . $atributos
            . ' | TEXTO: '
            . $texto
        );
    }    

    $sku = compuciber_normalizar_texto(
        $producto->sku
    );

    /*
    * PRECIO
    */

    $precio_producto = 0;

    if (
        isset( $producto->precio_oferta )
        && is_numeric( $producto->precio_oferta )
        && (float) $producto->precio_oferta > 0
    ) {

        $precio_producto =
            (float) $producto->precio_oferta;

    } elseif (
        isset( $producto->precio )
        && is_numeric( $producto->precio )
        && (float) $producto->precio > 0
    ) {

        $precio_producto =
            (float) $producto->precio;
    }


    /*
    * PRODUCTO VARIABLE
    */

    $precios_variaciones = array();

    if (
        $precio_producto <= 0
        && ! empty( $producto->variaciones )
    ) {

        preg_match_all(
            '/(?:Precio|Oferta):\s*([0-9]+(?:[.,][0-9]+)?)/iu',
            $producto->variaciones,
            $coincidencias
        );

        if (
            ! empty( $coincidencias[1] )
        ) {

            foreach (
                $coincidencias[1]
                as $precio_variacion
            ) {

                $precio_variacion =
                    str_replace(
                        ',',
                        '.',
                        $precio_variacion
                    );

                if (
                    is_numeric(
                        $precio_variacion
                    )
                ) {

                    $precios_variaciones[] =
                        (float) $precio_variacion;
                }
            }
        }
    }
    
    error_log(
        'PRODUCTO PRECIO DEBUG: '
        . $producto->nombre
        . ' | PRECIO: '
        . $precio_producto
        . ' | VARIACIONES: '
        . print_r( $precios_variaciones, true )
    );

    error_log(
        'PRECIO MAXIMO EN PUNTUACION: '
        . print_r( $datos_ia['precio_maximo'], true )
        . ' | PRODUCTO: '
        . $producto->nombre
    );

    /*
    * PRECIO MÁXIMO
    */


    if (
        $datos_ia['precio_maximo'] !== null
    ) {

        $precio_maximo =
            (float) $datos_ia['precio_maximo'];

        /*
        * Producto simple.
        */

        if (
            $precio_producto > 0
            && $precio_producto > $precio_maximo
        ) {

            return 0;
        }

        /*
        * Producto variable.
        */

        if (
            $precio_producto <= 0
            && ! empty( $precios_variaciones )
        ) {

            $cumple_precio = false;

            foreach (
                $precios_variaciones
                as $precio_variacion
            ) {

                if (
                    $precio_variacion <= $precio_maximo
                ) {

                    $cumple_precio = true;
                    break;
                }
            }

            if ( ! $cumple_precio ) {
                error_log(
                    'PRECIO MAXIMO RECHAZADO: '
                    . $producto->nombre
                    . ' | MAXIMO: '
                    . $precio_maximo
                    . ' | VARIACIONES: '
                    . print_r( $precios_variaciones, true )
                );                
                return 0;
            }
        }
    }


    /*
    * PRECIO MÍNIMO
    */

    if (
        $datos_ia['precio_minimo'] !== null
    ) {

        $precio_minimo =
            (float) $datos_ia['precio_minimo'];

        /*
        * Producto simple.
        */

        if (
            $precio_producto > 0
            && $precio_producto < $precio_minimo
        ) {

            return 0;
        }

        /*
        * Producto variable.
        */

        if (
            $precio_producto <= 0
            && ! empty( $precios_variaciones )
        ) {

            $cumple_precio = false;

            foreach (
                $precios_variaciones
                as $precio_variacion
            ) {

                if (
                    $precio_variacion >= $precio_minimo
                ) {

                    $cumple_precio = true;
                    break;
                }
            }

            if ( ! $cumple_precio ) {
                error_log(
                    'PRECIO MÍNIMO RECHAZADO: '
                    . $producto->nombre
                    . ' | MÍNIMO: '
                    . $precio_minimo
                    . ' | VARIACIONES: '
                    . print_r( $precios_variaciones, true )
                );
                return 0;
            }
        }
    }

    /*
    * CARACTERÍSTICA TÉCNICA DESDE LA BÚSQUEDA ORIGINAL
    *
    * Permite buscar directamente características como:
    * 8 GB
    * 16 GB
    * 512 GB
    * 1 TB
    * 1 TB SSD
    *
    * Funciona incluso cuando la IA no está disponible.
    */
    $busqueda_tecnica = compuciber_normalizar_texto(
        $busqueda
    );

    if (
        preg_match(
            '/\b\d+(?:\.\d+)?\s*(gb|tb|mb)(?:\s*(ssd|hdd))?\b/i',
            $busqueda_tecnica,
            $coincidencia_tecnica
        )
    ) {
        $caracteristica_tecnica =
            $coincidencia_tecnica[0];

        error_log(
            'TECNICO COINCIDENCIA: '
            . $producto->nombre
            . ' | BUSQUEDA: '
            . $busqueda_tecnica
            . ' | CARACTERISTICA: '
            . $caracteristica_tecnica
            . ' | TITULO: '
            . $titulo
            . ' | ATRIBUTOS: '
            . $atributos
        );            

        $coincide_tecnica =
            compuciber_coincide_caracteristica_tecnica(
                $titulo,
                $caracteristica_tecnica
            )
            ||
            compuciber_coincide_caracteristica_tecnica(
                $atributos,
                $caracteristica_tecnica
            )
            ||
            compuciber_coincide_caracteristica_tecnica(
                $texto,
                $caracteristica_tecnica
            );

        if ( ! $coincide_tecnica ) {
            return 0;
        }
    }

    $puntuacion = 0;

    /*
    * PRIORIDADES PRINCIPALES DE RANKING
    *
    * 1. SKU exacto
    * 2. Modelo / Part Number exacto
    * 3. Marca + modelo
    * 4. Título exacto
    */

    $busqueda_normalizada =
        compuciber_normalizar_texto(
            $busqueda
        );

    /*
    * SKU EXACTO
    */
    if (
        ! empty( $sku )
        && $sku === $busqueda_normalizada
    ) {
        $puntuacion += 1000;
    }

    /*
    * MODELO / PART NUMBER EXACTO
    */
    if (
        ! empty( $modelo )
        && $modelo === $busqueda_normalizada
    ) {
        $puntuacion += 800;
    }

    /*
    * MARCA + MODELO
    */
    if (
        ! empty( $marca )
        && ! empty( $modelo )
    ) {

        $marca_modelo =
            compuciber_normalizar_texto(
                $marca . ' ' . $modelo
            );

        $modelo_marca =
            compuciber_normalizar_texto(
                $modelo . ' ' . $marca
            );

        if (
            $busqueda_normalizada === $marca_modelo
            || $busqueda_normalizada === $modelo_marca
        ) {
            $puntuacion += 600;
        }
    }

    /*
    * TÍTULO EXACTO
    */
    if (
        ! empty( $titulo )
        && $titulo === $busqueda_normalizada
    ) {
        $puntuacion += 400;
    }   


    /*
     * PRODUCTO
     */

    $coincidencias_producto = 0;

    foreach (
        $datos_ia['producto']
        as $palabra
    ) {

        if (
            strpos(
                $titulo,
                $palabra
            ) !== false
        ) {

            $puntuacion += 30;
            $coincidencias_producto++;

        } elseif (
            strpos(
                $categorias,
                $palabra
            ) !== false
        ) {

            $puntuacion += 25;
            $coincidencias_producto++;

        } elseif (
            strpos(
                $atributos,
                $palabra
            ) !== false
        ) {

            $puntuacion += 20;
            $coincidencias_producto++;

        } elseif (
            strpos(
                $texto,
                $palabra
            ) !== false
        ) {

            $puntuacion += 12;
            $coincidencias_producto++;
        }
    }

    if (
        ! empty( $datos_ia['producto'] )
        && $coincidencias_producto === 0
    ) {

        return 0;
    }

    /*
     * MARCA
     */

    $coincidencias_marca = 0;

    foreach (
        $datos_ia['marca']
        as $palabra
    ) {

        if (
            strpos(
                $marca,
                $palabra
            ) !== false
        ) {

            $puntuacion += 40;
            $coincidencias_marca++;

        } elseif (
            strpos(
                $titulo,
                $palabra
            ) !== false
        ) {

            $puntuacion += 35;
            $coincidencias_marca++;

        } elseif (
            strpos(
                $texto,
                $palabra
            ) !== false
        ) {

            $puntuacion += 15;
            $coincidencias_marca++;
        }
    }


    if (
        ! empty( $datos_ia['marca'] )
        && $coincidencias_marca === 0
    ) {

        $puntuacion =
            floor(
                $puntuacion * 0.20
            );
    }


    /*
     * MODELO
     */

    $coincidencias_modelo = 0;

    foreach (
        $datos_ia['modelo']
        as $palabra
    ) {

        if (
            strpos(
                $modelo,
                $palabra
            ) !== false
        ) {

            $puntuacion += 50;
            $coincidencias_modelo++;

        } elseif (
            strpos(
                $titulo,
                $palabra
            ) !== false
        ) {

            $puntuacion += 45;
            $coincidencias_modelo++;

        } elseif (
            strpos(
                $texto,
                $palabra
            ) !== false
        ) {

            $puntuacion += 20;
            $coincidencias_modelo++;
        }
    }


    /*
    * CARACTERÍSTICAS
    */

    foreach (
        $datos_ia['caracteristicas']
        as $palabra
    ) {

        /*
        * CARACTERÍSTICAS TÉCNICAS
        *
        * Si la búsqueda contiene una cantidad técnica
        * como 8 GB, 16 GB o 1 TB SSD,
        * el producto debe contener exactamente
        * esa cantidad.
        *
        * El fuzzy no podrá introducir productos
        * que no cumplan esta característica.
        */

        if (
            preg_match(
                '/\b\d+(?:\.\d+)?\s*(gb|tb|mb)\b/i',
                $palabra
            )
        ) {

            $coincide_tecnica =
                compuciber_coincide_caracteristica_tecnica(
                    $titulo,
                    $palabra
                )
                ||
                compuciber_coincide_caracteristica_tecnica(
                    $atributos,
                    $palabra
                )
                ||
                compuciber_coincide_caracteristica_tecnica(
                    $texto,
                    $palabra
                );

            if ( ! $coincide_tecnica ) {
                return 0;
            }

            $puntuacion += 15;

            continue;
        }


        /*
        * Características normales.
        */

        if (
            compuciber_contiene_termino(
                $titulo,
                $palabra
            )
        ) {

            $puntuacion += 10;

        } elseif (
            compuciber_contiene_termino(
                $atributos,
                $palabra
            )
        ) {

            $puntuacion += 9;

        } elseif (
            compuciber_contiene_termino(
                $texto,
                $palabra
            )
        ) {

            $puntuacion += 5;
        }
    }


    /*
     * PALABRAS CLAVE
     */

    foreach (
        $datos_ia['palabras_clave']
        as $palabra
    ) {

        if (
            strpos(
                $titulo,
                $palabra
            ) !== false
        ) {

            $puntuacion += 8;

        } elseif (
            strpos(
                $categorias,
                $palabra
            ) !== false
        ) {

            $puntuacion += 6;

        } elseif (
            strpos(
                $texto,
                $palabra
            ) !== false
        ) {

            $puntuacion += 3;
        }
    }


    /*
     * BÚSQUEDA ORIGINAL
     */

    foreach (
        $palabras_busqueda
        as $palabra
    ) {

        if (
            compuciber_contiene_termino(
                $titulo,
                $palabra
            )
        ) {

            $puntuacion += 5;

        } elseif (
            compuciber_contiene_termino(
                $sku,
                $palabra
            )
        ) {

            $puntuacion += 4;

        } elseif (
            compuciber_contiene_termino(
                $texto,
                $palabra
            )
        ) {
            error_log(
                'COINCIDENCIA TEXTO DEBUG: '
                . $producto->nombre
                . ' | PALABRA: '
                . $palabra
                . ' | TEXTO: '
                . $texto
            );            

            $puntuacion += 2;
        }
    }


    /*
     * PRODUCTO + MARCA
     */

    if (
        ! empty( $datos_ia['producto'] )
        && ! empty( $datos_ia['marca'] )
        && $coincidencias_producto > 0
        && $coincidencias_marca > 0
    ) {

        $puntuacion += 30;
    }


    /*
     * PRODUCTO + MODELO
     */

    if (
        ! empty( $datos_ia['producto'] )
        && ! empty( $datos_ia['modelo'] )
        && $coincidencias_producto > 0
        && $coincidencias_modelo > 0
    ) {

        $puntuacion += 40;
    }

    /*
    * ANCLA DE TÉRMINOS ESPECÍFICOS
    *
    * Identifica términos que representan marca, modelo, SKU
    * u otros identificadores concretos del catálogo.
    *
    * Los términos genéricos como "laptop" no obligan a una
    * coincidencia específica.
    *
    * Si existen varios términos identificativos en la búsqueda,
    * el producto debe contener TODOS ellos.
    */

    static $datos_anclas_catalogo = null;

    if ( $datos_anclas_catalogo === null ) {

        global $wpdb;

        $tabla =
            $wpdb->prefix . 'compuciber_product_index';

        $filas_catalogo = $wpdb->get_results(
            "SELECT nombre, marca, modelo, categorias, atributos, texto_busqueda, sku
            FROM {$tabla}"
        );

        $datos_anclas_catalogo = array(
            'marcas'  => array(),
            'modelos' => array(),
            'skus'    => array(),
            'terminos'=> array()
        );

        foreach ( $filas_catalogo as $fila_catalogo ) {

            /*
            * MARCA
            */
            $marca_catalogo =
                compuciber_normalizar_texto(
                    trim( $fila_catalogo->marca )
                );

            if ( ! empty( $marca_catalogo ) ) {

                $datos_anclas_catalogo['marcas'][
                    $marca_catalogo
                ] = true;
            }

            /*
            * MODELO
            */
            $modelo_catalogo =
                compuciber_normalizar_texto(
                    trim( $fila_catalogo->modelo )
                );

            if ( ! empty( $modelo_catalogo ) ) {

                $datos_anclas_catalogo['modelos'][
                    $modelo_catalogo
                ] = true;
            }

            /*
            * SKU
            */
            $sku_catalogo =
                compuciber_normalizar_texto(
                    trim( $fila_catalogo->sku )
                );

            if ( ! empty( $sku_catalogo ) ) {

                $datos_anclas_catalogo['skus'][
                    $sku_catalogo
                ] = true;
            }

            /*
            * ATRIBUTOS
            */

            $atributos_catalogo =
                compuciber_normalizar_texto(
                    trim( $fila_catalogo->atributos )
                );

            if ( ! empty( $atributos_catalogo ) ) {

                if (
                    preg_match(
                        '/(?:^|\|)\s*marca\s*:\s*([^|]+)/i',
                        $atributos_catalogo,
                        $coincidencia_marca
                    )
                ) {

                    $marca_atributo =
                        compuciber_normalizar_texto(
                            trim( $coincidencia_marca[1] )
                        );

                    if ( ! empty( $marca_atributo ) ) {

                        $datos_anclas_catalogo['marcas'][
                            $marca_atributo
                        ] = true;
                    }
                }

                if (
                    preg_match(
                        '/(?:^|\|)\s*modelo\s*:\s*([^|]+)/i',
                        $atributos_catalogo,
                        $coincidencia_modelo
                    )
                ) {

                    $modelo_atributo =
                        compuciber_normalizar_texto(
                            trim( $coincidencia_modelo[1] )
                        );

                    if ( ! empty( $modelo_atributo ) ) {

                        $datos_anclas_catalogo['modelos'][
                            $modelo_atributo
                        ] = true;
                    }
                }
            }

            /*
            * TÉRMINOS DEL CATÁLOGO
            *
            * Se utiliza como respaldo para modelos,
            * códigos y otros términos específicos.
            */

            $texto_catalogo =
                compuciber_normalizar_texto(
                    trim(
                        $fila_catalogo->nombre
                        . ' '
                        . $fila_catalogo->marca
                        . ' '
                        . $fila_catalogo->modelo
                        . ' '
                        . $fila_catalogo->categorias
                        . ' '
                        . $fila_catalogo->atributos
                        . ' '
                        . $fila_catalogo->texto_busqueda
                        . ' '
                        . $fila_catalogo->sku
                    )
                );

            preg_match_all(
                '/\b[a-z0-9]+(?:[-][a-z0-9]+)*\b/i',
                $texto_catalogo,
                $coincidencias_catalogo
            );

            if ( ! empty( $coincidencias_catalogo[0] ) ) {

                foreach (
                    $coincidencias_catalogo[0]
                    as $termino_catalogo
                ) {

                    $termino_catalogo =
                        compuciber_normalizar_texto(
                            $termino_catalogo
                        );

                    if (
                        strlen( $termino_catalogo ) >= 2
                        && ! is_numeric( $termino_catalogo )
                    ) {

                        $datos_anclas_catalogo['terminos'][
                            $termino_catalogo
                        ] = true;
                    }
                }
            }
        }
    }


    /*
    * OBTIENE ANCLAS DE LA BÚSQUEDA
    */

    $anclas_busqueda = array();

    foreach ( $palabras_busqueda as $palabra_busqueda ) {

        $palabra_busqueda =
            compuciber_normalizar_texto(
                $palabra_busqueda
            );

        if (
            empty( $palabra_busqueda )
            || strlen( $palabra_busqueda ) < 2
            || is_numeric( $palabra_busqueda )
        ) {
            continue;
        }

        /*
        * MARCA EXACTA
        */
        if (
            isset(
                $datos_anclas_catalogo['marcas'][
                    $palabra_busqueda
                ]
            )
        ) {

            $anclas_busqueda[] = $palabra_busqueda;

            continue;
        }

        /*
        * SKU EXACTO
        */
        if (
            isset(
                $datos_anclas_catalogo['skus'][
                    $palabra_busqueda
                ]
            )
        ) {

            $anclas_busqueda[] = $palabra_busqueda;

            continue;
        }

        /*
        * MODELO EXACTO
        */
        $modelo_encontrado = false;

        foreach (
            array_keys(
                $datos_anclas_catalogo['modelos']
            ) as $modelo_catalogo
        ) {

            $modelo_normalizado =
                compuciber_normalizar_texto(
                    $modelo_catalogo
                );

            $partes_modelo =
                preg_split(
                    '/\s+/',
                    $modelo_normalizado
                );

            foreach (
                $partes_modelo as $parte_modelo
            ) {

                if (
                    $parte_modelo ===
                    $palabra_busqueda
                ) {

                    $anclas_busqueda[] =
                        $palabra_busqueda;

                    $modelo_encontrado = true;

                    break 2;
                }
            }
        }

        if ( $modelo_encontrado ) {
            continue;
        }

        /*
        * TÉRMINO ESPECÍFICO DEL CATÁLOGO
        * Los términos genéricos se excluyen explícitamente.
        */

        $terminos_genericos = array(
            'laptop',
            'portatil',
            'portátil',
            'computadora',
            'ordenador',
            'monitor',
            'mouse',
            'raton',
            'ratón',
            'teclado',
            'impresora',
            'impresora termica',
            'impresora térmica',
            'lector',
            'producto'
        );

        if (
            isset(
                $datos_anclas_catalogo['terminos'][
                    $palabra_busqueda
                ]
            )
            && ! in_array(
                $palabra_busqueda,
                $terminos_genericos,
                true
            )
        ) {

            $anclas_busqueda[] =
                $palabra_busqueda;
        }
    }

    error_log(
        'ANCLAS DEBUG: '
        . $producto->nombre
        . ' | '
        . print_r(
            $anclas_busqueda,
            true
        )
    );

    $anclas_busqueda =
        array_values(
            array_unique(
                $anclas_busqueda
            )
        );

    if ( ! empty( $anclas_busqueda ) ) {

        $texto_producto_completo =
            compuciber_normalizar_texto(
                trim(
                    $titulo
                    . ' '
                    . $marca
                    . ' '
                    . $modelo
                    . ' '
                    . $categorias
                    . ' '
                    . $atributos
                    . ' '
                    . $texto
                    . ' '
                    . $sku
                )
            );

        foreach (
            $anclas_busqueda as $ancla
        ) {

            /*
            * CARACTERÍSTICAS TÉCNICAS
            *
            * Si el ancla es una cantidad como:
            *
            * 8gb
            * 16gb
            * 512gb
            * 1tb
            *
            * utiliza la misma función técnica
            * que ya valida correctamente:
            *
            * 8 GB = 8GB
            * 512 GB = 512GB
            */
            if (
                preg_match(
                    '/^\d+(?:\.\d+)?\s*(gb|tb|mb)$/i',
                    $ancla
                )
            ) {

                if (
                    ! compuciber_coincide_caracteristica_tecnica(
                        $texto_producto_completo,
                        $ancla
                    )
                ) {

                    return 0;
                }

                continue;
            }


            /*
            * ANCLAS NORMALES
            *
            * Para marcas, modelos, SKU y demás
            * mantenemos la coincidencia exacta
            * que ya funciona.
            */
            if (
                ! preg_match(
                    '/(?<![a-z0-9])'
                    . preg_quote(
                        $ancla,
                        '/'
                    )
                    . '(?![a-z0-9])/i',
                    $texto_producto_completo
                )
            ) {

                return 0;
            }
        }
    }

    /*
    * FUZZY
    *
    * Solo se permite si existe alguna coincidencia
    * real previa con el producto.
    */

    $fuzzy = 0;

    $existe_coincidencia_real =
        compuciber_contiene_termino(
            $titulo,
            $busqueda_normalizada
        )
        ||
        compuciber_contiene_termino(
            $marca,
            $busqueda_normalizada
        )
        ||
        compuciber_contiene_termino(
            $modelo,
            $busqueda_normalizada
        )
        ||
        compuciber_contiene_termino(
            $sku,
            $busqueda_normalizada
        );


    if ( $existe_coincidencia_real ) {

        $fuzzy =
            compuciber_fuzzy_puntuacion(
                $busqueda,
                $texto
            );
    }


    error_log(
        'FUZZY DEBUG: '
        . $producto->nombre
        . ' | BUSQUEDA: '
        . $busqueda
        . ' | FUZZY: '
        . $fuzzy
    );


    if ( $fuzzy > 0 ) {

        $puntuacion += min(
            $fuzzy,
            15
        );
    }

    /*
    * APRENDIZAJE DE RANKING - V2.0
    *
    * Utiliza el historial de selecciones como
    * una señal adicional de relevancia.
    *
    * El bonus está limitado para evitar que
    * la popularidad sustituya las reglas
    * principales del motor de búsqueda.
    */
    if (
        $puntuacion > 0
        && get_option(
            'compuciber_aprendizaje_ranking',
            '0'
        ) === '1'
        && function_exists(
            'compuciber_obtener_selecciones_para_busqueda'
        )
    ) {

        $selecciones_aprendizaje =
            compuciber_obtener_selecciones_para_busqueda(
                absint( $producto->id ),
                $busqueda_normalizada
            );

        if ( $selecciones_aprendizaje >= 2 ) {

            $bonus_aprendizaje = min(
                20,
                $selecciones_aprendizaje * 2
            );

            $puntuacion += $bonus_aprendizaje;

        }
    }

    error_log(
        'SCORE FINAL DEBUG: '
        . $producto->nombre
        . ' | SCORE: '
        . $puntuacion
        . ' | BUSQUEDA: '
        . $busqueda
    );
    return $puntuacion;
}