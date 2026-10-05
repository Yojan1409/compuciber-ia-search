<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * ==========================================================
 * DEPENDENCIAS DEL SISTEMA DE LICENCIAS
 * ==========================================================
 */

require_once plugin_dir_path( __FILE__ )
    . 'licencias/interfaz.php';

require_once plugin_dir_path( __FILE__ )
    . 'licencias/compuciber-cloud.php';    


/*
 * ==========================================================
 * SISTEMA DE LICENCIAS - V2.0
 * ==========================================================
 */

/**
 * Estados disponibles para una licencia.
 */
function compuciber_obtener_estados_licencia() {

    return array(
        'sin_licencia',
        'activa',
        'inactiva',
        'expirada',
    );
}


/**
 * Obtiene la clave de licencia configurada.
 */
function compuciber_obtener_clave_licencia() {

    return sanitize_text_field(
        get_option(
            'compuciber_clave_licencia',
            ''
        )
    );
}


/**
 * Obtiene el estado actual de la licencia.
 *
 * Por defecto el plugin comienza sin licencia.
 */
function compuciber_obtener_estado_licencia() {

    $estado = sanitize_key(
        get_option(
            'compuciber_estado_licencia',
            'sin_licencia'
        )
    );

    $estados_permitidos =
        compuciber_obtener_estados_licencia();

    if (
        ! in_array(
            $estado,
            $estados_permitidos,
            true
        )
    ) {
        return 'sin_licencia';
    }

    return $estado;
}


/**
 * Indica si actualmente existe
 * una licencia válida y activa.
 */
function compuciber_licencia_activa() {

    return (
        compuciber_obtener_estado_licencia()
        === 'activa'
    );
}

/**
 * Obtiene la información básica de esta instalación.
 *
 * Estos datos podrán ser utilizados por el futuro
 * servidor de licencias / SaaS para identificar
 * la instalación del plugin.
 *
 * Esta función no envía información externamente.
 *
 * @return array
 */
function compuciber_obtener_datos_instalacion() {

    $dominio = wp_parse_url(
        home_url(),
        PHP_URL_HOST
    );

    if ( empty( $dominio ) ) {
        $dominio = '';
    }

    return array(

        'producto' => 'compuciber-ai-search',

        'plataforma' => 'wordpress-woocommerce',    

        'id_instalacion' =>
            compuciber_obtener_id_instalacion(),    

        'dominio' => sanitize_text_field(
            $dominio
        ),

        'url' => esc_url_raw(
            home_url()
        ),

        'version_plugin' =>
            defined( 'COMPUCIBER_AI_SEARCH_VERSION' )
                ? COMPUCIBER_AI_SEARCH_VERSION
                : '',

        'version_wordpress' =>
            get_bloginfo( 'version' ),

        'version_php' =>
            PHP_VERSION,

        'woocommerce' =>
            defined( 'WC_VERSION' )
                ? WC_VERSION
                : '',
    );
}

/**
 * Obtiene el identificador único de esta instalación.
 *
 * El identificador queda asociado al dominio actual.
 * Si WordPress es clonado a otro dominio, se genera
 * automáticamente un nuevo UUID para evitar que dos
 * tiendas compartan la misma identidad.
 *
 * @return string
 */
