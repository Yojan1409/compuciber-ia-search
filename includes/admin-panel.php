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

$clicks_por_origen = array(
    'resultados'     => 0,
    'autocompletado' => 0,
    'desconocido'    => 0,
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

    $clicks_por_origen =
        compuciber_obtener_clicks_por_origen();        

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

    /*
     * Contar solo productos publicados
     * que realmente tienen índice.
     */
    $resumen_indice =
        compuciber_resumen_indice_113();

    $productos_indexados =
        $resumen_indice['indexados'];

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

        $embeddings_sin_texto =
            absint(
                $estado_embeddings['sin_texto'] ?? 0
            );            

    } else {

        $embeddings_generados = 0;

        $embeddings_pendientes =
            $productos_indexados;

        $embeddings_sin_texto = 0;    
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

    <div class="postbox" style="padding:16px;max-width:900px;">

        <details>
            <summary style="
                cursor:pointer;
                font-size:18px;
                font-weight:600;
                padding:4px 0;
            ">
                Cómo instalar y utilizar el buscador
            </summary>

        <p>
            <strong>Shortcode principal:</strong>
            <code>[compuciber_ai_search]</code>
        </p>

        <p>
            También puedes utilizar:
            <code>[ai_product_search]</code>
        </p>

        <p>
            Inserta cualquiera de estos shortcodes
            mediante el bloque Shortcode de WordPress.
        </p>

        <hr>

        <h3>Buscador en cabeceras</h3>

        <p>
            Para mostrar únicamente la barra y
            las sugerencias utiliza:
        </p>

        <p>
            <code>[compuciber_ai_search solo_barra="si"]</code>
        </p>

        <p>
            Antes de utilizarlo, entra en
            <strong>Configuración → Destino de las búsquedas</strong>
            y selecciona una página de resultados.
        </p>

        <p>
            <strong>Modo Solo resultados (recomendado para cabeceras):</strong>
            si ya tienes una barra de búsqueda en la cabecera,
            coloca este shortcode en tu página de resultados:
        </p>

        <p>
            <code>[compuciber_ai_search solo_resultados="si"]</code>
        </p>

        <p>
            Así se mostrarán únicamente los productos encontrados,
            sin repetir la barra de búsqueda que ya aparece
            en la cabecera de la tienda.
        </p>

        <p>
            <strong>Buscador completo:</strong>
            si prefieres mostrar la barra y los resultados
            en una misma página, utiliza:
        </p>

        <p>
            <code>[compuciber_ai_search]</code>
        </p>

        <p>
            <strong>Páginas de resultados por perfil:</strong>
            puedes configurar una página general para todos
            los buscadores y, opcionalmente, seleccionar
            una página diferente dentro de la configuración
            de cada perfil.
        </p>

        <p>
            Si un perfil no tiene una página específica,
            utilizará automáticamente la página general.
            No es necesario crear una página por perfil.
        </p>

        <p>
            <strong>¿Cómo funciona "Solo resultados" con los perfiles?</strong>
        </p>

        <p>
            Para mostrar los productos encontrados sin repetir
            la barra de búsqueda, coloca este shortcode
            en la página de resultados:
            <code>[compuciber_ai_search solo_resultados="si"]</code>.
        </p>

        <p>
            Cuando un cliente realiza una búsqueda desde un
            perfil personalizado, el plugin conserva
            automáticamente el identificador del perfil
            mediante el parámetro <code>cis_perfil</code>.
        </p>

        <p>
            De esta manera, los productos encontrados respetan
            las categorías incluidas y excluidas del perfil
            desde el que se realizó la búsqueda.
        </p>

        <p>
            <strong>No necesitas configurar nuevamente
            las categorías ni indicar el perfil
            en el shortcode de resultados.</strong>
        </p>

        <p>
            Puedes colocar exactamente el mismo shortcode
            de "Solo resultados" en la página general
            o en las páginas específicas de los perfiles.
        </p>

        <p>
            La página de destino determina dónde aparecen
            los resultados, mientras que el perfil determina
            qué productos pueden mostrarse.
        </p>

        <p>
            <strong>Importante:</strong>
            si utilizas "Usar página actual",
            los resultados se mostrarán donde
            esté insertado el buscador.
            En cabeceras se recomienda una página separada.
        </p>

        <hr>

        <h3>Otras formas de insertar el buscador</h3>

        <p>
            <strong>Gutenberg:</strong>
            inserta el bloque "Compuciber AI Search"
            desde el editor de WordPress.

            Puedes seleccionar el buscador predeterminado
            o cualquiera de los perfiles activos.

            En "Modo de visualización" encontrarás
            tres opciones:
            <strong>Buscador completo</strong>,
            <strong>Solo barra</strong> y
            <strong>Solo resultados</strong>.
        </p>

        <p>
            <strong>Elementor:</strong>
            utiliza el widget "Compuciber AI Search"
            desde el constructor visual.

            Puedes seleccionar un perfil personalizado
            o utilizar el buscador predeterminado.

            En "Modo de visualización" elige:

            <br>
            <strong>Buscador completo:</strong>
            muestra la barra y los resultados.

            <br>
            <strong>Solo barra:</strong>
            recomendado para cabeceras.

            <br>
            <strong>Solo resultados:</strong>
            muestra únicamente los productos encontrados,
            sin repetir la barra de búsqueda.

            <br><br>
            También puedes personalizar la apariencia
            desde los controles visuales de Elementor.
        </p>

        <p class="description">
            <strong>Importante:</strong>
            cuando utilices "Solo barra" en una cabecera,
            configura una página de resultados.

            En esa página puedes utilizar
            "Solo resultados" para evitar
            mostrar dos barras de búsqueda.
        </p>

        <p>
            <strong>PHP:</strong>
            para desarrolladores, utiliza:
            <code>&lt;?php compuciber_ai_search(); ?&gt;</code>
        </p>

        <p>
            <strong>HTML personalizado (buscador predeterminado):</strong>
            utiliza este código en un bloque HTML personalizado
            o en el constructor de cabecera de tu tema.
            No necesitas crear ningún perfil.
        </p>

        <pre><code>&lt;div class="compuciber-ai-search-embed"
            data-modo="solo_barra"&gt;
        &lt;/div&gt;</code></pre>

        <p>
            <strong>HTML con perfil personalizado (opcional):</strong>
            si has creado un perfil en la configuración del plugin,
            puedes utilizar su identificador para aplicar sus filtros
            y estilos.
        </p>

        <pre><code>&lt;div class="compuciber-ai-search-embed"
            data-perfil="nombre-del-perfil"
            data-modo="solo_barra"&gt;
        &lt;/div&gt;</code></pre>

        <p>
            Sustituye <code>nombre-del-perfil</code>
            por el identificador real de un perfil activo.
            Si no utilizas perfiles, usa el primer código.
        </p>

        <p>
            <strong>Importante:</strong>
            para insertar el buscador en una cabecera,
            configura una página de resultados en
            AI Product Search → Configuración.
        </p>
        </details>

    </div>

    <div class="postbox" style="padding:16px;max-width:900px;">

        <h2 style="margin-top:0;">
            Diagnóstico de instalación
        </h2>

        <p>
            Comprueba la configuración básica del plugin
            sin modificar ajustes ni realizar búsquedas.
        </p>

        <p>
            <a
                class="button button-secondary"
                href="<?php
                    echo esc_url(
                        wp_nonce_url(
                            add_query_arg(
                                array(
                                    'page' => 'compuciber-ai-search',
                                    'cis_diagnostico' => '1',
                                ),
                                admin_url( 'admin.php' )
                            ),
                            'cis_diagnostico_instalacion'
                        )
                    );
                ?>"
            >
                Comprobar instalación
            </a>
        </p>

        <?php

        $cis_diag_ver = isset( $_GET['cis_diagnostico'] )
            && is_string( $_GET['cis_diagnostico'] )
            && '1' === $_GET['cis_diagnostico'];

        if ( $cis_diag_ver ) :

            $cis_diag_nonce = isset( $_GET['_wpnonce'] )
                && is_string( $_GET['_wpnonce'] )
                ? sanitize_text_field(
                    wp_unslash( $_GET['_wpnonce'] )
                )
                : '';

            if (
                ! wp_verify_nonce(
                    $cis_diag_nonce,
                    'cis_diagnostico_instalacion'
                )
            ) :

                ?>

                <p>
                    El enlace de diagnóstico ha caducado.
                    Vuelve a pulsar el botón.
                </p>

                <?php

            else :

                $cis_diag_filas = array();

                /*
                * WooCommerce.
                */
                $cis_diag_wc = class_exists( 'WooCommerce' );

                $cis_diag_filas[] = array(
                    'WooCommerce',
                    $cis_diag_wc ? 'Correcto' : 'Revisar',
                    $cis_diag_wc
                        ? 'Activo.'
                        : 'Debe estar instalado y activo.'
                );

                /*
                * Shortcode principal.
                */
                $cis_diag_shortcode = shortcode_exists(
                    'compuciber_ai_search'
                );

                $cis_diag_filas[] = array(
                    'Shortcode',
                    $cis_diag_shortcode ? 'Correcto' : 'Revisar',
                    $cis_diag_shortcode
                        ? 'Buscador registrado.'
                        : 'No se encuentra registrado.'
                );

                /*
                * Gutenberg.
                */
                $cis_diag_bloque = class_exists(
                    'WP_Block_Type_Registry'
                ) && WP_Block_Type_Registry::get_instance()
                    ->is_registered( 'compuciber/ai-search' );

                $cis_diag_filas[] = array(
                    'Gutenberg',
                    $cis_diag_bloque ? 'Correcto' : 'Revisar',
                    $cis_diag_bloque
                        ? 'Bloque registrado.'
                        : 'Bloque no registrado.'
                );

                /*
                * Elementor es opcional.
                */
                $cis_diag_elementor = class_exists(
                    'Elementor\Plugin'
                );

                $cis_diag_filas[] = array(
                    'Elementor',
                    $cis_diag_elementor
                        ? 'Disponible'
                        : 'Opcional',
                    $cis_diag_elementor
                        ? 'Activo.'
                        : 'No está activo; no es obligatorio.'
                );

                /*
                * Página general de resultados.
                */
                $cis_diag_pagina = absint(
                    get_option(
                        'compuciber_pagina_resultados',
                        0
                    )
                );

                $cis_diag_publicada =
                    $cis_diag_pagina > 0
                    && 'page' === get_post_type(
                        $cis_diag_pagina
                    )
                    && 'publish' === get_post_status(
                        $cis_diag_pagina
                    );

                $cis_diag_filas[] = array(
                    'Destino general',

                    $cis_diag_publicada
                        ? 'Correcto'
                        : (
                            $cis_diag_pagina
                                ? 'Revisar'
                                : 'Información'
                        ),

                    $cis_diag_publicada
                        ? 'Página seleccionada y publicada.'
                        : (
                            $cis_diag_pagina
                                ? 'La página no existe o no está publicada.'
                                : 'Usar página actual. Para Solo barra, configura una página publicada.'
                        )
                );

                /*
                * Comprobar páginas específicas
                * de los perfiles activos.
                */
                $cis_diag_perfiles = get_option(
                    'compuciber_perfiles_busqueda',
                    array()
                );

                $cis_diag_activos = 0;
                $cis_diag_invalidos = array();

                if ( is_array( $cis_diag_perfiles ) ) {

                    foreach (
                        $cis_diag_perfiles
                        as $cis_diag_id => $cis_diag_datos
                    ) {

                        if (
                            ! is_array( $cis_diag_datos )
                            || empty( $cis_diag_datos['activo'] )
                        ) {
                            continue;
                        }

                        $cis_diag_activos++;

                        $cis_diag_destino = absint(
                            $cis_diag_datos['pagina_resultados']
                            ?? 0
                        );

                        if (
                            $cis_diag_destino > 0
                            && (
                                'page' !== get_post_type(
                                    $cis_diag_destino
                                )
                                || 'publish' !== get_post_status(
                                    $cis_diag_destino
                                )
                            )
                        ) {

                            $cis_diag_invalidos[] =
                                sanitize_text_field(
                                    (string) (
                                        $cis_diag_datos['nombre']
                                        ?? $cis_diag_id
                                    )
                                );
                        }
                    }
                }

                $cis_diag_filas[] = array(
                    'Perfiles activos',

                    $cis_diag_invalidos
                        ? 'Revisar'
                        : 'Correcto',

                    $cis_diag_invalidos
                        ? 'Páginas específicas no publicadas: '
                            . implode(
                                ', ',
                                array_slice(
                                    $cis_diag_invalidos,
                                    0,
                                    5
                                )
                            )
                        : $cis_diag_activos
                            . ' perfil(es) activo(s).'
                );

                ?>

                <table
                    class="widefat striped"
                    style="max-width:850px;"
                >

                    <thead>
                        <tr>
                            <th>Comprobación</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $cis_diag_filas as $cis_diag_fila
                        ) : ?>

                            <tr>

                                <td>
                                    <?php echo esc_html(
                                        $cis_diag_fila[0]
                                    ); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo esc_html(
                                            $cis_diag_fila[1]
                                        ); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php echo esc_html(
                                        $cis_diag_fila[2]
                                    ); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

                <p class="description">
                    Este informe es informativo.
                    No modifica productos, perfiles,
                    estadísticas ni configuraciones.
                    No comprueba la conexión externa con Gemini.
                </p>

            <?php endif; ?>

        <?php endif; ?>

    </div>

