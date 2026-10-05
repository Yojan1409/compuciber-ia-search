<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * ==========================================================
 * DASHBOARD
 * ==========================================================
 */

function compuciber_ai_search_dashboard() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $tabla_estadisticas =
        $wpdb->prefix . 'compuciber_search_stats';

/*
 * ==========================================================
 * ESTADÍSTICAS AVANZADAS - V2.0
 * ==========================================================
 */

$resumen_estadisticas = array(
    'total_busquedas'             => 0,
    'busquedas_con_resultados'    => 0,
    'busquedas_sin_resultados'    => 0,
    'total_clicks'                => 0,
    'tasa_exito'                  => 0,
    'ctr'                         => 0,
);

$busquedas_por_tipo = array(
    'texto'  => 0,
    'voz'    => 0,
    'imagen' => 0,
);

$busquedas_populares = array();
$productos_mas_seleccionados = array();
$busquedas_sin_resultados_lista = array();
$busquedas_por_dia = array();
$busquedas_con_mas_clicks = array();

if (
    get_option(
        'compuciber_estadisticas',
        '0'
    ) === '1'
) {

    $resumen_estadisticas =
        compuciber_obtener_resumen_estadisticas();

    $busquedas_por_tipo =
        compuciber_obtener_busquedas_por_tipo();

    $busquedas_populares =
        compuciber_obtener_busquedas_populares( 10 );

    $productos_mas_seleccionados =
        compuciber_obtener_productos_mas_seleccionados( 10 );

    $busquedas_sin_resultados_lista =
        compuciber_obtener_busquedas_sin_resultados( 10 );

    $busquedas_por_dia =
        compuciber_obtener_busquedas_por_dia( 30 );

    $busquedas_con_mas_clicks =
        compuciber_obtener_busquedas_con_mas_clicks( 10 );
}  

    $productos_woocommerce = wp_count_posts( 'product' );

    $total_productos = 0;

    if ( $productos_woocommerce ) {
        $total_productos = isset( $productos_woocommerce->publish )
            ? (int) $productos_woocommerce->publish
            : 0;
    }

    $productos_indexados = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tabla}"
    );

    /*
    * Estado real de embeddings.
    *
    * Un embedding solamente se considera generado
    * cuando corresponde al texto, proveedor
    * y modelo actualmente utilizados.
    */
    if (
        function_exists(
            'compuciber_obtener_estado_embeddings'
        )
    ) {

        $estado_embeddings =
            compuciber_obtener_estado_embeddings();

        $embeddings_generados =
            absint(
                $estado_embeddings['generados']
            );

        $embeddings_pendientes =
            absint(
                $estado_embeddings['pendientes']
            );

    } else {

        $embeddings_generados = 0;

        $embeddings_pendientes =
            $productos_indexados;
    }

    $ultima_sincronizacion = $wpdb->get_var(
        "SELECT MAX(actualizado) FROM {$tabla}"
    );

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    ?>

    <div class="wrap">

        <h1>AI Product Search</h1>

        <p>
            Panel de control del buscador inteligente
            de productos WooCommerce.
        </p>

        <hr>

        <h2>Estado del sistema</h2>

        <table class="widefat striped">

            <thead>
                <tr>
                    <th>Elemento</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>

                <tr>
                    <td>WooCommerce</td>
                    <td>
                        <?php if ( class_exists( 'WooCommerce' ) ) : ?>

                            <strong style="color:green;">
                                Activo
                            </strong>

                        <?php else : ?>

                            <strong style="color:red;">
                                No disponible
                            </strong>

                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td>Productos WooCommerce</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $total_productos ); ?>
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td>Productos indexados</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $productos_indexados ); ?>
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td>Última sincronización</td>
                    <td>
                        <?php
                        echo ! empty( $ultima_sincronizacion )
                            ? esc_html( $ultima_sincronizacion )
                            : 'Sin sincronización registrada';
                        ?>
                    </td>
                </tr>

                    <tr>
                        <td>Proveedor de IA</td>
                        <td>
                            <strong>
                                <?php echo esc_html( $proveedor ); ?>
                            </strong>
                        </td>
                    </tr>


                    <!-- 🟢ESTADO IA -->

                    <tr>
                        <td>Estado IA</td>
                        <td>

                            <?php

                            $api_key_configurada = false;

                            if ( $proveedor === 'gemini' ) {

                                $api_key_configurada = ! empty(
                                    get_option(
                                        'compuciber_gemini_api_key',
                                        ''
                                    )
                                );

                            } elseif ( $proveedor === 'openai' ) {

                                $api_key_configurada = ! empty(
                                    get_option(
                                        'compuciber_openai_api_key',
                                        ''
                                    )
                                );
                            }

                            ?>

                            <?php if ( $api_key_configurada ) : ?>

                                <strong style="color:green;">
                                    Activa
                                </strong>

                            <?php else : ?>

                                <strong style="color:#d63638;">
                                    No configurada
                                </strong>

                            <?php endif; ?>

                        </td>
                    </tr>


                    <!-- 🟢 FIN -->


                    <tr>
                        <td>Índice de productos</td>
                        <td>

                            <?php if ( $productos_indexados > 0 ) : ?>

                                <strong style="color:green;">
                                    Activo
                                </strong>

                            <?php else : ?>

                                <strong style="color:#d63638;">
                                    Sin productos indexados
                                </strong>

                            <?php endif; ?>

                        </td>
                    </tr>

            </tbody>

        </table>

        <h2>Embeddings de productos</h2>

        <table class="widefat striped">

            <tbody>

                <tr>
                    <td>Productos indexados</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $productos_indexados ); ?>
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td>Embeddings generados</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $embeddings_generados ); ?>
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td>Embeddings pendientes</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $embeddings_pendientes ); ?>
                        </strong>
                    </td>
                </tr>

            </tbody>

        </table>


        <?php if (
            isset( $_GET['embeddings'] )
            && $_GET['embeddings'] === 'procesados'
        ) : ?>

            <div class="notice notice-success is-dismissible">

                <p>
                    Lote procesado.
                    Generados:
                    <strong>
                        <?php
                        echo esc_html(
                            absint(
                                $_GET['generados'] ?? 0
                            )
                        );
                        ?>
                    </strong>

                    | Fallidos:
                    <strong>
                        <?php
                        echo esc_html(
                            absint(
                                $_GET['fallidos'] ?? 0
                            )
                        );
                        ?>
                    </strong>

                    | Pendientes:
                    <strong>
                        <?php
                        echo esc_html(
                            absint(
                                $_GET['pendientes'] ?? 0
                            )
                        );
                        ?>
                    </strong>
                </p>

            </div>

        <?php endif; ?>


        <?php if ( $embeddings_pendientes > 0 ) : ?>

            <p>
                Los embeddings se generan por lotes de hasta
                5 productos para evitar sobrecargar la API.
            </p>

            <form
                method="post"
                action="<?php echo esc_url(
                    admin_url( 'admin-post.php' )
                ); ?>"
            >

                <input
                    type="hidden"
                    name="action"
                    value="compuciber_generar_embeddings"
                >

                <?php
                wp_nonce_field(
                    'compuciber_generar_embeddings'
                );
                ?>

                <?php
                submit_button(
                    'Generar embeddings pendientes',
                    'primary',
                    'submit',
                    false
                );
                ?>

            </form>

        <?php else : ?>

            <p>
                <strong>
                    Todos los productos indexados tienen embedding.
                </strong>
            </p>

        <?php endif; ?>        

        <br>

        <h2>Funciones del buscador</h2>

        <table class="widefat striped">

            <thead>
                <tr>
                    <th>Función</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>

                <tr>
                    <td>Autocompletado</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_autocompletado',
                            '1'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Corrección de búsqueda</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_correccion',
                            '1'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Búsqueda semántica con IA</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_busqueda_semantica',
                            '1'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Búsqueda por voz</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_busqueda_voz',
                            '0'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Búsqueda por imagen</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_busqueda_imagen',
                            '0'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Estadísticas</td>
                    <td>
                        <?php
                        echo get_option(
                            'compuciber_estadisticas',
                            '0'
                        ) ? 'Activado' : 'Desactivado';
                        ?>
                    </td>
                </tr>

            </tbody>

        </table>

        <h2>Estadísticas de búsquedas</h2>

        <?php if (
            get_option(
                'compuciber_estadisticas',
                '0'
            ) !== '1'
        ) : ?>

            <p>
                Las estadísticas de búsquedas están desactivadas.
            </p>

        <?php else : ?>

            <div style="
                display:grid;
                grid-template-columns:repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );
                gap:15px;
                margin:20px 0;
            ">

                <?php

                $tarjetas = array(

                    array(
                        'titulo' => 'Total de búsquedas',
                        'valor'  => absint(
                            $resumen_estadisticas['total_busquedas']
                        ),
                    ),

                    array(
                        'titulo' => 'Con resultados',
                        'valor'  => absint(
                            $resumen_estadisticas['busquedas_con_resultados']
                        ),
                    ),

                    array(
                        'titulo' => 'Sin resultados',
                        'valor'  => absint(
                            $resumen_estadisticas['busquedas_sin_resultados']
                        ),
                    ),

                    array(
                        'titulo' => 'Selecciones',
                        'valor'  => absint(
                            $resumen_estadisticas['total_clicks']
                        ),
                    ),

                    array(
                        'titulo' => 'Tasa de éxito',
                        'valor'  => number_format_i18n(
                            $resumen_estadisticas['tasa_exito'],
                            2
                        ) . '%',
                    ),

                    array(
                        'titulo' => 'CTR global',
                        'valor'  => number_format_i18n(
                            $resumen_estadisticas['ctr'],
                            2
                        ) . '%',
                    ),
                );

                foreach ( $tarjetas as $tarjeta ) :
                    ?>

                    <div style="
                        background:#fff;
                        border:1px solid #dcdcde;
                        border-radius:8px;
                        padding:20px;
                        box-shadow:0 1px 2px rgba(0,0,0,.04);
                    ">

                        <div style="
                            color:#646970;
                            margin-bottom:8px;
                        ">
                            <?php
                            echo esc_html(
                                $tarjeta['titulo']
                            );
                            ?>
                        </div>

                        <div style="
                            font-size:28px;
                            font-weight:600;
                            line-height:1.2;
                        ">
                            <?php
                            echo esc_html(
                                $tarjeta['valor']
                            );
                            ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>  
            
            <h3>Canales de búsqueda</h3>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>Canal</th>
                        <th>Total de búsquedas</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>Texto</td>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    absint(
                                        $busquedas_por_tipo['texto']
                                    )
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <td>Voz</td>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    absint(
                                        $busquedas_por_tipo['voz']
                                    )
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <td>Imagen</td>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    absint(
                                        $busquedas_por_tipo['imagen']
                                    )
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>

                </tbody>

            </table>

            <br> 
            
            <h3>Búsquedas más frecuentes</h3>

            <?php if ( ! empty( $busquedas_populares ) ) : ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Búsqueda</th>
                            <th>Veces buscada</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $busquedas_populares as $estadistica
                        ) : ?>

                            <tr>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $estadistica->busqueda
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            absint(
                                                $estadistica->total
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else : ?>

                <p>
                    Todavía no hay búsquedas registradas.
                </p>

            <?php endif; ?>

            <br>
            
            <h3>Búsquedas sin resultados</h3>

            <?php if (
                ! empty( $busquedas_sin_resultados_lista )
            ) : ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Búsqueda</th>
                            <th>Veces buscada</th>
                            <th>Última búsqueda</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $busquedas_sin_resultados_lista
                            as $busqueda_sin_resultado
                        ) : ?>

                            <tr>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $busqueda_sin_resultado->busqueda
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            absint(
                                                $busqueda_sin_resultado->total
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php

                                    if (
                                        ! empty(
                                            $busqueda_sin_resultado->ultima_busqueda
                                        )
                                    ) {

                                        echo esc_html(
                                            wp_date(
                                                'd/m/Y H:i',
                                                strtotime(
                                                    $busqueda_sin_resultado
                                                        ->ultima_busqueda
                                                )
                                            )
                                        );

                                    } else {

                                        echo '—';
                                    }

                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else : ?>

                <p>
                    No se han registrado búsquedas sin resultados.
                </p>

            <?php endif; ?>

            <br>  
            
            <h3>Productos más seleccionados</h3>

            <?php if (
                ! empty( $productos_mas_seleccionados )
            ) : ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Selecciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $productos_mas_seleccionados
                            as $seleccion
                        ) : ?>

                            <?php

                            $producto_id = absint(
                                $seleccion->producto_id
                            );

                            $producto = wc_get_product(
                                $producto_id
                            );

                            if ( ! $producto ) {
                                continue;
                            }

                            ?>

                            <tr>

                                <td>
                                    <a
                                        href="<?php echo esc_url(
                                            get_edit_post_link(
                                                $producto_id
                                            )
                                        ); ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            $producto->get_name()
                                        );
                                        ?>
                                    </a>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            absint(
                                                $seleccion->total
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else : ?>

                <p>
                    Todavía no hay productos seleccionados.
                </p>

            <?php endif; ?>

            <br>
            
            <h3>Consultas que generan más selecciones</h3>

            <?php if (
                ! empty( $busquedas_con_mas_clicks )
            ) : ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Consulta</th>
                            <th>Selecciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $busquedas_con_mas_clicks
                            as $consulta_click
                        ) : ?>

                            <tr>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $consulta_click->busqueda
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            absint(
                                                $consulta_click->total
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else : ?>

                <p>
                    Todavía no existen suficientes
                    selecciones para mostrar esta métrica.
                </p>

            <?php endif; ?>

            <br>
            
            <h3>Actividad de búsquedas - últimos 30 días</h3>

            <?php if ( ! empty( $busquedas_por_dia ) ) : ?>

                <?php

                $maximo_busquedas = 1;

                foreach ( $busquedas_por_dia as $actividad ) {

                    $total_dia = absint(
                        $actividad->total
                    );

                    if ( $total_dia > $maximo_busquedas ) {
                        $maximo_busquedas = $total_dia;
                    }
                }

                ?>

                <div style="
                    background:#fff;
                    border:1px solid #dcdcde;
                    border-radius:8px;
                    padding:20px;
                    margin-bottom:20px;
                    overflow-x:auto;
                ">

                    <div style="
                        display:flex;
                        align-items:flex-end;
                        gap:10px;
                        min-height:240px;
                        min-width:600px;
                        border-bottom:1px solid #dcdcde;
                        padding:10px 5px 0;
                    ">

                        <?php foreach (
                            $busquedas_por_dia as $actividad
                        ) : ?>

                            <?php

                            $total_dia = absint(
                                $actividad->total
                            );

                            $altura = (
                                $total_dia
                                / $maximo_busquedas
                            ) * 180;

                            $fecha_formateada = wp_date(
                                'd/m',
                                strtotime(
                                    $actividad->fecha
                                )
                            );

                            ?>

                            <div style="
                                flex:1;
                                min-width:35px;
                                text-align:center;
                            ">

                                <div style="
                                    font-weight:600;
                                    margin-bottom:5px;
                                ">
                                    <?php
                                    echo esc_html(
                                        $total_dia
                                    );
                                    ?>
                                </div>

                                <div
                                    style="
                                        height:<?php
                                        echo esc_attr(
                                            max( 5, $altura )
                                        );
                                        ?>px;
                                        background:#2271b1;
                                        border-radius:4px 4px 0 0;
                                        min-width:20px;
                                    "
                                    title="<?php
                                    echo esc_attr(
                                        $fecha_formateada
                                        . ' - '
                                        . $total_dia
                                        . ' búsquedas'
                                    );
                                    ?>"
                                ></div>

                                <div style="
                                    font-size:11px;
                                    margin-top:6px;
                                    color:#646970;
                                ">
                                    <?php
                                    echo esc_html(
                                        $fecha_formateada
                                    );
                                    ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php else : ?>

                <p>
                    No hay actividad registrada durante
                    los últimos 30 días.
                </p>

            <?php endif; ?>

            <br>            

        <?php endif; ?>        

    </div>

    <?php
}