function compuciber_obtener_id_instalacion() {

    $id_instalacion = get_option(
        'compuciber_id_instalacion',
        ''
    );

    $dominio_guardado = get_option(
        'compuciber_dominio_instalacion',
        ''
    );

    $dominio_actual = wp_parse_url(
        home_url(),
        PHP_URL_HOST
    );

    if ( empty( $dominio_actual ) ) {
        $dominio_actual = '';
    }

    $dominio_actual = strtolower(
        sanitize_text_field(
            $dominio_actual
        )
    );

    $dominio_guardado = strtolower(
        sanitize_text_field(
            $dominio_guardado
        )
    );

    /*
     * Si ya existe un UUID y pertenece al mismo
     * dominio, conservamos la identidad actual.
     */
    if (
        ! empty( $id_instalacion )
        && ! empty( $dominio_guardado )
        && $dominio_guardado === $dominio_actual
    ) {
        return sanitize_text_field(
            $id_instalacion
        );
    }

    /*
     * Si existe un UUID antiguo pero todavía no
     * habíamos guardado su dominio, lo conservamos
     * y asociamos al dominio actual.
     *
     * Esto permite actualizar instalaciones creadas
     * antes de incorporar el control multidominio.
     */
    if (
        ! empty( $id_instalacion )
        && empty( $dominio_guardado )
    ) {

        update_option(
            'compuciber_dominio_instalacion',
            $dominio_actual,
            false
        );

        return sanitize_text_field(
            $id_instalacion
        );
    }

    /*
    * Si ya existía una instalación asociada a otro
    * dominio, estamos ante una nueva identidad.
    *
    * No permitimos que la nueva tienda herede la
    * autorización de licencia de la instalación
    * anterior.
    */
    if (
        ! empty( $id_instalacion )
        && ! empty( $dominio_guardado )
        && $dominio_guardado !== $dominio_actual
    ) {

        update_option(
            'compuciber_estado_licencia',
            'sin_licencia'
        );

        delete_option(
            'compuciber_ultima_validacion_licencia'
        );

        delete_option(
            'compuciber_limite_instalaciones'
        );

        delete_option(
            'compuciber_instalaciones_activas'
        );

        delete_option(
            'compuciber_fecha_expiracion_licencia'
        );        

        delete_transient(
            'compuciber_validacion_licencia_lock'
        );
    }

    /*
     * Si no existe UUID o el dominio cambió,
     * generamos una identidad nueva.
     */
    $id_instalacion = wp_generate_uuid4();

    update_option(
        'compuciber_id_instalacion',
        $id_instalacion,
        false
    );

    update_option(
        'compuciber_dominio_instalacion',
        $dominio_actual,
        false
    );

    return $id_instalacion;
}

/**
 * Obtiene el momento de la última validación
 * realizada contra el sistema de licencias.
 *
 * @return int Timestamp Unix.
 */
function compuciber_obtener_ultima_validacion_licencia() {

    return absint(
        get_option(
            'compuciber_ultima_validacion_licencia',
            0
        )
    );
}


/**
 * Determina si la última validación de licencia
 * todavía puede considerarse reciente.
 *
 * Por defecto se utiliza una ventana de 24 horas.
 *
 * @param int $duracion Duración máxima de la caché en segundos.
 *
 * @return bool
 */
function compuciber_validacion_licencia_reciente(
    $duracion = DAY_IN_SECONDS
) {

    $ultima_validacion =
        compuciber_obtener_ultima_validacion_licencia();

    if ( $ultima_validacion < 1 ) {
        return false;
    }

    $duracion = absint( $duracion );

    if ( $duracion < 1 ) {
        $duracion = DAY_IN_SECONDS;
    }

    return (
        time() - $ultima_validacion
    ) < $duracion;
}

/**
 * Determina si la licencia necesita una nueva
 * comprobación contra el servidor remoto.
 *
 * Evita realizar solicitudes innecesarias cuando
 * existe una validación reciente.
 *
 * @return bool
 */
function compuciber_licencia_necesita_validacion() {

    /*
     * Sin una clave configurada no existe
     * ninguna licencia que comprobar.
     */
    if ( empty( compuciber_obtener_clave_licencia() ) ) {
        return false;
    }

    /*
     * Si nunca se realizó una validación confirmada,
     * debemos intentar validar la licencia.
     */
    $ultima_validacion =
        compuciber_obtener_ultima_validacion_licencia();

    if ( $ultima_validacion < 1 ) {
        return true;
    }

    /*
     * Si la última validación tiene menos de
     * 24 horas, utilizamos el estado almacenado.
     */
    if ( compuciber_validacion_licencia_reciente() ) {
        return false;
    }

    /*
     * La validación almacenada ya venció.
     */
    return true;
}

/**
 * Indica si actualmente existe una comprobación
 * automática de licencia en proceso.
 *
 * @return bool
 */
function compuciber_validacion_licencia_bloqueada() {

    return (
        get_transient(
            'compuciber_validacion_licencia_lock'
        ) === '1'
    );
}