<!-- SIMULADOR ADMINISTRATIVO DE BÚSQUEDAS -->

    <div class="postbox" style="padding:16px;max-width:900px;">

        <h2 style="margin-top:0;">
            Simulador de búsquedas
        </h2>

        <p>
            Comprueba los resultados del motor local
            y las categorías permitidas por cada perfil.
        </p>

        <?php

        $cis_sim_perfiles = get_option(
            'compuciber_perfiles_busqueda',
            array()
        );

        $cis_sim_perfiles = is_array( $cis_sim_perfiles )
            ? $cis_sim_perfiles
            : array();

        $cis_sim_ejecutar = isset( $_GET['cis_simular'] )
            && is_string( $_GET['cis_simular'] )
            && '1' === wp_unslash( $_GET['cis_simular'] );

        $cis_sim_texto = isset( $_GET['cis_sim_q'] )
            && is_string( $_GET['cis_sim_q'] )
            ? trim(
                sanitize_text_field(
                    wp_unslash( $_GET['cis_sim_q'] )
                )
            )
            : '';

        $cis_sim_texto = mb_substr(
            $cis_sim_texto,
            0,
            100
        );

        $cis_sim_perfil = isset( $_GET['cis_perfil'] )
            && is_string( $_GET['cis_perfil'] )
            ? sanitize_key(
                wp_unslash( $_GET['cis_perfil'] )
            )
            : '';

        ?>

        <form
            method="get"
            action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
        >

            <input
                type="hidden"
                name="page"
                value="compuciber-ai-search"
            >

            <input
                type="hidden"
                name="cis_simular"
                value="1"
            >

            <?php
            wp_nonce_field(
                'cis_simulacion_local',
                'cis_sim_nonce'
            );
            ?>

            <p>
                <label for="cis-sim-q">
                    <strong>Producto o término de búsqueda</strong>
                </label>
                <br>

                <input
                    type="search"
                    id="cis-sim-q"
                    name="cis_sim_q"
                    value="<?php echo esc_attr( $cis_sim_texto ); ?>"
                    maxlength="100"
                    required
                    class="regular-text"
                    placeholder="Ejemplo: laptop Lenovo"
                >
            </p>

            <p>
                <label for="cis-sim-perfil">
                    <strong>Perfil de búsqueda</strong>
                </label>
                <br>

                <select
                    name="cis_perfil"
                    id="cis-sim-perfil"
                >

                    <option value="">
                        Predeterminado (todo el catálogo)
                    </option>

                    <?php foreach (
                        $cis_sim_perfiles as $cis_id => $cis_datos
                    ) : ?>

                        <?php
                        if (
                            ! is_array( $cis_datos )
                            || empty( $cis_datos['activo'] )
                        ) {
                            continue;
                        }
                        ?>

                        <option
                            value="<?php echo esc_attr( $cis_id ); ?>"
                            <?php
                            selected(
                                $cis_sim_perfil,
                                $cis_id
                            );
                            ?>
                        >
                            <?php
                            echo esc_html(
                                $cis_datos['nombre'] ?? $cis_id
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </p>

            <p>
                <button
                    type="submit"
                    class="button button-primary"
                >
                    Simular búsqueda
                </button>
            </p>

        </form>

        <?php if ( $cis_sim_ejecutar ) : ?>

            <?php

            $cis_sim_nonce = isset( $_GET['cis_sim_nonce'] )
                && is_string( $_GET['cis_sim_nonce'] )
                ? sanitize_text_field(
                    wp_unslash( $_GET['cis_sim_nonce'] )
                )
                : '';

            $cis_sim_perfil_valido = $cis_sim_perfil === ''
                || (
                    isset( $cis_sim_perfiles[ $cis_sim_perfil ] )
                    && is_array(
                        $cis_sim_perfiles[ $cis_sim_perfil ]
                    )
                    && ! empty(
                        $cis_sim_perfiles[ $cis_sim_perfil ]['activo']
                    )
                );

            ?>

            <?php if (
                ! wp_verify_nonce(
                    $cis_sim_nonce,
                    'cis_simulacion_local'
                )
            ) : ?>

                <p class="notice notice-error inline">
                    La verificación ha caducado.
                    Vuelve a simular.
                </p>

            <?php elseif (
                $cis_sim_texto === ''
                || ! $cis_sim_perfil_valido
            ) : ?>

                <p class="notice notice-error inline">
                    Escribe una búsqueda y selecciona
                    un perfil activo.
                </p>

            <?php elseif (
                ! class_exists( 'WooCommerce' )
            ) : ?>

                <p class="notice notice-error inline">
                    WooCommerce debe estar activo.
                </p>

            <?php else : ?>

                <?php

                /*
                 * Aplicar las correcciones existentes.
                 */
                $cis_sim_corregida =
                    compuciber_corregir_busqueda(
                        $cis_sim_texto
                    );

                /*
                 * Desactivar temporalmente las funciones
                 * semánticas solo durante esta simulación.
                 *
                 * No modifica la configuración guardada.
                 */
                add_filter(
                    'pre_option_compuciber_busqueda_semantica',
                    '__return_zero',
                    PHP_INT_MAX
                );

                try {

                    /*
                     * Utilizar directamente el motor local.
                     *
                     * Esta llamada no registra búsquedas
                     * en las estadísticas del plugin.
                     */
                    $cis_sim_resultados =
                        compuciber_buscar_productos_inteligente(
                            $cis_sim_corregida,
                            array()
                        );

                } finally {

                    /*
                     * Restaurar inmediatamente el
                     * comportamiento habitual del plugin.
                     */
                    remove_filter(
                        'pre_option_compuciber_busqueda_semantica',
                        '__return_zero',
                        PHP_INT_MAX
                    );

                }

                ?>

                <?php if (
                    $cis_sim_resultados instanceof WP_Query
                ) : ?>

                    <p>
                        <strong>Productos encontrados:</strong>

                        <?php
                        echo esc_html(
                            (string) $cis_sim_resultados->found_posts
                        );
                        ?>
                    </p>

                    <?php if (
                        $cis_sim_corregida !== $cis_sim_texto
                    ) : ?>

                        <p>
                            Corrección aplicada:

                            <strong>
                                <?php
                                echo esc_html(
                                    $cis_sim_corregida
                                );
                                ?>
                            </strong>
                        </p>

                    <?php endif; ?>

                    <?php if (
                        ! empty( $cis_sim_resultados->posts )
                    ) : ?>

                        <h3>Primeros productos encontrados</h3>

                        <ol>

                            <?php foreach (
                                $cis_sim_resultados->posts
                                as $cis_sim_producto
                            ) : ?>

                                <li>

                                    <a
                                        href="<?php
                                            echo esc_url(
                                                get_permalink(
                                                    $cis_sim_producto->ID
                                                )
                                            );
                                        ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >

                                        <?php
                                        echo esc_html(
                                            get_the_title(
                                                $cis_sim_producto->ID
                                            )
                                        );
                                        ?>

                                    </a>

                                    <small>
                                        (ID:
                                        <?php
                                        echo esc_html(
                                            (string) $cis_sim_producto->ID
                                        );
                                        ?>)
                                    </small>

                                </li>

                            <?php endforeach; ?>

                        </ol>

                    <?php else : ?>

                        <p>
                            No encontramos productos
                            en esta simulación local.
                        </p>

                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        <?php endif; ?>

        <p class="description">
            <strong>Modo de prueba local:</strong>
            utiliza el motor de búsqueda existente
            respetando los perfiles y sus categorías.

            No consulta Gemini ni genera embeddings.

            Los resultados pueden diferir de una búsqueda
            real que utilice interpretación semántica
            o correcciones fuzzy adicionales.

            Las simulaciones no se registran
            como búsquedas de clientes.
        </p>

    </div>

    <!-- FIN DEL SIMULADOR ADMINISTRATIVO -->    

            <h2>Exportar estadísticas</h2>

            <p>
                Descarga los datos registrados por el buscador
                para su análisis y elaboración de reportes.
            </p>

            <?php if (
                get_option(
                    'compuciber_estadisticas',
                    '0'
                ) === '1'
            ) : ?>

                <p>
                    <a
                        href="<?php
                            echo esc_url(
                                wp_nonce_url(
                                    admin_url(
                                        'admin-post.php?action=compuciber_exportar_estadisticas'
                                    ),
                                    'compuciber_exportar_estadisticas'
                                )
                            );
                        ?>"
                        class="button button-secondary"
                    >
                        Exportar CSV
                    </a>

                    <a
                        href="<?php
                            echo esc_url(
                                wp_nonce_url(
                                    admin_url(
                                        'admin-post.php?action=compuciber_exportar_estadisticas_excel'
                                    ),
                                    'compuciber_exportar_estadisticas_excel'
                                )
                            );
                        ?>"
                        class="button button-secondary"
                    >
                        Exportar Excel
                    </a>
                </p>

            <?php else : ?>

                <p>
                    <em>
                        Activa las estadísticas para habilitar
                        las opciones de exportación.
                    </em>
                </p>

            <?php endif; ?>     

        <hr>

        <h2>Estado del sistema</h2>

        <?php
        $progreso_indice = $resumen_indice['total'] > 0
            ? round(
                100 * $resumen_indice['indexados']
                / $resumen_indice['total']
            )
            : 100;

        $estado_indice = get_option(
            'compuciber_indice_lotes_estado',
            array()
        );

        $estado_indice = is_array( $estado_indice )
            ? $estado_indice
            : array();

        $proximo_lote = wp_next_scheduled(
            'compuciber_indice_lote_113'
        );

        $error_indice = (
            $estado_indice['estado'] ?? ''
        ) === 'errores';

        $cron_atrasado = (
            $proximo_lote
            && $proximo_lote < time() - 300
        );

        if ( $resumen_indice['total'] === 0 ) {

            $mensaje_indice =
                'Sin productos publicados';

        } elseif (
            $resumen_indice['pendientes'] === 0
        ) {

            $mensaje_indice = 'Completado';

        } elseif ( $error_indice ) {

            $mensaje_indice =
                'Con errores: requiere revisión';

        } elseif ( $cron_atrasado ) {

            $mensaje_indice =
                'Detenido o retrasado: revisar WP-Cron';

        } else {

            $mensaje_indice =
                'En proceso o pendiente de ejecución';
        }
        ?>

        <div class="postbox"
             style="padding:12px 16px;max-width:700px;">

            <h3>Estado de indexación automática</h3>

            <p>
                <strong>
                    <?php echo esc_html(
                        $mensaje_indice
                    ); ?>
                </strong>
            </p>

            <p>
                <?php echo esc_html(
                    $resumen_indice['indexados']
                ); ?>

                de

                <?php echo esc_html(
                    $resumen_indice['total']
                ); ?>

                productos publicados indexados.
            </p>

            <progress
                value="<?php echo esc_attr(
                    $progreso_indice
                ); ?>"
                max="100"
                style="width:100%;height:18px;"
            ></progress>

            <p>
                Progreso:
                <strong>
                    <?php echo esc_html(
                        $progreso_indice
                    ); ?> %
                </strong>
            </p>

            <p>
                Pendientes:
                <strong>
                    <?php echo esc_html(
                        $resumen_indice['pendientes']
                    ); ?>
                </strong>
            </p>

            <?php if (
                $resumen_indice['pendientes'] > 0
            ) : ?>

                <form
                    action="<?php echo esc_url(
                        admin_url( 'admin-post.php' )
                    ); ?>"
                    method="post"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="compuciber_reintentar_indice_113"
                    >

                    <?php wp_nonce_field(
                        'compuciber_reintentar_indice_113'
                    ); ?>

                    <?php submit_button(
                        'Reintentar indexación',
                        'secondary',
                        'submit',
                        false
                    ); ?>

                </form>

                <p class="description">
                    El índice se procesa automáticamente
                    en lotes de hasta 40 productos.
                    WP-Cron debe estar funcionando
                    para completar el proceso.
                </p>

            <?php endif; ?>

        </div>        

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

                <tr>
                    <td>Productos sin texto semántico</td>
                    <td>
                        <strong>
                            <?php echo esc_html( $embeddings_sin_texto ); ?>
                        </strong>
                    </td>
                </tr>                

            </tbody>

        </table>
        <?php
        $estado_lote = isset( $_GET['embeddings'] )
            ? sanitize_key( wp_unslash( $_GET['embeddings'] ) )
            : '';

        $estados_permitidos = array(
            'completado',
            'parcial',
            'fallidos',
            'bloqueado',
        );

        if ( in_array( $estado_lote, $estados_permitidos, true ) ) :

            $procesados = isset( $_GET['procesados'] )
                ? absint( $_GET['procesados'] )
                : 0;

            $generados = isset( $_GET['generados'] )
                ? absint( $_GET['generados'] )
                : 0;

            $fallidos = isset( $_GET['fallidos'] )
                ? absint( $_GET['fallidos'] )
                : 0;

            $pendientes = isset( $_GET['pendientes'] )
                ? absint( $_GET['pendientes'] )
                : 0;

            $clase_aviso = 'notice-success';
            $mensaje = 'Lote procesado correctamente.';

            if ( $estado_lote === 'bloqueado' ) {

                $clase_aviso = 'notice-warning';

                $mensaje =
                    'Ya existe un lote de embeddings en ejecución. '
                    . 'Espera a que termine antes de intentarlo nuevamente.';

            } elseif ( $estado_lote === 'fallidos' ) {

                $clase_aviso = 'notice-error';

                $mensaje =
                    'El lote terminó con uno o más fallos. '
                    . 'Los productos pendientes podrán reintentarse.';

            } elseif ( $estado_lote === 'parcial' ) {

                $clase_aviso = 'notice-warning';

                $mensaje =
                    'El lote se detuvo por el límite de tiempo preventivo. '
                    . 'Puedes continuar procesando los pendientes.';
            }
        ?>

            <div class="notice <?php echo esc_attr( $clase_aviso ); ?> is-dismissible">

                <p>
                    <strong>
                        <?php echo esc_html( $mensaje ); ?>
                    </strong>
                </p>

                <?php if ( $estado_lote !== 'bloqueado' ) : ?>

                    <p>
                        Procesados:
                        <strong><?php echo esc_html( $procesados ); ?></strong>

                        | Generados:
                        <strong><?php echo esc_html( $generados ); ?></strong>

                        | Fallidos:
                        <strong><?php echo esc_html( $fallidos ); ?></strong>

                        | Pendientes:
                        <strong><?php echo esc_html( $pendientes ); ?></strong>
                    </p>

                <?php endif; ?>

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

                <?php if ( $embeddings_sin_texto > 0 ) : ?>

                    <p>
                        <strong>
                            No quedan embeddings pendientes de generación.
                        </strong>
                        Sin embargo, existen
                        <?php echo esc_html( $embeddings_sin_texto ); ?>
                        productos sin texto semántico que requieren
                        revisar su indexación.
                    </p>

                <?php else : ?>

                    <p>
                        <strong>
                            Todos los productos indexados tienen
                            su embedding actualizado.
                        </strong>
                    </p>

                <?php endif; ?>

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
                        'titulo' => 'Clics en resultados',
                        'valor'  => absint(
                            $clicks_por_origen['resultados']
                        ),
                    ),

                    array(
                        'titulo' => 'Clics en autocompletado',
                        'valor'  => absint(
                            $clicks_por_origen['autocompletado']
                        ),
                    ),

                    array(
                        'titulo' => 'Clics anteriores (sin origen)',
                        'valor'  => absint(
                            $clicks_por_origen['desconocido']
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

        <?php

        /*
        * Avisos de confirmación de Compuciber AI Search.
        * Solo afecta a los mensajes del administrador.
        */

        $cis_opciones_guardadas = isset( $_GET['settings-updated'] )
            && is_string( $_GET['settings-updated'] )
            && 'true' === sanitize_text_field(
                wp_unslash( $_GET['settings-updated'] )
            );

        if ( $cis_opciones_guardadas ) {

            settings_errors();

        } else {

            $cis_avisos = array(

                'cis_perfil' => array(

                    'creado' => array(
                        'success',
                        'Perfil creado correctamente.'
                    ),

                    'guardado' => array(
                        'success',
                        'Perfil actualizado correctamente.'
                    ),

                    'eliminado' => array(
                        'success',
                        'Perfil eliminado correctamente.'
                    ),

                    'nombre_vacio' => array(
                        'error',
                        'Escribe un nombre para crear el perfil.'
                    ),

                ),

                'cis_resultado' => array(

                    'creada' => array(
                        'success',
                        'Página de resultados preparada y seleccionada correctamente.'
                    ),

                    'error' => array(
                        'error',
                        'No se pudo crear la página de resultados.'
                    ),

                ),

            );

            foreach ( $cis_avisos as $cis_parametro => $cis_estados ) {

                if (
                    ! isset( $_GET[ $cis_parametro ] )
                    || ! is_string( $_GET[ $cis_parametro ] )
                ) {
                    continue;
                }

                $cis_estado = sanitize_key(
                    wp_unslash( $_GET[ $cis_parametro ] )
                );

                if ( ! isset( $cis_estados[ $cis_estado ] ) ) {
                    continue;
                }

                $cis_tipo_aviso = $cis_estados[ $cis_estado ][0];

                $cis_texto_aviso = $cis_estados[ $cis_estado ][1];

                ?>

                <div
                    class="notice notice-<?php echo esc_attr( $cis_tipo_aviso ); ?> is-dismissible"
                    role="<?php echo esc_attr(
                        'error' === $cis_tipo_aviso ? 'alert' : 'status'
                    ); ?>"
                >
                    <p>
                        <?php echo esc_html( $cis_texto_aviso ); ?>
                    </p>
                </div>

                <?php

                break;
            }
        }

        ?>        

        <?php
        $cis_perfiles_panel = get_option(
            'compuciber_perfiles_busqueda',
            array()
        );

        if ( ! is_array( $cis_perfiles_panel ) ) {
            $cis_perfiles_panel = array();
        }
        ?>

        <div class="postbox" style="padding:16px;max-width:900px;margin-top:20px;">

            <h2 style="margin-top:0;">
                Buscadores y perfiles
            </h2>

            <p>
                Crea perfiles con nombres personalizados.
                Cada perfil podrá tener sus propios filtros
                y configuraciones de búsqueda.
            </p>

            <form method="post"
                action="<?php echo esc_url(
                    admin_url( 'admin-post.php' )
                ); ?>">

                <input
                    type="hidden"
                    name="action"
                    value="compuciber_crear_perfil_busqueda"
                >

                <?php
                wp_nonce_field(
                    'compuciber_crear_perfil_busqueda'
                );
                ?>

                <p>
                    <label for="cis_nombre_perfil">
                        <strong>Nombre del nuevo perfil</strong>
                    </label>
                </p>

                <p>
                    <input
                        type="text"
                        id="cis_nombre_perfil"
                        name="cis_nombre_perfil"
                        class="regular-text"
                        maxlength="80"
                        placeholder="Ejemplo: Reacondicionados"
                        required
                    >
                </p>

                <?php
                submit_button(
                    'Crear perfil',
                    'secondary',
                    'submit',
                    false
                );
                ?>

            </form>

            <?php if ( ! empty( $cis_perfiles_panel ) ) : ?>

                <h3>Perfiles creados</h3>

                <?php
                $cis_categorias = taxonomy_exists( 'product_cat' )
                    ? get_terms( array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ) )
                    : array();

                if ( is_wp_error( $cis_categorias ) ) {
                    $cis_categorias = array();
                }
                ?>

                <?php foreach ( $cis_perfiles_panel as $cis_id => $cis_datos ) : ?>

                    <?php
                    if ( ! is_array( $cis_datos ) ) {
                        continue;
                    }

                    $cis_incluir = array_map(
                        'intval',
                        (array) ( $cis_datos['incluir'] ?? array() )
                    );

                    $cis_excluir = array_map(
                        'intval',
                        (array) ( $cis_datos['excluir'] ?? array() )
                    );
                    ?>

                    <details style="
                        margin:12px 0;
                        border:1px solid #c3c4c7;
                        border-radius:8px;
                        background:#fff;
                        overflow:hidden;
                    ">

                        <summary style="
                            padding:14px 16px;
                            cursor:pointer;
                            font-size:14px;
                            font-weight:600;
                        ">

                            <?php
                            echo esc_html(
                                $cis_datos['nombre'] ?? $cis_id
                            );
                            ?>

                            <span style="
                                float:right;
                                font-weight:400;
                                color:#50575e;
                            ">
                                <?php
                                echo ! empty( $cis_datos['activo'] )
                                    ? 'Activo'
                                    : 'Inactivo';
                                ?>
                            </span>

                        </summary>

                    <form method="post"
                        action="<?php echo esc_url(
                            admin_url( 'admin-post.php' )
                        ); ?>"
                        style="padding:15px;border:1px solid #ddd;margin:15px 0;">

                        <input type="hidden"
                            name="action"
                            value="compuciber_guardar_perfil_busqueda">

                        <input type="hidden"
                            name="cis_perfil_id"
                            value="<?php echo esc_attr( $cis_id ); ?>">

                        <?php
                        wp_nonce_field(
                            'compuciber_guardar_perfil_busqueda_' . $cis_id
                        );
                        ?>

                        <p>
                            <strong>Nombre del perfil</strong>
                        </p>

                        <p>
                            <input type="text"
                                name="cis_nombre_perfil_editar"
                                value="<?php echo esc_attr(
                                    $cis_datos['nombre'] ?? ''
                                ); ?>"
                                class="regular-text"
                                required>
                        </p>

                        <p>
                            <strong>Shortcode de este perfil:</strong>
                        </p>

                        <p>
                            <code>[compuciber_ai_search perfil="<?php
                                echo esc_attr( $cis_id );
                            ?>"]</code>
                        </p>

                        <p>
                            <strong>Para insertar en una cabecera:</strong>
                        </p>

                        <p>
                            <code>[compuciber_ai_search perfil="<?php
                                echo esc_attr( $cis_id );
                            ?>" solo_barra="si"]</code>
                        </p>

                        <p class="description">
                            Ambos shortcodes utilizan los filtros de este perfil.
                            Para el modo de cabecera, configura una página
                            independiente de resultados.
                        </p>                        

                        <p>
                            <label>
                                <input type="checkbox"
                                    name="cis_perfil_activo"
                                    value="1"
                                    <?php checked(
                                        ! empty( $cis_datos['activo'] )
                                    ); ?>>
                                Perfil activo
                            </label>
                        </p>

                        <p>
                            <label for="cis_pagina_resultados_<?php echo esc_attr( $cis_id ); ?>">
                                <strong>Página de resultados de este perfil</strong>
                            </label><br>

                            <?php
                            wp_dropdown_pages(
                                array(
                                    'name'              => 'cis_pagina_resultados',
                                    'id'                => 'cis_pagina_resultados_' . $cis_id,
                                    'selected'          => absint(
                                        $cis_datos['pagina_resultados'] ?? 0
                                    ),
                                    'show_option_none'  => 'Usar página general (predeterminado)',
                                    'option_none_value' => '0',
                                    'post_status'       => 'publish',
                                )
                            );
                            ?>
                        </p>

                        <p class="description">
                            Si no seleccionas otra página, este perfil utilizará
                            la página de resultados general configurada en el plugin.
                            Si eliges una página específica, deberá estar preparada
                            para mostrar los resultados de Compuciber AI Search.
                        </p>                        

                        <h4>Filtros por categorías</h4>

                        <p class="description">
                            Selecciona las categorías que este buscador
                            podrá incluir o excluir.
                            Las subcategorías se muestran también.
                        </p>

                        <?php if ( ! empty( $cis_categorias ) ) : ?>

                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Incluir</th>
                                        <th>Excluir</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ( $cis_categorias as $categoria ) : ?>

                                        <tr>
                                            <td>
                                                <?php
                                                echo esc_html( $categoria->name );

                                                if ( $categoria->parent ) {
                                                    echo ' <small>(Subcategoría)</small>';
                                                }
                                                ?>
                                            </td>

                                            <td>
                                                <input type="checkbox"
                                                    name="cis_categorias_incluir[]"
                                                    value="<?php echo esc_attr(
                                                        $categoria->term_id
                                                    ); ?>"
                                                    <?php checked(
                                                        in_array(
                                                            (int) $categoria->term_id,
                                                            $cis_incluir,
                                                            true
                                                        )
                                                    ); ?>>
                                            </td>

                                            <td>
                                                <input type="checkbox"
                                                    name="cis_categorias_excluir[]"
                                                    value="<?php echo esc_attr(
                                                        $categoria->term_id
                                                    ); ?>"
                                                    <?php checked(
                                                        in_array(
                                                            (int) $categoria->term_id,
                                                            $cis_excluir,
                                                            true
                                                        )
                                                    ); ?>>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        <?php else : ?>

                            <p>No se encontraron categorías WooCommerce.</p>

                        <?php endif; ?>

                        <p>
                            <?php
                            submit_button(
                                'Guardar perfil',
                                'secondary',
                                'submit',
                                false
                            );
                            ?>
                        </p>

                    </form>

                    <?php
                    $cis_estilos_guardados = get_option(
                        'compuciber_estilos_perfiles',
                        array()
                    );

                    if ( ! is_array( $cis_estilos_guardados ) ) {
                        $cis_estilos_guardados = array();
                    }

                    $cis_colores_base = array(
                        'fondo'       => '#ffffff',
                        'texto'       => '#222222',
                        'borde'       => '#d1d5db',
                        'boton'       => '#1e3a5f',
                        'texto_boton' => '#ffffff',
                    );

                    $cis_estilo_perfil = isset(
                        $cis_estilos_guardados[ $cis_id ]
                    ) && is_array(
                        $cis_estilos_guardados[ $cis_id ]
                    )
                        ? $cis_estilos_guardados[ $cis_id ]
                        : array();

                    $cis_etiquetas_colores = array(
                        'fondo'       => 'Fondo',
                        'texto'       => 'Texto',
                        'borde'       => 'Borde',
                        'boton'       => 'Botón de búsqueda',
                        'texto_boton' => 'Texto del botón',
                    );
                    ?>

                    <form method="post"
                        action="<?php echo esc_url(
                            admin_url( 'options.php' )
                        ); ?>"
                        style="padding:15px;border:1px solid #ddd;margin:0 0 20px;">

                        <?php
                        settings_fields(
                            'compuciber_estilos_perfiles_settings_group'
                        );
                        ?>

                        <?php
                        // Conservar los estilos de los demás perfiles.
                        foreach (
                            $cis_estilos_guardados as $cis_otro_id => $cis_otro_estilo
                        ) :

                            if (
                                $cis_otro_id === $cis_id
                                || ! is_array( $cis_otro_estilo )
                            ) {
                                continue;
                            }

                            foreach (
                                array(
                                    'fondo',
                                    'texto',
                                    'borde',
                                    'boton',
                                    'texto_boton',
                                    'radio',
                                    'placeholder'
                                ) as $cis_campo
                            ) :
                        ?>

                            <input type="hidden"
                                name="compuciber_estilos_perfiles[<?php
                                    echo esc_attr( $cis_otro_id );
                                ?>][<?php echo esc_attr( $cis_campo ); ?>]"
                                value="<?php echo esc_attr(
                                    $cis_otro_estilo[ $cis_campo ] ?? ''
                                ); ?>">

                        <?php
                            endforeach;
                        endforeach;
                        ?>

                        <h4>Personalizar apariencia del buscador</h4>

                        <?php foreach (
                            $cis_etiquetas_colores as $cis_campo => $cis_etiqueta
                        ) : ?>

                            <p>
                                <label>
                                    <?php echo esc_html( $cis_etiqueta ); ?>

                                    <input type="color"
                                        name="compuciber_estilos_perfiles[<?php
                                            echo esc_attr( $cis_id );
                                        ?>][<?php echo esc_attr( $cis_campo ); ?>]"
                                        value="<?php echo esc_attr(
                                            sanitize_hex_color(
                                                (string) (
                                                    $cis_estilo_perfil[ $cis_campo ] ?? ''
                                                )
                                            ) ?: $cis_colores_base[ $cis_campo ]
                                        ); ?>">
                                </label>
                            </p>

                        <?php endforeach; ?>

                        <p>
                            <label>
                                Redondeo de bordes (0 a 30 px)

                                <input type="number"
                                    min="0"
                                    max="30"
                                    name="compuciber_estilos_perfiles[<?php
                                        echo esc_attr( $cis_id );
                                    ?>][radio]"
                                    value="<?php echo esc_attr(
                                        min(
                                            30,
                                            absint(
                                                $cis_estilo_perfil['radio'] ?? 8
                                            )
                                        )
                                    ); ?>">
                            </label>
                        </p>

                        <p>
                            <label>
                                Texto del campo de búsqueda

                                <input type="text"
                                    class="regular-text"
                                    maxlength="120"
                                    name="compuciber_estilos_perfiles[<?php
                                        echo esc_attr( $cis_id );
                                    ?>][placeholder]"
                                    value="<?php echo esc_attr(
                                        $cis_estilo_perfil['placeholder'] ?? ''
                                    ); ?>"
                                    placeholder="¿Qué producto estás buscando?">
                            </label>
                        </p>

                        <?php
                        submit_button(
                            'Guardar estilo',
                            'secondary',
                            'submit',
                            false
                        );
                        ?>

                    </form>  
                    
                    <form method="post"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        style="margin:0 0 25px 0;"
                        onsubmit="return confirm('¿Seguro que deseas eliminar este perfil y sus estilos? Esta acción no se puede deshacer.');">

                        <input type="hidden"
                            name="action"
                            value="compuciber_eliminar_perfil_busqueda">

                        <input type="hidden"
                            name="cis_perfil_id"
                            value="<?php echo esc_attr( $cis_id ); ?>">

                        <?php
                        wp_nonce_field(
                            'compuciber_eliminar_perfil_busqueda_' . $cis_id
                        );
                        ?>

                        <p class="description">
                            Al eliminar este perfil, los buscadores
                            que lo utilizan dejarán de funcionar
                            hasta que selecciones otro perfil.
                        </p>

                        <button type="submit"
                            class="button button-secondary"
                            style="color:#b32d2e;border-color:#b32d2e;">
                            Eliminar perfil
                        </button>

                    </form>                    

                </details>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>        

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
                            <input type="hidden" name="compuciber_autocompletado" value="0">
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
                            <input type="hidden" name="compuciber_correccion" value="0">
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

            <h2>Destino de las búsquedas</h2>

            <p>
                Selecciona dónde se mostrarán los resultados
                de tu buscador inteligente.
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        Página de resultados
                    </th>
                    <td>
                        <?php
                        wp_dropdown_pages(
                            array(
                                'name' => 'compuciber_pagina_resultados',
                                'id' => 'compuciber_pagina_resultados',
                                'selected' => absint(
                                    get_option(
                                        'compuciber_pagina_resultados',
                                        0
                                    )
                                ),
                                'show_option_none' =>
                                    'Usar página actual',
                                'option_none_value' => '0',
                            )
                        );
                        ?>

                        <?php
                        $cis_destino_id = absint(
                            get_option( 'compuciber_pagina_resultados', 0 )
                        );

                        $cis_destino_valido =
                            $cis_destino_id > 0
                            && get_post_type( $cis_destino_id ) === 'page'
                            && get_post_status( $cis_destino_id ) === 'publish';
                        ?>

                        <?php if ( ! $cis_destino_valido ) : ?>

                            <div class="notice notice-warning inline"
                                style="margin:12px 0;">

                                <p>
                                    <strong>Atención al usarlo en la cabecera:</strong>
                                    no hay una página de resultados publicada
                                    y seleccionada.

                                    Los resultados del shortcode completo
                                    se mostrarán en la misma ubicación
                                    del buscador.
                                </p>

                                <p>
                                    <a href="<?php echo esc_url(
                                        admin_url( 'post-new.php?post_type=page' )
                                    ); ?>">
                                        Crear una página de resultados
                                    </a>

                                    y selecciónala arriba después de publicarla.
                                </p>

                            </div>

                        <?php else : ?>

                            <p class="description">

                                <strong>Página de resultados seleccionada:</strong>

                                <a href="<?php echo esc_url(
                                    get_permalink( $cis_destino_id )
                                ); ?>"
                                target="_blank"
                                rel="noopener noreferrer">
                                    Ver página
                                </a>.

                                Comprueba que esta página contenga
                                <code>[compuciber_ai_search solo_resultados="si"]</code>
                                si ya tienes una barra de búsqueda en la cabecera.

                                Si prefieres mostrar la barra y los productos
                                en la misma página, utiliza
                                <code>[compuciber_ai_search]</code>.

                            </p>

                        <?php endif; ?>                        

                        <p class="description">
                            <strong>¿Dónde aparecerán los resultados?</strong>
                        </p>

                        <p class="description">
                            Si seleccionas "Usar página actual",
                            el buscador completo mostrará los resultados
                            donde esté insertado.
                        </p>

                        <p class="description">
                            <strong>Recomendado para cabeceras:</strong>
                            selecciona una página de resultados
                            independiente y coloca en ella:
                            <code>[compuciber_ai_search solo_resultados="si"]</code>
                        </p>

                        <p class="description">
                            En la cabecera utiliza:
                            <code>[compuciber_ai_search solo_barra="si"]</code>
                        </p>

                        <p class="description">
                            Así, la cabecera mostrará únicamente
                            la barra y sus sugerencias.
                            Los productos aparecerán en la
                            página de resultados seleccionada,
                            sin repetir la barra.
                        </p>

                        <p class="description">
                            <strong>Buscador completo:</strong>
                            si prefieres mostrar el campo de búsqueda
                            y los productos juntos, utiliza:
                            <code>[compuciber_ai_search]</code>
                        </p>

                        <p class="description">
                            <strong>Perfiles personalizados:</strong>
                            cada perfil puede utilizar la página
                            general o una página específica.
                            Si no seleccionas otra, se utilizará
                            automáticamente la página general.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Presentación de resultados</h2>

            <p>
                Selecciona cómo se mostrarán los productos
                encontrados por el buscador inteligente.
            </p>

            <table class="form-table">

                <tr>

                    <th scope="row">

                        <label for="compuciber_modo_resultados">
                            Diseño de los resultados
                        </label>

                    </th>

                    <td>

                        <select
                            name="compuciber_modo_resultados"
                            id="compuciber_modo_resultados"
                        >

                            <option
                                value="propio"
                                <?php selected(
                                    get_option(
                                        'compuciber_modo_resultados',
                                        'propio'
                                    ),
                                    'propio'
                                ); ?>
                            >
                                Tarjetas propias del plugin
                            </option>

                            <option
                                value="woocommerce"
                                <?php selected(
                                    get_option(
                                        'compuciber_modo_resultados',
                                        'propio'
                                    ),
                                    'woocommerce'
                                ); ?>
                            >
                                Plantilla WooCommerce de la tienda
                            </option>

                        </select>

                        <p class="description">
                            <strong>Tarjetas propias del plugin:</strong>
                            muestra los productos con el diseño
                            integrado de Compuciber AI Search.
                            <br>
                            <strong>Plantilla WooCommerce de la tienda:</strong>
                            utiliza las plantillas de producto
                            compatibles de WooCommerce y del tema activo.
                            La apariencia puede variar según el tema
                            y sus personalizaciones.
                        </p>

                    </td>

                </tr>

            </table>            

        <?php submit_button( 'Guardar configuración' ); ?>

        </form>

        <?php
        $cis_pagina_id = absint(
            get_option( 'compuciber_pagina_resultados', 0 )
        );

        $cis_pagina_valida = $cis_pagina_id > 0
            && get_post_type( $cis_pagina_id ) === 'page'
            && get_post_status( $cis_pagina_id ) === 'publish';
        ?>

        <?php if ( ! $cis_pagina_valida ) : ?>

            <form method="post"
                action="<?php echo esc_url(
                    admin_url( 'admin-post.php' )
                ); ?>">

                <input
                    type="hidden"
                    name="action"
                    value="compuciber_crear_pagina_resultados"
                >

                <?php
                wp_nonce_field(
                    'compuciber_crear_pagina_resultados'
                );
                ?>

                <?php
                submit_button(
                    'Crear y seleccionar página de resultados',
                    'secondary',
                    'submit',
                    false
                );
                ?>

                <p class="description">
                    Se creará una página publicada con el
                    buscador inteligente y se seleccionará
                    como destino de las búsquedas.
                </p>

            </form>

        <?php endif; ?>

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
     * Procesar un máximo de cinco productos.
     */
    $resultado =
        compuciber_generar_lote_embeddings( 5 );

    /*
     * Determinar el estado de la operación.
     */
    if ( ! empty( $resultado['bloqueado'] ) ) {

        $estado = 'bloqueado';

    } elseif (
        (int) $resultado['fallidos'] > 0
    ) {

        $estado = 'fallidos';

    } elseif (
        ! empty( $resultado['tiempo_agotado'] )
    ) {

        $estado = 'parcial';

    } else {

        $estado = 'completado';
    }

    /*
     * Regresar al Dashboard.
     */
    $url = add_query_arg(
        array(
            'page'       => 'compuciber-ai-search',
            'embeddings' => $estado,
            'procesados' => absint(
                $resultado['procesados']
            ),
            'generados' => absint(
                $resultado['generados']
            ),
            'fallidos' => absint(
                $resultado['fallidos']
            ),
            'pendientes' => absint(
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