/*
 * ==========================================================
 * CONFIGURACIÓN
 * ==========================================================
 */

function compuciber_ai_search_configuracion() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    /*
    * Mensaje temporal generado por
    * la validación de licencia.
    */
    $mensaje_licencia = get_transient(
        'compuciber_mensaje_licencia_'
            . get_current_user_id()
    );

    if ( false !== $mensaje_licencia ) {

        delete_transient(
            'compuciber_mensaje_licencia_'
                . get_current_user_id()
        );
    }    

    $proveedor = get_option(
        'compuciber_proveedor_ia',
        'gemini'
    );

    $gemini_api_key = get_option(
        'compuciber_gemini_api_key',
        ''
    );

    $openai_api_key = get_option(
        'compuciber_openai_api_key',
        ''
    );

    ?>

    <div class="wrap">

    <?php if ( false !== $mensaje_licencia ) : ?>

        <div
            class="notice notice-info is-dismissible"
            style="margin-top:15px;"
        >
            <p>
                <?php echo esc_html( $mensaje_licencia ); ?>
            </p>
        </div>

    <?php endif; ?>    

        <div style="
            display:flex;
            align-items:center;
            gap:15px;
            margin-bottom:20px;
        ">
            <img
                src="<?php echo esc_url(
                    plugins_url(
                        '../assets/images/logo.jpeg',
                        __FILE__
                    )
                ); ?>"
                alt="Compuciber AI Search"
                style="
                    width:64px;
                    height:64px;
                    object-fit:contain;
                "
            >

            <div>
                <h1 style="margin:0;">
                    Compuciber AI Search
                </h1>

                <p style="
                    margin:5px 0 0;
                    color:#666;
                ">
                    Buscador inteligente de productos para WooCommerce
                </p>
            </div>
        </div>

        <h1>Configuración - AI Product Search</h1>

        <form method="post" action="options.php">

            <?php

            settings_fields(
                'compuciber_ai_search_settings_group'
            );

            ?>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        <label for="compuciber_proveedor_ia">
                            Proveedor de IA
                        </label>
                    </th>

                    <td>

                        <select
                            id="compuciber_proveedor_ia"
                            name="compuciber_proveedor_ia"
                        >

                            <option
                                value="gemini"
                                <?php selected(
                                    $proveedor,
                                    'gemini'
                                ); ?>
                            >
                                Google Gemini
                            </option>

                            <option
                                value="openai"
                                <?php selected(
                                    $proveedor,
                                    'openai'
                                ); ?>
                            >
                                OpenAI
                            </option>

                        </select>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="compuciber_gemini_api_key">
                            Gemini API Key
                        </label>
                    </th>

                    <td>

                        <input
                            type="password"
                            id="compuciber_gemini_api_key"
                            name="compuciber_gemini_api_key"
                            value="<?php echo esc_attr( $gemini_api_key ); ?>"
                            class="regular-text"
                            autocomplete="off"
                        >

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="compuciber_openai_api_key">
                            OpenAI API Key
                        </label>
                    </th>

                    <td>

                        <input
                            type="password"
                            id="compuciber_openai_api_key"
                            name="compuciber_openai_api_key"
                            value="<?php echo esc_attr( $openai_api_key ); ?>"
                            class="regular-text"
                            autocomplete="off"
                        >

                    </td>

                </tr>

            </table>

            <h2>Licencia</h2>

                        <table class="form-table">

                            <tr>

                                <th scope="row">
                                    <label for="compuciber_clave_licencia">
                                        Clave de licencia
                                    </label>
                                </th>

                                <td>

                                    <input
                                        type="password"
                                        id="compuciber_clave_licencia"
                                        name="compuciber_clave_licencia"
                                        value="<?php echo esc_attr(
                                            get_option(
                                                'compuciber_clave_licencia',
                                                ''
                                            )
                                        ); ?>"
                                        class="regular-text"
                                        autocomplete="off"
                                    >

                                    <p class="description">
                                        Introduce la clave de licencia de
                                        Compuciber AI Search.
                                    </p>

                                </td>

                            </tr>

                            <tr>

                                <th scope="row">
                                    Estado de licencia
                                </th>

                                <td>

                                    <?php
                                    $estado_licencia =
                                        compuciber_obtener_estado_licencia();

                                    $estado_operativo_licencia =
                                        compuciber_obtener_estado_operativo_licencia();

                                    ?>

                                    <strong>
                                        <?php
                                        switch ( $estado_licencia ) {

                                            case 'activa':
                                                echo 'Activa';
                                                break;

                                            case 'inactiva':
                                                echo 'Inactiva';
                                                break;

                                            case 'expirada':
                                                echo 'Expirada';
                                                break;

                                            default:
                                                echo 'Sin licencia';
                                                break;
                                        }
                                        ?>
                                    </strong>

                                    <p class="description">
                                        El estado se determina automáticamente
                                        mediante el sistema de licenciamiento.
                                    </p>

                                    <p>
                                        <button
                                            type="button"
                                            id="compuciber-validar-licencia"
                                            class="button button-secondary"
                                        >
                                            Validar licencia
                                        </button>
                                    </p>

                                    <script>
                                    document.addEventListener(
                                        'DOMContentLoaded',
                                        function () {

                                            const boton =
                                                document.getElementById(
                                                    'compuciber-validar-licencia'
                                                );

                                            if ( ! boton ) {
                                                return;
                                            }

                                            boton.addEventListener(
                                                'click',
                                                function () {
                                                    boton.disabled = true;
                                                    boton.textContent = 'Validando...';

                                                    const formulario =
                                                        document.createElement('form');

                                                    formulario.method = 'POST';

                                                    formulario.action =
                                                        <?php
                                                        echo wp_json_encode(
                                                            admin_url('admin-post.php')
                                                        );
                                                        ?>;

                                                    const accion =
                                                        document.createElement('input');

                                                    accion.type = 'hidden';
                                                    accion.name = 'action';
                                                    accion.value =
                                                        'compuciber_validar_licencia';

                                                    formulario.appendChild(
                                                        accion
                                                    );

                                                    const nonce =
                                                        document.createElement('input');

                                                    nonce.type = 'hidden';
                                                    nonce.name = '_wpnonce';
                                                    nonce.value =
                                                        <?php
                                                        echo wp_json_encode(
                                                            wp_create_nonce(
                                                                'compuciber_validar_licencia'
                                                            )
                                                        );
                                                        ?>;

                                                    formulario.appendChild(
                                                        nonce
                                                    );

                                                    document.body.appendChild(
                                                        formulario
                                                    );

                                                    formulario.submit();
                                                }
                                            );
                                        }
                                    );
                                    </script>                             

                                </td>

                            </tr>

                            <tr>
                                <th scope="row">
                                    Estado operativo
                                </th>

                                <td>
                                    <?php

                                    $etiquetas_operativas = array(
                                        'sin_licencia'  => 'Sin licencia',
                                        'activa'        => 'Activa',
                                        'gracia'        => 'Periodo de gracia',
                                        'sin_confirmar' => 'Sin confirmar',
                                        'inactiva'      => 'Inactiva',
                                        'expirada'      => 'Expirada',
                                    );

                                    $etiqueta_operativa =
                                        isset(
                                            $etiquetas_operativas[
                                                $estado_operativo_licencia
                                            ]
                                        )
                                            ? $etiquetas_operativas[
                                                $estado_operativo_licencia
                                            ]
                                            : 'Desconocido';

                                    ?>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $etiqueta_operativa
                                        );
                                        ?>
                                    </strong>


                                    <?php if ( $estado_licencia === 'activa' ) : ?>

                                        <?php

                                        $limite_instalaciones =
                                            compuciber_obtener_limite_instalaciones();

                                        $instalaciones_activas =
                                            compuciber_obtener_instalaciones_activas();

                                        $instalaciones_disponibles =
                                            compuciber_obtener_instalaciones_disponibles();

                                        $fecha_expiracion =
                                            compuciber_obtener_fecha_expiracion_licencia();

                                        ?>
                                        

                                        <p class="description">
                                            
                                            Instalaciones:
                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $instalaciones_activas
                                                );
                                                ?>
                                            </strong>
                                            de
                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $limite_instalaciones
                                                );
                                                ?>
                                            </strong>
                                            utilizadas.
                                            Disponibles:
                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $instalaciones_disponibles
                                                );
                                                ?>
                                            </strong>.
                                        </p>

                                        <?php if ( ! empty( $fecha_expiracion ) ) : ?>

                                            <p class="description">
                                                Fecha de expiración:
                                                <strong>
                                                    <?php
                                                    echo esc_html(
                                                        $fecha_expiracion
                                                    );
                                                    ?>
                                                </strong>
                                            </p>

                                        <?php endif; ?>
                                    <?php endif; ?>


                                    <p class="description">
                                        Indica si la licencia puede utilizarse
                                        actualmente, considerando la última
                                        validación y el periodo de gracia.
                                    </p>

                                </td>
                            </tr>
                            </tr>                            

                        </table>

            <h2>Modelos de IA</h2>

                <?php if ( $proveedor === 'gemini' ) : ?>

            <!-- Conserva la configuración de OpenAI al guardar Gemini -->

            <input
                type="hidden"
                name="compuciber_openai_modelo_texto"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_openai_modelo_texto',
                        'gpt-5.6'
                    )
                ); ?>"
            >

            <input
                type="hidden"
                name="compuciber_openai_modelo_embeddings"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_openai_modelo_embeddings',
                        'text-embedding-3-small'
                    )
                ); ?>"
            >

            <input
                type="hidden"
                name="compuciber_openai_modelo_multimodal"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_openai_modelo_multimodal',
                        'gpt-5.6'
                    )
                ); ?>"
            >

        <?php else : ?>

            <!-- Conserva la configuración de Gemini al guardar OpenAI -->

            <input
                type="hidden"
                name="compuciber_gemini_modelo_texto"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_gemini_modelo_texto',
                        'gemini-3.6-flash'
                    )
                ); ?>"
            >

            <input
                type="hidden"
                name="compuciber_gemini_modelo_embeddings"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_gemini_modelo_embeddings',
                        'gemini-embedding-2'
                    )
                ); ?>"
            >

            <input
                type="hidden"
                name="compuciber_gemini_modelo_multimodal"
                value="<?php echo esc_attr(
                    get_option(
                        'compuciber_gemini_modelo_multimodal',
                        'gemini-3.6-flash'
                    )
                ); ?>"
            >

        <?php endif; ?>    

            <table class="form-table">

                <?php if ( $proveedor === 'gemini' ) : ?>

                    <!-- ==================================================
                        GOOGLE GEMINI
                        ================================================== -->

                    <tr>

                        <th scope="row">
                            Modelo de texto
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_gemini_modelo_texto"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_gemini_modelo_texto',
                                        'gemini-3.6-flash'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gemini-3.6-flash"
                            >

                            <p class="description">
                                Modelo utilizado por Google Gemini
                                para interpretar búsquedas de texto.
                            </p>

                        </td>

                    </tr>


                    <tr>

                        <th scope="row">
                            Modelo de embeddings
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_gemini_modelo_embeddings"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_gemini_modelo_embeddings',
                                        'gemini-embedding-2'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gemini-embedding-2"
                            >

                            <p class="description">
                                Modelo utilizado por Google Gemini
                                para generar embeddings semánticos.
                            </p>

                        </td>

                    </tr>


                    <tr>

                        <th scope="row">
                            Modelo multimodal
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_gemini_modelo_multimodal"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_gemini_modelo_multimodal',
                                        'gemini-3.6-flash'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gemini-3.6-flash"
                            >

                            <p class="description">
                                Modelo utilizado por Google Gemini
                                para analizar imágenes.
                            </p>

                        </td>

                    </tr>

                <?php else : ?>

                    <!-- ==================================================
                        OPENAI
                        ================================================== -->

                    <tr>

                        <th scope="row">
                            Modelo de texto
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_openai_modelo_texto"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_openai_modelo_texto',
                                        'gpt-5.6'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gpt-5.6"
                            >

                            <p class="description">
                                Modelo utilizado por OpenAI
                                para interpretar búsquedas de texto.
                            </p>

                        </td>

                    </tr>


                    <tr>

                        <th scope="row">
                            Modelo de embeddings
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_openai_modelo_embeddings"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_openai_modelo_embeddings',
                                        'text-embedding-3-small'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="text-embedding-3-small"
                            >

                            <p class="description">
                                Modelo utilizado por OpenAI
                                para generar embeddings semánticos.
                            </p>

                        </td>

                    </tr>


                    <tr>

                        <th scope="row">
                            Modelo multimodal
                        </th>

                        <td>

                            <input
                                type="text"
                                name="compuciber_openai_modelo_multimodal"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_openai_modelo_multimodal',
                                        'gpt-5.6'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gpt-5.6"
                            >

                            <p class="description">
                                Modelo utilizado por OpenAI
                                para analizar imágenes.
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>


                <!-- ======================================================
                    VOZ
                    ====================================================== -->

                <tr>

                    <th scope="row">
                        Modelo de voz
                    </th>

                    <td>

                        <input
                            type="text"
                            value="Reconocimiento del navegador (Web Speech API)"
                            class="regular-text"
                            readonly
                        >

                        <p class="description">
                            La búsqueda por voz utiliza el reconocimiento
                            disponible en el navegador y es independiente
                            del proveedor de inteligencia artificial.
                        </p>

                    </td>

                </tr>

            </table>


            <h2>Funciones</h2>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        Autocompletado
                    </th>

                    <td>

                        <label>

                            <input
                                type="checkbox"
                                name="compuciber_autocompletado"
                                value="1"
                                <?php checked(
                                    get_option(
                                        'compuciber_autocompletado',
                                        '1'
                                    ),
                                    '1'
                                ); ?>
                            >

                            Activar autocompletado

                        </label>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        Corrección
                    </th>

                    <td>

                        <label>

                            <input
                                type="checkbox"
                                name="compuciber_correccion"
                                value="1"
                                <?php checked(
                                    get_option(
                                        'compuciber_correccion',
                                        '1'
                                    ),
                                    '1'
                                ); ?>
                            >

                            Corregir errores de escritura

                        </label>

                    </td>

                </tr>

                <tr>

                                    <th scope="row">
                                        Búsqueda semántica
                                    </th>

                                    <td>

                                        <label>

                                            <input
                                                type="hidden"
                                                name="compuciber_busqueda_semantica"
                                                value="0"
                                            >

                                            <input
                                                type="checkbox"
                                                name="compuciber_busqueda_semantica"
                                                value="1"
                                                <?php checked(
                                                    get_option(
                                                        'compuciber_busqueda_semantica',
                                                        '0'
                                                    ),
                                                    '1'
                                                ); ?>
                                            >

                                            Activar búsqueda semántica con IA

                                        </label>

                                    </td>

                                </tr>
                <tr>

                    <th scope="row">
                        Búsqueda por voz
                    </th>

                    <td>

                        <label>

                            <input
                                type="checkbox"
                                name="compuciber_busqueda_voz"
                                value="1"
                                <?php checked(
                                    get_option(
                                        'compuciber_busqueda_voz',
                                        '0'
                                    ),
                                    '1'
                                ); ?>
                            >

                            Activar búsqueda por voz

                        </label>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        Búsqueda por imagen
                    </th>

                    <td>

                        <label>

                            <input
                                type="checkbox"
                                name="compuciber_busqueda_imagen"
                                value="1"
                                <?php checked(
                                    get_option(
                                        'compuciber_busqueda_imagen',
                                        '0'
                                    ),
                                    '1'
                                ); ?>
                            >

                            Activar búsqueda por imagen

                        </label>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        Estadísticas
                    </th>

                    <td>

                        <label>

                            <input
                                type="checkbox"
                                name="compuciber_estadisticas"
                                value="1"
                                <?php checked(
                                    get_option(
                                        'compuciber_estadisticas',
                                        '0'
                                    ),
                                    '1'
                                ); ?>
                            >

                            Activar estadísticas

                        </label>

                    </td>

                </tr>

                <tr>

                                    <th scope="row">
                                        Aprendizaje de ranking
                                    </th>

                                    <td>

                                        <label>

                                            <input
                                                type="hidden"
                                                name="compuciber_aprendizaje_ranking"
                                                value="0"
                                            >

                                            <input
                                                type="checkbox"
                                                name="compuciber_aprendizaje_ranking"
                                                value="1"
                                                <?php checked(
                                                    get_option(
                                                        'compuciber_aprendizaje_ranking',
                                                        '0'
                                                    ),
                                                    '1'
                                                ); ?>
                                            >

                                            Activar aprendizaje de ranking

                                        </label>

                                        <p class="description">
                                            Utiliza las selecciones de productos registradas
                                            para mejorar progresivamente el orden de los
                                            resultados de búsqueda.
                                        </p>

                                    </td>

                                </tr>                

            </table>

            <?php submit_button( 'Guardar configuración' ); ?>

        </form>

    </div>

    <?php
}

