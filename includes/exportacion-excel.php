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
     * Crear un Excel .xlsx real (Open XML), sin Composer.
     * Las búsquedas se guardan como texto, nunca como fórmulas.
     */
    $encabezados = array(
        'Búsqueda',
        'Búsqueda interpretada',
        'Tipo',
        'Veces buscada',
        'Con resultados',
        'Sin resultados',
        'Total resultados mostrados',
        'Selecciones (total por búsqueda, una sola vez)',
        'Última búsqueda',
    );

    $celda = static function ( $columna, $fila, $valor, $es_numero = false, $es_titulo = false ) {
        $ref = $columna . $fila;

        if ( $es_numero ) {
            return '<c r="' . $ref . '" t="n"><v>' . (int) $valor . '</v></c>';
        }

        $texto = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $valor );
        $texto = htmlspecialchars( $texto, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

        return '<c r="' . $ref . '"' . ( $es_titulo ? ' s="1"' : '' )
            . ' t="inlineStr"><is><t xml:space="preserve">'
            . $texto . '</t></is></c>';
    };

    $filas_xml = '<row r="1">';

    foreach ( $encabezados as $posicion => $titulo ) {
        $filas_xml .= $celda( chr( 65 + $posicion ), 1, $titulo, false, true );
    }

    $filas_xml .= '</row>';
    $numero_fila = 1;

    // Evitar mostrar varias veces las selecciones
    // de una misma búsqueda en el archivo Excel.
    $cis_selecciones_ya_mostradas = array();

    foreach ( $estadisticas as $estadistica ) {

        ++$numero_fila;

        $busqueda = (string) $estadistica->busqueda;

        if ( ! isset( $cis_selecciones_ya_mostradas[ $busqueda ] ) ) {

            $total_selecciones = isset(
                $selecciones_por_busqueda[ $busqueda ]
            )
                ? (int) $selecciones_por_busqueda[ $busqueda ]
                : 0;

            $cis_selecciones_ya_mostradas[ $busqueda ] = true;

        } else {

            // Las selecciones ya se mostraron en otra fila.
            $total_selecciones = '';

        }

        $valores_fila = array(
            $busqueda,
            (string) $estadistica->busqueda_interpretada,
            (string) $estadistica->tipo,
            (int) $estadistica->veces_buscada,
            (int) $estadistica->con_resultados,
            (int) $estadistica->sin_resultados,
            (int) $estadistica->total_resultados,
            $total_selecciones,
            (string) $estadistica->ultima_busqueda,
        );

        $filas_xml .= '<row r="' . $numero_fila . '">';

        foreach ( $valores_fila as $posicion => $valor ) {
            $filas_xml .= $celda(
                chr( 65 + $posicion ),
                $numero_fila,
                $valor,
                $posicion >= 3 && $posicion <= 7 && $valor !== ''
            );
        }

        $filas_xml .= '</row>';
    }

    $xml_inicio = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $hoja_xml = $xml_inicio
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
        . '</sheetView></sheetViews>'
        . '<cols>'
        . '<col min="1" max="2" width="32" customWidth="1"/>'
        . '<col min="3" max="3" width="14" customWidth="1"/>'
        . '<col min="4" max="6" width="18" customWidth="1"/>'
        . '<col min="7" max="7" width="29" customWidth="1"/>'
        . '<col min="8" max="8" width="17" customWidth="1"/>'
        . '<col min="9" max="9" width="23" customWidth="1"/>'
        . '</cols><sheetData>' . $filas_xml . '</sheetData>'
        . '<autoFilter ref="A1:I' . $numero_fila . '"/>'
        . '</worksheet>';

    $archivos_excel = array(
        '[Content_Types].xml' => $xml_inicio
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $xml_inicio
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => $xml_inicio
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Estadísticas" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => $xml_inicio
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $xml_inicio
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF245A81"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $hoja_xml,
    );

    // WordPress ya incluye PclZip; no se requieren extensiones PHP adicionales.
    require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

    $archivo_temporal = tempnam( get_temp_dir(), 'cisxlsx_' );

    if ( false === $archivo_temporal ) {
        wp_die( 'No se pudo preparar el archivo Excel.' );
    }

    $entradas_zip = array();

    foreach ( $archivos_excel as $ruta => $contenido ) {
        $entradas_zip[] = array(
            PCLZIP_ATT_FILE_NAME => $ruta,
            PCLZIP_ATT_FILE_CONTENT => $contenido,
        );
    }

    $zip = new PclZip( $archivo_temporal );
    $creado = $zip->create( $entradas_zip );

    unset( $entradas_zip, $archivos_excel, $filas_xml, $hoja_xml );

    clearstatcache( true, $archivo_temporal );

    if ( 0 === $creado || ! is_file( $archivo_temporal ) || filesize( $archivo_temporal ) === 0 ) {
        @unlink( $archivo_temporal );
        wp_die( 'No se pudo generar el archivo Excel.' );
    }

    if ( headers_sent() ) {
        @unlink( $archivo_temporal );
        wp_die( 'La descarga Excel no puede iniciarse porque ya se enviaron datos.' );
    }

    $nombre_archivo = 'compuciber-estadisticas-' . wp_date( 'Y-m-d-His' ) . '.xlsx';

    nocache_headers();
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="' . $nombre_archivo . '"' );
    header( 'Content-Length: ' . filesize( $archivo_temporal ) );
    header( 'X-Content-Type-Options: nosniff' );

    readfile( $archivo_temporal );
    @unlink( $archivo_temporal );
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