/**
 * Activa temporalmente el bloqueo de validación.
 *
 * El bloqueo evita que varias solicitudes de
 * WordPress consulten simultáneamente el servidor.
 */
function compuciber_bloquear_validacion_licencia() {

    set_transient(
        'compuciber_validacion_licencia_lock',
        '1',
        MINUTE_IN_SECONDS
    );
}

/**
 * Libera el bloqueo de validación automática
 * una vez finalizada la comprobación remota.
 */
function compuciber_desbloquear_validacion_licencia() {

    delete_transient(
        'compuciber_validacion_licencia_lock'
    );
}

/**
 * Guarda localmente una licencia que ha sido
 * confirmada como válida por Compuciber Cloud.
 *
 * Centraliza el almacenamiento para que la
 * validación manual y automática utilicen
 * exactamente la misma lógica.
 *
 * @param array $resultado Respuesta normalizada del servidor.
 */
function compuciber_guardar_licencia_validada(
    $resultado
) {

    if ( ! is_array( $resultado ) ) {
        return;
    }

    $estado = isset( $resultado['estado'] )
        ? sanitize_key( $resultado['estado'] )
        : '';

    $tipo_respuesta =
        isset( $resultado['tipo_respuesta'] )
            ? sanitize_key(
                $resultado['tipo_respuesta']
            )
            : '';

    /*
    * Solamente guardamos una licencia cuando
    * el servidor la confirma expresamente
    * como válida y activa.
    */
    if (
        $tipo_respuesta !== 'valida'
        || $estado !== 'activa'
    ) {
        return;
    }

    $limite_instalaciones =
        isset( $resultado['limite_instalaciones'] )
            ? absint(
                $resultado['limite_instalaciones']
            )
            : 1;

    $instalaciones_activas =
        isset( $resultado['instalaciones_activas'] )
            ? absint(
                $resultado['instalaciones_activas']
            )
            : 1;

    $fecha_expiracion =
        isset( $resultado['fecha_expiracion'] )
            ? sanitize_text_field(
                $resultado['fecha_expiracion']
            )
            : '';            

    if ( $limite_instalaciones < 1 ) {
        $limite_instalaciones = 1;
    }

    update_option(
        'compuciber_estado_licencia',
        'activa'
    );

    update_option(
        'compuciber_ultima_validacion_licencia',
        time(),
        false
    );

    update_option(
        'compuciber_limite_instalaciones',
        $limite_instalaciones,
        false
    );

    update_option(
        'compuciber_instalaciones_activas',
        $instalaciones_activas,
        false
    );

    update_option(
        'compuciber_fecha_expiracion_licencia',
        $fecha_expiracion,
        false
    );    
    
}

/**
 * Guarda localmente el rechazo confirmado
 * de una licencia por Compuciber Cloud.
 *
 * Al ser una respuesta autoritativa del servidor,
 * se elimina cualquier información de instalaciones
 * perteneciente a una validación anterior.
 *
 * @param string $estado Estado rechazado.
 */
function compuciber_guardar_licencia_rechazada(
    $estado
) {

    $estado = sanitize_key(
        (string) $estado
    );

    $estados_permitidos = array(
        'sin_licencia',
        'inactiva',
        'expirada',
    );

    /*
     * Nunca permitimos guardar "activa"
     * mediante esta función.
     */
    if ( ! in_array(
        $estado,
        $estados_permitidos,
        true
    ) ) {
        $estado = 'sin_licencia';
    }

    update_option(
        'compuciber_estado_licencia',
        $estado
    );

    update_option(
        'compuciber_ultima_validacion_licencia',
        time(),
        false
    );

    /*
     * Los datos del plan anterior dejan
     * de ser válidos.
     */
    delete_option(
        'compuciber_limite_instalaciones'
    );

    delete_option(
        'compuciber_instalaciones_activas'
    );
    delete_option(
        'compuciber_fecha_expiracion_licencia'
    );    
}

/**
 * Obtiene el número máximo de instalaciones
 * permitido por la licencia.
 *
 * @return int
 */
function compuciber_obtener_limite_instalaciones() {

    $limite = absint(
        get_option(
            'compuciber_limite_instalaciones',
            1
        )
    );

    return max( 1, $limite );
}


