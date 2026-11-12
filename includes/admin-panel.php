<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Progreso de procesos por lotes.
 *
 * Solo lectura.
 * No ejecuta indexaciones, auditorías
 * ni solicitudes a las API.
 */
function compuciber_ajax_progreso_lotes() {

    if ( ! current_user_can( 'manage_options' ) ) {

        wp_send_json_error(
            array( 'mensaje' => 'Sin permisos.' ),
            403
        );
    }

    check_ajax_referer(
        'cis_progreso_lotes',
        'nonce'
    );

    /*
     * Consultar estados existentes.
     */

    $resumen = function_exists(
        'compuciber_resumen_indice_113'
    )
        ? compuciber_resumen_indice_113()
        : array();

    $indice = get_option(
        'compuciber_indice_lotes_estado',
        array()
    );

    $auditoria = get_option(
        'compuciber_auditoria_integral_estado',
        array()
    );

    $cola = get_option(
        'compuciber_cola_embeddings_estado',
        array()
    );

    $indice = is_array( $indice )
        ? $indice
        : array();

    $auditoria = is_array( $auditoria )
        ? $auditoria
        : array();

    $cola = is_array( $cola )
        ? $cola
        : array();

    $fecha = static function ( $timestamp ) {

        return $timestamp
            ? wp_date(
                'Y-m-d H:i:s',
                absint( $timestamp )
            )
            : '—';
    };

    /*
     * Indexación.
     */

    $total = absint(
        $resumen['total'] ?? 0
    );

    $indexados = absint(
        $resumen['indexados'] ?? 0
    );

    $pendientes = absint(
        $resumen['pendientes'] ?? 0
    );

    /*
     * Auditoría.
     */

    $total_auditoria = absint(
        $auditoria['total'] ?? 0
    );

    $revisados = absint(
        $auditoria['revisados'] ?? 0
    );

    /*
     * Embeddings.
     */

    $meta = absint(
        $cola['limite_total'] ?? 0
    );

    $procesados = absint(
        $cola['procesados'] ?? 0
    );

    /*
     * Consultar la próxima ejecución real
     * de la indexación automática.
     */
    $proximo_indice = wp_next_scheduled(
        'compuciber_indice_lote_113'
    );

    $proximo_auditoria = ! empty(
        $auditoria['token']
    )
        ? wp_next_scheduled(
            'compuciber_auditoria_integral_lote',
            array( $auditoria['token'] )
        )
        : false;

    $reparacion = get_option(
            'compuciber_reparacion_indice_estado',
            array()
        );

    $reparacion = is_array( $reparacion )
        ? $reparacion
        : array();

    /*
     * Respuesta para las barras existentes.
     */

    wp_send_json_success(
        array(

            'indice' => array(

                'total' => $total,

                'indexados' => $indexados,

                'pendientes' => $pendientes,

                'porcentaje' => $total
                    ? round(
                        100 * $indexados / $total,
                        1
                    )
                    : 0,

                'estado' => sanitize_key(
                    $indice['estado'] ?? 'pendiente'
                ),

                'cursor' => absint(
                    $indice['cursor'] ?? 0
                ),

                'ultima' => $fecha(
                    $indice['ultima'] ?? 0
                ),

                'proximo' => $fecha(
                    $proximo_indice
                ),

                'cron_atrasado' => (bool) (
                    $proximo_indice
                    && $proximo_indice < time() - 300
                ),
            ),

            'auditoria' => array(

                'total' => $total_auditoria,

                'revisados' => $revisados,

                'porcentaje' => $total_auditoria
                    ? min(
                        100,
                        round(
                            100 * $revisados
                            / $total_auditoria,
                            1
                        )
                    )
                    : 0,

                'estado' => sanitize_key(
                    $auditoria['situacion']
                        ?? 'sin_iniciar'
                ),

                'diferencias' => absint(
                    $auditoria['con_diferencias'] ?? 0
                ),

                'no_visibles' => absint(
                    $auditoria['no_visibles'] ?? 0
                ),

                'no_verificados' => absint(
                    $auditoria['no_verificados'] ?? 0
                ),

                'cursor' => absint(
                    $auditoria['cursor'] ?? 0
                ),

                'proximo' => $fecha(
                    $proximo_auditoria
                ),
            ),

            'reparacion' => array(

                'estado' => sanitize_key(
                    $reparacion['situacion'] ?? 'sin_iniciar'
                ),

                'total' => absint(
                    $reparacion['total'] ?? 0
                ),

                'revisados' => absint(
                    $reparacion['revisados'] ?? 0
                ),

                'reindexados' => absint(
                    $reparacion['reindexados'] ?? 0
                ),

                'fallidos' => absint(
                    $reparacion['fallidos'] ?? 0
                ),

            ),

            'embeddings' => array(

                'meta' => $meta,

                'procesados' => $procesados,

                'porcentaje' => $meta
                    ? min(
                        100,
                        round(
                            100 * $procesados / $meta,
                            1
                        )
                    )
                    : 0,

                'estado' => sanitize_key(
                    $cola['situacion']
                        ?? 'sin_iniciar'
                ),

                'generados' => absint(
                    $cola['generados'] ?? 0
                ),

                'fallidos' => absint(
                    $cola['fallidos'] ?? 0
                ),

                'por_lote' => absint(
                    $cola['por_lote'] ?? 0
                ),

                'proximo' => $fecha(
                    wp_next_scheduled(
                        'compuciber_cola_embeddings_paso'
                    )
                ),

                'ultima' => $fecha(
                    $cola['ultima'] ?? 0
                ),
            ),
        )
    );
}

add_action(
    'wp_ajax_compuciber_progreso_lotes',
    'compuciber_ajax_progreso_lotes'
);

/*
 * Actualizar las barras existentes del administrador.
 *
 * No crea nuevos registros ni inicia procesos.
 */
function compuciber_admin_progreso_script() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $pagina = isset( $_GET['page'] )
        && is_string( $_GET['page'] )
            ? sanitize_key(
                wp_unslash( $_GET['page'] )
            )
            : '';

    /*
     * Ejecutar solamente en las páginas
     * de nuestro plugin que muestran progreso.
     */

    if (
        ! in_array(
            $pagina,
            array(
                'compuciber-ai-search',
                'compuciber-ai-search-diagnostico',
            ),
            true
        )
    ) {
        return;
    }

    $ajustes = array(
        'url' => admin_url( 'admin-ajax.php' ),

        'nonce' => wp_create_nonce(
            'cis_progreso_lotes'
        ),
    );

    ?>

    <script>
    (function () {

        'use strict';

        const ajustes = <?php
            echo wp_json_encode(
                $ajustes,
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            );
        ?>;

        /*
         * Localizar las secciones existentes.
         */

        function seccion(nombre) {

            return Array.from(
                document.querySelectorAll('.wrap details')
            ).find(function (d) {

                const titulo = d.querySelector(
                    ':scope > summary'
                );

                return titulo
                    && titulo.textContent.trim() === nombre;
            });
        }

        function parrafo(seccionActual, etiqueta) {

            if (!seccionActual) {
                return null;
            }

            return Array.from(
                seccionActual.children
            ).find(function (n) {

                return n.tagName === 'P'
                    && n.textContent.includes(etiqueta);
            });
        }

        /*
         * Actualizar valores sin recargar HTML
         * de los formularios ni de los botones.
         */

        function escribirLinea(nodo, datos) {

            if (!nodo) {
                return;
            }

            nodo.replaceChildren();

            datos.forEach(function (fila, i) {

                const negrita = document.createElement(
                    'strong'
                );

                negrita.textContent = fila[0];

                nodo.append(
                    negrita,
                    document.createTextNode(
                        ' ' + fila[1] +
                        (
                            i < datos.length - 1
                                ? ' | '
                                : ''
                        )
                    )
                );
            });
        }

        function porcentaje(n) {

            const valor = Number(n);

            return Number.isFinite(valor)
                ? Math.min(
                    100,
                    Math.max(0, valor)
                )
                : 0;
        }

        /*
         * INDEXACIÓN.
         */

        function actualizarIndice(i) {

            const d = seccion(
                'Estado y seguimiento de indexación'
            );

            if (d) {

                const barra = d.querySelector(
                    'progress'
                );

                if (barra) {
                    barra.value = porcentaje(
                        i.porcentaje
                    );
                }

                escribirLinea(
                    parrafo(d, 'Productos publicados:'),
                    [
                        ['Productos publicados:', i.total],
                        ['Con registro:', i.indexados],
                        ['Sin registro:', i.pendientes],
                        ['Avance:', i.porcentaje + ' %']
                    ]
                );

                escribirLinea(
                    parrafo(d, 'Estado del proceso:'),
                    [
                        ['Estado del proceso:', i.estado],
                        ['Último ID del cursor:', i.cursor]
                    ]
                );

                escribirLinea(
                    parrafo(d, 'Última actividad:'),
                    [
                        ['Última actividad:', i.ultima],
                        ['Próximo lote programado:', i.proximo]
                    ]
                );
            }

            /*
             * Actualizar también la barra original
             * del Dashboard.
             */

            const h3 = Array.from(
                document.querySelectorAll('.wrap h3')
            ).find(function (n) {

                return n.textContent.trim()
                    === 'Estado de indexación automática';
            });

            const caja = h3 && h3.closest(
                '.postbox'
            );

            if (!caja) {
                return;
            }

            const barra = caja.querySelector(
                'progress'
            );

            if (barra) {
                barra.value = porcentaje(
                    i.porcentaje
                );
            }

            const ps = Array.from(
                caja.children
            ).filter(function (n) {

                return n.tagName === 'P';
            });

            if (ps[0]) {

                const fuerte = ps[0].querySelector(
                    'strong'
                );

                if (fuerte) {

                    fuerte.textContent =
                        i.total === 0
                            ? 'Sin productos publicados'
                            : i.pendientes === 0
                                ? 'Completado'
                                : i.estado === 'errores'
                                    ? 'Con errores: requiere revisión'
                                    : i.cron_atrasado
                                        ? 'Detenido o retrasado: revisar WP-Cron'
                                        : 'En proceso o pendiente de ejecución';
                }
            }

            if (ps[1]) {

                ps[1].textContent =
                    i.indexados + ' de ' +
                    i.total +
                    ' productos publicados indexados.';
            }

            if (ps[2]) {

                escribirLinea(
                    ps[2],
                    [
                        [
                            'Progreso:',
                            i.porcentaje + ' %'
                        ]
                    ]
                );
            }

            if (ps[3]) {

                escribirLinea(
                    ps[3],
                    [
                        ['Pendientes:', i.pendientes]
                    ]
                );
            }
        }

        /*
         * AUDITORÍA INTEGRAL.
         */

        function actualizarAuditoria(a) {

            const d = seccion(
                'Auditoría integral del catálogo (por lotes)'
            );

            if (!d) {
                return;
            }

            const barra = d.querySelector(
                'progress'
            );

            if (barra) {

                barra.value = porcentaje(
                    a.porcentaje
                );
            }

            escribirLinea(
                parrafo(d, 'Revisados:'),
                [
                    ['Estado:', a.estado],

                    [
                        'Revisados:',
                        a.revisados + ' / ' + a.total
                    ],

                    [
                        'Avance:',
                        a.porcentaje + ' %'
                    ]
                ]
            );

            escribirLinea(
                parrafo(d, 'Productos con diferencias:'),
                [
                    [
                        'Productos con diferencias:',
                        a.diferencias
                    ],

                    [
                        'No visibles (con índice):',
                        a.no_visibles
                    ],

                    [
                        'Comprobaciones no realizadas:',
                        a.no_verificados
                    ]
                ]
            );

            escribirLinea(
                parrafo(d, 'Último ID recorrido:'),
                [
                    ['Último ID recorrido:', a.cursor],
                    ['Próximo lote:', a.proximo]
                ]
            );
        }

        /*
         * EMBEDDINGS.
         */

        function actualizarEmbeddings(e) {

            const d = seccion(
                'Cola automática de embeddings'
            );

            if (!d) {
                return;
            }

            escribirLinea(
                parrafo(d, 'Procesados en esta sesión:'),
                [
                    [
                        'Procesados en esta sesión:',
                        e.procesados
                    ],

                    ['Generados:', e.generados],

                    ['Fallidos:', e.fallidos]
                ]
            );

            escribirLinea(
                parrafo(d, 'Tope elegido:'),
                [
                    [
                        'Tope elegido:',
                        e.meta + ' intentos'
                    ],

                    ['Por lote:', e.por_lote]
                ]
            );

            escribirLinea(
                parrafo(d, 'Próxima ejecución:'),
                [
                    ['Próxima ejecución:', e.proximo]
                ]
            );

            escribirLinea(
                parrafo(d, 'Último procesamiento:'),
                [
                    ['Último procesamiento:', e.ultima]
                ]
            );

            escribirLinea(
                parrafo(d, 'Estado:'),
                [
                    ['Estado:', e.estado]
                ]
            );

            /*
             * La cola todavía no tiene una barra
             * propia: añadirla al mismo apartado.
             */

            let barra = d.querySelector(
                'progress[data-cis-cola]'
            );

            if (!barra) {

                const despues = parrafo(
                    d,
                    'Procesados en esta sesión:'
                );

                if (despues) {

                    barra = document.createElement(
                        'progress'
                    );

                    barra.dataset.cisCola = '1';

                    barra.max = 100;

                    barra.style.cssText =
                        'display:block;' +
                        'width:100%;' +
                        'max-width:700px;' +
                        'height:18px;' +
                        'margin:12px 0;';

                    barra.setAttribute(
                        'aria-label',
                        'Intentos procesados respecto del máximo autorizado'
                    );

                    despues.insertAdjacentElement(
                        'afterend',
                        barra
                    );
                }
            }

            if (barra) {

                barra.value = porcentaje(
                    e.porcentaje
                );
            }
        }
        
        /*  
         * Actualización de reparación selectiva.
         */

        function actualizarReparacion(r) {

            if (!r) {
                return;
            }

            const escribir = function (id, valor) {

                const elemento = document.getElementById(id);

                if (elemento) {
                    elemento.textContent = valor;
                }
            };

            escribir('cis-rep-estado', r.estado);
            escribir('cis-rep-revisados', r.revisados);
            escribir('cis-rep-total', r.total);
            escribir('cis-rep-reindexados', r.reindexados);
            escribir('cis-rep-fallidos', r.fallidos);

            const barra = document.getElementById(
                'cis-rep-barra'
            );

            if (barra) {

                barra.value = r.total > 0
                    ? Math.min(
                        100,
                        100 * r.revisados / r.total
                    )
                    : 0;
            }
        }

        /*
         * CONSULTA AUTOMÁTICA.
         *
         * Una petición cada diez segundos.
         * Solo cuando la pestaña está visible.
         */

        let trabajando = false;

        async function refrescar() {

            if (document.hidden || trabajando) {
                return;
            }

            trabajando = true;

            const controlador = new AbortController();

            const temporizador = setTimeout(
                function () {

                    controlador.abort();

                },
                8000
            );

            try {

                const url = new URL(
                    ajustes.url
                );

                url.searchParams.set(
                    'action',
                    'compuciber_progreso_lotes'
                );

                url.searchParams.set(
                    'nonce',
                    ajustes.nonce
                );

                const respuesta = await fetch(
                    url.toString(),
                    {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: controlador.signal
                    }
                );

                if (!respuesta.ok) {
                    return;
                }

                const json = await respuesta.json();

                if (
                    !json.success
                    || !json.data
                ) {
                    return;
                }

                actualizarIndice(
                    json.data.indice
                );

                actualizarAuditoria(
                    json.data.auditoria
                );

                actualizarEmbeddings(
                    json.data.embeddings
                );

                actualizarReparacion(
                    json.data.reparacion
                );

            } catch (error) {

                /*
                 * No generar logs adicionales.
                 * Se volverá a consultar normalmente.
                 */

            } finally {

                clearTimeout(
                    temporizador
                );

                trabajando = false;
            }
        }

        /*
         * Primera actualización y seguimiento.
         */

        refrescar();

        setInterval(
            refrescar,
            10000
        );

    })();
    </script>

    <?php
}

