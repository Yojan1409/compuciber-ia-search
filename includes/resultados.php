<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'resultado/normalizacion.php';
require_once plugin_dir_path( __FILE__ ) . 'resultado/puntuacion.php';
require_once plugin_dir_path( __FILE__ ) . 'resultado/consulta.php';


function compuciber_buscar_productos_inteligente(
    $busqueda,
    $interpretacion
) {

    error_log( 'RESULTADOS.PHP NUEVO EJECUTADO' );

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $productos_candidatos = $wpdb->get_results(
        "SELECT * FROM {$tabla}"
    );

    if ( empty( $productos_candidatos ) ) {
        return compuciber_consulta_vacia();
    }

    error_log(
        'INTERPRETACION RECIBIDA: '
        . print_r( $interpretacion, true )
    );    
    
    $datos_ia = compuciber_preparar_datos_ia(
        $interpretacion,
        $busqueda
    );

    error_log(
        'DATOS IA EN RESULTADOS: '
        . print_r( $datos_ia, true )
    );    

    $busqueda_expandida = compuciber_expandir_sinonimos(
        $busqueda
    );

    $palabras_busqueda = compuciber_obtener_palabras(
        $busqueda_expandida
    );

    $productos_puntuados = array();

    foreach ( $productos_candidatos as $producto ) {

        $score = compuciber_calcular_puntuacion_producto(
            $producto,
            $busqueda,
            $palabras_busqueda,
            $datos_ia
        );

        if ( $score > 0 ) {

            $productos_puntuados[] = array(
                'id'    => (int) $producto->id,
                'score' => $score,
            );
        }
    }

    usort(
        $productos_puntuados,
        function ( $a, $b ) {

            if ( $a['score'] === $b['score'] ) {
                return 0;
            }

            return ( $a['score'] > $b['score'] )
                ? -1
                : 1;
        }
    );

    $productos_puntuados = array_slice(
        $productos_puntuados,
        0,
        10
    );

    $ids_resultados = array();

    foreach ( $productos_puntuados as $resultado ) {
        $ids_resultados[] = $resultado['id'];
    }

    return compuciber_consulta_productos(
        $ids_resultados
    );
}