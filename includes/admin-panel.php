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

    $total_busquedas = 0;
    $busquedas_sin_resultados = 0;
    $busquedas_texto = 0;
    $busquedas_voz = 0;
    $busquedas_imagen = 0;
    $busquedas_populares = array();
    $productos_mas_seleccionados = array();

    if (
        get_option(
            'compuciber_estadisticas',
            '0'
        ) === '1'
    ) {

        $total_busquedas = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tabla_estadisticas}"
        );

        $busquedas_sin_resultados = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tabla_estadisticas}
            WHERE resultados = 0"
        );

        $busquedas_texto = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tabla_estadisticas}
            WHERE tipo = 'texto'"
        );

        $busquedas_voz = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tabla_estadisticas}
            WHERE tipo = 'voz'"
        );

        $busquedas_imagen = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tabla_estadisticas}
            WHERE tipo = 'imagen'"
        );

        $busquedas_populares =
            compuciber_obtener_busquedas_populares( 10 );

        $productos_mas_seleccionados =
            compuciber_obtener_productos_mas_seleccionados( 10 );

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

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>Métrica</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>Total de búsquedas</td>
                        <td>
                            <strong>
                                <?php echo esc_html( $total_busquedas ); ?>
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <td>Búsquedas sin resultados</td>
                        <td>
                            <?php echo esc_html( $busquedas_sin_resultados ); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Búsquedas por texto</td>
                        <td>
                            <?php echo esc_html( $busquedas_texto ); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Búsquedas por voz</td>
                        <td>
                            <?php echo esc_html( $busquedas_voz ); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Búsquedas por imagen</td>
                        <td>
                            <?php echo esc_html( $busquedas_imagen ); ?>
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
                                    <?php
                                    echo esc_html(
                                        $estadistica->total
                                    );
                                    ?>
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

                        <h3>Productos más seleccionados</h3>

                        <?php if ( ! empty( $productos_mas_seleccionados ) ) : ?>

                            <table class="widefat striped">

                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Selecciones</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $productos_mas_seleccionados as $seleccion
                                    ) : ?>

                                        <?php

                                        $producto_id =
                                            absint(
                                                $seleccion->producto_id
                                            );

                                        $producto =
                                            wc_get_product(
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

    $modelo_embeddings = get_option(
        'compuciber_modelo_embeddings',
        ''
    );

    $modelo_multimodal = get_option(
        'compuciber_modelo_multimodal',
        ''
    );

    $modelo_voz = get_option(
        'compuciber_modelo_voz',
        ''
    );

    ?>

    <div class="wrap">

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


            <h2>Modelos de IA</h2>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        Modelo de texto
                    </th>

                    <td>

                        <?php if ( $proveedor === 'gemini' ) : ?>

                            <input
                                type="text"
                                name="compuciber_modelo_ia"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_modelo_ia',
                                        'gemini-3.6-flash'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gemini-3.6-flash"
                            >

                            <p class="description">
                                Modelo de texto utilizado por Google Gemini.
                                Cambiar únicamente por un modelo compatible
                                con la API de Gemini.
                            </p>

                        <?php else : ?>

                            <input
                                type="text"
                                name="compuciber_modelo_ia"
                                value="<?php echo esc_attr(
                                    get_option(
                                        'compuciber_modelo_ia',
                                        'gpt-5.6'
                                    )
                                ); ?>"
                                class="regular-text"
                                placeholder="gpt-5.6"
                            >

                            <p class="description">
                                Modelo de texto utilizado por OpenAI.
                            </p>

                        <?php endif; ?>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        Modelo de embeddings
                    </th>

                    <td>

                        <?php if ( $proveedor === 'gemini' ) : ?>

                            <input
                                type="text"
                                value="gemini-embedding-2"
                                class="regular-text"
                                readonly
                            >

                            <p class="description">
                                Modelo de embeddings utilizado por Google Gemini.
                            </p>

                        <?php else : ?>

                            <input
                                type="text"
                                name="compuciber_modelo_embeddings"
                                value="<?php echo esc_attr(
                                    ! empty( $modelo_embeddings )
                                        ? $modelo_embeddings
                                        : 'text-embedding-3-small'
                                ); ?>"
                                class="regular-text"
                                placeholder="text-embedding-3-small"
                            >

                            <p class="description">
                                Modelo de embeddings utilizado por OpenAI.
                            </p>

                        <?php endif; ?>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        Modelo multimodal
                    </th>

                    <td>

                        <?php if ( $proveedor === 'gemini' ) : ?>

                            <input
                                type="text"
                                name="compuciber_modelo_multimodal"
                                value="<?php echo esc_attr(
                                    ! empty( $modelo_multimodal )
                                        ? $modelo_multimodal
                                        : 'gemini-3.6-flash'
                                ); ?>"
                                class="regular-text"
                                placeholder="gemini-3.6-flash"
                            >

                            <p class="description">
                                Modelo multimodal utilizado por Google Gemini.
                                Cambiar únicamente por un modelo compatible
                                con entrada de imágenes en la API de Gemini.
                            </p>

                        <?php else : ?>

                            <input
                                type="text"
                                name="compuciber_modelo_multimodal"
                                value="<?php echo esc_attr(
                                    ! empty( $modelo_multimodal )
                                        ? $modelo_multimodal
                                        : 'gpt-5.6'
                                ); ?>"
                                class="regular-text"
                                placeholder="gpt-5.6"
                            >

                            <p class="description">
                                Modelo multimodal utilizado por OpenAI.
                            </p>

                        <?php endif; ?>

                    </td>

                </tr>

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
                            de voz disponible en el navegador y no requiere
                            un modelo de IA configurado.
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