/**
 * Obtiene el número de instalaciones activas
 * informado por Compuciber Cloud.
 *
 * @return int
 */
function compuciber_obtener_instalaciones_activas() {

    return absint(
        get_option(
            'compuciber_instalaciones_activas',
            0
        )
    );
}

/**
 * Obtiene el número de instalaciones disponibles
 * según la información confirmada por Compuciber Cloud.
 *
 * Este valor es informativo. La autorización final
 * para registrar una nueva instalación corresponde
 * siempre al servidor de licencias.
 *
 * @return int
 */
function compuciber_obtener_instalaciones_disponibles() {

    $limite =
        compuciber_obtener_limite_instalaciones();

    $activas =
        compuciber_obtener_instalaciones_activas();

    /*
     * Nunca devolvemos valores negativos.
     */
    return max(
        0,
        $limite - $activas
    );
}

/**
 * Obtiene la fecha de expiración informada
 * por el servidor de licencias.
 *
 * @return string
 */
function compuciber_obtener_fecha_expiracion_licencia() {

    return sanitize_text_field(
        get_option(
            'compuciber_fecha_expiracion_licencia',
            ''
        )
    );
}

/**
 * Invalida la validación anterior cuando cambia
 * la clave de licencia.
 *
 * Esto evita que una nueva clave herede el estado,
 * la fecha de validación o los límites pertenecientes
 * a la clave anterior.
 *
 * @param mixed $valor_anterior Clave anterior.
 * @param mixed $valor_nuevo    Nueva clave.
 */
function compuciber_detectar_cambio_clave_licencia(
    $valor_anterior,
    $valor_nuevo
) {

    $clave_anterior =
        sanitize_text_field(
            (string) $valor_anterior
        );

    $clave_nueva =
        sanitize_text_field(
            (string) $valor_nuevo
        );

    /*
     * Si la clave no cambió, no hacemos nada.
     */
    if ( hash_equals(
        $clave_anterior,
        $clave_nueva
    ) ) {
        return;
    }

    /*
     * La nueva clave todavía no ha sido validada,
     * por lo que eliminamos cualquier estado
     * perteneciente a la clave anterior.
     */
    update_option(
        'compuciber_estado_licencia',
        'sin_licencia'
    );

    delete_option(
        'compuciber_ultima_validacion_licencia'
    );

    delete_option(
        'compuciber_limite_instalaciones'
    );

    delete_option(
        'compuciber_instalaciones_activas'
    );

    delete_option(
        'compuciber_fecha_expiracion_licencia'
    );

    delete_transient(
        'compuciber_validacion_licencia_lock'
    );
}

add_action(
    'update_option_compuciber_clave_licencia',
    'compuciber_detectar_cambio_clave_licencia',
    10,
    2
);

/**
 * Ejecuta una comprobación automática de la licencia
 * únicamente cuando la validación almacenada ha vencido.
 *
 * Esta función no genera redirecciones ni mensajes
 * administrativos.
 *
 * @return array
 */
function compuciber_validar_licencia_automaticamente() {

    /*
    * Evitamos solicitudes simultáneas al
    * servidor de licenciamiento.
    */
    if ( compuciber_validacion_licencia_bloqueada() ) {

        return array(
            'comprobada' => false,
            'estado'     =>
                compuciber_obtener_estado_licencia(),
            'motivo'     => 'validacion_en_proceso',
        );
    }

    /*
     * Si la licencia todavía tiene una validación
     * reciente, no consultamos el servidor.
     */
    if ( ! compuciber_licencia_necesita_validacion() ) {

        return array(
            'comprobada' => false,
            'estado'     =>
                compuciber_obtener_estado_licencia(),
        );
    }

    /*
    * A partir de este punto sí realizaremos una
    * consulta remota, por lo que activamos el bloqueo.
    */
    compuciber_bloquear_validacion_licencia();    

    $estado_anterior =
        compuciber_obtener_estado_licencia();

    $resultado =
        compuciber_validar_licencia();

    $tipo_respuesta =
        isset( $resultado['tipo_respuesta'] )
            ? sanitize_key(
                $resultado['tipo_respuesta']
            )
            : 'error_local';

    $estado =
        isset( $resultado['estado'] )
            ? sanitize_key(
                $resultado['estado']
            )
            : 'sin_licencia';


    /*
     * El servidor confirmó una licencia activa.
     */
    if ( $tipo_respuesta === 'valida' ) {

        compuciber_guardar_licencia_validada(
            $resultado
        );

        $estado = 'activa';
    }


    /*
     * El servidor respondió correctamente,
     * pero rechazó la licencia.
     */
    elseif ( $tipo_respuesta === 'rechazada' ) {

        compuciber_guardar_licencia_rechazada(
            $estado
        );
    }


    /*
     * Ante errores de conexión o errores locales
     * conservamos el último estado conocido.
     *
     * Tampoco renovamos la fecha de validación.
     */
    else {

        $estado =
            $estado_anterior;
    }

    /*
    * La comprobación terminó, por lo que
    * liberamos inmediatamente el bloqueo.
    */
    compuciber_desbloquear_validacion_licencia();

    return array(
        'comprobada'     => true,
        'estado'         => $estado,
        'tipo_respuesta' => $tipo_respuesta,
        'resultado'      => $resultado,
    );
}