add_action(
    'admin_footer',
    'compuciber_admin_progreso_script'
);

/*
 * Control administrativo del historial técnico
 * de búsquedas reales.
 */
function compuciber_traza_control_admin() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acceso no autorizado.' );
    }

    check_admin_referer( 'cis_traza_control' );

    $operacion = isset( $_POST['operacion'] )
        && is_string( $_POST['operacion'] )
            ? sanitize_key(
                wp_unslash( $_POST['operacion'] )
            )
            : '';

    if ( $operacion === 'activar' ) {

        update_option(
            'cis_traza_hasta',
            time() + 30 * MINUTE_IN_SECONDS,
            false
        );

    } elseif ( $operacion === 'desactivar' ) {

        update_option(
            'cis_traza_hasta',
            0,
            false
        );

    } elseif ( $operacion === 'limpiar' ) {

        delete_transient(
            'cis_trazas_recientes'
        );
    }

    wp_safe_redirect(
        admin_url(
            'admin.php?page=compuciber-ai-search-diagnostico'
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_traza_control',
    'compuciber_traza_control_admin'
);

/*
 * Centro de diagnóstico de Compuciber AI Search.
 * Captura un historial breve de eventos de WC_Logger.
 */

if ( ! function_exists( 'compuciber_historial_capturar' ) ) {

    function compuciber_historial_capturar(
        $mensaje,
        $nivel,
        $contexto,
        $handler = null
    ) {

        if (
            ! is_string( $mensaje )
            || ! is_array( $contexto )
            || ( $contexto['source'] ?? '' ) !== 'compuciber-ai-search'
        ) {
            return $mensaje;
        }

        $nivel = sanitize_key( (string) $nivel );

        if (
            ! in_array(
                $nivel,
                array(
                    'emergency',
                    'alert',
                    'critical',
                    'error',
                    'warning',
                    'notice',
                    'info'
                ),
                true
            )
        ) {
            return $mensaje;
        }

        $texto = wp_html_excerpt(
            sanitize_text_field(
                wp_strip_all_tags( $mensaje )
            ),
            280,
            '…'
        );

        $firma = hash(
            'sha256',
            $nivel . '|' . $texto
        );

        $ahora = time();

        $historial = get_option(
            'compuciber_historial_diagnostico',
            array()
        );

        $historial = is_array( $historial )
            ? $historial
            : array();

        foreach ( $historial as $evento ) {

            if (
                is_array( $evento )
                && ( $evento['firma'] ?? '' ) === $firma
                && $ahora - (int) ( $evento['ts'] ?? 0 ) < 60
            ) {
                return $mensaje;
            }
        }

        array_unshift(
            $historial,
            array(
                'fecha'   => current_time( 'mysql' ),
                'nivel'   => $nivel,
                'mensaje' => $texto,
                'firma'   => $firma,
                'ts'      => $ahora
            )
        );

        $historial = array_values(
            array_filter(
                $historial,
                static function ( $evento ) use ( $ahora ) {

                    return is_array( $evento )
                        && (int) ( $evento['ts'] ?? 0 )
                        >= $ahora - 14 * DAY_IN_SECONDS;
                }
            )
        );

        update_option(
            'compuciber_historial_diagnostico',
            array_slice( $historial, 0, 40 ),
            false
        );

        return $mensaje;
    }
}

if (
    ! has_filter(
        'woocommerce_logger_log_message',
        'compuciber_historial_capturar'
    )
) {

    add_filter(
        'woocommerce_logger_log_message',
        'compuciber_historial_capturar',
        10,
        4
    );
}


/*
 * Página independiente de Diagnóstico y registros.
 */

function compuciber_ai_search_diagnostico_registros() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            'No tienes permisos para acceder a este apartado.'
        );
    }

    $historial = get_option(
        'compuciber_historial_diagnostico',
        array()
    );

    $historial = is_array( $historial )
        ? $historial
        : array();

    $ahora = time();

    $historial = array_filter(
        $historial,
        static function ( $evento ) use ( $ahora ) {

            return is_array( $evento )
                && (int) ( $evento['ts'] ?? 0 )
                >= $ahora - 14 * DAY_IN_SECONDS;
        }
    );

    ?>

    <div class="wrap">

        <h1>
            Diagnóstico y registros - Compuciber AI Search
        </h1>

    <!-- Diagnóstico de recursos y conexión AJAX -->

    <div class="postbox" style="padding:16px;max-width:1000px;">

        <details>

            <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                Comprobación de AJAX, JavaScript y CSS
            </summary>

            <p>
                Comprueba si el navegador puede acceder a los
                archivos del buscador y al endpoint AJAX.

                No utiliza inteligencia artificial ni modifica productos.
            </p>

            <button
                type="button"
                class="button button-primary"
                id="cis-verificar-recursos"
            >
                Comprobar recursos
            </button>

            <div
                id="cis-resultado-recursos"
                role="status"
                aria-live="polite"
            ></div>

        </details>

    </div>

    <script>
    (function () {

        const boton = document.getElementById(
            'cis-verificar-recursos'
        );

        const salida = document.getElementById(
            'cis-resultado-recursos'
        );

        if (!boton || !salida) {
            return;
        }

        const pruebas = <?php
            echo wp_json_encode(
                array(
                    array(
                        'nombre' => 'AJAX de renovación de tokens',
                        'url' => add_query_arg(
                            'action',
                            'compuciber_tokens_publicos',
                            admin_url('admin-ajax.php')
                        ),
                        'tipo' => 'ajax'
                    ),
                    array(
                        'nombre' => 'JavaScript del buscador',
                        'url' => plugins_url(
                            'assets/js/buscador-v59.js',
                            dirname(__DIR__) . '/compuciber-ai-search.php'
                        ),
                        'tipo' => 'js'
                    ),
                    array(
                        'nombre' => 'CSS del buscador',
                        'url' => plugins_url(
                            'assets/css/buscador.css',
                            dirname(__DIR__) . '/compuciber-ai-search.php'
                        ),
                        'tipo' => 'css'
                    )
                ),
                JSON_HEX_TAG | JSON_HEX_AMP |
                JSON_HEX_APOS | JSON_HEX_QUOT
            );
        ?>;

        boton.addEventListener('click', async function () {

            boton.disabled = true;
            salida.textContent = 'Comprobando recursos...';

            const resultados = await Promise.all(
                pruebas.map(async function (prueba) {

                    const controlador = new AbortController();

                    const reloj = setTimeout(function () {
                        controlador.abort();
                    }, 10000);

                    try {

                        const inicio = performance.now();

                        const respuesta = await fetch(
                            prueba.url,
                            {
                                credentials: 'same-origin',
                                cache: 'no-store',
                                signal: controlador.signal
                            }
                        );

                        const texto = await respuesta.text();

                        if (!respuesta.ok) {
                            throw new Error(
                                'HTTP ' + respuesta.status
                            );
                        }

                        if (prueba.tipo === 'ajax') {

                            const datos = JSON.parse(texto);

                            if (
                                datos.success !== true ||
                                !datos.data ||
                                !datos.data.imageNonce
                            ) {
                                throw new Error(
                                    'Respuesta AJAX inesperada'
                                );
                            }
                        }

                        if (
                            prueba.tipo === 'js' &&
                            !texto.includes(
                                'compuciberInicializarBuscadorPublico'
                            )
                        ) {
                            throw new Error(
                                'JavaScript inesperado'
                            );
                        }

                        if (
                            prueba.tipo === 'css' &&
                            !texto.includes(
                                '.compuciber-ai-search'
                            )
                        ) {
                            throw new Error(
                                'CSS inesperado'
                            );
                        }

                        return prueba.nombre +
                            ': CORRECTO (' +
                            Math.round(
                                performance.now() - inicio
                            ) +
                            ' ms)';

                    } catch (error) {

                        return prueba.nombre +
                            ': REVISAR (' +
                            error.message +
                            ')';

                    } finally {

                        clearTimeout(reloj);
                    }
                })
            );

            salida.replaceChildren();

            resultados.forEach(function (mensaje) {

                const parrafo = document.createElement('p');

                parrafo.textContent = mensaje;

                salida.appendChild(parrafo);
            });

            boton.disabled = false;
        });

    })();
    </script>   
    
    <?php

    /*
    * Diagnóstico de URL y página de resultados.
    * Solo lectura.
    */

    $cis_d_url_id = absint(
        get_option(
            'compuciber_pagina_resultados',
            0
        )
    );

    $cis_d_url_valida =
        $cis_d_url_id > 0
        && get_post_type( $cis_d_url_id ) === 'page'
        && get_post_status( $cis_d_url_id ) === 'publish';

    $cis_d_url_base = $cis_d_url_valida
        ? get_permalink( $cis_d_url_id )
        : '';

    $cis_d_url_prueba = $cis_d_url_base !== ''
        ? add_query_arg(
            array(
                'cis_q' => 'laptop',
                'cis_page' => 3
            ),
            $cis_d_url_base
        )
        : '';

    $cis_d_scheme_home = wp_parse_url(
        home_url(),
        PHP_URL_SCHEME
    );

    $cis_d_scheme_site = wp_parse_url(
        site_url(),
        PHP_URL_SCHEME
    );

    ?>

    <div class="postbox" style="padding:16px;max-width:1000px;">

        <details>

            <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                Página de resultados y enlaces de paginación
            </summary>

            <p>
                <strong>Estado de la página global:</strong>

                <?php
                echo esc_html(
                    $cis_d_url_valida
                        ? 'Publicada'
                        : 'No hay página global válida'
                );
                ?>
            </p>

            <?php if ( $cis_d_url_valida ) : ?>

                <p>
                    <strong>URL de resultados:</strong>

                    <br>

                    <code>
                        <?php echo esc_html( $cis_d_url_base ); ?>
                    </code>
                </p>

                <p>
                    <strong>Ejemplo de URL para la página 3:</strong>

                    <br>

                    <code style="overflow-wrap:anywhere;">
                        <?php echo esc_html( $cis_d_url_prueba ); ?>
                    </code>
                </p>

                <p class="description">
                    Este ejemplo utiliza la página global.
                    Un perfil puede tener una página específica.

                    La dirección es ilustrativa y no se abre
                    automáticamente.
                </p>

            <?php endif; ?>

            <p>
                <strong>Configuración de URL:</strong>

                <?php

                echo esc_html(
                    $cis_d_scheme_home === $cis_d_scheme_site
                        ? 'Esquemas coincidentes'
                        : 'Esquemas diferentes: revisar configuración'
                );

                ?>
            </p>

            <p class="description">
                Este diagnóstico permite identificar la página
                configurada y comprobar la estructura de las URL.

                No garantiza que los botones de Wolmart funcionen.

                Abrir manualmente una búsqueda pública puede
                activar solicitudes a la inteligencia artificial.
            </p>

        </details>

    </div>    

        <p>
            Centro de diagnóstico del buscador.
            Aquí reuniremos los registros de indexación,
            búsquedas, inteligencia artificial y compatibilidad.
        </p>

        <?php
        /*
        * Auditoría de productos indexados.
        * Solo lectura: no modifica productos ni el índice.
        */

        global $wpdb;

        $cis_a_tabla = $wpdb->prefix . 'compuciber_product_index';

        $cis_a_resumen = compuciber_resumen_indice_113();

        $cis_a_q = isset( $_GET['cis_a_q'] )
            && is_string( $_GET['cis_a_q'] )
            ? substr(
                sanitize_text_field(
                    wp_unslash( $_GET['cis_a_q'] )
                ),
                0,
                100
            )
            : '';

        $cis_a_estado = isset( $_GET['cis_a_estado'] )
            && is_string( $_GET['cis_a_estado'] )
            ? sanitize_key(
                wp_unslash( $_GET['cis_a_estado'] )
            )
            : 'todos';

        if (
            ! in_array(
                $cis_a_estado,
                array( 'todos', 'faltantes', 'incompletos' ),
                true
            )
        ) {
            $cis_a_estado = 'todos';
        }

        $cis_a_pagina = isset( $_GET['cis_a_pagina'] )
            ? max( 1, absint( $_GET['cis_a_pagina'] ) )
            : 1;

        $cis_a_where =
            "p.post_type = 'product' AND p.post_status = 'publish'";

        $cis_a_args = array();

        if ( 'faltantes' === $cis_a_estado ) {

            $cis_a_where .= ' AND i.id IS NULL';

        } elseif ( 'incompletos' === $cis_a_estado ) {

            $cis_a_where .= "
                AND i.id IS NOT NULL
                AND (
                    TRIM(i.nombre) = ''
                    OR TRIM(i.texto_busqueda) = ''
                )
            ";
        }

        if ( '' !== $cis_a_q ) {

            $cis_a_like =
                '%' . $wpdb->esc_like( $cis_a_q ) . '%';

            $cis_a_where .= "
                AND (
                    CAST(p.ID AS CHAR) = %s
                    OR p.post_title LIKE %s
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->postmeta} pm
                        WHERE pm.post_id = p.ID
                        AND pm.meta_key = '_sku'
                        AND pm.meta_value LIKE %s
                    )
                )
            ";

            array_push(
                $cis_a_args,
                $cis_a_q,
                $cis_a_like,
                $cis_a_like
            );
        }

        $cis_a_base = "
            FROM {$wpdb->posts} p
            LEFT JOIN {$cis_a_tabla} i
                ON i.id = p.ID
            WHERE {$cis_a_where}
        ";

        $cis_a_count_sql = 'SELECT COUNT(*) ' . $cis_a_base;

        $cis_a_total = (int) $wpdb->get_var(
            $cis_a_args
                ? $wpdb->prepare(
                    $cis_a_count_sql,
                    $cis_a_args
                )
                : $cis_a_count_sql
        );

        $cis_a_paginas = max(
            1,
            (int) ceil( $cis_a_total / 25 )
        );

        $cis_a_pagina = min(
            $cis_a_pagina,
            $cis_a_paginas
        );

        $cis_a_rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT
                    p.ID,
                    p.post_title,
                    i.id AS indice_id,
                    i.nombre,
                    i.texto_busqueda,
                    i.actualizado '
                    . $cis_a_base .
                    ' ORDER BY p.ID DESC
                    LIMIT %d OFFSET %d',

                array_merge(
                    $cis_a_args,
                    array(
                        25,
                        ( $cis_a_pagina - 1 ) * 25
                    )
                )
            )
        );

        $cis_a_incompletos = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$cis_a_tabla} i
                ON i.id = p.ID
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (
                TRIM(i.nombre) = ''
                OR TRIM(i.texto_busqueda) = ''
            )"
        );
        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Auditoría de productos indexados
                </summary>

                <p>
                    Publicados:
                    <strong>
                        <?php echo esc_html( $cis_a_resumen['total'] ); ?>
                    </strong>

                    | Con índice:
                    <strong>
                        <?php echo esc_html( $cis_a_resumen['indexados'] ); ?>
                    </strong>

                    | Sin índice:
                    <strong>
                        <?php echo esc_html( $cis_a_resumen['pendientes'] ); ?>
                    </strong>

                    | Registros incompletos:
                    <strong>
                        <?php echo esc_html( $cis_a_incompletos ); ?>
                    </strong>
                </p>

                <form
                    action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                    method="get"
                >

                    <input
                        type="hidden"
                        name="page"
                        value="compuciber-ai-search-diagnostico"
                    >

                    <input
                        name="cis_a_q"
                        type="search"
                        maxlength="100"
                        placeholder="Nombre, ID o SKU"
                        value="<?php echo esc_attr( $cis_a_q ); ?>"
                    >

                    <select name="cis_a_estado">

                        <option
                            value="todos"
                            <?php selected( $cis_a_estado, 'todos' ); ?>
                        >
                            Todos
                        </option>

                        <option
                            value="faltantes"
                            <?php selected( $cis_a_estado, 'faltantes' ); ?>
                        >
                            Sin índice
                        </option>

                        <option
                            value="incompletos"
                            <?php selected( $cis_a_estado, 'incompletos' ); ?>
                        >
                            Registros incompletos
                        </option>

                    </select>

                    <button class="button">
                        Consultar
                    </button>

                </form>

                <p>
                    Coincidencias:
                    <?php echo esc_html( $cis_a_total ); ?>

                    | Página
                    <?php echo esc_html( $cis_a_pagina ); ?>
                    de
                    <?php echo esc_html( $cis_a_paginas ); ?>
                </p>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Índice</th>
                            <th>Visibilidad</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ( (array) $cis_a_rows as $cis_a_fila ) : ?>

                            <?php
                            $cis_a_producto =
                                function_exists( 'wc_get_product' )
                                    ? wc_get_product( $cis_a_fila->ID )
                                    : false;

                            $cis_a_presente =
                                null !== $cis_a_fila->indice_id;

                            $cis_a_incompleto =
                                $cis_a_presente
                                && (
                                    '' === trim( (string) $cis_a_fila->nombre )
                                    || '' === trim( (string) $cis_a_fila->texto_busqueda )
                                );
                            ?>

                            <tr>

                                <td>
                                    <?php echo esc_html( $cis_a_fila->ID ); ?>
                                </td>

                                <td>
                                    <a href="<?php
                                        echo esc_url(
                                            get_edit_post_link(
                                                $cis_a_fila->ID
                                            )
                                        );
                                    ?>">
                                        <?php
                                        echo esc_html(
                                            $cis_a_fila->post_title
                                        );
                                        ?>
                                    </a>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        get_post_meta(
                                            $cis_a_fila->ID,
                                            '_sku',
                                            true
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        ! $cis_a_presente
                                            ? 'Sin índice'
                                            : (
                                                $cis_a_incompleto
                                                    ? 'Incompleto'
                                                    : 'Registro presente'
                                            )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $cis_a_producto
                                            ? (
                                                compuciber_producto_visible_en_busqueda( $cis_a_producto )
                                                    ? 'Visible'
                                                    : 'No visible'
                                            )
                                            : 'No disponible'
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if ( empty( $cis_a_rows ) ) : ?>

                            <tr>
                                <td colspan="5">
                                    No hay productos que coincidan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

                <p>

                    <?php
                    foreach (
                        array(
                            'Anterior'  => $cis_a_pagina - 1,
                            'Siguiente' => $cis_a_pagina + 1
                        ) as $cis_a_etiqueta => $cis_a_destino
                    ) :

                        if (
                            $cis_a_destino < 1
                            || $cis_a_destino > $cis_a_paginas
                        ) {
                            continue;
                        }

                        $cis_a_url = add_query_arg(
                            array(
                                'page'         => 'compuciber-ai-search-diagnostico',
                                'cis_a_q'      => $cis_a_q,
                                'cis_a_estado' => $cis_a_estado,
                                'cis_a_pagina' => $cis_a_destino,
                            ),
                            admin_url( 'admin.php' )
                        );
                    ?>

                        <a
                            class="button"
                            href="<?php echo esc_url( $cis_a_url ); ?>"
                        >
                            <?php echo esc_html( $cis_a_etiqueta ); ?>
                        </a>

                    <?php endforeach; ?>

                </p>

                <p class="description">
                    La auditoría comprueba la existencia del registro
                    y sus campos básicos de búsqueda.
                    No garantiza que todos los datos estén actualizados
                    ni que un producto deba aparecer en todas las búsquedas.
                </p>

            </details>

        </div>
        
        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Entorno y posibles interferencias
                </summary>

                <?php

                /*
                * Comprobaciones informativas.
                * No modifican configuraciones.
                */

                $cis_entorno_plugins = (array) get_option(
                    'active_plugins',
                    array()
                );

                $cis_entorno_litespeed =
                    in_array(
                        'litespeed-cache/litespeed-cache.php',
                        $cis_entorno_plugins,
                        true
                    )
                    || defined( 'LSCWP_V' );

                $cis_entorno_cron_manual =
                    defined( 'DISABLE_WP_CRON' )
                    && DISABLE_WP_CRON;

                $cis_entorno_resumen =
                    compuciber_resumen_indice_113();

                $cis_entorno_pendientes = absint(
                    $cis_entorno_resumen['pendientes'] ?? 0
                );

                $cis_entorno_proximo = wp_next_scheduled(
                    'compuciber_indice_lote_113'
                );

                ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Comprobación</th>
                            <th>Dato encontrado</th>
                            <th>Interpretación</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr>

                            <td>LiteSpeed Cache</td>

                            <td>
                                <?php
                                echo esc_html(
                                    $cis_entorno_litespeed
                                        ? 'Detectado'
                                        : 'No detectado'
                                );
                                ?>
                            </td>

                            <td>
                                Si está activo, revisar exclusiones
                                de caché y optimización de JavaScript
                                cuando existan conflictos.
                            </td>

                        </tr>

                        <tr>

                            <td>WP-Cron</td>

                            <td>
                                <?php
                                echo esc_html(
                                    $cis_entorno_cron_manual
                                        ? 'Automático desactivado'
                                        : 'No desactivado por constante'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $cis_entorno_cron_manual
                                        ? 'Comprobar que el hosting ejecuta un cron alternativo.'
                                        : 'Esta configuración no garantiza que las tareas se ejecuten puntualmente.'
                                );
                                ?>
                            </td>

                        </tr>

                        <tr>

                            <td>Próximo lote del índice</td>

                            <td>
                                <?php
                                echo esc_html(
                                    $cis_entorno_proximo
                                        ? wp_date(
                                            'Y-m-d H:i:s',
                                            $cis_entorno_proximo
                                        )
                                        : 'Sin programación detectada'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    'Productos pendientes: '
                                    . $cis_entorno_pendientes
                                );
                                ?>
                            </td>

                        </tr>

                        <tr>

                            <td>Tema activo</td>

                            <td>
                                <?php
                                echo esc_html(
                                    wp_get_theme()->get( 'Name' )
                                );
                                ?>
                            </td>

                            <td>
                                El tema puede modificar plantillas,
                                estilos y navegación.
                            </td>

                        </tr>

                        <tr>

                            <td>Caché de WordPress</td>

                            <td>
                                <?php
                                echo esc_html(
                                    defined( 'WP_CACHE' ) && WP_CACHE
                                        ? 'WP_CACHE activo'
                                        : 'WP_CACHE no activo'
                                );
                                ?>
                            </td>

                            <td>
                                Esta comprobación no identifica
                                todas las capas de caché,
                                proxies o CDN.
                            </td>

                        </tr>

                    </tbody>

                </table>

                <p class="description">
                    Estas comprobaciones son informativas.
                    Detectar un plugin o una configuración
                    no significa que exista un conflicto.

                    Los errores JavaScript y de red del navegador
                    necesitarán comprobaciones adicionales.
                </p>

            </details>

        </div>        

        <!-- Diagnóstico administrativo del ranking: solo lectura. -->

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Diagnóstico de búsquedas y relevancia
                </summary>

                <p>
                    Simula una búsqueda local y muestra los puntos
                    calculados por el motor. No utiliza Gemini,
                    OpenAI ni genera embeddings.
                </p>

                <?php

                $cis_dr_perfiles = get_option(
                    'compuciber_perfiles_busqueda',
                    array()
                );

                $cis_dr_perfiles = is_array( $cis_dr_perfiles )
                    ? $cis_dr_perfiles
                    : array();

                $cis_dr_ejecutar =
                    isset( $_GET['cis_dr_ejecutar'] )
                    && is_string( $_GET['cis_dr_ejecutar'] )
                    && '1' === wp_unslash( $_GET['cis_dr_ejecutar'] );

                $cis_dr_q =
                    isset( $_GET['cis_dr_q'] )
                    && is_string( $_GET['cis_dr_q'] )
                        ? mb_substr(
                            trim(
                                sanitize_text_field(
                                    wp_unslash( $_GET['cis_dr_q'] )
                                )
                            ),
                            0,
                            100
                        )
                        : '';

                $cis_dr_perfil =
                    isset( $_GET['cis_perfil'] )
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
                        value="compuciber-ai-search-diagnostico"
                    >

                    <input
                        type="hidden"
                        name="cis_dr_ejecutar"
                        value="1"
                    >

                    <?php
                    wp_nonce_field(
                        'cis_dr_revision',
                        'cis_dr_nonce'
                    );
                    ?>

                    <p>
                        <label for="cis-dr-q">
                            Palabra o consulta:
                        </label>

                        <br>

                        <input
                            type="search"
                            id="cis-dr-q"
                            name="cis_dr_q"
                            value="<?php echo esc_attr( $cis_dr_q ); ?>"
                            maxlength="100"
                            class="regular-text"
                            required
                        >
                    </p>

                    <p>
                        <label for="cis-dr-perfil">
                            Perfil de búsqueda:
                        </label>

                        <br>

                        <select id="cis-dr-perfil" name="cis_perfil">

                            <option value="">
                                Sin perfil (catálogo general)
                            </option>

                            <?php foreach (
                                $cis_dr_perfiles as $cis_dr_id => $cis_dr_datos
                            ) : ?>

                                <?php
                                if (
                                    ! is_array( $cis_dr_datos )
                                    || empty( $cis_dr_datos['activo'] )
                                ) {
                                    continue;
                                }
                                ?>

                                <option
                                    value="<?php echo esc_attr( $cis_dr_id ); ?>"
                                    <?php selected(
                                        $cis_dr_perfil,
                                        $cis_dr_id
                                    ); ?>
                                >
                                    <?php
                                    echo esc_html(
                                        $cis_dr_datos['nombre'] ?? $cis_dr_id
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </p>

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        Analizar relevancia
                    </button>

                </form>

                <?php if ( $cis_dr_ejecutar ) : ?>

                    <?php

                    $cis_dr_nonce =
                        isset( $_GET['cis_dr_nonce'] )
                        && is_string( $_GET['cis_dr_nonce'] )
                            ? sanitize_text_field(
                                wp_unslash( $_GET['cis_dr_nonce'] )
                            )
                            : '';

                    $cis_dr_perfil_valido =
                        '' === $cis_dr_perfil
                        || (
                            isset( $cis_dr_perfiles[ $cis_dr_perfil ] )
                            && is_array(
                                $cis_dr_perfiles[ $cis_dr_perfil ]
                            )
                            && ! empty(
                                $cis_dr_perfiles[ $cis_dr_perfil ]['activo']
                            )
                        );

                    ?>

                    <?php if (
                        ! wp_verify_nonce(
                            $cis_dr_nonce,
                            'cis_dr_revision'
                        )
                    ) : ?>

                        <p class="notice notice-error inline">
                            La validación caducó.
                            Vuelve a enviar la consulta.
                        </p>

                    <?php elseif (
                        '' === $cis_dr_q
                        || ! $cis_dr_perfil_valido
                    ) : ?>

                        <p class="notice notice-error inline">
                            Indica una búsqueda y un perfil válido.
                        </p>

                    <?php elseif (
                        ! class_exists( 'WooCommerce' )
                        || ! function_exists(
                            'compuciber_buscar_productos_inteligente'
                        )
                    ) : ?>

                        <p class="notice notice-error inline">
                            El motor de búsqueda o WooCommerce
                            no está disponible.
                        </p>

                    <?php else : ?>

                        <?php

                        $cis_dr_info = null;

                        $cis_dr_capturar =
                            static function ( $info ) use ( &$cis_dr_info ) {
                                $cis_dr_info = $info;
                            };

                        $cis_dr_busqueda =
                            compuciber_corregir_busqueda( $cis_dr_q );

                        add_action(
                            'compuciber_diagnostico_ranking',
                            $cis_dr_capturar,
                            10,
                            1
                        );

                        add_filter(
                            'pre_option_compuciber_busqueda_semantica',
                            '__return_zero',
                            PHP_INT_MAX
                        );

                        try {

                            $cis_dr_query =
                                compuciber_buscar_productos_inteligente(
                                    $cis_dr_busqueda,
                                    array()
                                );

                        } finally {

                            remove_filter(
                                'pre_option_compuciber_busqueda_semantica',
                                '__return_zero',
                                PHP_INT_MAX
                            );

                            remove_action(
                                'compuciber_diagnostico_ranking',
                                $cis_dr_capturar,
                                10
                            );
                        }

                        ?>

                        <?php if (
                            $cis_dr_busqueda !== $cis_dr_q
                        ) : ?>

                            <p>
                                Corrección local aplicada:

                                <strong>
                                    <?php echo esc_html( $cis_dr_busqueda ); ?>
                                </strong>
                            </p>

                        <?php endif; ?>

                        <?php
                        /*
                        * Diagnóstico de paginación y visibilidad.
                        * Solo lectura.
                        */

                        $cis_dp_es_consulta = $cis_dr_query instanceof WP_Query;

                        $cis_dp_pagina = $cis_dp_es_consulta
                            ? max( 1, (int) $cis_dr_query->get( 'paged' ) )
                            : 1;

                        $cis_dp_total = $cis_dp_es_consulta
                            ? (int) $cis_dr_query->found_posts
                            : 0;

                        $cis_dp_paginas = $cis_dp_es_consulta
                            ? (int) $cis_dr_query->max_num_pages
                            : 0;

                        $cis_dp_por_pagina = $cis_dp_es_consulta
                            ? (int) $cis_dr_query->get( 'posts_per_page' )
                            : 0;

                        $cis_dp_posts = $cis_dp_es_consulta
                            && is_array( $cis_dr_query->posts )
                                ? $cis_dr_query->posts
                                : array();

                        $cis_dp_visibles = 0;
                        $cis_dp_omitidos = 0;

                        foreach ( $cis_dp_posts as $cis_dp_post ) {

                            $cis_dp_producto = wc_get_product(
                                $cis_dp_post->ID
                            );

                            if (
                                $cis_dp_producto
                                && compuciber_producto_visible_en_busqueda( $cis_dp_producto )
                            ) {
                                $cis_dp_visibles++;

                            } else {
                                $cis_dp_omitidos++;
                            }
                        }
                        ?>

                        <div class="postbox" style="padding:14px;margin-top:18px;">

                            <h3>Diagnóstico de paginación y elegibilidad para búsqueda</h3>

                            <p>
                                <strong>Resultados que cuenta WooCommerce:</strong>
                                <?php echo esc_html( $cis_dp_total ); ?>

                                | <strong>Productos por página:</strong>
                                <?php echo esc_html( $cis_dp_por_pagina ); ?>

                                | <strong>Página solicitada:</strong>
                                <?php echo esc_html( $cis_dp_pagina ); ?>

                                | <strong>Total de páginas:</strong>
                                <?php echo esc_html( $cis_dp_paginas ); ?>
                            </p>

                            <p>
                                <strong>Productos recuperados en esta página:</strong>
                                <?php echo esc_html( count( $cis_dp_posts ) ); ?>

                                | <strong>Aptos para búsqueda:</strong>
                                <?php echo esc_html( $cis_dp_visibles ); ?>

                                | <strong>No aptos:</strong>
                                <?php echo esc_html( $cis_dp_omitidos ); ?>
                            </p>

                            <?php if ( $cis_dp_posts ) : ?>

                                <table class="widefat striped">

                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Producto</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php foreach ( $cis_dp_posts as $cis_dp_post ) : ?>

                                            <?php

                                            $cis_dp_producto = wc_get_product(
                                                $cis_dp_post->ID
                                            );

                                            $cis_dp_visible = $cis_dp_producto
                                                && compuciber_producto_visible_en_busqueda( $cis_dp_producto );

                                            ?>

                                            <tr>

                                                <td>
                                                    <?php echo esc_html( $cis_dp_post->ID ); ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    echo esc_html(
                                                        get_the_title( $cis_dp_post->ID )
                                                    );
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    echo esc_html(
                                                        $cis_dp_visible
                                                            ? 'Apto para búsqueda'
                                                            : 'No apto para búsqueda'
                                                    );
                                                    ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            <?php endif; ?>

                            <?php if ( $cis_dp_paginas > 1 ) : ?>

                                <p>
                                    <strong>Comprobar navegación:</strong>
                                </p>

                                <p>

                                    <?php

                                    $cis_dp_destinos = array_unique(
                                        array(
                                            1,
                                            2,
                                            3,
                                            $cis_dp_paginas
                                        )
                                    );

                                    foreach ( $cis_dp_destinos as $cis_dp_destino ) :

                                        if ( $cis_dp_destino > $cis_dp_paginas ) {
                                            continue;
                                        }

                                        $cis_dp_url = add_query_arg(
                                            array(
                                                'page' => 'compuciber-ai-search-diagnostico',
                                                'cis_dr_ejecutar' => '1',
                                                'cis_dr_q' => $cis_dr_q,
                                                'cis_perfil' => $cis_dr_perfil,
                                                'cis_dr_nonce' => wp_create_nonce(
                                                    'cis_dr_revision'
                                                ),
                                                'cis_page' => $cis_dp_destino
                                            ),
                                            admin_url( 'admin.php' )
                                        );

                                    ?>

                                        <a
                                            class="button <?php
                                                echo $cis_dp_destino === $cis_dp_pagina
                                                    ? 'button-primary'
                                                    : '';
                                            ?>"
                                            href="<?php echo esc_url( $cis_dp_url ); ?>"
                                        >
                                            <?php
                                            echo esc_html(
                                                'Página ' . $cis_dp_destino
                                            );
                                            ?>
                                        </a>

                                    <?php endforeach; ?>

                                </p>

                            <?php endif; ?>

                            <p class="description">
                                Este diagnóstico comprueba la consulta local
                                y la visibilidad de los productos de esta página.

                                Si la página 3 funciona aquí, pero no en la tienda,
                                debemos investigar los enlaces públicos,
                                scripts del tema o sistemas de caché.

                                Esta comprobación no modifica los resultados.
                            </p>

                        </div>                        

                        <?php if ( is_array( $cis_dr_info ) ) : ?>

                            <p>
                                <strong>Registros evaluados:</strong>

                                <?php
                                echo esc_html(
                                    $cis_dr_info['candidatos']
                                );
                                ?>

                                |

                                <strong>Coincidencias puntuadas:</strong>

                                <?php
                                echo esc_html(
                                    $cis_dr_info['coincidencias']
                                );
                                ?>

                                |

                                <strong>Total consultado por WooCommerce:</strong>

                                <?php
                                echo esc_html(
                                    $cis_dr_query instanceof WP_Query
                                        ? $cis_dr_query->found_posts
                                        : 0
                                );
                                ?>
                            </p>

                            <table class="widefat striped">

                                <thead>
                                    <tr>
                                        <th>Posición</th>
                                        <th>Producto</th>
                                        <th>Puntos</th>
                                        <th>Coincidencia visible</th>
                                        <th>Inventario</th>
                                        <th>Visibilidad en WooCommerce</th>
                                        <th>Visibilidad</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $cis_dr_info['primeros']
                                        as $cis_dr_pos => $cis_dr_fila
                                    ) : ?>

                                        <?php

                                        $cis_dr_id = absint(
                                            $cis_dr_fila['id']
                                        );

                                        $cis_dr_producto =
                                            wc_get_product( $cis_dr_id );

                                        $cis_dr_titulo =
                                            compuciber_normalizar_texto(
                                                get_the_title( $cis_dr_id )
                                            );

                                        $cis_dr_termino =
                                            compuciber_normalizar_texto(
                                                $cis_dr_busqueda
                                            );

                                        $cis_dr_senal =
                                            $cis_dr_titulo === $cis_dr_termino
                                                ? 'Título exacto'
                                                : (
                                                    strpos(
                                                        $cis_dr_titulo,
                                                        $cis_dr_termino
                                                    ) === 0
                                                        ? 'Al inicio del título'
                                                        : (
                                                            strpos(
                                                                $cis_dr_titulo,
                                                                $cis_dr_termino
                                                            ) !== false
                                                                ? 'Dentro del título'
                                                                : 'Otras señales'
                                                        )
                                                );

                                        ?>

                                        <tr>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $cis_dr_pos + 1
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    get_the_title( $cis_dr_id )
                                                );
                                                ?>

                                                (ID:
                                                <?php
                                                echo esc_html( $cis_dr_id );
                                                ?>)
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    round(
                                                        (float) $cis_dr_fila['score'],
                                                        2
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php echo esc_html( $cis_dr_senal ); ?>
                                            </td>

                                            <td>
                                                <?php
                                                $cis_estados_stock = array(
                                                    'instock'     => 'Disponible',
                                                    'outofstock'  => 'Agotado',
                                                    'onbackorder' => 'Bajo pedido',
                                                );

                                                $cis_estado_stock = $cis_dr_producto
                                                    ? $cis_dr_producto->get_stock_status()
                                                    : '';

                                                $cis_stock_texto =
                                                    $cis_estados_stock[ $cis_estado_stock ]
                                                    ?? 'Desconocido';

                                                if (
                                                    $cis_dr_producto
                                                    && $cis_dr_producto->managing_stock()
                                                    && null !== $cis_dr_producto->get_stock_quantity()
                                                ) {
                                                    $cis_stock_texto .= ' ('
                                                        . (int) $cis_dr_producto->get_stock_quantity()
                                                        . ')';
                                                }

                                                echo esc_html( $cis_stock_texto );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                $cis_tipos_visibilidad = array(
                                                    'visible' => 'Tienda y búsqueda',
                                                    'catalog' => 'Solo tienda',
                                                    'search'  => 'Solo búsqueda',
                                                    'hidden'  => 'Oculto',
                                                );

                                                $cis_visibilidad_wc = $cis_dr_producto
                                                    ? $cis_dr_producto->get_catalog_visibility()
                                                    : '';

                                                echo esc_html(
                                                    $cis_tipos_visibilidad[ $cis_visibilidad_wc ]
                                                    ?? 'Desconocida'
                                                );
                                                ?>
                                            </td>                                            

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $cis_dr_producto
                                                    && compuciber_producto_visible_en_busqueda( $cis_dr_producto )
                                                        ? 'Visible'
                                                        : 'No visible'
                                                );
                                                ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        <?php else : ?>

                            <p>
                                La búsqueda utilizó una ruta directa,
                                por ejemplo, una coincidencia de SKU,
                                o no generó un listado puntuado.
                            </p>

                        <?php endif; ?>

                        <p class="description">
                            Se muestran las puntuaciones del motor local,
                            sin interpretación de IA ni embeddings.

                            La coincidencia visible es orientativa y
                            no representa un desglose completo de los puntos.

                            El orden real puede variar cuando interviene
                            la inteligencia artificial.

                            Esta consulta es de solo lectura.
                        </p>

                    <?php endif; ?>

                <?php endif; ?>

            </details>

        </div>

        <?php
        /*
        * Estado del indexador.
        * Solo lectura: no inicia ni modifica lotes.
        */

        $cis_pi_resumen =
            compuciber_resumen_indice_113();

        $cis_pi_estado = get_option(
            'compuciber_indice_lotes_estado',
            array()
        );

        $cis_pi_estado = is_array( $cis_pi_estado )
            ? $cis_pi_estado
            : array();

        $cis_pi_fallos =
            isset( $cis_pi_estado['fallos'] )
            && is_array( $cis_pi_estado['fallos'] )
                ? $cis_pi_estado['fallos']
                : array();

        $cis_pi_programado = wp_next_scheduled(
            'compuciber_indice_lote_113'
        );

        $cis_pi_total = absint(
            $cis_pi_resumen['total'] ?? 0
        );

        $cis_pi_indexados = absint(
            $cis_pi_resumen['indexados'] ?? 0
        );

        $cis_pi_pendientes = absint(
            $cis_pi_resumen['pendientes'] ?? 0
        );

        $cis_pi_porcentaje = $cis_pi_total > 0
            ? round(
                100 * $cis_pi_indexados / $cis_pi_total,
                1
            )
            : 0;
        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Estado y seguimiento de indexación
                </summary>

                <p>
                    <strong>Productos publicados:</strong>
                    <?php echo esc_html( $cis_pi_total ); ?>

                    |

                    <strong>Con registro:</strong>
                    <?php echo esc_html( $cis_pi_indexados ); ?>

                    |

                    <strong>Sin registro:</strong>
                    <?php echo esc_html( $cis_pi_pendientes ); ?>

                    |

                    <strong>Avance:</strong>
                    <?php echo esc_html( $cis_pi_porcentaje ); ?> %
                </p>

                <progress
                    max="100"
                    value="<?php echo esc_attr( $cis_pi_porcentaje ); ?>"
                    style="width:100%;max-width:700px;"
                ></progress>

                <p>
                    <strong>Estado del proceso:</strong>

                    <?php
                    echo esc_html(
                        $cis_pi_estado['estado'] ?? 'sin información'
                    );
                    ?>

                    |

                    <strong>Último ID del cursor:</strong>

                    <?php
                    echo esc_html(
                        absint( $cis_pi_estado['cursor'] ?? 0 )
                    );
                    ?>
                </p>

                <p>
                    <strong>Productos con intentos fallidos:</strong>

                    <?php
                    echo esc_html(
                        count( $cis_pi_fallos )
                    );
                    ?>

                    |

                    <strong>Total de intentos fallidos:</strong>

                    <?php
                    echo esc_html(
                        array_sum(
                            array_map(
                                'absint',
                                $cis_pi_fallos
                            )
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Última actividad:</strong>

                    <?php
                    echo esc_html(
                        ! empty( $cis_pi_estado['ultima'] )
                            ? wp_date(
                                'Y-m-d H:i:s',
                                absint( $cis_pi_estado['ultima'] )
                            )
                            : 'Sin fecha registrada'
                    );
                    ?>

                    |

                    <strong>Próximo lote programado:</strong>

                    <?php
                    echo esc_html(
                        $cis_pi_programado
                            ? wp_date(
                                'Y-m-d H:i:s',
                                $cis_pi_programado
                            )
                            : 'No programado'
                    );
                    ?>
                </p>

                <?php if (
                    $cis_pi_pendientes > 0
                    && ( $cis_pi_estado['estado'] ?? '' ) === 'errores'
                ) : ?>

                    <p class="notice notice-warning inline">
                        La indexación indica errores y faltan productos.
                        Consulta la auditoría y los registros
                        para investigar la causa.
                    </p>

                <?php endif; ?>

                <p class="description">
                    El porcentaje indica cuántos productos publicados
                    tienen registro en el índice.

                    No garantiza que sus datos estén actualizados
                    ni que el contenido del registro esté completo.

                    El cursor indica el último ID de la pasada,
                    no necesariamente un producto en ejecución.

                    WP-Cron puede presentar retrasos.
                </p>

            </details>

        </div>        

        <?php
        /*
        * Diagnóstico bajo demanda:
        * registros posiblemente desactualizados.
        */

        $cis_ix_pedir = isset( $_GET['cis_ix_ver'] )
            && is_string( $_GET['cis_ix_ver'] )
            && '1' === wp_unslash( $_GET['cis_ix_ver'] );

        $cis_ix_nonce = isset( $_GET['cis_ix_nonce'] )
            && is_string( $_GET['cis_ix_nonce'] )
            ? sanitize_text_field(
                wp_unslash( $_GET['cis_ix_nonce'] )
            )
            : '';

        $cis_ix_autorizado = $cis_ix_pedir
            && wp_verify_nonce(
                $cis_ix_nonce,
                'cis_ix_revision'
            );

        $cis_ix_resumen = null;
        $cis_ix_muestras = array();

        if ( $cis_ix_autorizado ) {

            global $wpdb;

            $cis_ix_tabla =
                $wpdb->prefix . 'compuciber_product_index';

            $cis_ix_resumen = $wpdb->get_row(
                "SELECT
                    COUNT(*) AS indexados,

                    SUM(
                        CASE
                            WHEN p.post_modified > i.actualizado
                            THEN 1 ELSE 0
                        END
                    ) AS fecha_anterior,

                    SUM(
                        CASE
                            WHEN i.nombre <> p.post_title
                            THEN 1 ELSE 0
                        END
                    ) AS titulo_diferente

                FROM {$wpdb->posts} p

                INNER JOIN {$cis_ix_tabla} i
                    ON i.id = p.ID

                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'",

                ARRAY_A
            );

            /*
            * Mostrar únicamente 20 ejemplos.
            */

            $cis_ix_muestras = $wpdb->get_results(
                "SELECT
                    p.ID,
                    p.post_title,
                    p.post_modified,
                    i.nombre,
                    i.actualizado

                FROM {$wpdb->posts} p

                INNER JOIN {$cis_ix_tabla} i
                    ON i.id = p.ID

                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'

                AND (
                    p.post_modified > i.actualizado
                    OR i.nombre <> p.post_title
                )

                ORDER BY p.ID DESC
                LIMIT 20"
            );
        }
        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php echo $cis_ix_pedir ? 'open' : ''; ?>>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Revisión de registros posiblemente desactualizados
                </summary>

                <p>
                    Comprueba si la fecha de modificación
                    o el título del producto difieren
                    de los datos almacenados en el índice.
                </p>

                <form
                    method="get"
                    action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                >

                    <input
                        type="hidden"
                        name="page"
                        value="compuciber-ai-search-diagnostico"
                    >

                    <input
                        type="hidden"
                        name="cis_ix_ver"
                        value="1"
                    >

                    <?php
                    wp_nonce_field(
                        'cis_ix_revision',
                        'cis_ix_nonce'
                    );
                    ?>

                    <button class="button" type="submit">
                        Revisar actualización del índice
                    </button>

                </form>

                <?php if (
                    $cis_ix_pedir
                    && ! $cis_ix_autorizado
                ) : ?>

                    <p class="notice notice-error inline">
                        La solicitud caducó.
                        Ejecuta otra revisión.
                    </p>

                <?php elseif (
                    $cis_ix_autorizado
                    && is_array( $cis_ix_resumen )
                ) : ?>

                    <p>
                        <strong>Productos publicados con índice:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_ix_resumen['indexados'] ?? 0
                            )
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Índice anterior a la última modificación:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_ix_resumen['fecha_anterior'] ?? 0
                            )
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Productos con título diferente:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_ix_resumen['titulo_diferente'] ?? 0
                            )
                        );
                        ?>
                    </p>

                    <?php if ( $cis_ix_muestras ) : ?>

                        <table class="widefat striped">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Última modificación</th>
                                    <th>Última indexación</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $cis_ix_muestras as $cis_ix_fila
                                ) : ?>

                                    <?php

                                    $cis_ix_motivos = array();

                                    if (
                                        $cis_ix_fila->post_modified >
                                        $cis_ix_fila->actualizado
                                    ) {

                                        $cis_ix_motivos[] =
                                            'Fecha del índice anterior';
                                    }

                                    if (
                                        $cis_ix_fila->nombre !==
                                        $cis_ix_fila->post_title
                                    ) {

                                        $cis_ix_motivos[] =
                                            'Título diferente';
                                    }

                                    ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_ix_fila->ID
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <a href="<?php
                                                echo esc_url(
                                                    get_edit_post_link(
                                                        $cis_ix_fila->ID
                                                    )
                                                );
                                            ?>">
                                                <?php
                                                echo esc_html(
                                                    $cis_ix_fila->post_title
                                                );
                                                ?>
                                            </a>
                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_ix_fila->post_modified
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_ix_fila->actualizado
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                implode(
                                                    ' / ',
                                                    $cis_ix_motivos
                                                )
                                            );
                                            ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                        <p class="description">
                            Se muestran como máximo 20 ejemplos.
                            Los contadores incluyen todos los
                            productos publicados con índice.
                        </p>

                    <?php else : ?>

                        <p>
                            No se encontraron diferencias
                            de fecha o título.
                        </p>

                    <?php endif; ?>

                <?php elseif ( $cis_ix_autorizado ) : ?>

                    <p>
                        No fue posible obtener el resumen.
                        Revisa los registros de base de datos.
                    </p>

                <?php endif; ?>

                <p class="description">
                    Esta comprobación identifica posibles
                    desactualizaciones, no errores confirmados.

                    Algunos cambios de precio, stock,
                    categorías o variaciones pueden necesitar
                    verificaciones adicionales.

                    No modifica ni reindexa productos.
                </p>

            </details>

        </div>

        <?php
        /*
        * Diagnóstico de embeddings bajo demanda.
        * No genera vectores ni consume API.
        */

        $cis_em_pedir = isset( $_GET['cis_em_ver'] )
            && is_string( $_GET['cis_em_ver'] )
            && '1' === wp_unslash( $_GET['cis_em_ver'] );

        $cis_em_nonce = isset( $_GET['cis_em_nonce'] )
            && is_string( $_GET['cis_em_nonce'] )
            ? sanitize_text_field(
                wp_unslash( $_GET['cis_em_nonce'] )
            )
            : '';

        $cis_em_autorizado = $cis_em_pedir
            && wp_verify_nonce(
                $cis_em_nonce,
                'cis_em_revision'
            );

        $cis_em_estado = null;
        $cis_em_config = array();

        if (
            $cis_em_autorizado
            && function_exists(
                'compuciber_obtener_estado_embeddings'
            )
            && function_exists(
                'compuciber_obtener_configuracion_embeddings'
            )
        ) {

            $cis_em_config =
                compuciber_obtener_configuracion_embeddings();

            $cis_em_estado =
                compuciber_obtener_estado_embeddings();
        }

        $cis_em_bloqueo = get_option(
            'compuciber_embeddings_lote_lock',
            false
        );

        $cis_em_bloqueo_segundos =
            is_array( $cis_em_bloqueo )
                ? max(
                    0,
                    time() - absint(
                        $cis_em_bloqueo['creado'] ?? 0
                    )
                )
                : 0;
        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php echo $cis_em_pedir ? 'open' : ''; ?>>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Estado de embeddings y procesamiento por lotes
                </summary>

                <p>
                    Comprueba los vectores semánticos existentes
                    sin llamar a Gemini ni OpenAI.
                </p>

                <form
                    method="get"
                    action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                >

                    <input
                        type="hidden"
                        name="page"
                        value="compuciber-ai-search-diagnostico"
                    >

                    <input
                        type="hidden"
                        name="cis_em_ver"
                        value="1"
                    >

                    <?php
                    wp_nonce_field(
                        'cis_em_revision',
                        'cis_em_nonce'
                    );
                    ?>

                    <button class="button" type="submit">
                        Comprobar embeddings
                    </button>

                </form>

                <?php if (
                    $cis_em_pedir
                    && ! $cis_em_autorizado
                ) : ?>

                    <p class="notice notice-error inline">
                        La solicitud caducó.
                        Ejecuta otra revisión.
                    </p>

                <?php elseif ( is_array( $cis_em_estado ) ) : ?>

                    <p>
                        <strong>Proveedor:</strong>

                        <?php
                        echo esc_html(
                            $cis_em_config['proveedor'] ?? ''
                        );
                        ?>

                        |

                        <strong>Modelo:</strong>

                        <?php
                        echo esc_html(
                            $cis_em_config['modelo'] ?? ''
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Búsqueda semántica:</strong>

                        <?php
                        echo get_option(
                            'compuciber_busqueda_semantica',
                            '0'
                        ) === '1'
                            ? 'Activada'
                            : 'Desactivada';
                        ?>
                    </p>

                    <p>
                        <strong>Registros revisados:</strong>

                        <?php
                        echo esc_html(
                            absint( $cis_em_estado['total'] ?? 0 )
                        );
                        ?>

                        |

                        <strong>Embeddings válidos:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_em_estado['generados'] ?? 0
                            )
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Pendientes o desactualizados:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_em_estado['pendientes'] ?? 0
                            )
                        );
                        ?>

                        |

                        <strong>Sin texto semántico:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                $cis_em_estado['sin_texto'] ?? 0
                            )
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Último ID usado por los lotes:</strong>

                        <?php
                        echo esc_html(
                            absint(
                                get_option(
                                    'compuciber_embeddings_ultimo_id',
                                    0
                                )
                            )
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Bloqueo de ejecución:</strong>

                        <?php

                        echo esc_html(
                            is_array( $cis_em_bloqueo )
                                ? (
                                    $cis_em_bloqueo_segundos > 180
                                        ? 'Antiguo: revisar'
                                        : 'Registrado'
                                )
                                : 'Sin bloqueo'
                        );

                        ?>
                    </p>

                    <?php if (
                        get_option(
                            'compuciber_busqueda_semantica',
                            '0'
                        ) !== '1'
                    ) : ?>

                        <p class="notice notice-warning inline">
                            La búsqueda semántica está desactivada.
                            El indexador no generará nuevos embeddings
                            hasta habilitarla.
                        </p>

                    <?php endif; ?>

                    <p>

                        <a
                            class="button"
                            href="<?php
                                echo esc_url(
                                    admin_url(
                                        'admin.php?page=compuciber-ai-search'
                                    )
                                );
                            ?>"
                        >
                            Ir al Dashboard para generar lotes
                        </a>

                    </p>

                <?php elseif ( $cis_em_autorizado ) : ?>

                    <p>
                        Las funciones de diagnóstico
                        de embeddings no están disponibles.
                    </p>

                <?php endif; ?>

                <p class="description">
                    Esta revisión recorre el índice únicamente
                    cuando pulsas el botón.

                    No genera embeddings ni consume tokens.

                    El generador actual procesa un máximo
                    de cinco productos por petición.

                    La automatización segura de la cola
                    todavía está pendiente.
                </p>

            </details>

        </div>

        <?php

        $cis_cola_estado = get_option(
            'compuciber_cola_embeddings_estado',
            array()
        );

        $cis_cola_estado = is_array( $cis_cola_estado )
            ? $cis_cola_estado
            : array();

        $cis_cola_proxima = wp_next_scheduled(
            'compuciber_cola_embeddings_paso'
        );

        $cis_cola_aviso = (
            isset( $_GET['cis_cola_aviso'] )
            && is_string( $_GET['cis_cola_aviso'] )
        )
            ? sanitize_key(
                wp_unslash(
                    $_GET['cis_cola_aviso']
                )
            )
            : '';

        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php echo $cis_cola_aviso ? 'open' : ''; ?>>

                <summary style="
                    font-size:18px;
                    font-weight:600;
                    cursor:pointer;
                ">
                    Cola automática de embeddings
                </summary>

                <p>
                    Esta función realiza solicitudes a la API
                    y puede consumir cuota o generar costes.

                    Solamente se inicia cuando lo autorizas.
                </p>

                <?php if ( $cis_cola_aviso ) : ?>

                    <p>
                        <strong>Última operación:</strong>

                        <?php
                        echo esc_html(
                            str_replace(
                                '_',
                                ' ',
                                $cis_cola_aviso
                            )
                        );
                        ?>
                    </p>

                <?php endif; ?>

                <p>
                    <strong>Estado:</strong>

                    <?php
                    echo esc_html(
                        $cis_cola_estado['situacion']
                            ?? 'sin iniciar'
                    );
                    ?>
                </p>

                <p>
                    <strong>Procesados en esta sesión:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_cola_estado['procesados'] ?? 0
                        )
                    );
                    ?>

                    |

                    <strong>Generados:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_cola_estado['generados'] ?? 0
                        )
                    );
                    ?>

                    |

                    <strong>Fallidos:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_cola_estado['fallidos'] ?? 0
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Tope elegido:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_cola_estado['limite_total'] ?? 0
                        )
                    );
                    ?>

                    intentos

                    |

                    <strong>Por lote:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_cola_estado['por_lote'] ?? 0
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Próxima ejecución:</strong>

                    <?php
                    echo esc_html(
                        $cis_cola_proxima
                            ? wp_date(
                                'Y-m-d H:i:s',
                                $cis_cola_proxima
                            )
                            : 'No programada'
                    );
                    ?>
                </p>

                <p>
                    <strong>Último procesamiento:</strong>

                    <?php
                    echo esc_html(
                        ! empty( $cis_cola_estado['ultima'] )
                            ? wp_date(
                                'Y-m-d H:i:s',
                                absint(
                                    $cis_cola_estado['ultima']
                                )
                            )
                            : 'Sin actividad'
                    );
                    ?>
                </p>

                <form
                    method="post"
                    action="<?php
                        echo esc_url(
                            admin_url( 'admin-post.php' )
                        );
                    ?>"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="compuciber_control_cola_embeddings"
                    >

                    <?php
                    wp_nonce_field(
                        'compuciber_control_cola_embeddings'
                    );
                    ?>

                    <?php if (
                        ! empty(
                            $cis_cola_estado['activo']
                        )
                    ) : ?>

                        <input
                            type="hidden"
                            name="operacion"
                            value="detener"
                        >

                        <button
                            type="submit"
                            class="button"
                        >
                            Detener cola
                        </button>

                    <?php else : ?>

                        <input
                            type="hidden"
                            name="operacion"
                            value="iniciar"
                        >

                        <p>
                            <label>
                                Productos por lote:

                                <select name="por_lote">

                                    <option value="1">
                                        1 (conservador)
                                    </option>

                                    <option value="2">
                                        2
                                    </option>

                                    <option value="5">
                                        5
                                    </option>

                                </select>
                            </label>
                        </p>

                        <p>
                            <label>
                                Cantidad total a procesar

                                <input
                                    type="number"
                                    name="limite_total"
                                    min="1"
                                    max="2000"
                                    step="1"
                                    value="100"
                                    required
                                    style="width:120px;"
                                >
                            </label>
                        </p>

                        <p>
                            <label>
                                Minutos entre lotes:

                                <input
                                    type="number"
                                    name="cada_minutos"
                                    min="1"
                                    max="10"
                                    step="1"
                                    value="5"
                                    required
                                    style="width:80px;"
                                >
                            </label>
                        </p>

                        <p class="description">
                            Recomendado: 5 minutos para comenzar
                            con una API gratuita.

                            Con una API de pago y cuota suficiente,
                            puedes probar 1 minuto.

                            Una frecuencia mayor puede aumentar
                            el consumo y alcanzar límites de la API.
                        </p>                        

                        <button
                            type="submit"
                            class="button button-primary"
                        >
                            Iniciar cola
                        </button>

                    <?php endif; ?>

                </form>

                <p class="description">
                    Se programa un lote aproximadamente cada
                    cinco minutos mediante WP-Cron.

                    La cola se pausa cuando detecta fallos
                    o cambios de proveedor y modelo.

                    Detener la cola no interrumpe una
                    solicitud HTTP que ya esté ejecutándose.

                    Recarga esta pantalla para actualizar
                    los contadores.

                    Seleccionar 2000 permite realizar
                    numerosas solicitudes a la API.
                </p>

            </details>

        </div>        

        <?php
        /*
        * Auditoría selectiva de los datos indexados.
        * Solo lectura y sin consumo de API.
        */

        $cis_fd_solicitada =
            isset( $_GET['cis_fd_revisar'] )
            && '1' === (string) wp_unslash(
                $_GET['cis_fd_revisar']
            );

        $cis_fd_nonce =
            isset( $_GET['cis_fd_nonce'] )
            && is_string( $_GET['cis_fd_nonce'] )
                ? sanitize_text_field(
                    wp_unslash( $_GET['cis_fd_nonce'] )
                )
                : '';

        $cis_fd_autorizada =
            $cis_fd_solicitada
            && wp_verify_nonce(
                $cis_fd_nonce,
                'cis_fd_revision'
            );

        $cis_fd_id = isset( $_GET['cis_fd_id'] )
            ? absint( $_GET['cis_fd_id'] )
            : 0;

        $cis_fd_pagina = isset( $_GET['cis_fd_pagina'] )
            ? max( 1, absint( $_GET['cis_fd_pagina'] ) )
            : 1;

        $cis_fd_total = 0;
        $cis_fd_filas = array();
        $cis_fd_incidencias = 0;
        $cis_fd_tabla = array();
        $cis_fd_error = '';

        if ( $cis_fd_autorizada ) {

            global $wpdb;

            $cis_fd_indice =
                $wpdb->prefix . 'compuciber_product_index';

            $cis_fd_filtro =
                "p.post_type = 'product'
                AND p.post_status = 'publish'";

            /*
            * Permitir consultar un producto concreto.
            */

            if ( $cis_fd_id ) {

                $cis_fd_filtro .= $wpdb->prepare(
                    ' AND p.ID = %d',
                    $cis_fd_id
                );
            }

            $cis_fd_total = (int) $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                WHERE {$cis_fd_filtro}"
            );

            if ( $wpdb->last_error ) {

                $cis_fd_error =
                    'No se pudo contar el catálogo.';

            } else {

                $cis_fd_paginas = max(
                    1,
                    (int) ceil( $cis_fd_total / 15 )
                );

                $cis_fd_pagina = min(
                    $cis_fd_pagina,
                    $cis_fd_paginas
                );

                /*
                * Cargar solamente quince productos.
                */

                $cis_fd_filas = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            p.ID,
                            p.post_title,
                            i.id AS indice_id,
                            i.sku,
                            i.precio,
                            i.precio_oferta,
                            i.stock,
                            i.categorias,
                            i.variaciones

                        FROM {$wpdb->posts} p

                        LEFT JOIN {$cis_fd_indice} i
                            ON i.id = p.ID

                        WHERE {$cis_fd_filtro}

                        ORDER BY p.ID ASC

                        LIMIT %d OFFSET %d",
                        15,
                        ( $cis_fd_pagina - 1 ) * 15
                    )
                );

                if (
                    $wpdb->last_error
                    || ! is_array( $cis_fd_filas )
                ) {

                    $cis_fd_filas = array();

                    $cis_fd_error =
                        'No se pudieron consultar los registros del índice.';

                } else {

                    foreach (
                        $cis_fd_filas as $cis_fd_fila
                    ) {

                        $cis_fd_diferencias = array();
                        $cis_fd_advertencia = '';

                        $cis_fd_producto = wc_get_product(
                            (int) $cis_fd_fila->ID
                        );

                        if ( ! $cis_fd_producto ) {

                            $cis_fd_diferencias[] =
                                'Producto no disponible en WooCommerce';

                        } elseif (
                            null === $cis_fd_fila->indice_id
                        ) {

                            $cis_fd_diferencias[] =
                                'Sin índice';

                        } else {

                            /*
                            * SKU.
                            */

                            if (
                                (string) $cis_fd_fila->sku
                                !==
                                (string) $cis_fd_producto->get_sku()
                            ) {

                                $cis_fd_diferencias[] = 'SKU';
                            }

                            /*
                            * Precio regular.
                            */

                            if (
                                abs(
                                    (float) $cis_fd_fila->precio
                                    -
                                    (float) $cis_fd_producto->get_regular_price()
                                ) > 0.000001
                            ) {

                                $cis_fd_diferencias[] =
                                    'Precio regular';
                            }

                            /*
                            * Precio de oferta.
                            */

                            if (
                                abs(
                                    (float) $cis_fd_fila->precio_oferta
                                    -
                                    (float) $cis_fd_producto->get_sale_price()
                                ) > 0.000001
                            ) {

                                $cis_fd_diferencias[] =
                                    'Precio de oferta';
                            }

                            /*
                            * Stock.
                            */

                            $cis_fd_stock_wc =
                                $cis_fd_producto->get_stock_quantity();

                            if ( null === $cis_fd_stock_wc ) {

                                $cis_fd_stock_wc =
                                    $cis_fd_producto->get_stock_status();
                            }

                            if (
                                (string) $cis_fd_fila->stock
                                !==
                                (string) $cis_fd_stock_wc
                            ) {

                                $cis_fd_diferencias[] =
                                    'Stock';
                            }

                            /*
                            * Categorías.
                            */

                            $cis_fd_categorias_wc =
                                wp_get_post_terms(
                                    $cis_fd_fila->ID,
                                    'product_cat',
                                    array(
                                        'fields' => 'names'
                                    )
                                );

                            if (
                                is_wp_error( $cis_fd_categorias_wc )
                            ) {

                                $cis_fd_advertencia =
                                    'No se pudieron leer las categorías';

                            } elseif (
                                (string) $cis_fd_fila->categorias
                                !==
                                implode(
                                    ', ',
                                    $cis_fd_categorias_wc
                                )
                            ) {

                                $cis_fd_diferencias[] =
                                    'Categorías';
                            }

                            /*
                            * Variaciones.
                            *
                            * Evitar consultas excesivas
                            * para productos con muchas variaciones.
                            */

                            if (
                                $cis_fd_producto->is_type( 'variable' )
                                && count(
                                    $cis_fd_producto->get_children()
                                ) > 25
                            ) {

                                $cis_fd_advertencia = trim(
                                    $cis_fd_advertencia
                                    . '; variaciones no comparadas (>25)',
                                    '; '
                                );

                            } elseif (
                                (string) $cis_fd_fila->variaciones
                                !==
                                (string) compuciber_obtener_variaciones_producto(
                                    $cis_fd_fila->ID
                                )
                            ) {

                                $cis_fd_diferencias[] =
                                    'Variaciones';
                            }
                        }

                        if ( $cis_fd_diferencias ) {
                            $cis_fd_incidencias++;
                        }

                        $cis_fd_tabla[] = array(
                            'id' => absint(
                                $cis_fd_fila->ID
                            ),
                            'titulo' => (string) $cis_fd_fila->post_title,
                            'diferencias' => $cis_fd_diferencias,
                            'advertencia' => $cis_fd_advertencia,
                        );
                    }
                }
            }
        }
        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php echo $cis_fd_solicitada ? 'open' : ''; ?>>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Comprobar datos de WooCommerce frente al índice
                </summary>

                <p>
                    Revisa hasta 15 productos por página
                    sin modificar productos ni utilizar IA.
                </p>

                <form
                    method="get"
                    action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                >

                    <input
                        type="hidden"
                        name="page"
                        value="compuciber-ai-search-diagnostico"
                    >

                    <input
                        type="hidden"
                        name="cis_fd_revisar"
                        value="1"
                    >

                    <?php
                    wp_nonce_field(
                        'cis_fd_revision',
                        'cis_fd_nonce'
                    );
                    ?>

                    <label for="cis-fd-id">
                        ID de producto (opcional):
                    </label>

                    <input
                        id="cis-fd-id"
                        type="number"
                        min="1"
                        name="cis_fd_id"
                        value="<?php
                            echo $cis_fd_id
                                ? esc_attr( $cis_fd_id )
                                : '';
                        ?>"
                    >

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        Comprobar datos
                    </button>

                </form>

                <?php if (
                    $cis_fd_solicitada
                    && ! $cis_fd_autorizada
                ) : ?>

                    <p>
                        La validación caducó.
                        Ejecuta una consulta nueva.
                    </p>

                <?php elseif ( $cis_fd_autorizada ) : ?>

                    <?php if ( $cis_fd_error ) : ?>

                        <p class="notice notice-error inline">
                            <?php echo esc_html( $cis_fd_error ); ?>
                        </p>

                    <?php else : ?>

                        <p>
                            <?php
                            echo esc_html(
                                'Productos de esta página: '
                                . count( $cis_fd_tabla )
                                . ' | Diferencias detectadas aquí: '
                                . $cis_fd_incidencias
                                . ' | Productos de la consulta: '
                                . $cis_fd_total
                            );
                            ?>
                        </p>

                        <table class="widefat striped">

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Resultado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $cis_fd_tabla as $cis_fd_item
                                ) : ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_fd_item['id']
                                            );
                                            ?>
                                        </td>

                                        <td>

                                            <a href="<?php
                                                echo esc_url(
                                                    get_edit_post_link(
                                                        $cis_fd_item['id']
                                                    )
                                                );
                                            ?>">

                                                <?php
                                                echo esc_html(
                                                    $cis_fd_item['titulo']
                                                );
                                                ?>

                                            </a>

                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_fd_item['diferencias']
                                                    ? implode(
                                                        ', ',
                                                        $cis_fd_item['diferencias']
                                                    )
                                                    : 'Sin diferencias en los campos revisados'
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $cis_fd_item['advertencia']
                                                    ?: '—'
                                            );
                                            ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                <?php if ( ! $cis_fd_tabla ) : ?>

                                    <tr>
                                        <td colspan="4">
                                            No se encontraron productos.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                        <?php if (
                            ! $cis_fd_id
                            && $cis_fd_total > 15
                        ) : ?>

                            <p>

                                <?php foreach (
                                    array(
                                        'Anterior' => $cis_fd_pagina - 1,
                                        'Siguiente' => $cis_fd_pagina + 1
                                    ) as $cis_fd_texto => $cis_fd_destino
                                ) : ?>

                                    <?php

                                    if (
                                        $cis_fd_destino < 1
                                        || $cis_fd_destino >
                                            max(
                                                1,
                                                (int) ceil(
                                                    $cis_fd_total / 15
                                                )
                                            )
                                    ) {
                                        continue;
                                    }

                                    $cis_fd_enlace = add_query_arg(
                                        array(
                                            'page' =>
                                                'compuciber-ai-search-diagnostico',

                                            'cis_fd_revisar' => '1',

                                            'cis_fd_nonce' =>
                                                wp_create_nonce(
                                                    'cis_fd_revision'
                                                ),

                                            'cis_fd_pagina' =>
                                                $cis_fd_destino,
                                        ),
                                        admin_url( 'admin.php' )
                                    );

                                    ?>

                                    <a
                                        class="button"
                                        href="<?php
                                            echo esc_url(
                                                $cis_fd_enlace
                                            );
                                        ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            $cis_fd_texto
                                        );
                                        ?>
                                    </a>

                                <?php endforeach; ?>

                            </p>

                        <?php endif; ?>

                    <?php endif; ?>

                <?php endif; ?>

                <p class="description">
                    Las diferencias corresponden únicamente
                    a los productos revisados en esta página.

                    No es una auditoría completa de los
                    2.000 productos.

                    La comparación de categorías puede detectar
                    diferencias debidas solamente al orden
                    de sus nombres.

                    Las variaciones con más de 25 elementos
                    se omiten para proteger el rendimiento.
                </p>

            </details>

        </div>

        <?php

        /*
        * Panel de auditoría integral.
        */

        $cis_ai_estado = get_option(
            'compuciber_auditoria_integral_estado',
            array()
        );

        $cis_ai_estado = is_array( $cis_ai_estado )
            ? $cis_ai_estado
            : array();

        $cis_ai_token = (string) (
            $cis_ai_estado['token'] ?? ''
        );

        $cis_ai_siguiente = $cis_ai_token !== ''
            ? wp_next_scheduled(
                'compuciber_auditoria_integral_lote',
                array( $cis_ai_token )
            )
            : false;

        $cis_ai_total = absint(
            $cis_ai_estado['total'] ?? 0
        );

        $cis_ai_revisados = absint(
            $cis_ai_estado['revisados'] ?? 0
        );

        $cis_ai_progreso = $cis_ai_total > 0
            ? min(
                100,
                round(
                    100 * $cis_ai_revisados / $cis_ai_total,
                    1
                )
            )
            : 0;

        $cis_ai_aviso = isset(
            $_GET['cis_auditoria_aviso']
        )
            && is_string( $_GET['cis_auditoria_aviso'] )
                ? sanitize_key(
                    wp_unslash(
                        $_GET['cis_auditoria_aviso']
                    )
                )
                : '';

        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php
                echo (
                    $cis_ai_aviso
                    || ! empty( $cis_ai_estado['activo'] )
                ) ? 'open' : '';
            ?>>

                <summary style="
                    cursor:pointer;
                    font-size:18px;
                    font-weight:600;
                ">
                    Auditoría integral del catálogo (por lotes)
                </summary>

                <p>
                    Examina los productos publicados,
                    de 15 en 15, mediante WP-Cron.

                    No modifica el índice, no genera
                    embeddings y no utiliza API.
                </p>

                <?php if ( $cis_ai_aviso ) : ?>

                    <p>
                        <strong>Operación:</strong>

                        <?php
                        echo esc_html(
                            str_replace(
                                '_',
                                ' ',
                                $cis_ai_aviso
                            )
                        );
                        ?>
                    </p>

                <?php endif; ?>

                <p>
                    <strong>Estado:</strong>

                    <?php
                    echo esc_html(
                        $cis_ai_estado['situacion']
                            ?? 'sin iniciar'
                    );
                    ?>

                    |

                    <strong>Revisados:</strong>

                    <?php echo esc_html( $cis_ai_revisados ); ?>

                    /

                    <?php echo esc_html( $cis_ai_total ); ?>

                    |

                    <strong>Avance:</strong>

                    <?php echo esc_html( $cis_ai_progreso ); ?> %
                </p>

                <progress
                    max="100"
                    value="<?php echo esc_attr( $cis_ai_progreso ); ?>"
                    style="width:100%;max-width:700px;"
                ></progress>

                <p>
                    <strong>Productos con diferencias:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_ai_estado['con_diferencias'] ?? 0
                        )
                    );
                    ?>

                    |

                    <strong>No aptos para búsqueda (con índice):</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_ai_estado['no_visibles'] ?? 0
                        )
                    );
                    ?>

                    |

                    <strong>Comprobaciones no realizadas:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_ai_estado['no_verificados'] ?? 0
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Último ID recorrido:</strong>

                    <?php
                    echo esc_html(
                        absint(
                            $cis_ai_estado['cursor'] ?? 0
                        )
                    );
                    ?>

                    |

                    <strong>Próximo lote:</strong>

                    <?php
                    echo esc_html(
                        $cis_ai_siguiente
                            ? wp_date(
                                'Y-m-d H:i:s',
                                $cis_ai_siguiente
                            )
                            : 'No programado'
                    );
                    ?>
                </p>

                <form
                    method="post"
                    action="<?php
                        echo esc_url(
                            admin_url( 'admin-post.php' )
                        );
                    ?>"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="compuciber_auditoria_integral_control"
                    >

                    <?php
                    wp_nonce_field(
                        'compuciber_auditoria_integral_control'
                    );
                    ?>

                    <?php if (
                        ! empty( $cis_ai_estado['activo'] )
                    ) : ?>

                        <input
                            type="hidden"
                            name="operacion"
                            value="detener"
                        >

                        <button type="submit" class="button">
                            Detener auditoría
                        </button>

                    <?php else : ?>

                        <input
                            type="hidden"
                            name="operacion"
                            value="iniciar"
                        >

                        <button
                            type="submit"
                            class="button button-primary"
                        >
                            Iniciar auditoría integral
                        </button>

                    <?php endif; ?>

                </form>

                <?php if (
                    ! empty( $cis_ai_estado['motivos'] )
                ) : ?>

                    <h4>Diferencias detectadas durante el recorrido</h4>

                    <table class="widefat striped">

                        <thead>
                            <tr>
                                <th>Motivo</th>
                                <th>Productos afectados</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $cis_ai_estado['motivos']
                                as $motivo => $cantidad
                            ) : ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $motivo
                                            )
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            absint( $cantidad )
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

                <?php if (
                    ! empty( $cis_ai_estado['muestras'] )
                ) : ?>

                    <h4>Primeros 60 productos con diferencias</h4>

                    <table class="widefat striped">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Diferencias</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $cis_ai_estado['muestras']
                                as $muestra
                            ) : ?>

                                <?php
                                $cis_ai_id = absint(
                                    $muestra['id'] ?? 0
                                );
                                ?>

                                <tr>

                                    <td>
                                        <?php echo esc_html( $cis_ai_id ); ?>
                                    </td>

                                    <td>
                                        <a href="<?php
                                            echo esc_url(
                                                get_edit_post_link( $cis_ai_id )
                                            );
                                        ?>">
                                            <?php
                                            echo esc_html(
                                                get_the_title( $cis_ai_id )
                                            );
                                            ?>
                                        </a>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            implode(
                                                ', ',
                                                (array) (
                                                    $muestra['motivos']
                                                    ?? array()
                                                )
                                            )
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

                <?php if (
                    ! empty(
                        $cis_ai_estado['incidencias']
                    )
                ) : ?>

                    <p>

                        <a
                            class="button button-secondary"
                            href="<?php

                                echo esc_url(
                                    wp_nonce_url(
                                        admin_url(
                                            'admin-post.php?action=compuciber_auditoria_integral_exportar'
                                        ),
                                        'compuciber_auditoria_integral_exportar'
                                    )
                                );

                            ?>"
                        >
                            Exportar incidencias (CSV)
                        </a>

                    </p>

                    <?php if (
                        ! empty(
                            $cis_ai_estado['activo']
                        )
                    ) : ?>

                        <p class="description">
                            La auditoría todavía está ejecutándose.
                            El CSV contendrá resultados parciales.
                        </p>

                    <?php endif; ?>

                    <?php if (
                        ! empty(
                            $cis_ai_estado['registro_limitado']
                        )
                    ) : ?>

                        <p class="notice notice-warning inline">
                            Se alcanzó el límite de 5000 incidencias.
                            El archivo puede estar incompleto.
                        </p>

                    <?php endif; ?>

                <?php endif; ?>                

                <p class="description">
                    El total corresponde al catálogo
                    existente cuando comenzó la auditoría.

                    Los productos pueden modificarse
                    mientras se realiza el recorrido.

                    Una diferencia es una señal para
                    investigar, no necesariamente un error.

                    No se comparan las variaciones de productos
                    que tengan más de 20 variaciones.

                    La comparación de categorías puede dar
                    diferencias inexactas si sus nombres
                    contienen comas.

                    WP-Cron depende de las visitas o de
                    un cron real configurado en el hosting.

                    Recarga esta pantalla para actualizar
                    los contadores.
                </p>

            </details>

        </div>        

        <?php

        $cis_traza_activa =
            (int) get_option( 'cis_traza_hasta', 0 ) > time();

        $cis_trazas_recientes = get_transient(
            'cis_trazas_recientes'
        );

        $cis_trazas_recientes =
            is_array( $cis_trazas_recientes )
                ? $cis_trazas_recientes
                : array();

        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php echo $cis_traza_activa ? 'open' : ''; ?>>

                <summary style="
                    font-size:18px;
                    font-weight:600;
                    cursor:pointer;
                ">
                    Últimas cinco búsquedas: traza técnica
                </summary>

                <p>
                    Estado:
                    <strong>
                        <?php
                        echo esc_html(
                            $cis_traza_activa
                                ? 'Captura activa'
                                : 'Captura desactivada'
                        );
                        ?>
                    </strong>
                </p>

                <p>
                    La captura se desactiva automáticamente
                    30 minutos después de iniciarse.
                </p>

                <form
                    method="post"
                    action="<?php
                        echo esc_url(
                            admin_url( 'admin-post.php' )
                        );
                    ?>"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="compuciber_traza_control"
                    >

                    <?php wp_nonce_field( 'cis_traza_control' ); ?>

                    <?php if ( $cis_traza_activa ) : ?>

                        <button
                            class="button"
                            type="submit"
                            name="operacion"
                            value="desactivar"
                        >
                            Detener captura
                        </button>

                    <?php else : ?>

                        <button
                            class="button button-primary"
                            type="submit"
                            name="operacion"
                            value="activar"
                        >
                            Capturar durante 30 minutos
                        </button>

                    <?php endif; ?>

                    <button
                        class="button"
                        type="submit"
                        name="operacion"
                        value="limpiar"
                    >
                        Borrar las cinco trazas
                    </button>

                </form>

                <?php if ( empty( $cis_trazas_recientes ) ) : ?>

                    <p>No hay búsquedas capturadas todavía.</p>

                <?php endif; ?>

                <?php foreach (
                    $cis_trazas_recientes as $cis_traza
                ) : ?>

                    <details style="
                        padding:10px 0;
                        border-top:1px solid #ddd;
                    ">

                        <summary>

                            <strong>
                                <?php
                                echo esc_html(
                                    $cis_traza['fecha'] ?? ''
                                );
                                ?>
                            </strong>

                            —

                            <?php
                            echo esc_html(
                                $cis_traza['original'] ?? ''
                            );
                            ?>

                            (
                            <?php
                            echo esc_html(
                                absint(
                                    $cis_traza['resultados'] ?? 0
                                )
                            );
                            ?>
                            resultados)

                        </summary>

                        <p>
                            <strong>Consulta final:</strong>

                            <?php
                            echo esc_html(
                                $cis_traza['final'] ?? ''
                            );
                            ?>

                            |

                            <strong>Perfil:</strong>

                            <?php
                            echo esc_html(
                                ! empty( $cis_traza['perfil'] )
                                    ? $cis_traza['perfil']
                                    : 'general'
                            );
                            ?>
                        </p>

                        <p>
                            <strong>Interpretación IA disponible:</strong>

                            <?php
                            echo ! empty(
                                $cis_traza['interpretacion_ia']
                            ) ? 'Sí' : 'No';
                            ?>

                            |

                            <strong>Semántica configurada:</strong>

                            <?php
                            echo ! empty(
                                $cis_traza['semantica_configurada']
                            ) ? 'Sí' : 'No';
                            ?>
                        </p>

                        <?php foreach (
                            (array) (
                                $cis_traza['pasadas'] ?? array()
                            ) as $cis_numero => $cis_paso
                        ) : ?>

                            <p>

                                <strong>
                                    Pasada
                                    <?php
                                    echo esc_html(
                                        $cis_numero + 1
                                    );
                                    ?>:
                                </strong>

                                <?php
                                echo esc_html(
                                    absint(
                                        $cis_paso['candidatos'] ?? 0
                                    )
                                );
                                ?>
                                candidatos,

                                <?php
                                echo esc_html(
                                    absint(
                                        $cis_paso['puntuados'] ?? 0
                                    )
                                );
                                ?>
                                puntuados.

                            </p>

                            <p>

                                <strong>Primeros ID y puntos:</strong>

                                <?php

                                $cis_puntos = array();

                                foreach (
                                    (array) (
                                        $cis_paso['mejores'] ?? array()
                                    ) as $cis_item
                                ) {

                                    $cis_puntos[] =
                                        '#'
                                        . absint(
                                            $cis_item['id'] ?? 0
                                        )
                                        . ' ('
                                        . (float) (
                                            $cis_item['puntos'] ?? 0
                                        )
                                        . ')';
                                }

                                echo esc_html(
                                    implode( ', ', $cis_puntos )
                                );

                                ?>

                            </p>

                        <?php endforeach; ?>

                        <p>
                            <strong>Primeros productos finales (ID):</strong>

                            <?php
                            echo esc_html(
                                implode(
                                    ', ',
                                    array_map(
                                        'absint',
                                        (array) (
                                            $cis_traza['finales'] ?? array()
                                        )
                                    )
                                )
                            );
                            ?>
                        </p>

                        <?php

                        $cis_dv = $cis_traza['diagnostico_visibilidad'] ?? array();

                        if ( is_array( $cis_dv ) && $cis_dv ) :

                        ?>

                            <p>
                                <strong>Diagnóstico de visibilidad:</strong>

                                Contexto WP es búsqueda:
                                <?php
                                echo ! empty( $cis_dv['es_busqueda_wp'] )
                                    ? 'Sí'
                                    : 'No';
                                ?>

                                | Ocultar agotados:
                                <?php
                                echo ! empty( $cis_dv['ocultar_agotados'] )
                                    ? 'Sí'
                                    : 'No';
                                ?>

                                | Filtros de visibilidad:
                                <?php
                                echo ! empty( $cis_dv['filtro_woocommerce'] )
                                    ? 'Registrados'
                                    : 'No registrados';
                                ?>
                            </p>

                            <p>
                                <strong>Primeros productos evaluados:</strong>
                                <br>

                                <?php foreach (
                                    (array) ( $cis_dv['muestras'] ?? array() )
                                    as $cis_muestra
                                ) : ?>

                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            '#%d | Estado: %s | Catálogo: %s | Stock: %s | Visible: %s',

                                            absint( $cis_muestra['id'] ?? 0 ),

                                            (string) ( $cis_muestra['estado'] ?? '' ),

                                            (string) ( $cis_muestra['catalogo'] ?? '' ),

                                            (string) ( $cis_muestra['stock'] ?? '' ),

                                            ! empty( $cis_muestra['visible'] )
                                                ? 'Sí'
                                                : 'No'
                                        )
                                    );
                                    ?>

                                    <br>

                                <?php endforeach; ?>
                            </p>

                        <?php endif; ?>
                        
                        <p class="description">
                            Se muestran puntuaciones totales,
                            no el desglose de cada regla.

                            La semántica configurada no confirma
                            que se hayan comparado embeddings.

                            Las coincidencias directas por SKU
                            pueden no generar una pasada
                            de puntuación.
                        </p>

                    </details>

                <?php endforeach; ?>

                <p class="description">
                    Se conservan como máximo cinco búsquedas.
                    El historial temporal caduca tras un día
                    sin nuevas capturas.

                    No se almacenan IP ni identidades
                    de clientes. Se ocultan consultas que
                    parecen contener correos o teléfonos.
                </p>

            </details>

        </div>
        
        <?php

        $cis_rep_estado = get_option(
            'compuciber_reparacion_indice_estado',
            array()
        );

        $cis_rep_estado = is_array( $cis_rep_estado )
            ? $cis_rep_estado
            : array();

        $cis_rep_auditoria = get_option(
            'compuciber_auditoria_integral_estado',
            array()
        );

        $cis_rep_auditoria = is_array( $cis_rep_auditoria )
            ? $cis_rep_auditoria
            : array();

        $cis_rep_habilitada =
            ( $cis_rep_auditoria['situacion'] ?? '' ) === 'completada'
            && empty( $cis_rep_auditoria['registro_limitado'] );

        $cis_rep_elegibles = 0;

        $cis_rep_motivos = array_flip(
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

        if ( $cis_rep_habilitada ) {

            foreach (
                (array) (
                    $cis_rep_auditoria['incidencias'] ?? array()
                ) as $fila
            ) {

                if (
                    is_array( $fila )
                    && array_intersect_key(
                        array_flip(
                            (array) ( $fila['motivos'] ?? array() )
                        ),
                        $cis_rep_motivos
                    )
                ) {

                    $cis_rep_elegibles++;
                }
            }
        }

        $cis_rep_total = absint(
            $cis_rep_estado['total'] ?? 0
        );

        $cis_rep_revisados = absint(
            $cis_rep_estado['revisados'] ?? 0
        );

        $cis_rep_pct = $cis_rep_total
            ? min(
                100,
                round(
                    100 * $cis_rep_revisados / $cis_rep_total,
                    1
                )
            )
            : 0;

        $cis_rep_aviso = (
            isset( $_GET['cis_reparacion_aviso'] )
            && is_string( $_GET['cis_reparacion_aviso'] )
        )
            ? sanitize_key(
                wp_unslash( $_GET['cis_reparacion_aviso'] )
            )
            : '';

        ?>

        <div class="postbox" style="padding:16px;max-width:1000px;">

            <details <?php
                echo ! empty( $cis_rep_estado['activo'] )
                    || $cis_rep_aviso
                        ? 'open'
                        : '';
            ?>>

                <summary style="cursor:pointer;font-size:18px;font-weight:600;">
                    Reindexación selectiva de productos afectados
                </summary>

                 <p>
                    Utiliza la última auditoría integral completada.
                    Reindexa productos sin registro en el índice
                    o con diferencias de nombre, SKU, precio, oferta,
                    stock, categorías, variaciones o campos incompletos.
                </p>

                <p>
                    No reindexa productos cuya única incidencia
                    sea la fecha de actualización. Si la indexación
                    inicial sigue activa, deja que termine antes
                    de iniciar esta reparación selectiva.
                </p>

                <p>
                    Los productos <strong>sin índice</strong> ya los
                    procesa «Reintentar indexación» del Dashboard.

                    No se reparan automáticamente diferencias
                    indicadas solo por fecha o categoría.
                </p>

                <p>
                    <strong>Elegibles en la última auditoría:</strong>

                    <?php echo esc_html( $cis_rep_elegibles ); ?>

                    |

                    <strong>Estado:</strong>

                    <span id="cis-rep-estado">
                        <?php
                        echo esc_html(
                            $cis_rep_estado['situacion']
                                ?? 'sin iniciar'
                        );
                        ?>
                    </span>
                </p>

                <p>
                    <strong>Procesados:</strong>

                    <span id="cis-rep-revisados">
                        <?php echo esc_html( $cis_rep_revisados ); ?>
                    </span>

                    /

                    <span id="cis-rep-total">
                        <?php echo esc_html( $cis_rep_total ); ?>
                    </span>

                    |

                    <strong>Reindexados:</strong>

                    <span id="cis-rep-reindexados">
                        <?php
                        echo esc_html(
                            absint(
                                $cis_rep_estado['reindexados'] ?? 0
                            )
                        );
                        ?>
                    </span>

                    |

                    <strong>Fallidos:</strong>

                    <span id="cis-rep-fallidos">
                        <?php
                        echo esc_html(
                            absint(
                                $cis_rep_estado['fallidos'] ?? 0
                            )
                        );
                        ?>
                    </span>
                </p>

                <progress
                    id="cis-rep-barra"
                    max="100"
                    value="<?php echo esc_attr( $cis_rep_pct ); ?>"
                    style="width:100%;max-width:700px;"
                ></progress>

                <?php if ( $cis_rep_aviso ) : ?>

                    <p>
                        <strong>Última acción:</strong>

                        <?php
                        echo esc_html(
                            str_replace(
                                '_',
                                ' ',
                                $cis_rep_aviso
                            )
                        );
                        ?>
                    </p>

                <?php endif; ?>

                <?php if (
                    ! $cis_rep_habilitada
                    && empty( $cis_rep_estado['activo'] )
                ) : ?>

                    <p>
                        Para iniciar, completa primero
                        la auditoría integral.
                    </p>

                <?php endif; ?>

                <form
                    method="post"
                    action="<?php
                        echo esc_url(
                            admin_url( 'admin-post.php' )
                        );
                    ?>"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="compuciber_reparacion_indice_control"
                    >

                    <?php
                    wp_nonce_field(
                        'compuciber_reparacion_indice_control'
                    );
                    ?>

                    <?php if (
                        ! empty( $cis_rep_estado['activo'] )
                    ) : ?>

                        <button
                            class="button"
                            type="submit"
                            name="operacion"
                            value="detener"
                        >
                            Detener reparación
                        </button>

                    <?php elseif (
                        $cis_rep_habilitada
                        && $cis_rep_elegibles
                    ) : ?>

                        <button
                            class="button button-primary"
                            type="submit"
                            name="operacion"
                            value="iniciar"
                        >
                            Reindexar productos elegibles
                        </button>

                    <?php endif; ?>

                </form>

                <?php if (
                    ! empty( $cis_rep_estado['ids_fallidos'] )
                ) : ?>

                    <p>
                        <strong>ID que requieren revisión:</strong>

                        <?php
                        echo esc_html(
                            implode(
                                ', ',
                                array_map(
                                    'absint',
                                    (array) $cis_rep_estado['ids_fallidos']
                                )
                            )
                        );
                        ?>
                    </p>

                <?php endif; ?>

                <p class="description">
                    Cada ejecución procesa hasta diez productos.

                    No genera embeddings ni modifica el catálogo
                    de WooCommerce.

                    Los embeddings que hayan quedado desactualizados
                    deberán procesarse después con la cola existente.
                </p>

            </details>

        </div>        
        
        <div
            class="postbox"
            style="padding:16px;max-width:1000px;"
        >

            <details open>

                <summary
                    style="
                        font-size:18px;
                        font-weight:600;
                        cursor:pointer;
                    "
                >
                    Registro de eventos recientes
                </summary>

                <p>
                    Últimos 40 eventos del plugin,
                    conservados hasta 14 días.
                </p>

                <?php if ( empty( $historial ) ) : ?>

                    <p>
                        Por ahora no hay eventos registrados.
                        Los errores anteriores no se recuperan
                        automáticamente.
                    </p>

                <?php else : ?>

                    <table class="widefat striped">

                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nivel</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ( $historial as $evento ) : ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $evento['fecha'] ?? ''
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $evento['nivel'] ?? ''
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $evento['mensaje'] ?? ''
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

                <p>

                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-status&tab=logs'
                                )
                            );
                        ?>"
                    >
                        Abrir registros completos de WooCommerce
                    </a>

                </p>

                <p class="description">
                    Este historial muestra eventos nuevos
                    enviados por Compuciber a WooCommerce.
                    Los fallos del navegador y de otros plugins
                    necesitarán comprobaciones adicionales.
                </p>

            </details>

        </div>

    </div>

    <?php
}

