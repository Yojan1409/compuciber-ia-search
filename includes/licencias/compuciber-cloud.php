<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene la URL del servidor de licencias.
 *
 * La URL podrá definirse posteriormente mediante
 * una constante o modificarse mediante un filtro
 * de WordPress.
 *
 * @return string
 */
function compuciber_obtener_url_servidor_licencias() {

    $url = '';

    /*
     * Permitimos definir el servidor mediante
     * wp-config.php u otra configuración externa.
     */
    if (
        defined(
            'COMPUCIBER_LICENSE_SERVER_URL'
        )
    ) {
        $url =
            COMPUCIBER_LICENSE_SERVER_URL;
    }

    /*
     * Permitimos que otras integraciones puedan
     * modificar el endpoint sin editar este archivo.
     */
    $url = apply_filters(
        'compuciber_license_server_url',
        $url
    );

    $url = esc_url_raw(
        trim( $url )
    );

    return $url;
}

/**
 * Normaliza y valida una respuesta recibida
 * desde el servidor de licencias.
 *
 * @param array $datos Respuesta decodificada del servidor.
 *
 * @return array
 */
function compuciber_normalizar_respuesta_licencia(
    $datos
) {

    if ( ! is_array( $datos ) ) {

        return array(
            'valida'         => false,
            'estado'         => 'sin_licencia',
            'tipo_respuesta' => 'error_conexion',
            'mensaje'        => 'Respuesta no válida del servidor de licencias.',
        );
    }

    $estados_permitidos = array(
        'activa',
        'inactiva',
        'expirada',
        'sin_licencia',
    );

    $estado = isset( $datos['estado'] )
        ? sanitize_key( $datos['estado'] )
        : 'sin_licencia';

    if (
        ! in_array(
            $estado,
            $estados_permitidos,
            true
        )
    ) {
        return array(
            'valida'         => false,
            'estado'         => 'sin_licencia',
            'tipo_respuesta' => 'error_conexion',
            'mensaje'        => 'El servidor devolvió un estado de licencia no reconocido.',
        );
    }

    /*
     * Una licencia solamente se considera válida
     * cuando el servidor confirma expresamente
     * el estado "activa".
     */
    if ( $estado === 'activa' ) {

        $limite_instalaciones =
            isset( $datos['limite_instalaciones'] )
                ? absint( $datos['limite_instalaciones'] )
                : 1;

        $instalaciones_activas =
            isset( $datos['instalaciones_activas'] )
                ? absint( $datos['instalaciones_activas'] )
                : 1;

        /*
        * Una licencia válida debe permitir como mínimo
        * una instalación.
        */
        if ( $limite_instalaciones < 1 ) {
            $limite_instalaciones = 1;
        }

        return array(
            'valida'                 => true,
            'estado'                 => 'activa',
            'tipo_respuesta'         => 'valida',
            'limite_instalaciones'   => $limite_instalaciones,
            'instalaciones_activas'  => $instalaciones_activas,
            'mensaje'                => isset( $datos['mensaje'] )
                ? sanitize_text_field(
                    $datos['mensaje']
                )
                : 'Licencia validada correctamente.',
        );
    }

    /*
     * El servidor respondió correctamente,
     * pero la licencia no está activa.
     */
    return array(
        'valida'         => false,
        'estado'         => $estado,
        'tipo_respuesta' => 'rechazada',
        'mensaje'        => isset( $datos['mensaje'] )
            ? sanitize_text_field(
                $datos['mensaje']
            )
            : 'La licencia no se encuentra activa.',
    );
}

/*
 * ==========================================================
 * PROVEEDOR DE LICENCIAS COMPUCIBER CLOUD - V2.0
 * ==========================================================
 */

/**
 * Proveedor de licenciamiento preparado para comunicarse
 * con la futura API de Compuciber.
 */
class Compuciber_CloudLicenseProvider
    implements Compuciber_LicenseProviderInterface {

    /**
     * Valida una licencia para una instalación
     * mediante el servidor de Compuciber Cloud.
     *
     * Si el servidor no está configurado o no puede
     * ser alcanzado, devuelve un error de conexión
     * sin invalidar automáticamente la licencia local.
     *
     * @param string $clave        Clave de licencia.
     * @param array  $instalacion  Datos de la instalación.
     *
     * @return array
     */
    public function validar(
        $clave,
        $instalacion
    ) {

        $clave = sanitize_text_field(
            $clave
        );

        /*
         * Verificamos que los datos de instalación
         * tengan una estructura válida.
         */
        if ( ! is_array( $instalacion ) ) {

            return array(
                'valida'         => false,
                'estado'         => 'sin_licencia',
                'tipo_respuesta' => 'error_local',
                'mensaje'        => 'Datos de instalación no válidos.',
            );
        }
        /*
        * Obtenemos el endpoint configurado para
        * el servidor de licenciamiento.
        */
        $url_servidor =
            compuciber_obtener_url_servidor_licencias();

        /*
        * Si todavía no existe un servidor configurado,
        * detenemos aquí la validación.
        *
        * Esto evita realizar solicitudes hacia
        * direcciones inexistentes o incorrectas.
        */
        if ( empty( $url_servidor ) ) {

            return array(
                'valida'         => false,
                'estado'         => 'sin_licencia',
                'tipo_respuesta' => 'error_conexion',
                'mensaje'        => 'Servidor de licencias no configurado.',
            );
        }


        /*
        * Preparamos los datos que serán enviados
        * al servidor de licenciamiento.
        */
        $datos_envio = array(
            'licencia'    => $clave,
            'instalacion' => $instalacion,
        );


        /*
        * Realizamos la solicitud mediante la API HTTP
        * nativa de WordPress.
        */
        $respuesta = wp_remote_post(
            $url_servidor,
            array(
                'timeout'     => 10,
                'redirection' => 0,

                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),

                'body' => wp_json_encode(
                    $datos_envio
                ),
            )
        );


        /*
        * Error de transporte:
        * timeout, DNS, SSL, servidor inaccesible, etc.
        *
        * No debe interpretarse como una licencia rechazada.
        */
        if ( is_wp_error( $respuesta ) ) {

            return array(
                'valida'         => false,
                'estado'         => 'sin_licencia',
                'tipo_respuesta' => 'error_conexion',
                'mensaje'        => 'No fue posible conectar con el servidor de licencias.',
            );
        }


        /*
        * Comprobamos el código HTTP recibido.
        */
        $codigo_http =
            wp_remote_retrieve_response_code(
                $respuesta
            );

        if ( $codigo_http < 200 || $codigo_http >= 300 ) {

            return array(
                'valida'         => false,
                'estado'         => 'sin_licencia',
                'tipo_respuesta' => 'error_conexion',
                'mensaje'        => 'El servidor de licencias devolvió una respuesta HTTP no válida.',
            );
        }


        /*
        * Recuperamos y decodificamos el JSON.
        */
        $cuerpo =
            wp_remote_retrieve_body(
                $respuesta
            );

        $datos =
            json_decode(
                $cuerpo,
                true
            );

        if (
            json_last_error() !== JSON_ERROR_NONE
            || ! is_array( $datos )
        ) {

            return array(
                'valida'         => false,
                'estado'         => 'sin_licencia',
                'tipo_respuesta' => 'error_conexion',
                'mensaje'        => 'El servidor de licencias devolvió una respuesta no válida.',
            );
        }


        /*
        * La respuesta llegó correctamente.
        * El normalizador decidirá si la licencia
        * está activa, inactiva o expirada.
        */
        return compuciber_normalizar_respuesta_licencia(
            $datos
        );
    }
}