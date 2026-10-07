<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ==========================================================
 * EXPORTACIÓN DE ESTADÍSTICAS PARA EXCEL
 * ==========================================================
 *
 * Genera una hoja de cálculo compatible con Microsoft Excel
 * utilizando HTML tabular.
 *
 * No requiere Composer ni dependencias externas.
 */
function compuciber_exportar_estadisticas_excel() {

    /*
     * ------------------------------------------------------
     * SEGURIDAD
     * ------------------------------------------------------
     */
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            esc_html__(
                'No tienes permisos para exportar estas estadísticas.',
                'compuciber-ai-search'
            )
        );
    }

    check_admin_referer(
        'compuciber_exportar_estadisticas_excel'
    );

    global $wpdb;

    $tabla_busquedas =
        $wpdb->prefix . 'compuciber_search_stats';

    $tabla_clicks =
        $wpdb->prefix . 'compuciber_search_clicks';


    /*
     * ------------------------------------------------------
     * OBTENER ESTADÍSTICAS
     * ------------------------------------------------------
     */
    $estadisticas = $wpdb->get_results(
        "SELECT
            busqueda,
            MAX(busqueda_interpretada)
                AS busqueda_interpretada,
            tipo,
            COUNT(*) AS veces_buscada,
            SUM(
                CASE
                    WHEN resultados > 0 THEN 1
                    ELSE 0
                END
            ) AS con_resultados,
            SUM(
                CASE
                    WHEN resultados = 0 THEN 1
                    ELSE 0
                END
            ) AS sin_resultados,
            SUM(resultados)
                AS total_resultados,
            MAX(fecha)
                AS ultima_busqueda
        FROM {$tabla_busquedas}
        GROUP BY busqueda, tipo
        ORDER BY veces_buscada DESC,
            ultima_busqueda DESC"
    );


    /*
     * ------------------------------------------------------
     * OBTENER SELECCIONES
     * ------------------------------------------------------
     */
    $selecciones = $wpdb->get_results(
        "SELECT
            busqueda,
            COUNT(*) AS total
        FROM {$tabla_clicks}
        WHERE busqueda <> ''
        GROUP BY busqueda"
    );

    $selecciones_por_busqueda = array();

    foreach ( $selecciones as $seleccion ) {

        $clave =
            (string) $seleccion->busqueda;

        $selecciones_por_busqueda[ $clave ] =
            (int) $seleccion->total;
    }


    /*
     * ------------------------------------------------------
     * NOMBRE DEL ARCHIVO
     * ------------------------------------------------------
     */
    $nombre_archivo =
        'compuciber-estadisticas-'
        . wp_date( 'Y-m-d-His' )
        . '.xls';


    /*
     * ------------------------------------------------------
     * CABECERAS DE DESCARGA
     * ------------------------------------------------------
     */
    nocache_headers();

    header(
        'Content-Type: application/vnd.ms-excel; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="'
        . $nombre_archivo
        . '"'
    );

    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );


    /*
     * BOM UTF-8
     *
     * Ayuda a que Excel interprete correctamente
     * tildes, ñ y caracteres especiales.
     */
    echo "\xEF\xBB\xBF";

    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }

        th,
        td {
            border: 1px solid #808080;
            padding: 6px 8px;
            vertical-align: middle;
        }

        th {
            background-color: #e7e6e6;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }

        td {
            background-color: #ffffff;
        }

        .texto {
            text-align: left;
            min-width: 180px;
        }

        .texto-amplio {
            text-align: left;
            min-width: 220px;
        }

        .tipo {
            text-align: center;
            min-width: 90px;
        }

        .numero {
            text-align: center;
            min-width: 100px;
        }

        .fecha {
            text-align: center;
            min-width: 150px;
            white-space: nowrap;
        }
    </style>
</head>

<body>

<table>
    <thead>
        <tr>
            <th>Búsqueda</th>
            <th>Búsqueda interpretada</th>
            <th>Tipo</th>
            <th>Veces buscada</th>
            <th>Con resultados</th>
            <th>Sin resultados</th>
            <th>Total resultados mostrados</th>
            <th>Selecciones</th>
            <th>Última búsqueda</th>
        </tr>
    </thead>

    <tbody>

        <?php foreach ( $estadisticas as $estadistica ) : ?>

            <?php

            $busqueda =
                (string) $estadistica->busqueda;

            $total_selecciones =
                isset(
                    $selecciones_por_busqueda[
                        $busqueda
                    ]
                )
                    ? $selecciones_por_busqueda[
                        $busqueda
                    ]
                    : 0;

            ?>

            <tr>

                <td class="texto">
                    <?php
                    echo esc_html(
                        $busqueda
                    );
                    ?>
                </td>

                <td class="texto-amplio">
                    <?php
                    echo esc_html(
                        (string)
                        $estadistica
                            ->busqueda_interpretada
                    );
                    ?>
                </td>

                <td class="tipo">
                    <?php
                    echo esc_html(
                        (string)
                        $estadistica->tipo
                    );
                    ?>
                </td>

                <td class="numero">
                    <?php
                    echo esc_html(
                        (int)
                        $estadistica->veces_buscada
                    );
                    ?>
                </td>

                <td class="numero">
                    <?php
                    echo esc_html(
                        (int)
                        $estadistica->con_resultados
                    );
                    ?>
                </td>

                <td class="numero">
                    <?php
                    echo esc_html(
                        (int)
                        $estadistica->sin_resultados
                    );
                    ?>
                </td>

                <td class="numero">
                    <?php
                    echo esc_html(
                        (int)
                        $estadistica->total_resultados
                    );
                    ?>
                </td>

                <td class="numero">
                    <?php
                    echo esc_html(
                        $total_selecciones
                    );
                    ?>
                </td>

                <td class="fecha">
                    <?php
                    echo esc_html(
                        (string)
                        $estadistica->ultima_busqueda
                    );
                    ?>
                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>
    <?php

    exit;
}


/*
 * ==========================================================
 * ACCIÓN DE WORDPRESS
 * ==========================================================
 */
add_action(
    'admin_post_compuciber_exportar_estadisticas_excel',
    'compuciber_exportar_estadisticas_excel'
);