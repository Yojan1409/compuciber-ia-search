<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Crea las tablas e indexa productos
 * al activar el plugin.
 */
function compuciber_activar_indexacion() {

    /*
     * Crea la tabla del índice de productos.
     */
    compuciber_crear_tabla_indice_productos();


    /*
     * Crea la tabla de estadísticas
     * de búsquedas.
     */
    compuciber_crear_tabla_estadisticas();


    /*
     * Crea la tabla de productos
     * seleccionados desde el buscador.
     */
    compuciber_crear_tabla_selecciones();


    /*
     * Guarda la versión actual
     * del índice.
     */
    update_option(
        'compuciber_indice_version',
        COMPUCIBER_INDICE_VERSION
    );


    /*
     * Indexación inicial por lotes.
     * No bloquea la activación del plugin.
     */
    update_option(
        'compuciber_indice_lotes_estado',
        array(
            'cursor' => 0,
            'fallos' => array(),
            'ultima' => 0,
            'estado' => 'pendiente',
        ),
        false
    );

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );

    compuciber_programar_indice_113();
}

/**
 * Comprueba la cobertura real del índice.
 *
 * Cuenta solamente productos publicados.
 * Las variaciones se incluyen mediante
 * su producto padre.
 */
function compuciber_resumen_indice_113() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'product'
           AND post_status = 'publish'"
    );

    $pendientes = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$tabla} i ON i.id = p.ID
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           AND i.id IS NULL"
    );

    return array(
        'total' => $total,
        'indexados' => max( 0, $total - $pendientes ),
        'pendientes' => $pendientes,
    );
}

/**
 * Programa el siguiente lote solamente
 * cuando no hay otro pendiente.
 */
function compuciber_programar_indice_113() {

    if (
        ! wp_next_scheduled(
            'compuciber_indice_lote_113'
        )
    ) {

        wp_schedule_single_event(
            time() + 10,
            'compuciber_indice_lote_113'
        );
    }
}

/**
 * Procesa hasta 40 productos pendientes.
 */