/*
 * ==========================================================
 * GENERACIÓN DE EMBEDDINGS POR LOTES
 * ==========================================================
 */

/**
 * Procesa un lote de embeddings desde
 * el panel de administración.
 */
function compuciber_admin_generar_embeddings() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            esc_html__(
                'No tienes permisos para realizar esta acción.',
                'compuciber-ai-search'
            )
        );
    }

    check_admin_referer(
        'compuciber_generar_embeddings'
    );

    if (
        ! function_exists(
            'compuciber_generar_lote_embeddings'
        )
    ) {
        wp_die(
            esc_html__(
                'El generador de embeddings no está disponible.',
                'compuciber-ai-search'
            )
        );
    }


    /*
     * Procesamos un máximo de 5 productos
     * por petición para evitar timeouts
     * y consumo excesivo de API.
     */
    $resultado =
        compuciber_generar_lote_embeddings( 5 );


    /*
     * Regresamos al Dashboard mostrando
     * el resultado del lote.
     */
    $url = add_query_arg(
        array(
            'page'        => 'compuciber-ai-search',
            'embeddings'  => 'procesados',
            'procesados'  => absint(
                $resultado['procesados']
            ),
            'generados'   => absint(
                $resultado['generados']
            ),
            'fallidos'    => absint(
                $resultado['fallidos']
            ),
            'pendientes'  => absint(
                $resultado['pendientes']
            ),
        ),
        admin_url( 'admin.php' )
    );


    wp_safe_redirect( $url );
    exit;
}


add_action(
    'admin_post_compuciber_generar_embeddings',
    'compuciber_admin_generar_embeddings'
);