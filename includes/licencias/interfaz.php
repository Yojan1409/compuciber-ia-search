<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/*
 * ==========================================================
 * INTERFAZ DE LICENCIAMIENTO - V2.0
 * ==========================================================
 */

/**
 * Contrato que deberá implementar cualquier sistema
 * encargado de validar licencias de Compuciber AI Search.
 *
 * Esto permite cambiar el servidor o proveedor de
 * licenciamiento sin modificar el motor del plugin.
 */
interface Compuciber_LicenseProviderInterface {

    /**
     * Valida una clave de licencia para una instalación.
     *
     * @param string $clave        Clave de licencia.
     * @param array  $instalacion  Datos de la instalación.
     *
     * @return array
     */
    public function validar(
        $clave,
        $instalacion
    );

}