function compuciber_procesar_indice_113() {
    global $wpdb;

    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $estado = get_option(
        'compuciber_indice_lotes_estado',
        array()
    );

    if ( ! is_array( $estado ) ) {
        $estado = array();
    }

    if (
        ( $estado['estado'] ?? '' ) === 'errores'
    ) {
        return;
    }

    $cursor = absint( $estado['cursor'] ?? 0 );

    $fallos = (array) (
        $estado['fallos'] ?? array()
    );

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    /*
     * Obtener productos publicados que
     * todavía no tienen registro en el índice.
     */
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$tabla} i ON i.id = p.ID
             WHERE p.post_type = %s
               AND p.post_status = %s
               AND i.id IS NULL
               AND p.ID > %d
             ORDER BY p.ID ASC
             LIMIT %d",
            'product',
            'publish',
            $cursor,
            40
        )
    );

    /*
     * Registrar un error SQL, sin declarar
     * que la indexación terminó.
     */
    if ( $wpdb->last_error ) {

        /*
        * Registrar errores SQL de indexación.
        */
        if ( function_exists( 'wc_get_logger' ) ) {

            wc_get_logger()->error(
                'Indexación inicial: error SQL al buscar productos pendientes. ' .
                substr(
                    sanitize_text_field( $wpdb->last_error ),
                    0,
                    200
                ),
                array(
                    'source' => 'compuciber-ai-search'
                )
            );
        }

        $estado['estado'] = 'errores';
        $estado['ultima'] = time();

        update_option(
            'compuciber_indice_lotes_estado',
            $estado,
            false
        );

        return;
    }

    foreach ( (array) $ids as $id ) {

        $id = absint( $id );
        $cursor = $id;

        /*
         * Máximo tres intentos por producto
         * durante este proceso.
         */
        if (
            (int) ( $fallos[ $id ] ?? 0 ) >= 3
        ) {
            continue;
        }

        /*
         * Conservar la función original
         * de indexación WooCommerce.
         *
         * false evita generar embeddings
         * durante estos lotes iniciales.
         */
        compuciber_indexar_producto(
            $id,
            false
        );

        /*
         * Confirmar que existe el registro.
         */
        $existe = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$tabla}
                 WHERE id = %d
                 LIMIT 1",
                $id
            )
        );

        if ( $existe ) {

            unset( $fallos[ $id ] );

        } elseif (
            get_post_status( $id ) === 'publish'
        ) {

            $fallos[ $id ] =
                (int) ( $fallos[ $id ] ?? 0 ) + 1;

            /*
            * Registrar productos que alcanzaron
            * el máximo de tres intentos.
            */
            if (
                $fallos[ $id ] === 3
                && function_exists( 'wc_get_logger' )
            ) {

                wc_get_logger()->warning(
                    'Indexación inicial: producto ID ' .
                    $id .
                    ' sin registro tras tres intentos.',
                    array(
                        'source' => 'compuciber-ai-search'
                    )
                );
            }
                
        }
    }

    $resumen = compuciber_resumen_indice_113();

    $estado['cursor'] = $cursor;
    $estado['fallos'] = $fallos;
    $estado['ultima'] = time();

    if ( $resumen['pendientes'] === 0 ) {

        /*
        * Registrar finalización de la indexación.
        */
        if ( function_exists( 'wc_get_logger' ) ) {

            wc_get_logger()->info(
                sprintf(
                    'Indexación inicial completada: %d de %d productos publicados con registro.',
                    absint( $resumen['indexados'] ),
                    absint( $resumen['total'] )
                ),
                array(
                    'source' => 'compuciber-ai-search'
                )
            );
        }

        $estado['estado'] = 'completado';
        $estado['cursor'] = 0;
        $estado['fallos'] = array();

    } elseif ( count( (array) $ids ) < 40 ) {

        /*
         * Terminó una pasada.
         * Comprobar si quedan fallos
         * que todavía puedan reintentarse.
         */
        if (
            count( $fallos ) >=
            $resumen['pendientes']
        ) {

            $todos_agotados = true;

            foreach ( $fallos as $intentos ) {

                if ( (int) $intentos < 3 ) {
                    $todos_agotados = false;
                    break;
                }
            }

        } else {

            $todos_agotados = false;
        }

        if ( $todos_agotados ) {

            $estado['estado'] = 'errores';

        } else {

            $estado['cursor'] = 0;
            $estado['estado'] = 'en_proceso';
        }

    } else {

        $estado['estado'] = 'en_proceso';
    }

    update_option(
        'compuciber_indice_lotes_estado',
        $estado,
        false
    );

    if ( $estado['estado'] === 'en_proceso' ) {
        compuciber_programar_indice_113();
    }
}

add_action(
    'compuciber_indice_lote_113',
    'compuciber_procesar_indice_113'
);

function compuciber_revisar_indice_113() {

    if (
        ! current_user_can( 'manage_options' )
        || ! class_exists( 'WooCommerce' )
    ) {
        return;
    }

    /*
     * Evitar consultar el catálogo cuando ya existe
     * un lote pendiente de ejecución.
     */
    if (
        wp_next_scheduled(
            'compuciber_indice_lote_113'
        )
    ) {
        return;
    }

    $estado = get_option(
        'compuciber_indice_lotes_estado',
        array()
    );

    if ( ! is_array( $estado ) ) {
        $estado = array();
    }

    /*
     * Los errores requieren revisión o reintento manual.
     */
    if (
        ( $estado['estado'] ?? '' ) === 'errores'
    ) {
        return;
    }

    /*
     * Permitir que una ejecución reciente termine
     * antes de programar una tarea de recuperación.
     */
    if (
        time() -
        (int) ( $estado['ultima'] ?? 0 ) < 90
    ) {
        return;
    }

    /*
     * Contar faltantes solo si no hay tarea pendiente
     * y ha transcurrido el margen de seguridad.
     */
    $resumen = compuciber_resumen_indice_113();

    if ( $resumen['pendientes'] === 0 ) {
        return;
    }

    compuciber_programar_indice_113();
}

add_action(
    'admin_init',
    'compuciber_revisar_indice_113',
    20
);

/**
 * Permitir reintento desde el Dashboard.
 */