/*
 * Historial breve de errores de Compuciber.
 * Los registros completos siguen en WooCommerce.
 */
function compuciber_historial_capturar( $mensaje, $nivel, $contexto, $handler = null ) {

    if (
        ! is_string( $mensaje )
        || ! is_array( $contexto )
        || ( $contexto['source'] ?? '' ) !== 'compuciber-ai-search'
    ) {
        return $mensaje;
    }

    $nivel = sanitize_key( $nivel );

    if (
        ! in_array(
            $nivel,
            array( 'error', 'warning', 'critical', 'alert', 'emergency', 'info' ),
            true
        )
    ) {
        return $mensaje;
    }

    $texto = sanitize_text_field(
        wp_strip_all_tags( $mensaje )
    );

    $texto = wp_html_excerpt( $texto, 280, '…' );

    $ahora = time();

    $historial = get_option(
        'compuciber_historial_diagnostico',
        array()
    );

    $historial = is_array( $historial )
        ? $historial
        : array();

    $firma = hash(
        'sha256',
        $nivel . '|' . $texto
    );

    foreach ( $historial as $evento ) {

        if (
            isset( $evento['firma'], $evento['ts'] )
            && hash_equals(
                (string) $evento['firma'],
                $firma
            )
            && $ahora - (int) $evento['ts'] < 60
        ) {
            return $mensaje;
        }
    }

    array_unshift(
        $historial,
        array(
            'fecha'   => current_time( 'mysql' ),
            'nivel'   => $nivel,
            'mensaje' => $texto,
            'firma'   => $firma,
            'ts'      => $ahora,
        )
    );

    $historial = array_values(
        array_filter(
            $historial,
            static function ( $evento ) use ( $ahora ) {

                return is_array( $evento )
                    && isset( $evento['ts'] )
                    && $ahora - (int) $evento['ts'] < 14 * DAY_IN_SECONDS;
            }
        )
    );

    update_option(
        'compuciber_historial_diagnostico',
        array_slice( $historial, 0, 40 ),
        false
    );

    return $mensaje;
}