/**
 * Determina si una licencia previamente activa
 * se encuentra dentro del periodo de gracia.
 *
 * El periodo de gracia permite mantener temporalmente
 * el funcionamiento cuando el servidor de licencias
 * no puede ser consultado.
 *
 * Por defecto se utilizan 7 días.
 *
 * @param int $duracion Duración del periodo de gracia.
 *
 * @return bool
 */
function compuciber_licencia_en_periodo_gracia(
    $duracion = 7 * DAY_IN_SECONDS
) {

    /*
     * El periodo de gracia solamente puede aplicarse
     * a una licencia que previamente fue validada
     * como activa.
     */
    if ( ! compuciber_licencia_activa() ) {
        return false;
    }

    $ultima_validacion =
        compuciber_obtener_ultima_validacion_licencia();

    if ( $ultima_validacion < 1 ) {
        return false;
    }

    $duracion = absint( $duracion );

    if ( $duracion < DAY_IN_SECONDS ) {
        $duracion = 7 * DAY_IN_SECONDS;
    }

    return (
        time() - $ultima_validacion
    ) <= $duracion;
}

/**
 * Determina si la licencia puede considerarse
 * operativa en este momento.
 *
 * Una licencia es operativa cuando:
 *
 * 1. Su último estado confirmado es "activa".
 * 2. La última validación tiene menos de 24 horas,
 *    o todavía se encuentra dentro del periodo
 *    de gracia de 7 días.
 *
 * @return bool
 */
function compuciber_licencia_operativa() {

    /*
     * El último estado confirmado por el servidor
     * debe ser necesariamente "activa".
     */
    if ( ! compuciber_licencia_activa() ) {
        return false;
    }

    /*
     * Una validación reciente permite utilizar
     * normalmente la licencia.
     */
    if ( compuciber_validacion_licencia_reciente() ) {
        return true;
    }

    /*
     * Si la validación ya no es reciente,
     * comprobamos el periodo de gracia.
     */
    if ( compuciber_licencia_en_periodo_gracia() ) {
        return true;
    }

    /*
     * El último estado pudo haber sido "activa",
     * pero han transcurrido más de 7 días sin
     * poder confirmar nuevamente la licencia.
     */
    return false;
}

/**
 * Obtiene el estado operativo actual de la licencia.
 *
 * Este estado tiene en cuenta tanto la última
 * validación confirmada como el periodo de gracia.
 *
 * @return string
 */
function compuciber_obtener_estado_operativo_licencia() {

    $estado =
        compuciber_obtener_estado_licencia();

    /*
     * Si el último estado confirmado no es activo,
     * devolvemos directamente ese estado.
     */
    if ( $estado !== 'activa' ) {
        return $estado;
    }

    /*
     * Licencia activa y validación reciente.
     */
    if ( compuciber_validacion_licencia_reciente() ) {
        return 'activa';
    }

    /*
     * La última validación ya venció, pero todavía
     * estamos dentro del periodo de gracia.
     */
    if ( compuciber_licencia_en_periodo_gracia() ) {
        return 'gracia';
    }

    /*
     * La licencia fue activa anteriormente,
     * pero ya superó el periodo de gracia sin
     * conseguir una nueva validación.
     */
    return 'sin_confirmar';
}