function compuciber_reintentar_indice_113() {

    if (
        ! current_user_can( 'manage_options' )
    ) {
        wp_die( 'No autorizado.' );
    }

    check_admin_referer(
        'compuciber_reintentar_indice_113'
    );

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );

    update_option(
        'compuciber_indice_lotes_estado',
        array(
            'cursor' => 0,
            'fallos' => array(),
            'ultima' => 0,
            'estado' => 'pendiente',
        ),
        false
    );

    compuciber_programar_indice_113();

    wp_safe_redirect(
        admin_url(
            'admin.php?page=compuciber-ai-search'
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_reintentar_indice_113',
    'compuciber_reintentar_indice_113'
);

/**
 * Al desactivar, limpiar las tareas
 * pendientes de indexación inicial.
 * No elimina los productos indexados.
 */
function compuciber_detener_indice_113() {

    wp_clear_scheduled_hook(
        'compuciber_indice_lote_113'
    );
}

register_deactivation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_detener_indice_113'
);

register_activation_hook(
    dirname( dirname( __DIR__ ) ) .
    '/compuciber-ai-search.php',
    'compuciber_activar_indexacion'
);

/*
 * AUDITORÍA INTEGRAL DEL CATÁLOGO
 *
 * Revisión por lotes de 15 productos.
 * Solo lectura: no reindexa ni genera embeddings.
 */

function compuciber_auditoria_integral_programar(
    $token,
    $espera = 15
) {

    $args = array( $token );

    if (
        wp_next_scheduled(
            'compuciber_auditoria_integral_lote',
            $args
        )
    ) {
        return true;
    }

    return wp_schedule_single_event(
        time() + $espera,
        'compuciber_auditoria_integral_lote',
        $args,
        true
    );
}


/*
 * Procesar un lote de la auditoría.
 */