add_filter(
    'woocommerce_logger_log_message',
    'compuciber_historial_capturar',
    10,
    4
);

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

                /*
                * Comprobaciones informativas del entorno.
                */

                $cis_litespeed = function_exists( 'is_plugin_active' )
                    && is_plugin_active(
                        'litespeed-cache/litespeed-cache.php'
                    );

                $cis_diag_filas[] = array(

                    'LiteSpeed Cache',

                    $cis_litespeed
                        ? 'Detectado'
                        : 'No detectado',

                    $cis_litespeed
                        ? 'Revisar exclusiones de caché/JS si hay conflictos. No implica un fallo.'
                        : 'No se detectó el plugin activo con su ruta habitual.'
                );


                /*
                * Estado de WP-Cron.
                */

                $cis_cron_deshabilitado =
                    defined( 'DISABLE_WP_CRON' )
                    && DISABLE_WP_CRON;

                $cis_diag_filas[] = array(

                    'WP-Cron',

                    $cis_cron_deshabilitado
                        ? 'Revisar'
                        : 'Configuración habitual',

                    $cis_cron_deshabilitado
                        ? 'WP-Cron automático deshabilitado; verificar cron real en hosting.'
                        : 'WP-Cron no está deshabilitado mediante esta constante.'
                );


                /*
                * Próximo lote de indexación.
                */

                $cis_pendientes = absint(
                    $resumen_indice['pendientes'] ?? 0
                );

                $cis_programado = wp_next_scheduled(
                    'compuciber_indice_lote_113'
                );

                $cis_diag_filas[] = array(

                    'Siguiente lote del índice',

                    $cis_pendientes > 0 && ! $cis_programado
                        ? 'Revisar'
                        : 'Información',

                    $cis_programado
                        ? 'Lote programado para '
                            . wp_date(
                                'Y-m-d H:i:s',
                                $cis_programado
                            )
                        : (
                            $cis_pendientes > 0
                                ? 'Hay productos pendientes sin lote programado; revisar estado de indexación.'
                                : 'No hay lote pendiente programado.'
                        )
                );


                /*
                * Identificar el tema utilizado.
                */

                $cis_diag_filas[] = array(

                    'Tema activo',

                    'Información',

                    wp_get_theme()->get( 'Name' )
                        . ' (plantilla: '
                        . get_template()
                        . ').'
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

                <?php
                /* Auditoría de solo lectura. */

                $cis_audit_texto = isset( $_GET['cis_audit_buscar'] )
                    && is_string( $_GET['cis_audit_buscar'] )
                    ? substr(
                        sanitize_text_field(
                            wp_unslash( $_GET['cis_audit_buscar'] )
                        ),
                        0,
                        100
                    )
                    : '';

                $cis_audit_pagina = isset( $_GET['cis_audit_pagina'] )
                    ? max( 1, absint( $_GET['cis_audit_pagina'] ) )
                    : 1;

                $cis_audit_por_pagina = 25;

                $cis_audit_where =
                    'p.post_type = %s AND p.post_status = %s';

                $cis_audit_args = array( 'product', 'publish' );

                if ( $cis_audit_texto !== '' ) {

                    $cis_audit_like =
                        '%' . $wpdb->esc_like( $cis_audit_texto ) . '%';

                    $cis_audit_where .= "
                        AND (
                            p.ID = %d
                            OR p.post_title LIKE %s
                            OR EXISTS (
                                SELECT 1
                                FROM {$wpdb->postmeta} pm
                                WHERE pm.post_id = p.ID
                                AND pm.meta_key = '_sku'
                                AND pm.meta_value LIKE %s
                            )
                        )
                    ";

                    $cis_audit_args[] = absint( $cis_audit_texto );
                    $cis_audit_args[] = $cis_audit_like;
                    $cis_audit_args[] = $cis_audit_like;
                }

                $cis_audit_total = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                        FROM {$wpdb->posts} p
                        WHERE {$cis_audit_where}",
                        $cis_audit_args
                    )
                );

                $cis_audit_paginas = max(
                    1,
                    (int) ceil(
                        $cis_audit_total / $cis_audit_por_pagina
                    )
                );

                $cis_audit_pagina = min(
                    $cis_audit_pagina,
                    $cis_audit_paginas
                );

                $cis_audit_offset =
                    ( $cis_audit_pagina - 1 ) * $cis_audit_por_pagina;

                $cis_audit_filas = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            p.ID,
                            p.post_title,
                            i.id AS indice_id,
                            i.actualizado,
                            CASE
                                WHEN i.id IS NOT NULL
                                AND (
                                    TRIM(i.nombre) = ''
                                    OR TRIM(i.texto_busqueda) = ''
                                )
                                THEN 1
                                ELSE 0
                            END AS incompleto
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$tabla} i
                            ON i.id = p.ID
                        WHERE {$cis_audit_where}
                        ORDER BY p.ID DESC
                        LIMIT %d OFFSET %d",
                        array_merge(
                            $cis_audit_args,
                            array(
                                $cis_audit_por_pagina,
                                $cis_audit_offset
                            )
                        )
                    )
                );

                $cis_audit_incompletos = (int) $wpdb->get_var(
                    "SELECT COUNT(*)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$tabla} i
                        ON i.id = p.ID
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND (
                        TRIM(i.nombre) = ''
                        OR TRIM(i.texto_busqueda) = ''
                    )"
                );
                ?>

                <h3>Auditoría del índice de productos</h3>

                <p>
                    Publicados:
                    <strong>
                        <?php echo esc_html( $resumen_indice['total'] ); ?>
                    </strong>

                    | Indexados:
                    <strong>
                        <?php echo esc_html( $resumen_indice['indexados'] ); ?>
                    </strong>

                    | Sin índice:
                    <strong>
                        <?php echo esc_html( $resumen_indice['pendientes'] ); ?>
                    </strong>

                    | Registros incompletos:
                    <strong>
                        <?php echo esc_html( $cis_audit_incompletos ); ?>
                    </strong>
                </p>

                <p class="description">
                    Esta auditoría comprueba la existencia del registro
                    y sus campos básicos de búsqueda.
                    No modifica productos ni ejecuta indexaciones.
                </p>

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
                        name="cis_diagnostico"
                        value="1"
                    >

                    <?php
                    wp_nonce_field( 'cis_diagnostico_instalacion' );
                    ?>

                    <label for="cis-audit-buscar">
                        Buscar por nombre, SKU o ID:
                    </label>

                    <input
                        type="search"
                        id="cis-audit-buscar"
                        name="cis_audit_buscar"
                        value="<?php echo esc_attr( $cis_audit_texto ); ?>"
                        maxlength="100"
                    >

                    <button type="submit" class="button">
                        Consultar índice
                    </button>
                </form>

                <table
                    class="widefat striped"
                    style="max-width:900px;margin-top:12px;"
                >
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Estado</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            (array) $cis_audit_filas as $cis_audit_fila
                        ) : ?>

                            <?php
                            $cis_audit_estado =
                                is_null( $cis_audit_fila->indice_id )
                                    ? 'Sin indexar'
                                    : (
                                        (int) $cis_audit_fila->incompleto === 1
                                            ? 'Registro incompleto'
                                            : 'Indexado'
                                    );
                            ?>

                            <tr>
                                <td>
                                    <?php
                                    echo esc_html( $cis_audit_fila->ID );
                                    ?>
                                </td>

                                <td>
                                    <a href="<?php
                                        echo esc_url(
                                            get_edit_post_link(
                                                $cis_audit_fila->ID
                                            )
                                        );
                                    ?>">
                                        <?php
                                        echo esc_html(
                                            $cis_audit_fila->post_title
                                        );
                                        ?>
                                    </a>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        get_post_meta(
                                            $cis_audit_fila->ID,
                                            '_sku',
                                            true
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php echo esc_html( $cis_audit_estado ); ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $cis_audit_fila->actualizado ?: '—'
                                    );
                                    ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if ( empty( $cis_audit_filas ) ) : ?>

                            <tr>
                                <td colspan="5">
                                    No se encontraron productos.
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>

                <p>
                    Mostrando
                    <?php echo esc_html( count( (array) $cis_audit_filas ) ); ?>
                    de
                    <?php echo esc_html( $cis_audit_total ); ?>
                    productos.

                    Página
                    <?php echo esc_html( $cis_audit_pagina ); ?>
                    de
                    <?php echo esc_html( $cis_audit_paginas ); ?>.

                    <?php if ( $cis_audit_pagina > 1 ) : ?>

                        <a
                            class="button"
                            href="<?php
                                echo esc_url(
                                    add_query_arg(
                                        'cis_audit_pagina',
                                        $cis_audit_pagina - 1
                                    )
                                );
                            ?>"
                        >
                            Anterior
                        </a>

                    <?php endif; ?>

                    <?php if (
                        $cis_audit_pagina < $cis_audit_paginas
                    ) : ?>

                        <a
                            class="button"
                            href="<?php
                                echo esc_url(
                                    add_query_arg(
                                        'cis_audit_pagina',
                                        $cis_audit_pagina + 1
                                    )
                                );
                            ?>"
                        >
                            Siguiente
                        </a>

                    <?php endif; ?>
                </p>                

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
            : 0;

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