/**
 * Valida la licencia configurada utilizando
 * el proveedor de licenciamiento disponible.
 *
 * @return array
 */


function compuciber_validar_licencia() {

    $clave = compuciber_obtener_clave_licencia();

    if ( empty( $clave ) ) {

        return array(
            'valida'         => false,
            'estado'         => 'sin_licencia',
            'tipo_respuesta' => 'error_local',
            'mensaje'        => 'No se ha configurado una licencia.',
        );
    }

    /*
    * Obtenemos los datos que identifican
    * esta instalación del plugin.
    */
    $instalacion =
        compuciber_obtener_datos_instalacion();

    if (
        ! is_array( $instalacion )
        || empty( $instalacion['id_instalacion'] )
        || empty( $instalacion['dominio'] )
    ) {

        return array(
            'valida'         => false,
            'estado'         => 'sin_licencia',
            'tipo_respuesta' => 'error_local',
            'mensaje'        => 'No se pudo identificar la instalación.',
        );
    }

    /*
     * El motor del plugin solamente conoce la interfaz.
     * La implementación concreta puede sustituirse
     * posteriormente sin modificar el buscador.
     */
    $proveedor =
        new Compuciber_CloudLicenseProvider();

    $resultado = $proveedor->validar(
        $clave,
        $instalacion
    );

    /*
     * Validamos también la respuesta del proveedor.
     */
    if (
        ! is_array( $resultado )
        || ! isset( $resultado['estado'] )
    ) {

        return array(
            'valida'         => false,
            'estado'         => 'sin_licencia',
            'tipo_respuesta' => 'error_local',
            'mensaje'        => 'Respuesta de licencia no válida.',
        );
    }

    $estado = sanitize_key(
        $resultado['estado']
    );

    if (
        ! in_array(
            $estado,
            compuciber_obtener_estados_licencia(),
            true
        )
    ) {
        $estado = 'sin_licencia';
    }


    /*
    * Validamos el tipo de respuesta recibido
    * desde el proveedor de licenciamiento.
    */
    $tipos_respuesta_permitidos = array(
        'valida',
        'rechazada',
        'error_conexion',
        'error_local',
    );

    $tipo_respuesta =
        isset( $resultado['tipo_respuesta'] )
            ? sanitize_key(
                $resultado['tipo_respuesta']
            )
            : 'error_local';

    if (
        ! in_array(
            $tipo_respuesta,
            $tipos_respuesta_permitidos,
            true
        )
    ) {
        $tipo_respuesta = 'error_local';
    }


    $resultado['estado'] =
        $estado;

    $resultado['tipo_respuesta'] =
        $tipo_respuesta;

    return $resultado;
}

/*
 * ==========================================================
 * ACCIÓN DE VALIDACIÓN DE LICENCIA
 * ==========================================================
 */

/**
 * Procesa manualmente la validación de la licencia
 * desde el panel de administración.
 */