function compuciber_auditoria_integral_lote( $token ) {

    global $wpdb;

    $opcion = 'compuciber_auditoria_integral_estado';

    $estado = get_option( $opcion, array() );

    if (
        ! is_array( $estado )
        || empty( $estado['activo'] )
        || ( $estado['token'] ?? '' ) !== $token
    ) {
        return;
    }

    if ( ! function_exists( 'wc_get_product' ) ) {

        $estado['activo'] = false;
        $estado['situacion'] = 'woocommerce_no_disponible';

        update_option( $opcion, $estado, false );

        return;
    }

    /*
     * Evitar procesamientos simultáneos.
     */

    $nombre_lock = 'compuciber_auditoria_integral_lock';

    $lock = get_option( $nombre_lock, array() );

    if (
        is_array( $lock )
        && isset( $lock['ts'] )
        && time() - (int) $lock['ts'] > 180
    ) {
        delete_option( $nombre_lock );
    }

    if (
        ! add_option(
            $nombre_lock,
            array( 'ts' => time() ),
            '',
            false
        )
    ) {

        compuciber_auditoria_integral_programar(
            $token,
            60
        );

        return;
    }

    try {

        $tabla = $wpdb->prefix . 'compuciber_product_index';

        /*
         * Recuperar solamente 15 productos.
         */

        $filas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    p.ID,
                    p.post_modified,
                    i.id AS indice_id,
                    i.nombre,
                    i.sku,
                    i.precio,
                    i.precio_oferta,
                    i.stock,
                    i.categorias,
                    i.variaciones,
                    i.texto_busqueda,
                    i.actualizado

                FROM {$wpdb->posts} p

                LEFT JOIN {$tabla} i
                    ON i.id = p.ID

                WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND p.ID > %d

                ORDER BY p.ID ASC
                LIMIT 15",

                absint( $estado['cursor'] ?? 0 )
            )
        );

        if (
            ! is_array( $filas )
            || $wpdb->last_error
        ) {

            if ( function_exists( 'wc_get_logger' ) ) {

                wc_get_logger()->error(
                    'Auditoría SQL: '
                    . wp_html_excerpt(
                        sanitize_text_field(
                            (string) $wpdb->last_error
                        ),
                        180,
                        '…'
                    ),
                    array(
                        'source' => 'compuciber-ai-search'
                    )
                );
            }

            $estado['activo'] = false;
            $estado['situacion'] = 'error_sql';

        } else {

            foreach ( $filas as $fila ) {

                $id = (int) $fila->ID;

                $motivos = array();

                $producto = wc_get_product( $id );

                $estado['cursor'] = $id;

                $estado['revisados']++;

                /*
                 * Producto inexistente en el índice.
                 */

                if ( null === $fila->indice_id ) {

                    $motivos[] = 'sin_indice';

                } elseif ( ! $producto ) {

                    $motivos[] = 'producto_no_disponible';

                } else {

                    /*
                     * Campos básicos.
                     */

                    if (
                        '' === trim( (string) $fila->nombre )
                        || '' === trim(
                            (string) $fila->texto_busqueda
                        )
                    ) {
                        $motivos[] = 'incompleto';
                    }

                    /*
                     * Nombre.
                     */

                    if (
                        (string) $fila->nombre !==
                        (string) $producto->get_name()
                    ) {
                        $motivos[] = 'nombre';
                    }

                    /*
                     * SKU.
                     */

                    if (
                        (string) $fila->sku !==
                        (string) $producto->get_sku()
                    ) {
                        $motivos[] = 'sku';
                    }

                    /*
                     * Precio regular.
                     */

                    if (
                        abs(
                            (float) $fila->precio
                            - (float) $producto->get_regular_price()
                        ) > 0.000001
                    ) {
                        $motivos[] = 'precio';
                    }

                    /*
                     * Precio de oferta.
                     */

                    if (
                        abs(
                            (float) $fila->precio_oferta
                            - (float) $producto->get_sale_price()
                        ) > 0.000001
                    ) {
                        $motivos[] = 'oferta';
                    }

                    /*
                     * Stock.
                     */

                    $stock_wc = $producto->get_stock_quantity();

                    if ( null === $stock_wc ) {

                        $stock_wc =
                            $producto->get_stock_status();
                    }

                    if (
                        (string) $fila->stock !==
                        (string) $stock_wc
                    ) {
                        $motivos[] = 'stock';
                    }

                    /*
                     * Categorías.
                     */

                    $categorias_wc = wp_get_post_terms(
                        $id,
                        'product_cat',
                        array( 'fields' => 'names' )
                    );

                    if ( ! is_wp_error( $categorias_wc ) ) {

                        /*
                         * Comparar las categorías utilizando
                         * el mismo formato que el indexador.
                         *
                         * No dividir los nombres por comas.
                         */

                        if (
                            (string) $fila->categorias !==
                            implode(
                                ', ',
                                $categorias_wc
                            )
                        ) {

                            $motivos[] = 'categorias';
                        }

                    } else {

                        $estado['no_verificados']++;
                    }

                    /*
                     * Variaciones.
                     */

                    if ( $producto->is_type( 'variable' ) ) {

                        $hijos = $producto->get_children();

                        if ( count( $hijos ) > 20 ) {

                            $estado['no_verificados']++;

                        } elseif (
                            (string) $fila->variaciones !==
                            (string) compuciber_obtener_variaciones_producto(
                                $id
                            )
                        ) {

                            $motivos[] = 'variaciones';
                        }
                    }

                    /*
                     * Fecha de actualización.
                     */

                    if (
                        $fila->post_modified >
                        $fila->actualizado
                    ) {
                        $motivos[] = 'fecha_posterior';
                    }

                    /*
                     * Visibilidad.
                     */

                    if ( ! $producto->is_visible() ) {

                        $estado['no_visibles']++;
                    }
                }

                /*
                 * Registrar diferencias.
                 */

                if ( $motivos ) {

                    $estado['con_diferencias']++;

                    foreach ( $motivos as $motivo ) {

                        $estado['motivos'][ $motivo ] =
                            (int) (
                                $estado['motivos'][ $motivo ]
                                ?? 0
                            ) + 1;
                    }

                    /*
                     * Conservar hasta 60 ejemplos.
                     */

                    if (
                        count( $estado['muestras'] ) < 60
                    ) {

                        $estado['muestras'][] = array(
                            'id' => $id,
                            'motivos' => $motivos,
                        );
                    }

                    /*
                     * Guardar los ID de todos los productos
                     * afectados para exportarlos después.
                     *
                     * Máximo 5000 registros para controlar
                     * el tamaño almacenado en WordPress.
                     */

                    if (
                        ! isset( $estado['incidencias'] )
                        || ! is_array(
                            $estado['incidencias']
                        )
                    ) {

                        $estado['incidencias'] = array();
                    }

                    if (
                        count(
                            $estado['incidencias']
                        ) < 5000
                    ) {

                        $estado['incidencias'][] = array(
                            'id' => $id,
                            'motivos' => $motivos,
                        );

                    } else {

                        $estado['registro_limitado'] = true;
                    }

                }
            }

            $estado['ultima'] = time();

            /*
             * Finalizar al terminar el recorrido.
             */

            if ( count( $filas ) < 15 ) {

                $estado['activo'] = false;
                $estado['situacion'] = 'completada';

            } else {

                $estado['situacion'] = 'en_proceso';
            }
        }

        /*
         * No sobrescribir una detención manual.
         */

        $actual = get_option(
            $opcion,
            array()
        );

        if (
            ( $actual['token'] ?? '' ) !== $token
            || empty( $actual['activo'] )
        ) {
            return;
        }

        update_option(
            $opcion,
            $estado,
            false
        );

        /*
         * Programar el siguiente lote.
         */

        if ( ! empty( $estado['activo'] ) ) {

            $programado =
                compuciber_auditoria_integral_programar(
                    $token
                );

            if (
                is_wp_error( $programado )
                || ! $programado
            ) {

                $estado['activo'] = false;
                $estado['situacion'] = 'error_programacion';

                update_option(
                    $opcion,
                    $estado,
                    false
                );
            }
        }

    } catch ( Throwable $error ) {

        $actual = get_option(
            $opcion,
            array()
        );

        if (
            ( $actual['token'] ?? '' ) === $token
            && ! empty( $actual['activo'] )
        ) {

            $actual['activo'] = false;
            $actual['situacion'] = 'error_procesamiento';
            $actual['ultima'] = time();

            update_option(
                $opcion,
                $actual,
                false
            );
        }

        if ( function_exists( 'wc_get_logger' ) ) {

            wc_get_logger()->error(
                sprintf(
                    'Auditoría integral: excepción=%s | último_id=%d',
                    get_class( $error ),
                    absint( $estado['cursor'] ?? 0 )
                ),
                array(
                    'source' => 'compuciber-ai-search'
                )
            );
        }

    } finally {

        delete_option( $nombre_lock );
    }
}