/*
 * Control administrativo de la cola de embeddings.
 */
function compuciber_admin_control_cola_embeddings() {

    if (
        ! current_user_can(
            'manage_options'
        )
    ) {
        wp_die(
            'Acceso no autorizado.'
        );
    }

    check_admin_referer(
        'compuciber_control_cola_embeddings'
    );

    $accion = (
        isset( $_POST['operacion'] )
        && is_string( $_POST['operacion'] )
    )
        ? sanitize_key(
            wp_unslash(
                $_POST['operacion']
            )
        )
        : '';

    $opcion =
        'compuciber_cola_embeddings_estado';

    $estado = get_option(
        $opcion,
        array()
    );

    $estado = is_array( $estado )
        ? $estado
        : array();

    $aviso = 'sin_cambios';

    /*
     * Detener la programación.
     */
    if ( $accion === 'detener' ) {

        $estado['activo'] = false;

        $estado['situacion'] =
            'detenida_manual';

        $estado['ultima'] = time();

        update_option(
            $opcion,
            $estado,
            false
        );

        wp_clear_scheduled_hook(
            'compuciber_cola_embeddings_paso'
        );

        $aviso = 'detenida';
    }

    /*
     * Iniciar una nueva sesión.
     */
    if (
        $accion === 'iniciar'
        && empty( $estado['activo'] )
    ) {

        $config =
            compuciber_obtener_configuracion_embeddings();

        $opcion_clave =
            $config['proveedor'] === 'openai'
                ? 'compuciber_openai_api_key'
                : 'compuciber_gemini_api_key';

        $tamano = isset(
            $_POST['por_lote']
        )
            ? absint(
                $_POST['por_lote']
            )
            : 1;

        $limite = isset(
            $_POST['limite_total']
        )
            ? absint(
                $_POST['limite_total']
            )
            : 100;

        /*
         * Solo permitir valores autorizados.
         */
        if (
            ! in_array(
                $tamano,
                array( 1, 2, 5 ),
                true
            )
        ) {
            $tamano = 1;
        }

        /*
         * Cantidad total configurable.
         * Admite cualquier entero de 1 a 2000.
         */
        $limite = max(
            1,
            min(
                2000,
                $limite
            )
        );

        /*
         * Separación configurable entre lotes.
         *
         * Mínimo: 1 minuto.
         * Máximo: 10 minutos.
         */
        $minutos = isset(
            $_POST['cada_minutos']
        )
            ? absint(
                $_POST['cada_minutos']
            )
            : 5;

        $minutos = max(
            1,
            min(
                10,
                $minutos
            )
        );

        /*
         * Comprobar que la generación
         * está configurada correctamente.
         */
        if (
            ! class_exists( 'WooCommerce' )

            || get_option(
                'compuciber_busqueda_semantica',
                '0'
            ) !== '1'

            || ! trim(
                (string) get_option(
                    $opcion_clave,
                    ''
                )
            )
        ) {

            $aviso = 'configuracion';

        } elseif (
            ! function_exists(
                'compuciber_obtener_estado_embeddings'
            )

            || ! function_exists(
                'compuciber_generar_lote_embeddings'
            )
        ) {

            $aviso = 'no_disponible';

        } else {

            $conteo =
                compuciber_obtener_estado_embeddings();

            if (
                absint(
                    $conteo['pendientes'] ?? 0
                ) === 0
            ) {

                $aviso = 'sin_pendientes';

            } else {

                wp_clear_scheduled_hook(
                    'compuciber_cola_embeddings_paso'
                );

                $estado = array(

                    'activo'       => true,

                    'situacion'    => 'en_cola',

                    'token'        => wp_generate_uuid4(),

                    'proveedor'    => $config['proveedor'],

                    'modelo'       => $config['modelo'],

                    'por_lote'     => $tamano,

                    'limite_total' => $limite,

                    'cada_minutos' => $minutos,

                    'procesados'   => 0,

                    'generados'    => 0,

                    'fallidos'     => 0,

                    'inicio'      => time(),

                    'ultima'      => 0,
                );

                update_option(
                    $opcion,
                    $estado,
                    false
                );

                $programado = wp_schedule_single_event(
                    time() + MINUTE_IN_SECONDS,
                    'compuciber_cola_embeddings_paso',
                    array(),
                    true
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

    /*
     * Regresar al quinto apartado.
     */
    wp_safe_redirect(
        add_query_arg(
            array(
                'page' =>
                    'compuciber-ai-search-diagnostico',

                'cis_cola_aviso' => $aviso,
            ),
            admin_url( 'admin.php' )
        )
    );

    exit;
}

add_action(
    'admin_post_compuciber_control_cola_embeddings',
    'compuciber_admin_control_cola_embeddings'
);