function compuciber_procesar_validacion_licencia() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            'No tienes permisos para realizar esta acción.'
        );
    }

    /*
    * La validación manual modifica el estado interno
    * de la licencia, por lo que solamente aceptamos
    * solicitudes POST.
    */
    if (
        ! isset( $_SERVER['REQUEST_METHOD'] )
        || $_SERVER['REQUEST_METHOD'] !== 'POST'
    ) {
        wp_die(
            'Método de solicitud no permitido.'
        );
    }    

    check_admin_referer(
        'compuciber_validar_licencia'
    );

    /*
     * Conservamos el estado existente antes
     * de realizar una nueva comprobación.
     */
    $estado_anterior =
        compuciber_obtener_estado_licencia();

    $resultado =
        compuciber_validar_licencia();

    $estado = isset( $resultado['estado'] )
        ? sanitize_key( $resultado['estado'] )
        : 'sin_licencia';

    if (
        ! in_array(
            $estado,
            compuciber_obtener_estados_licencia(),
            true
        )
    ) {
        $estado = 'sin_licencia';
    }

    $tipo_respuesta =
        isset( $resultado['tipo_respuesta'] )
            ? sanitize_key(
                $resultado['tipo_respuesta']
            )
            : 'error_local';

    /*
     * Obtenemos el mensaje devuelto por
     * el proveedor de licenciamiento.
     */
    $mensaje = isset( $resultado['mensaje'] )
        ? sanitize_text_field(
            $resultado['mensaje']
        )
        : 'No se pudo validar la licencia.';


    /*
     * RESPUESTA VÁLIDA DEL SERVIDOR
     *
     * El servidor confirmó una licencia válida.
     * Guardamos el estado y renovamos la fecha
     * de la última validación satisfactoria.
     */
    if ( $tipo_respuesta === 'valida' ) {

        compuciber_guardar_licencia_validada(
            $resultado
        );

        $estado = 'activa';
    }


    /*
     * LICENCIA RECHAZADA
     *
     * El servidor respondió correctamente, pero
     * determinó que la licencia no es válida,
     * está inactiva o ha expirado.
     *
     * No se aplica periodo de gracia.
     */
    elseif ( $tipo_respuesta === 'rechazada' ) {

        compuciber_guardar_licencia_rechazada(
            $estado
        );
    }


    /*
     * ERROR DE CONEXIÓN
     *
     * No modificamos el último estado conocido
     * ni la fecha de su última validación.
     *
     * Esto evita reiniciar artificialmente
     * el periodo de gracia.
     */
    elseif ( $tipo_respuesta === 'error_conexion' ) {

        $estado = $estado_anterior;

        if (
            $estado_anterior === 'activa'
            && compuciber_licencia_en_periodo_gracia()
        ) {
            $mensaje .=
                ' Se mantiene temporalmente la licencia'
                . ' mediante el periodo de gracia.';
        }
    }


    /*
     * ERROR LOCAL
     *
     * Un problema interno tampoco puede convertir
     * una licencia en activa ni modificar la última
     * validación confirmada.
     */
    else {

        $estado = $estado_anterior;
    }


    /*
     * Mensaje temporal para el administrador.
     */
    set_transient(
        'compuciber_mensaje_licencia_'
            . get_current_user_id(),
        $mensaje,
        60
    );

    $url = add_query_arg(
        array(
            'page'     =>
                'compuciber-ai-search-configuracion',

            'licencia' =>
                $estado,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $url );

    exit;
}


add_action(
    'admin_post_compuciber_validar_licencia',
    'compuciber_procesar_validacion_licencia'
);

/*
 * ==========================================================
 * VALIDACIÓN AUTOMÁTICA DE LICENCIA
 * ==========================================================
 */

/**
 * Programa la comprobación periódica de licencia
 * si todavía no existe una tarea programada.
 */
function compuciber_programar_validacion_licencia() {

    if (
        ! wp_next_scheduled(
            'compuciber_validacion_licencia_cron'
        )
    ) {

        wp_schedule_event(
            time() + HOUR_IN_SECONDS,
            'hourly',
            'compuciber_validacion_licencia_cron'
        );
    }
}


/**
 * Ejecuta la comprobación periódica.
 *
 * Aunque este evento se ejecute cada hora,
 * compuciber_licencia_necesita_validacion()
 * evita contactar con el servidor mientras
 * exista una validación reciente.
 */
function compuciber_ejecutar_validacion_licencia_cron() {

    compuciber_validar_licencia_automaticamente();
}


add_action(
    'compuciber_validacion_licencia_cron',
    'compuciber_ejecutar_validacion_licencia_cron'
);


/*
 * Comprobamos que el evento esté programado.
 */
add_action(
    'init',
    'compuciber_programar_validacion_licencia'
);

/**
 * Elimina todas las tareas programadas
 * de validación de licencia cuando
 * el plugin se desactiva.
 */
function compuciber_limpiar_cron_licencia() {

    wp_clear_scheduled_hook(
        'compuciber_validacion_licencia_cron'
    );

    /*
     * Eliminamos también cualquier bloqueo
     * temporal que pudiera haber quedado activo.
     */
    delete_transient(
        'compuciber_validacion_licencia_lock'
    );
}