add_action(
    'compuciber_auditoria_integral_lote',
    'compuciber_auditoria_integral_lote',
    10,
    1
);


/*
 * Control administrativo:
 * iniciar y detener la auditoría.
 */

function compuciber_auditoria_integral_control() {

    if ( ! current_user_can( 'manage_options' ) ) {

        wp_die( 'No autorizado.' );
    }

    check_admin_referer(
        'compuciber_auditoria_integral_control'
    );

    $opcion = 'compuciber_auditoria_integral_estado';

    $estado = get_option( $opcion, array() );

    $estado = is_array( $estado )
        ? $estado
        : array();

    $operacion = isset( $_POST['operacion'] )
        ? sanitize_key(
            wp_unslash( $_POST['operacion'] )
        )
        : '';

    $aviso = 'sin_cambios';

    /*
     * Detener.
     */

    if (
        'detener' === $operacion
        && ! empty( $estado['activo'] )
    ) {

        $estado['activo'] = false;
        $estado['situacion'] = 'detenida_manual';

        update_option(
            $opcion,
            $estado,
            false
        );

        wp_clear_scheduled_hook(
            'compuciber_auditoria_integral_lote',
            array( $estado['token'] )
        );

        $aviso = 'detenida';

    /*
     * Iniciar.
     */

    } elseif (
        'iniciar' === $operacion
        && empty( $estado['activo'] )
    ) {

        if ( ! class_exists( 'WooCommerce' ) ) {

            $aviso = 'woocommerce_no_disponible';

        } else {

            $resumen =
                compuciber_resumen_indice_113();

            $token = wp_generate_uuid4();

            $estado = array(
                'activo' => true,
                'token' => $token,
                'situacion' => 'programada',
                'total' => (int) $resumen['total'],
                'revisados' => 0,
                'cursor' => 0,
                'con_diferencias' => 0,
                'no_visibles' => 0,
                'no_verificados' => 0,
                'motivos' => array(),
                'muestras' => array(),
                'incidencias' => array(),
                'registro_limitado' => false,
                'inicio' => time(),
                'ultima' => 0,
            );

            update_option(
                $opcion,
                $estado,
                false
            );

            $programado =
                compuciber_auditoria_integral_programar(
                    $token
                );

            if (
                is_wp_error( $programado )
                || ! $programado
            ) {

                $estado['activo'] = false;
                $estado['situacion'] = 'error_programacion';

                update_option(
                    $opcion,
                    $estado,
                    false
                );

                $aviso = 'error_programacion';

            } else {

                $aviso = 'iniciada';
            }
        }
    }

    /*
     * Regresar a Diagnóstico y registros.
     */

    wp_safe_redirect(
        add_query_arg(
            array(
                'page' => 'compuciber-ai-search-diagnostico',
                'cis_auditoria_aviso' => $aviso,
            ),
            admin_url( 'admin.php' )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_auditoria_integral_control',
    'compuciber_auditoria_integral_control'
);

/*
 * Exportar las incidencias de la auditoría integral.
 *
 * Solo administradores.
 * No consulta el catálogo ni modifica productos.
 */

function compuciber_auditoria_integral_exportar() {

    if ( ! current_user_can( 'manage_options' ) ) {

        wp_die( 'No autorizado.' );
    }

    check_admin_referer(
        'compuciber_auditoria_integral_exportar'
    );

    $estado = get_option(
        'compuciber_auditoria_integral_estado',
        array()
    );

    $incidencias = is_array( $estado )
        ? (array) (
            $estado['incidencias'] ?? array()
        )
        : array();

    if ( empty( $incidencias ) ) {

        wp_die(
            'No hay incidencias registradas para exportar. Ejecuta una nueva auditoría integral.'
        );
    }

    if ( headers_sent() ) {

        wp_die(
            'No se puede iniciar la descarga porque ya se enviaron cabeceras.'
        );
    }

    /*
     * Preparar archivo CSV.
     */

    nocache_headers();

    header(
        'Content-Type: text/csv; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="compuciber-incidencias-'
        . gmdate( 'Ymd-His' )
        . '.csv"'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    $salida = fopen(
        'php://output',
        'w'
    );

    if ( false === $salida ) {
        exit;
    }

    /*
     * Compatibilidad con Excel.
     */

    fwrite(
        $salida,
        "\xEF\xBB\xBF"
    );

    fputcsv(
        $salida,
        array(
            'ID producto',
            'Motivos',
            'Enlace de edición',
            'Estado auditoría'
        ),
        ';',
        '"',
        ''
    );

    $situacion = sanitize_key(
        (string) (
            $estado['situacion'] ?? ''
        )
    );

    /*
     * Exportar los resultados guardados.
     * No realiza consultas de productos.
     */

    foreach ( $incidencias as $item ) {

        if ( ! is_array( $item ) ) {
            continue;
        }

        $id = absint(
            $item['id'] ?? 0
        );

        if ( ! $id ) {
            continue;
        }

        $motivos = array_map(
            'sanitize_key',
            (array) (
                $item['motivos'] ?? array()
            )
        );

        fputcsv(
            $salida,
            array(
                $id,

                implode(
                    ', ',
                    $motivos
                ),

                admin_url(
                    'post.php?post='
                    . $id
                    . '&action=edit'
                ),

                $situacion,
            ),
            ';',
            '"',
            ''
        );
    }

    fclose( $salida );

    exit;
}

add_action(
    'admin_post_compuciber_auditoria_integral_exportar',
    'compuciber_auditoria_integral_exportar'
);

/*
 * Detener la auditoría al desactivar el plugin.
 */

function compuciber_auditoria_integral_desactivar() {

    $estado = get_option(
        'compuciber_auditoria_integral_estado',
        array()
    );

    if ( is_array( $estado ) ) {

        if ( ! empty( $estado['token'] ) ) {

            wp_clear_scheduled_hook(
                'compuciber_auditoria_integral_lote',
                array( $estado['token'] )
            );
        }

        $estado['activo'] = false;
        $estado['situacion'] = 'plugin_desactivado';

        update_option(
            'compuciber_auditoria_integral_estado',
            $estado,
            false
        );
    }

    delete_option(
        'compuciber_auditoria_integral_lock'
    );
}

register_deactivation_hook(
    dirname( dirname( __DIR__ ) )
        . '/compuciber-ai-search.php',
    'compuciber_auditoria_integral_desactivar'
);

/*
 * Reparación selectiva del índice.
 * Reutiliza el indexador existente, sin generar embeddings.
 */

function compuciber_reparacion_indice_programar( $token ) {

    return wp_schedule_single_event(
        time() + 20,
        'compuciber_reparacion_indice_lote',
        array( $token ),
        true
    );
}

function compuciber_reparacion_indice_lote( $token ) {

    $opcion = 'compuciber_reparacion_indice_estado';

    $estado = get_option( $opcion, array() );

    if (
        ! is_array( $estado )
        || empty( $estado['activo'] )
        || ( $estado['token'] ?? '' ) !== $token
    ) {
        return;
    }

    /*
     * Evitar que dos lotes se ejecuten simultáneamente.
     */

    $lock = 'compuciber_reparacion_indice_lock';

    if ( ! add_option( $lock, time(), '', false ) ) {

        $desde = (int) get_option( $lock, 0 );

        if ( $desde && time() - $desde > 180 ) {
            delete_option( $lock );
        }

        compuciber_reparacion_indice_programar( $token );

        return;
    }

    try {

        global $wpdb;

        $tabla = $wpdb->prefix . 'compuciber_product_index';

        $ids = (array) ( $estado['ids'] ?? array() );

        $inicio = absint( $estado['cursor'] ?? 0 );

        /*
         * Procesar como máximo diez productos.
         */

        $lote = array_slice( $ids, $inicio, 10 );

        foreach ( $lote as $id ) {

            $id = absint( $id );

            $estado['revisados'] =
                absint( $estado['revisados'] ?? 0 ) + 1;

            $estado['cursor'] =
                absint( $estado['cursor'] ?? 0 ) + 1;

            if (
                ! $id
                || get_post_type( $id ) !== 'product'
                || get_post_status( $id ) !== 'publish'
            ) {

                $estado['omitidos'] =
                    absint( $estado['omitidos'] ?? 0 ) + 1;

                continue;
            }

            $producto_wc = wc_get_product( $id );

            if ( ! $producto_wc ) {

                $estado['omitidos'] =
                    absint( $estado['omitidos'] ?? 0 ) + 1;

                continue;
            }

            /*
             * Utilizar nuestro indexador original.
             * FALSE evita generar embeddings.
             */

            compuciber_indexar_producto( $id, false );

            /*
             * Confirmar que se guardó el registro.
             */

            $fila = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT nombre, sku, texto_busqueda
                     FROM {$tabla}
                     WHERE id = %d",
                    $id
                )
            );

            if (
                $fila
                && '' !== trim( (string) $fila->nombre )
                && '' !== trim( (string) $fila->texto_busqueda )
                && (string) $fila->sku
                    === (string) $producto_wc->get_sku()
            ) {

                $estado['reindexados'] =
                    absint( $estado['reindexados'] ?? 0 ) + 1;

            } else {

                $estado['fallidos'] =
                    absint( $estado['fallidos'] ?? 0 ) + 1;

                if (
                    count(
                        (array) (
                            $estado['ids_fallidos'] ?? array()
                        )
                    ) < 30
                ) {

                    $estado['ids_fallidos'][] = $id;
                }
            }
        }

        /*
         * Respetar una detención manual.
         */

        $actual = get_option( $opcion, array() );

        if (
            ! is_array( $actual )
            || empty( $actual['activo'] )
            || ( $actual['token'] ?? '' ) !== $token
        ) {
            return;
        }

        $estado['ultima'] = time();

        $estado['activo'] =
            $estado['cursor'] < count( $ids );

        $estado['situacion'] = $estado['activo']
            ? 'en_proceso'
            : (
                ! empty( $estado['fallidos'] )
                    ? 'finalizada_con_fallos'
                    : 'completada'
            );

        update_option( $opcion, $estado, false );

        /*
         * Programar el siguiente lote.
         */

        if ( $estado['activo'] ) {

            $programado =
                compuciber_reparacion_indice_programar(
                    $token
                );

            if (
                is_wp_error( $programado )
                || ! $programado
            ) {

                $estado['activo'] = false;

                $estado['situacion'] =
                    'error_programacion';

                update_option(
                    $opcion,
                    $estado,
                    false
                );
            }
        }

    } catch ( Throwable $excepcion ) {

        $estado['activo'] = false;

        $estado['situacion'] = 'error_procesamiento';

        $estado['ultima'] = time();

        update_option( $opcion, $estado, false );

    } finally {

        delete_option( $lock );
    }
}

add_action(
    'compuciber_reparacion_indice_lote',
    'compuciber_reparacion_indice_lote'
);


/*
 * Controles administrativos.
 */

function compuciber_reparacion_indice_control() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No autorizado.' );
    }

    check_admin_referer(
        'compuciber_reparacion_indice_control'
    );

    $opcion = 'compuciber_reparacion_indice_estado';

    $estado = get_option( $opcion, array() );

    $estado = is_array( $estado )
        ? $estado
        : array();

    $operacion = (
        isset( $_POST['operacion'] )
        && is_string( $_POST['operacion'] )
    )
        ? sanitize_key(
            wp_unslash( $_POST['operacion'] )
        )
        : '';

    $aviso = 'sin_cambios';

    /*
     * Detener la reparación.
     */

    if (
        'detener' === $operacion
        && ! empty( $estado['activo'] )
    ) {

        $estado['activo'] = false;

        $estado['situacion'] = 'detenida_manual';

        update_option( $opcion, $estado, false );

        wp_clear_scheduled_hook(
            'compuciber_reparacion_indice_lote',
            array( $estado['token'] )
        );

        $aviso = 'detenida';

    /*
     * Preparar una reparación nueva.
     */

    } elseif (
        'iniciar' === $operacion
        && empty( $estado['activo'] )
    ) {

        $auditoria = get_option(
            'compuciber_auditoria_integral_estado',
            array()
        );

        /*
         * Exigir una auditoría terminada.
         */

        if (
            ! is_array( $auditoria )
            || ( $auditoria['situacion'] ?? '' )
                !== 'completada'
            || ! empty( $auditoria['registro_limitado'] )
        ) {

            $aviso = 'auditoria_incompleta';

        } else {

            /*
             * Reparar diferencias verificables del índice.
             * Excluimos incidencias solo de fecha.
             */

            $aceptados = array_flip(
                array(
                    'sin_indice',
                    'incompleto',
                    'nombre',
                    'sku',
                    'precio',
                    'oferta',
                    'stock',
                    'categorias',
                    'variaciones'
                )
            );

            $ids = array();

            foreach (
                (array) (
                    $auditoria['incidencias'] ?? array()
                ) as $fila
            ) {

                if ( ! is_array( $fila ) ) {
                    continue;
                }

                $id = absint( $fila['id'] ?? 0 );

                $motivos = (array) (
                    $fila['motivos'] ?? array()
                );

                if (
                    $id
                    && array_intersect_key(
                        array_flip( $motivos ),
                        $aceptados
                    )
                ) {

                    $ids[ $id ] = $id;
                }
            }

            if ( ! $ids ) {

                $aviso = 'sin_elegibles';

            } else {

                $token = wp_generate_uuid4();

                $estado = array(
                    'activo' => true,
                    'token' => $token,
                    'situacion' => 'programada',
                    'ids' => array_values( $ids ),
                    'total' => count( $ids ),
                    'cursor' => 0,
                    'revisados' => 0,
                    'reindexados' => 0,
                    'omitidos' => 0,
                    'fallidos' => 0,
                    'ids_fallidos' => array(),
                    'ultima' => 0,
                );

                update_option( $opcion, $estado, false );

                $programado =
                    compuciber_reparacion_indice_programar(
                        $token
                    );

                if (
                    is_wp_error( $programado )
                    || ! $programado
                ) {

                    $estado['activo'] = false;

                    $estado['situacion'] =
                        'error_programacion';

                    update_option(
                        $opcion,
                        $estado,
                        false
                    );

                    $aviso = 'error_programacion';

                } else {

                    $aviso = 'iniciada';
                }
            }
        }
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page' =>
                    'compuciber-ai-search-diagnostico',

                'cis_reparacion_aviso' => $aviso,
            ),
            admin_url( 'admin.php' )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_reparacion_indice_control',
    'compuciber_reparacion_indice_control'
);


/*
 * Cancelar tareas al desactivar el plugin.
 */

function compuciber_reparacion_indice_desactivar() {

    $estado = get_option(
        'compuciber_reparacion_indice_estado',
        array()
    );

    if (
        is_array( $estado )
        && ! empty( $estado['token'] )
    ) {

        wp_clear_scheduled_hook(
            'compuciber_reparacion_indice_lote',
            array( $estado['token'] )
        );

        $estado['activo'] = false;

        $estado['situacion'] = 'plugin_desactivado';

        update_option(
            'compuciber_reparacion_indice_estado',
            $estado,
            false
        );
    }

    delete_option(
        'compuciber_reparacion_indice_lock'
    );
}

register_deactivation_hook(
    dirname( dirname( __DIR__ ) )
        . '/compuciber-ai-search.php',
    'compuciber_reparacion_indice_desactivar'
);