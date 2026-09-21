<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'COMPUCIBER_INDICE_VERSION', '2.1' );

/**
 * Crea o actualiza la tabla del índice.
 */
function compuciber_crear_tabla_indice_productos() {

    global $wpdb;

    $tabla = $wpdb->prefix . 'compuciber_product_index';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$tabla} (
        id bigint(20) unsigned NOT NULL,
        nombre varchar(255) NOT NULL DEFAULT '',
        sku varchar(255) NOT NULL DEFAULT '',
        marca varchar(255) NOT NULL DEFAULT '',
        modelo varchar(255) NOT NULL DEFAULT '',
        categorias text NOT NULL,
        atributos longtext NOT NULL,
        etiquetas text NOT NULL,
        contenido longtext NOT NULL,
        precio decimal(20,6) NOT NULL DEFAULT 0,
        precio_oferta decimal(20,6) NOT NULL DEFAULT 0,
        stock varchar(255) NOT NULL DEFAULT '',
        variaciones longtext NOT NULL,
        texto_busqueda longtext NOT NULL,
        url varchar(2048) NOT NULL DEFAULT '',
        imagen varchar(2048) NOT NULL DEFAULT '',
        actualizado datetime NOT NULL,
        PRIMARY KEY (id),
        KEY sku (sku(191)),
        KEY marca (marca(191)),
        KEY modelo (modelo(191)),
        KEY actualizado (actualizado)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( $sql );
}


/**
 * Verifica y actualiza el esquema automáticamente.
 */
function compuciber_verificar_esquema_indice() {

    $version_actual = get_option(
        'compuciber_indice_version',
        ''
    );

    if ( $version_actual !== COMPUCIBER_INDICE_VERSION ) {

        compuciber_crear_tabla_indice_productos();

        update_option(
            'compuciber_indice_version',
            COMPUCIBER_INDICE_VERSION
        );
    }
}

add_action(
    'plugins_loaded',
    'compuciber_verificar_esquema_indice',
    20
);